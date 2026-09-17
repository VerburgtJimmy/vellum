<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Vellum\Content\Document;
use Vellum\Content\SearchIndexBuilder;
use Vellum\Markdown\Islands\MarkdownPipeline;

/**
 * Turns a spec into the same Document objects Markdown compiles to.
 *
 * Going through Document rather than a parallel page type is the whole point:
 * search, breadcrumbs, prev/next, the table of contents, raw Markdown and
 * static export are written against Document and need no cases for this.
 *
 * @phpstan-import-type SearchDocument from SearchIndexBuilder
 */
final class PageBuilder
{
    public function __construct(
        private readonly ViewFactory $view,
        private readonly Grouper $grouper = new Grouper,
        private readonly MarkdownPipeline $pipeline = new MarkdownPipeline,
    ) {}

    /**
     * @return list<Document>
     */
    public function build(Spec $spec, ?string $version = null): array
    {
        $groups = $this->grouper->group($spec, $this->groupBy());
        $prefix = $this->prefix();

        $documents = [$this->overview($spec, $groups, $prefix, $version)];

        foreach ($groups as $group) {
            $documents[] = $this->groupPage($group, $prefix, $spec, $version);
        }

        return $documents;
    }

    /**
     * Search entries for individual operations, which are sections rather
     * than pages and would otherwise only be findable by their group name.
     *
     * @return list<SearchDocument>
     */
    public function searchEntries(Spec $spec, string $routePrefix, ?string $version = null): array
    {
        $prefix = $this->prefix();
        $entries = [];

        foreach ($this->grouper->group($spec, $this->groupBy()) as $group) {
            foreach ($group->operations as $operation) {
                $url = '/'.trim($routePrefix, '/').'/'.$prefix.'/'.$group->slug.'#'.$operation->headingId();

                $entries[] = [
                    'id' => $prefix.'/'.$group->slug.'#'.$operation->headingId(),
                    'title' => $operation->method.' '.$operation->path,
                    'description' => $operation->summary ?? '',
                    'content' => $this->operationText($operation),
                    'url' => $url,
                    'headings' => [$operation->title()],
                    'access' => 'guest',
                ];
            }
        }

        return $entries;
    }

    /**
     * @param  list<Group>  $groups
     */
    private function overview(Spec $spec, array $groups, string $prefix, ?string $version): Document
    {
        $routePrefix = trim((string) config('vellum.route.prefix', 'docs'), '/');

        $html = $this->view->make('vellum::openapi.overview', [
            'spec' => $spec,
            'groups' => $groups,
            'markdown' => $this->markdown(...),
            'href' => fn (Group $group): string => '/'.$routePrefix.'/'.$prefix.'/'.$group->slug,
        ])->render();

        $headings = [];

        if ($spec->servers() !== []) {
            $headings[] = ['id' => 'servers', 'text' => 'Servers', 'level' => 2];
        }

        if ($spec->securitySchemes() !== []) {
            $headings[] = ['id' => 'authentication', 'text' => 'Authentication', 'level' => 2];
        }

        $headings[] = [
            'id' => 'groups',
            'text' => count($groups) === 1 ? 'Endpoints' : 'Endpoint groups',
            'level' => 2,
        ];

        if ($spec->webhookNames() !== []) {
            $headings[] = ['id' => 'webhooks', 'text' => 'Webhooks', 'level' => 2];
        }

        return $this->document(
            slug: $prefix,
            title: $this->title($spec),
            html: $html,
            headings: $headings,
            description: $spec->description() === null ? null : $this->summarise($spec->description()),
            spec: $spec,
            version: $version,
        );
    }

    private function groupPage(Group $group, string $prefix, Spec $spec, ?string $version): Document
    {
        $html = $this->view->make('vellum::openapi.group', [
            'group' => $group,
            'markdown' => $this->markdown(...),
        ])->render();

        $headings = array_map(
            static fn (Operation $operation): array => [
                'id' => $operation->headingId(),
                'text' => $operation->title(),
                'level' => 2,
            ],
            $group->operations,
        );

        return $this->document(
            slug: $prefix.'/'.$group->slug,
            title: $group->name,
            html: $html,
            headings: $headings,
            description: $group->description === null
                ? $group->methodCount().' endpoints'
                : $this->summarise($group->description),
            spec: $spec,
            version: $version,
        );
    }

    /**
     * @param  list<array{id: string, text: string, level: int}>  $headings
     */
    private function document(
        string $slug,
        string $title,
        string $html,
        array $headings,
        ?string $description,
        Spec $spec,
        ?string $version,
    ): Document {
        return new Document(
            slug: $slug,
            title: $title,
            html: $html,
            headings: $headings,
            // Marks the page as generated, for anything that needs to tell.
            frontmatter: ['title' => $title, 'openapi' => true],
            path: $spec->path,
            mtime: is_file($spec->path) ? (int) filemtime($spec->path) : 0,
            description: $description,
            version: $version,
        );
    }

    private function title(Spec $spec): string
    {
        $configured = config('vellum.openapi.title');

        return is_string($configured) && trim($configured) !== '' ? $configured : $spec->title();
    }

    private function markdown(string $text): string
    {
        return $this->pipeline->render($text);
    }

    /**
     * First sentence or so, for the meta description and prev/next cards.
     */
    private function summarise(string $markdown): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($this->markdown($markdown))) ?? '');

        return mb_strlen($text) > 160 ? mb_substr($text, 0, 157).'…' : $text;
    }

    private function operationText(Operation $operation): string
    {
        $parts = [$operation->summary ?? '', $operation->description ?? ''];

        foreach ($operation->parameters as $parameter) {
            if (is_string($parameter['name'] ?? null)) {
                $parts[] = $parameter['name'];
            }
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($parts))) ?? '');
    }

    private function prefix(): string
    {
        $prefix = config('vellum.openapi.prefix', 'api');

        return is_string($prefix) && trim($prefix, '/') !== '' ? trim($prefix, '/') : 'api';
    }

    private function groupBy(): string
    {
        return config('vellum.openapi.group_by') === 'path' ? 'path' : 'tag';
    }
}
