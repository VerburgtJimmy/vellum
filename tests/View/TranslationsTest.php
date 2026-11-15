<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Vellum\View\ScriptStrings;

require_once __DIR__.'/../OpenApi/helpers.php';

/**
 * The English language files of each namespace.
 *
 * @return array<string, string>
 */
function languageFiles(): array
{
    $root = dirname(__DIR__, 2);

    return [
        'vellum' => $root.'/resources/lang/en',
        'vellum-core' => $root.'/packages/core/resources/lang/en',
        'vellum-openapi' => $root.'/packages/openapi/resources/lang/en',
    ];
}

/**
 * A locale in which every line is a marker that keeps the line's
 * placeholders and plural forms, so any English left on a page was never
 * a line.
 */
function useMarkerLocale(): void
{
    foreach (languageFiles() as $namespace => $directory) {
        foreach (glob($directory.'/*.php') ?: [] as $file) {
            $group = basename($file, '.php');
            $lines = [];

            foreach (Arr::dot(require $file) as $key => $line) {
                $lines[$group.'.'.$key] = implode('|', array_map(
                    static fn (string $form): string => '¤'.(preg_match_all('/:\w+/', $form, $match) > 0 ? ' '.implode(' ', $match[0]) : ''),
                    explode('|', (string) $line),
                ));
            }

            app('translator')->addLines($lines, 'xx', $namespace);
        }
    }

    app()->setLocale('xx');
}

/**
 * The words a reader sees or hears on a page: its text outside code, the
 * attributes a screen reader speaks, and the strings a script binds to them.
 *
 * @return list<string>
 */
function readableWords(string $html): array
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR);
    $xpath = new DOMXPath($dom);
    $texts = [];

    foreach ($xpath->query('//text()[not(ancestor::script) and not(ancestor::style) and not(ancestor::pre) and not(ancestor::code)]') ?: [] as $node) {
        $texts[] = $node->nodeValue;
    }

    foreach ($xpath->query('//@aria-label | //@title | //@placeholder | //@alt | //meta[@name="description"]/@content') ?: [] as $attribute) {
        $texts[] = $attribute->nodeValue;
    }

    foreach ($xpath->query('//@*[name()=":aria-label" or name()=":title" or name()=":placeholder" or name()="x-text"]') ?: [] as $attribute) {
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", (string) $attribute->nodeValue, $match);
        array_push($texts, ...$match[1]);
    }

    preg_match_all('/\b[A-Za-z]{3,}\b/', implode("\n", $texts), $words);

    return array_values(array_unique($words[0]));
}

/**
 * Words that are not English to translate: the fixture's own made-up words,
 * the product, and the vocabulary of HTTP and of a schema.
 */
const NOT_UI = [
    'Zorp', 'Zorps', 'zorp', 'zorps', 'Blib', 'blib', 'Frob', 'frob', 'Quux', 'quux', 'Glorb', 'glorb',
    'Vellum', 'GET', 'POST', 'https', 'test', 'application', 'json', 'JSON', 'cURL', 'Ctrl', 'ESC', 'esc',
    'string', 'integer', 'uuid', 'Authorization', 'Bearer', 'token', 'http', 'bearer', 'object',
];

beforeEach(function (): void {
    $this->writeDoc('meta.json', json_encode(['pages' => ['index', 'blib', 'frob']], JSON_THROW_ON_ERROR));
    $this->writeDoc('index.md', "---\ntitle: Zorp\n---\nBlib frob.\n\n## Quux\n\nZorp blib.\n\n```bash\nzorp blib\n```\n\n:::note\nBlib.\n:::\n\n:::tip[Glorb]\nFrob.\n:::\n\n:::tabs\n::tab[Zorp]\nBlib.\n::tab[Quux]\nFrob.\n:::\n");
    $this->writeDoc('blib.md', "---\ntitle: Blib\ndescription: Zorp blib.\n---\nFrob quux.\n\n".str_repeat('Zorp blib frob quux glorb. ', 140)."\n\n## Glorb\n\nBlib.");
    $this->writeDoc('frob.md', "---\ntitle: Frob\n---\nQuux.");
});

it('defines every line a view, a class or a script asks for', function (): void {
    $root = dirname(__DIR__, 2);
    $missing = [];
    $sources = [];

    foreach (['resources/views', 'packages/core/resources/views', 'packages/openapi/resources/views', 'src', 'packages/core/src', 'packages/openapi/src'] as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (str_ends_with((string) $file, '.php')) {
                $sources[] = (string) file_get_contents((string) $file);
            }
        }
    }

    // A key whole, or the start of one a view completes, such as a callout's type.
    preg_match_all("/(?:__|Translate::line)\\(\\s*'((?:vellum|vellum-core|vellum-openapi)::[a-z_.]+?)(\\.)?'/", implode("\n", $sources), $keys, PREG_SET_ORDER);

    foreach ($keys as $key) {
        if (! app('translator')->has($key[1], 'en', false)) {
            $missing[] = $key[1];
        }
    }

    $scripts = '';

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/js', FilesystemIterator::SKIP_DOTS)) as $file) {
        $scripts .= file_get_contents((string) $file)."\n";
    }

    $files = implode('|', array_keys(ScriptStrings::FILES));
    preg_match_all("/['`](($files)\.[a-z_.]+?)(\.\\$\{)?['`]/", $scripts, $lines, PREG_SET_ORDER);

    expect($lines)->not->toBeEmpty();

    foreach ($lines as [, $key, $file]) {
        [, $path] = explode('.', $key, 2);

        if (! app('translator')->has(ScriptStrings::FILES[$file].'.'.$path, 'en', false)) {
            $missing[] = $key;
        }

        // A line every page's scripts read has to be in the page's block.
        $carried = $file === ScriptStrings::PLAYGROUND || array_filter(
            ScriptStrings::KEYS,
            static fn (string $prefix): bool => $key === $prefix || str_starts_with($key, $prefix.'.'),
        ) !== [];

        if (! $carried) {
            $missing[] = $key.' (not in the block)';
        }
    }

    expect($keys)->not->toBeEmpty()
        ->and($missing)->toBe([]);
});

