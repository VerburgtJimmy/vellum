<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Vellum\Content\Document;
use Vellum\Markdown\Islands\MarkdownPipeline;
use Vellum\OpenApi\Samples\SampleBuilder;

/**
 * Turns a spec into the same Document objects Markdown compiles to.
 *
 * One page per operation, foldered by tag, which is how reference docs are
 * read: someone arrives at "delete a pet" from a search result or a link in
 * their own code, not by scrolling a page of every verb on /pets. It also
 * means each endpoint has a URL worth sharing.
 *
 * Going through Document rather than a parallel page type is the point:
 * search, breadcrumbs, prev/next, the compiled store and static export are
 * written against Document and need no cases for this.
 */
final class PageBuilder
{
    public function __construct(
        private readonly ViewFactory $view,
        private readonly Grouper $grouper = new Grouper,
        private readonly MarkdownPipeline $pipeline = new MarkdownPipeline,
        private readonly SampleBuilder $samples = new SampleBuilder,
    ) {}

    /**
     * @return list<Document>
     */
    public function build(Spec $spec, ?string $version = null): array
    {
        $groups = $this->grouper->group($spec, $this->groupBy());
        $prefix = Mount::slugPrefix();

        $documents = [$this->overview($spec, $groups, $prefix, $version)];

        foreach ($groups as $group) {
            foreach ($group->operations as $operation) {
                $documents[] = $this->operationPage($operation, $group, $prefix, $spec, $version);
            }
        }

        return $documents;
    }

