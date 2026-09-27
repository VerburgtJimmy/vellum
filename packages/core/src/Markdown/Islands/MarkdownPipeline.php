<?php

declare(strict_types=1);

namespace Vellum\Markdown\Islands;

use Vellum\Markdown\MarkdownRenderer;

/**
 * Markdown to HTML with component islands extracted and compiled recursively.
 *
 * @phpstan-import-type Heading from MarkdownRenderer
 *
 * @phpstan-type RenderedDocument array{html: string, headings: list<Heading>, islands: list<Island>}
 */
final class MarkdownPipeline
{
    public function __construct(
        private readonly MarkdownRenderer $renderer = new MarkdownRenderer,
    ) {}

    /**
     * @return RenderedDocument
     */
    public function convert(string $markdown): array
    {
        $protected = CodeGuard::protect($markdown);
        $extracted = IslandExtractor::extract($protected['markdown']);
        $outer = CodeGuard::restore($extracted['markdown'], $protected['codes']);
        $rendered = $this->renderer->convert($outer);

        return [
            'html' => $rendered['html'],
            'headings' => $rendered['headings'],
            'islands' => $this->compileIslands($extracted['islands'], $protected['codes']),
        ];
    }

    public function render(string $markdown): string
    {
        $converted = $this->convert($markdown);

        return (new IslandRenderer)->render($converted['html'], $converted['islands']);
    }

    /**
     * @param  list<RawIsland>  $raws
     * @param  list<string>  $codes
     * @return list<Island>
     */
    private function compileIslands(array $raws, array $codes): array
    {
        $islands = [];

        foreach ($raws as $raw) {
            $slotMarkdown = CodeGuard::restore($raw->slotMarkdown, $codes);
            $slotHtml = $raw->selfClosing || trim($slotMarkdown) === ''
                ? ''
                : $this->renderer->convert($slotMarkdown)['html'];

            $islands[] = new Island(
                id: $raw->id,
                name: $raw->name,
                attributes: $raw->attributes,
                slotHtml: $slotHtml,
                children: $this->compileIslands($raw->children, $codes),
                selfClosing: $raw->selfClosing,
            );
        }

        return $islands;
    }
}