it('leaves no English on a page, the changelog, a missing page or an operation', function (): void {
    $changelog = $this->writeDoc('../CHANGELOG.md', "# Zorp\n\n## [1.0.0] - 2026-01-02\n\n### Blib\n\n- Frob.\n");
    config()->set('vellum.changelog', $changelog);
    config()->set('vellum.name', 'Vellum');
    enableOpenApi($this->cachePath(), [
        'openapi' => '3.1.0',
        'info' => ['title' => 'Zorp', 'version' => '1.0.0', 'description' => 'Blib.'],
        'servers' => [['url' => 'https://zorp.test/{glorb}', 'description' => 'Blib', 'variables' => ['glorb' => ['default' => 'zorp', 'enum' => ['zorp', 'blib']]]]],
        'tags' => [['name' => 'Zorps', 'description' => 'Frob.']],
        'components' => ['securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer']]],
        'security' => [['bearer' => []]],
        'paths' => [
            '/zorps/{zorp}' => ['post' => [
                'tags' => ['Zorps'],
                'operationId' => 'frobZorp',
                'summary' => 'Frob',
                'deprecated' => true,
                'parameters' => [
                    ['name' => 'zorp', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']],
                    ['name' => 'quux', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 2], 'example' => 3],
                ],
                'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                    'type' => 'object',
                    'required' => ['blib'],
                    'properties' => [
                        'blib' => ['type' => 'string', 'enum' => ['zorp', 'frob']],
                        'glorb' => ['type' => 'object', 'properties' => ['quux' => ['type' => 'string']]],
                    ],
                ]]]],
                'responses' => ['200' => ['description' => 'Blib', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['zorp' => ['type' => 'string']]]]]]],
            ]],
            '/frobs' => ['get' => ['summary' => 'Glorb', 'responses' => ['200' => ['description' => 'Blib']]]],
        ],
        'webhooks' => ['zorp' => ['post' => ['summary' => 'Quux', 'responses' => ['200' => ['description' => 'Blib']]]]],
    ], ['title' => 'Glorb']);
    useMarkerLocale();

    $pages = [
        '/docs' => 200,
        '/docs/blib' => 200,
        '/docs/changelog' => 200,
        '/docs/glorb' => 404,
        '/docs/api' => 200,
        '/docs/api/zorps/frob-zorp' => 200,
    ];

    foreach ($pages as $page => $status) {
        $html = (string) $this->get($page)->assertStatus($status)->getContent();

        expect(array_values(array_diff(readableWords($html), NOT_UI)))->toBe([], $page)
            ->and($html)->toContain('lang="xx"');

        // The scripts' lines are markers too.
        preg_match('#<script type="application/json" id="vellum-strings">(.*?)</script>#s', $html, $block);
        $lines = Arr::dot(json_decode($block[1] ?? '', true, 512, JSON_THROW_ON_ERROR));

        expect($lines)->not->toBeEmpty()
            ->and(array_filter($lines, static fn (mixed $line): bool => preg_match('/^¤/u', (string) $line) !== 1))->toBe([]);
    }
});

it('writes the date a page changed in the app language', function (): void {
    $this->writeDoc('frob.md', "---\ntitle: Frob\nupdated: 2026-11-14\n---\nQuux.");
    app()->setLocale('nl');

    expect($this->get('/docs/frob')->assertOk()->getContent())
        ->toContain('title="14 november 2026">Updated 14 nov. 2026</time>');
});

it('writes the date in English by default, as before', function (): void {
    $this->writeDoc('frob.md', "---\ntitle: Frob\nupdated: 2026-11-14\n---\nQuux.");

    expect($this->get('/docs/frob')->assertOk()->getContent())
        ->toContain('title="November 14, 2026">Updated Nov 14, 2026</time>');
});

it('exports the scripts lines in the locale it runs in', function (): void {
    useMarkerLocale();
    $out = $this->cachePath().'/export';

    $this->artisan('vellum:export', ['--out' => $out])->assertSuccessful();

    expect(file_get_contents($out.'/docs/index.html'))
        ->toContain('lang="xx"')
        ->toContain('<script type="application/json" id="vellum-strings">{"search":{"recent":"¤"');
});

it('publishes the strings of all three packages under one tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(null, 'vellum-lang');

    expect(array_map(static fn (string $to): string => basename($to), array_values($paths)))
        ->toEqualCanonicalizing(['vellum', 'vellum-core', 'vellum-openapi'])
        ->and(array_map(static fn (string $from): bool => is_file($from.'/en/page.php') || is_file($from.'/en/code.php') || is_file($from.'/en/reference.php'), array_keys($paths)))
        ->each->toBeTrue();
});
