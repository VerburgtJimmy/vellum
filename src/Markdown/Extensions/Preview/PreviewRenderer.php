<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Preview;

use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\DB;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Throwable;
use Vellum\Exceptions\InvalidPreviewException;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;
use Vellum\Markdown\Islands\MarkdownPipeline;
use Vellum\Support\MarkdownView;

/**
 * Renders :::preview[name] by compiling a Blade view from the configured
 * previews directory and showing it beside its own source.
 *
 * Views resolve only from vellum.previews.path. A docs page naming an
 * arbitrary path would be a way to render any Blade file in the application,
 * so the name is a view name and nothing else.
 */
final class PreviewRenderer implements NodeRendererInterface
{
    private static int $sequence = 0;

    /**
     * The page being compiled, for error messages. A preview fails at build
     * time, far away from the Markdown that asked for it.
     */
    private static string $docsFile = 'a docs page';

    public static function compiling(string $docsFile): void
    {
        self::$docsFile = $docsFile;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?\Stringable
    {
        if (! $node instanceof DirectiveBlock || $node->getName() !== 'preview') {
            return null;
        }

        $name = trim((string) $node->getTitle());

        if ($name === '') {
            throw InvalidPreviewException::missingName(self::$docsFile);
        }

        if (preg_match('/^[a-z0-9]+(?:[-_a-z0-9]*)(?:\.[a-z0-9][-_a-z0-9]*)*$/i', $name) !== 1) {
            throw InvalidPreviewException::unsafeName($name, self::$docsFile);
        }

        $path = $this->resolve($name);

        if ($path === null) {
            throw InvalidPreviewException::notFound($name, $this->pathFor($name), self::$docsFile);
        }

        $id = 'vellum-preview-'.(++self::$sequence);

        return MarkdownView::render('preview', [
            'id' => $id,
            'name' => $name,
            'document' => PreviewDocument::build($id, $this->compile($name, $path), $this->stylesheets(), $this->padding($node)),
            'code' => $this->highlight(rtrim((string) file_get_contents($path))),
            'height' => $this->height($node),
            'showCode' => $node->getAttribute('code') !== 'false',
        ]);
    }

    /**
     * The source, through the same code block the rest of the docs use, so it
     * arrives with highlighting and a copy button rather than as a <pre>.
     */
    private function highlight(string $source): string
    {
        $fence = str_repeat('`', max(3, $this->longestFence($source) + 1));

        return (new MarkdownPipeline)->render($fence."blade\n".$source."\n".$fence);
    }

    private function longestFence(string $source): int
    {
        preg_match_all('/^`+/m', $source, $matches);

        return max(0, ...array_map(strlen(...), $matches[0] ?: ['']));
    }

    /**
     * Render the view, refusing one that reads from the database.
     *
     * A preview is compiled once and served as fixed HTML, so anything it
     * reads is frozen at build time. Silently baking one row of real data
     * into the docs is worse than refusing to build.
     */
    private function compile(string $name, string $path): string
    {
        $queries = [];
        $listening = $this->listenForQueries($queries);

        try {
            return (string) app(Factory::class)->file($path)->render();
        } catch (InvalidPreviewException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw InvalidPreviewException::failed($name, self::$docsFile, $exception);
        } finally {
            if ($listening && $queries !== []) {
                throw InvalidPreviewException::impure($name, self::$docsFile, (string) $queries[0]);
            }
        }
    }

    /**
     * @param  list<string>  $queries
     */
    private function listenForQueries(array &$queries): bool
    {
        try {
            DB::listen(static function (object $query) use (&$queries): void {
                $queries[] = $query->sql ?? 'a query';
            });

            return true;
        } catch (Throwable) {
            // No database configured, which is common for a docs-only app and
            // means there is nothing impure to catch.
            return false;
        }
    }

    /**
     * Your previews directory first, then the examples Vellum ships.
     *
     * The fallback is what lets Vellum's own documentation show a working
     * preview: its pages are rendered by whoever installed the package, and
     * a view that only existed in one application would fail every other
     * build. A view of your own with the same name takes precedence, so the
     * fallback can only ever resolve names the package itself provides.
     */
    private function resolve(string $name): ?string
    {
        foreach ([$this->pathFor($name), $this->packagePathFor($name)] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function pathFor(string $name): string
    {
        $root = rtrim((string) config('vellum.previews.path'), '/\\');

        return $root.DIRECTORY_SEPARATOR.str_replace('.', DIRECTORY_SEPARATOR, $name).'.blade.php';
    }

    private function packagePathFor(string $name): string
    {
        return dirname(__DIR__, 4).'/resources/previews/'
            .str_replace('.', DIRECTORY_SEPARATOR, $name).'.blade.php';
    }

    /**
     * @return list<string>
     */
    private function stylesheets(): array
    {
        $sheets = config('vellum.previews.stylesheets', []);

        if (! is_array($sheets)) {
            return [];
        }

        return array_values(array_filter($sheets, static fn (mixed $href): bool => is_string($href) && $href !== ''));
    }

    private function padding(DirectiveBlock $node): string
    {
        $padding = (string) ($node->getAttribute('padding') ?? '');

        return in_array($padding, ['none', 'sm', 'md', 'lg'], true) ? $padding : 'md';
    }

    private function height(DirectiveBlock $node): ?int
    {
        $height = $node->getAttribute('height');

        return is_string($height) && ctype_digit($height) ? (int) $height : null;
    }
}
