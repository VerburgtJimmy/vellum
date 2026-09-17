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
            'path' => $this->highlightPath(...),
            'securityFor' => fn (Operation $operation): array => $this->security($operation, $spec),
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
            endpoints: $group->methodCount(),
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
        ?int $endpoints = null,
    ): Document {
        return new Document(
            slug: $slug,
            title: $title,
            html: $html,
            headings: $headings,
            // Marks the page as generated, and carries the endpoint count so
            // the sidebar badge does not have to infer it from the headings.
            frontmatter: array_filter(
                ['title' => $title, 'openapi' => true, 'endpoints' => $endpoints],
                static fn (mixed $value): bool => $value !== null,
            ),
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
     * The request path with its parameters marked, so {petId} reads as a slot
     * to fill rather than as part of the literal URL.
     */
    private function highlightPath(string $path): string
    {
        return (string) preg_replace(
            '/\{([^}]+)\}/',
            '<span class="vellum-api-param">{$1}</span>',
            e($path),
        );
    }

    /**
     * Security schemes that apply to an operation, with something useful to
     * say about each. An operation's own security replaces the document's,
     * including an explicit empty array, which means "no auth here".
     *
     * @return list<array{name: string, hint: string}>
     */
    private function security(Operation $operation, Spec $spec): array
    {
        $requirements = $operation->security ?? $spec->defaultSecurity();
        $schemes = $spec->securitySchemes();
        $out = [];
        $seen = [];

        foreach ($requirements as $requirement) {
            foreach (array_keys($requirement) as $name) {
                if (! is_string($name) || isset($seen[$name])) {
                    continue;
                }

                $seen[$name] = true;
                $out[] = ['name' => $name, 'hint' => $this->securityHint($schemes[$name] ?? [])];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $scheme
     */
    private function securityHint(array $scheme): string
    {
        if (is_string($scheme['description'] ?? null) && trim($scheme['description']) !== '') {
            return $scheme['description'];
        }

        $type = is_string($scheme['type'] ?? null) ? $scheme['type'] : 'unknown';

        return match ($type) {
            'http' => is_string($scheme['scheme'] ?? null) && strtolower($scheme['scheme']) === 'bearer'
                ? 'Send a bearer token in the Authorization header.'
                : 'HTTP '.($scheme['scheme'] ?? 'authentication').' in the Authorization header.',
            'apiKey' => 'Send an API key in the '.($scheme['in'] ?? 'header').' '.($scheme['name'] ?? '').'.',
            'oauth2' => 'OAuth 2 access token.',
            'openIdConnect' => 'OpenID Connect token.',
            default => 'This endpoint requires authentication.',
        };
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
