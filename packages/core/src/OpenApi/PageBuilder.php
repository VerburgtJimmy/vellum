<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Closure;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Vellum\Content\Document;
use Vellum\Markdown\Islands\MarkdownPipeline;
use Vellum\Markdown\MarkdownRenderer;
use Vellum\OpenApi\Samples\SampleBuilder;

/**
 * Turns a spec into the same Document objects Markdown compiles to.
 *
 * One page per operation, foldered by tag, which is how a reference is read:
 * someone arrives at "delete a pet" from a search result or a link in their
 * own code, not by scrolling a page of every verb on /pets. It also gives
 * each endpoint a URL worth sharing.
 *
 * Going through Document rather than a page type of its own is the point:
 * search, breadcrumbs, previous and next, the compiled store and the static
 * export are written against Document and need no case for this.
 *
 * @phpstan-type Security list<array{name: string, hint: string, detail: string}>
 * @phpstan-type Page array{
 *     operation: Operation,
 *     security: Security,
 *     samples: list<array{key: string, label: string, highlight: string, code: string}>,
 *     responseExamples: array<string, string>
 * }
 */
final class PageBuilder
{
    /** What the spec was when these pages were built. Stamped on each one. */
    private ?string $signature = null;

    public function __construct(
        private readonly ViewFactory $view,
        private readonly Grouper $grouper = new Grouper,
        private readonly MarkdownPipeline $pipeline = new MarkdownPipeline,
        private readonly MarkdownRenderer $prose = new MarkdownRenderer,
        private readonly SampleBuilder $samples = new SampleBuilder,
        private readonly MarkdownRendition $rendition = new MarkdownRendition,
    ) {}

    /**
     * @param  Closure(string): string  $href  The URL of a slug.
     * @param  string|null  $signature  See SpecSource::signature().
     * @return list<Document>
     */
    public function build(Spec $spec, ?string $version, Closure $href, ?string $signature = null): array
    {
        $this->signature = $signature;
        $groups = $this->grouper->group($spec, self::groupBy());
        $prefix = Mount::prefix();
        $documents = [$this->overview($spec, $groups, $prefix, $version, $href)];

        foreach ($groups as $group) {
            foreach ($group->operations as $operation) {
                $documents[] = $this->operationPage($operation, $group, $prefix, $spec, $version);
            }
        }

        return $documents;
    }

    public static function groupBy(): string
    {
        return config('vellum.openapi.group_by') === 'path' ? 'path' : 'tag';
    }