    /**
     * @param  list<Group>  $groups
     */
    private function overview(Spec $spec, array $groups, string $prefix, ?string $version): Document
    {
        $html = $this->view->make('vellum::openapi.overview', [
            'spec' => $spec,
            'groups' => $groups,
            'markdown' => $this->markdown(...),
            'href' => fn (Group $group, Operation $operation): string => Mount::href(
                $prefix.'/'.$group->slug.'/'.$group->slugFor($operation),
                $version,
            ),
        ])->render();

        $headings = [];

        if ($spec->servers() !== []) {
            $headings[] = ['id' => 'servers', 'text' => 'Servers', 'level' => 2];
        }

        if ($spec->securitySchemes() !== []) {
            $headings[] = ['id' => 'authentication', 'text' => 'Authentication', 'level' => 2];
        }

        foreach ($groups as $group) {
            $headings[] = ['id' => 'group-'.$group->slug, 'text' => $group->name, 'level' => 2];
        }

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
            // The overview is prose and a card grid, so it keeps its contents column.
            full: false,
        );
    }

    private function operationPage(
        Operation $operation,
        Group $group,
        string $prefix,
        Spec $spec,
        ?string $version,
    ): Document {
        $security = $this->security($operation, $spec);

        $html = $this->view->make('vellum::openapi.operation', [
            'operation' => $operation,
            'markdown' => $this->markdown(...),
            'code' => $this->code(...),
            'path' => $this->highlightPath(...),
            'security' => $security,
            'samples' => $this->samples->build($operation, $this->serverUrl($spec, $operation), $security),
            'responseExamples' => $this->responseExamples($operation),
        ])->render();

        $headings = [];

        if ($security !== []) {
            $headings[] = ['id' => 'authorization', 'text' => 'Authorization', 'level' => 2];
        }

        foreach (array_keys($operation->parametersByLocation()) as $location) {
            $headings[] = [
                'id' => $location.'-parameters',
                'text' => ucfirst($location).' parameters',
                'level' => 2,
            ];
        }

        if (is_array($operation->requestBody['content'] ?? null)) {
            $headings[] = ['id' => 'request-body', 'text' => 'Request body', 'level' => 2];
        }

        if ($operation->responses !== []) {
            $headings[] = ['id' => 'responses', 'text' => 'Responses', 'level' => 2];
        }

        return $this->document(
            slug: $prefix.'/'.$group->slug.'/'.$group->slugFor($operation),
            title: $operation->title(),
            html: $html,
            headings: $headings,
            // Null rather than the request line: the layout prints that
            // immediately below in the endpoint card, and twice is noise.
            description: $operation->description !== null
                ? $this->summarise($operation->description)
                : null,
            spec: $spec,
            version: $version,
            // Two columns need the width the contents column would take.
            full: true,
            extra: ['openapi_method' => $operation->method, 'openapi_path' => $operation->path],
        );
    }

    /**
     * @param  list<array{id: string, text: string, level: int}>  $headings
     * @param  array<string, mixed>  $extra
     */
    private function document(
        string $slug,
        string $title,
        string $html,
        array $headings,
        ?string $description,
        Spec $spec,
        ?string $version,
        bool $full,
        array $extra = [],
    ): Document {
        return new Document(
            slug: $slug,
            title: $title,
            html: $html,
            headings: $headings,
            frontmatter: ['title' => $title, 'openapi' => true, 'full' => $full, ...$extra],
            path: $spec->path,
            mtime: is_file($spec->path) ? (int) filemtime($spec->path) : 0,
            description: $description,
            version: $version,
            full: $full,
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
     * A sample as a real Vellum code block, so it gets the same highlighting,
     * frame and copy button as every other block in the docs.
     */
    private function code(string $source, string $language): string
    {
        $fence = str_repeat('`', max(3, $this->longestFence($source) + 1));

        return $this->pipeline->render($fence.$language."\n".$source."\n".$fence);
    }

    private function longestFence(string $source): int
    {
        preg_match_all('/^`+/m', $source, $matches);

        return max(0, ...array_map(strlen(...), $matches[0] ?: ['']));
    }

    /**
     * One example body per status, for the panel beside the schema.
     *
     * @return array<string, string>
     */
    private function responseExamples(Operation $operation): array
    {
        $examples = [];

        foreach ($operation->responses as $status => $response) {
            $content = is_array($response['content'] ?? null) ? $response['content'] : [];

            if ($content === []) {
                continue;
            }

            $media = reset($content);
            $example = is_array($media) ? ExampleGenerator::forMedia($media) : null;

            if ($example === null) {
                continue;
            }

            $examples[(string) $status] = (string) json_encode(
                $example,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        }

        return $examples;
    }

    private function serverUrl(Spec $spec, Operation $operation): ?string
    {
        $configured = config('vellum.openapi.base_url');

        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/');
        }

        foreach ($operation->servers as $server) {
            if (is_string($server['url'] ?? null) && $server['url'] !== '') {
                return rtrim($server['url'], '/');
            }
        }

        return $spec->serverUrl();
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
     * @return list<array{name: string, hint: string, detail: string}>
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
                $scheme = $schemes[$name] ?? [];

                $out[] = [
                    'name' => $name,
                    'hint' => $this->securityHint($scheme),
                    'detail' => $this->securityDetail($scheme),
                ];
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
     * The literal header or parameter a reader has to send.
     *
     * @param  array<string, mixed>  $scheme
     */
    private function securityDetail(array $scheme): string
    {
        $type = is_string($scheme['type'] ?? null) ? $scheme['type'] : '';

        if ($type === 'apiKey') {
            return (string) ($scheme['name'] ?? 'X-Api-Key').': <key>';
        }

        $httpScheme = is_string($scheme['scheme'] ?? null) ? ucfirst($scheme['scheme']) : 'Bearer';

        return 'Authorization: '.$httpScheme.' <token>';
    }

    /**
     * First sentence or so, for the meta description and prev/next cards.
     */
    private function summarise(string $markdown): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($this->markdown($markdown))) ?? '');

        return mb_strlen($text) > 160 ? mb_substr($text, 0, 157).'…' : $text;
    }

    private function groupBy(): string
    {
        return config('vellum.openapi.group_by') === 'path' ? 'path' : 'tag';
    }
}
