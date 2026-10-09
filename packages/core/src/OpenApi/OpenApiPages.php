<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Closure;
use Vellum\Content\Document;

/**
 * The OpenAPI half of the content set: resolve a spec, build its pages, and
 * hand ContentRepository the same Documents Markdown produces.
 *
 * Work is done once per instance. A build, a sidebar and a page all ask for
 * the same pages, and parsing a large spec three times in one request shows.
 */
final class OpenApiPages
{
    /** @var list<Document>|null */
    private ?array $documents = null;

    private ?Spec $spec = null;

    private bool $resolved = false;

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(
        private readonly SpecSource $source = new SpecSource,
        private readonly SpecParser $parser = new SpecParser,
        private readonly ?PageBuilder $builder = null,
    ) {}

    /**
     * What the pages would be built from, without building them. Local
     * development compares this with the one the last build recorded.
     */
    public function signature(): ?string
    {
        return $this->source->signature();
    }

    /**
     * @param  Closure(string): string  $href  The URL of a slug.
     * @return list<Document>
     */
    public function documents(?string $version, Closure $href): array
    {
        if ($this->documents !== null) {
            return $this->documents;
        }

        // Before the spec is resolved: an export changes nothing the
        // signature reads, but a failed one must not be remembered as fresh.
        $signature = $this->signature();
        $spec = $this->spec();

        return $this->documents = $spec === null
            ? []
            : $this->builder()->build($spec, $version, $href, $signature);
    }

    /**
     * The reference as one sidebar folder: a folder per tag inside it, each
     * holding its operations with the method beside the title, which is how
     * a reader scans a reference.
     *
     * @param  Closure(string): string  $href
     * @return array<string, mixed>|null
     */
    public function navGroup(?string $version, Closure $href): ?array
    {
        $documents = $this->documents($version, $href);
        $spec = $this->spec();

        if ($spec === null || $documents === []) {
            return null;
        }

        $bySlug = [];

        foreach ($documents as $document) {
            $bySlug[$document->slug] = $document;
        }

        $prefix = Mount::prefix();
        $children = [];

        if (isset($bySlug[$prefix])) {
            $children[] = $this->pageNode($bySlug[$prefix], 'Overview', $href, null);
        }

        foreach ((new Grouper)->group($spec, PageBuilder::groupBy()) as $group) {
            $pages = [];

            foreach ($group->operations as $operation) {
                $document = $bySlug[$prefix.'/'.$group->slug.'/'.$group->slugFor($operation)] ?? null;

                if ($document !== null) {
                    $pages[] = $this->pageNode($document, $document->title, $href, $operation);
                }
            }

            $children[] = [
                'type' => 'folder',
                'title' => $group->name,
                'icon' => null,
                'defaultOpen' => false,
                'children' => $pages,
            ];
        }

        $title = config('vellum.openapi.title');

        return [
            'type' => 'folder',
            'title' => is_string($title) && trim($title) !== '' ? $title : 'API reference',
            'icon' => null,
            'defaultOpen' => false,
            'children' => $children,
        ];
    }

    /**
     * @param  Closure(string): string  $href
     * @return array<string, mixed>
     */
    private function pageNode(Document $document, string $title, Closure $href, ?Operation $operation): array
    {
        $node = [
            'type' => 'page',
            'slug' => $document->slug,
            'title' => $title,
            'description' => $document->description,
            'icon' => null,
            'href' => $href($document->slug),
            'access' => $document->access(),
            'updated' => null,
        ];

        if ($operation !== null) {
            $node['method'] = $operation->method;
            $node['deprecated'] = $operation->deprecated;
        }

        return $node;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        $this->spec();

        return $this->warnings;
    }

    private function spec(): ?Spec
    {
        if ($this->resolved) {
            return $this->spec;
        }

        $this->resolved = true;

        $path = $this->source->resolve();
        $this->warnings = $this->source->warnings();

        if ($path === null) {
            return null;
        }

        $this->spec = $this->parser->parseFile($path);
        $this->warnings = [...$this->warnings, ...$this->spec->warnings];

        return $this->spec;
    }

    private function builder(): PageBuilder
    {
        return $this->builder ?? new PageBuilder(app('view'));
    }
}