    /**
     * @param  list<Group>  $groups
     * @param  Closure(string): string  $href
     */
    private function overview(Spec $spec, array $groups, string $prefix, ?string $version, Closure $href): Document
    {
        $link = static fn (Group $group, Operation $operation): string => $href(
            $prefix.'/'.$group->slug.'/'.$group->slugFor($operation),
        );

        $html = $this->view->make('vellum::openapi.overview', [
            'spec' => $spec,
            'groups' => $groups,
            'markdown' => $this->markdown(...),
            'intro' => $this->afterOpening($spec->description()),
            'href' => $link,
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

        $title = $this->title($spec);

        return $this->document(
            slug: $prefix,
            title: $title,
            html: $html,
            headings: $headings,
            description: $spec->description() === null ? null : $this->summarise($spec->description()),
            markdown: $this->rendition->overview($title, $spec, $groups, $link),
            spec: $spec,
            version: $version,
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
        $server = $this->serverUrl($spec, $operation);

        $page = [
            'operation' => $operation,
            'security' => $security,
            'samples' => $this->samples->build($operation, $server, $security),
            'responseExamples' => $this->responseExamples($operation),
        ];

        $html = $this->view->make('vellum::openapi.operation', [
            ...$page,
            'markdown' => $this->markdown(...),
            'body' => $this->afterOpening($operation->description),
            'path' => $this->highlightPath(...),
            'sampleTabs' => $this->codeTabs(
                array_map(static fn (array $sample): array => [
                    'label' => $sample['label'],
                    'language' => $sample['highlight'],
                    'code' => $sample['code'],
                ], $page['samples']),
                'api-sample',
            ),
            'responseTabs' => $this->codeTabs(
                array_map(static fn (string $status, string $example): array => [
                    'label' => $status,
                    'language' => 'json',
                    'code' => $example,
                ], array_map(strval(...), array_keys($page['responseExamples'])), array_values($page['responseExamples'])),
                null,
            ),
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
            // Not the request line: the page prints that right under the
            // title, and twice is noise.
            description: $operation->description !== null
                ? $this->summarise($operation->description)
                : null,
            markdown: $this->rendition->operation($page),
            spec: $spec,
            version: $version,
            extra: [
                'openapi_method' => $operation->method,
                'openapi_path' => $operation->path,
                'openapi_deprecated' => $operation->deprecated,
            ],
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
        string $markdown,
        Spec $spec,
        ?string $version,
        array $extra = [],
    ): Document {
        return new Document(
            slug: $slug,
            title: $title,
            html: $html,
            headings: $headings,
            frontmatter: [
                'title' => $title,
                'openapi' => true,
                'openapi_signature' => $this->signature,
                'access' => self::access(),
                ...$extra,
            ],
            path: $spec->path,
            mtime: is_file($spec->path) ? (int) filemtime($spec->path) : 0,
            description: $description,
            version: $version,
            markdown: $markdown,
        );
    }

    /**
     * Who may read the reference. One setting for all of it: a spec has no
     * folders to gate one by one.
     */
    public static function access(): string
    {
        $access = config('vellum.openapi.access');

        return is_string($access) && trim($access) !== '' ? trim($access) : 'guest';
    }

    private function title(Spec $spec): string
    {
        $configured = config('vellum.openapi.title');

        return is_string($configured) && trim($configured) !== '' ? $configured : $spec->title();
    }

    /**
     * Prose from the spec. It goes through the Markdown renderer alone, not
     * the component pipeline: a description is someone else's text as often
     * as not, and it has no business rendering a Blade component of the
     * app's. Vellum's own directives, a callout say, are markup and still work.
     */
    private function markdown(string $text): string
    {
        return $this->prose->convert($text)['html'];
    }

    /**
     * Samples as the docs' own code tabs, so they get the same card,
     * highlighting and copy button as every other block.
     *
     * @param  list<array{label: string, language: string, code: string}>  $blocks
     */
    private function codeTabs(array $blocks, ?string $persist): string
    {
        if ($blocks === []) {
            return '';
        }

        $markdown = ':::tabs'.($persist === null ? '' : ' persist="'.$persist.'"')."\n";

        foreach ($blocks as $block) {
            $fence = str_repeat('`', max(3, self::longestFence($block['code']) + 1));
            $label = trim(str_replace(['[', ']', "\n"], '', $block['label']));
            $markdown .= '::tab['.$label."]\n".$fence.$block['language']."\n".$block['code']."\n".$fence."\n";
        }

        return $this->pipeline->render($markdown.":::\n");
    }

    public static function longestFence(string $source): int
    {
        preg_match_all('/^\s*(`+)/m', $source, $matches);

        return max(0, ...array_map(strlen(...), $matches[1] === [] ? [''] : $matches[1]));
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
            $content = is_array($response) && is_array($response['content'] ?? null) ? $response['content'] : [];

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
     * @return Security
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
        $http = is_string($scheme['scheme'] ?? null) ? $scheme['scheme'] : 'authentication';
        $in = is_string($scheme['in'] ?? null) ? $scheme['in'] : 'header';
        $name = is_string($scheme['name'] ?? null) ? $scheme['name'] : '';

        return match ($type) {
            'http' => strtolower($http) === 'bearer'
                ? 'Send a bearer token in the Authorization header.'
                : 'HTTP '.$http.' in the Authorization header.',
            'apiKey' => trim('Send an API key in the '.$in.' '.$name).'.',
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
            return (is_string($scheme['name'] ?? null) ? $scheme['name'] : 'X-Api-Key').': <key>';
        }

        $httpScheme = is_string($scheme['scheme'] ?? null) ? ucfirst($scheme['scheme']) : 'Bearer';

        return 'Authorization: '.$httpScheme.' <token>';
    }

    /**
     * A description without its opening paragraph, rendered. The page shows
     * that paragraph under the title already, as the page's description.
     */
    private function afterOpening(?string $markdown): string
    {
        $rest = preg_split('/\R\s*\R/', trim((string) $markdown), 2)[1] ?? '';

        return trim($rest) === '' ? '' : $this->markdown($rest);
    }

    /**
     * First sentence or so, for the meta description and previous and next.
     */
    private function summarise(string $markdown): string
    {
        // The opening paragraph only: whatever follows it, a list or a
        // callout, does not read as part of a sentence.
        $opening = preg_split('/\R\s*\R/', trim($markdown), 2)[0] ?? '';
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($this->markdown($opening))) ?? '');

        return mb_strlen($text) > 160 ? mb_substr($text, 0, 157).'…' : $text;
    }
}
