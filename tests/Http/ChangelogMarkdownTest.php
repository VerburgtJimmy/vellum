<?php

declare(strict_types=1);

use Vellum\Support\LlmsTxt;

const CHANGELOG_SOURCE = <<<'MD'
# Changelog

Project notes.

## [Unreleased]

- Not shipped yet

## [0.2.0] - 2026-09-13

- Second release

[unreleased]: https://example.com/compare/0.2.0...HEAD
[0.2.0]: https://example.com/releases/0.2.0
MD;

const CHANGELOG_PUBLISHED = <<<'MD'
# Changelog

Project notes.

## [0.2.0] - 2026-09-13

- Second release

[unreleased]: https://example.com/compare/0.2.0...HEAD
[0.2.0]: https://example.com/releases/0.2.0
MD;

beforeEach(function (): void {
    config()->set('app.url', 'https://docs.example.com');
    $this->changelogDir = $this->docsPath().'-changelog';
    $this->changelogPath = $this->changelogDir.'/CHANGELOG.md';

    if (! is_dir($this->changelogDir)) {
        mkdir($this->changelogDir, 0755, true);
    }

    file_put_contents($this->changelogPath, CHANGELOG_SOURCE);
    config()->set('vellum.changelog', ['path' => $this->changelogPath, 'unreleased' => false]);
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
});

afterEach(function (): void {
    $this->deleteDirectory($this->changelogDir);
});

it('serves the changelog as raw markdown without the hidden unreleased section', function (): void {
    $response = $this->get('/docs/_vellum/raw/changelog.md')->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/markdown; charset=UTF-8')
        ->and($response->getContent())->toBe(CHANGELOG_PUBLISHED);

    $response->assertHeader('Link', '<https://docs.example.com/docs/changelog>; rel="canonical"')
        ->assertHeaderMissing('Last-Modified')
        ->assertHeaderMissing('X-Vellum-Docs-Version');
});

it('serves the changelog file as written when unreleased is shown', function (): void {
    config()->set('vellum.changelog.unreleased', true);

    expect($this->get('/docs/_vellum/raw/changelog.md')->assertOk()->getContent())->toBe(CHANGELOG_SOURCE);
});

it('keeps a changelog whose last section is unreleased readable', function (): void {
    file_put_contents($this->changelogPath, "# Changelog\n\n## [0.1.0] - 2026-09-11\n\n- First\n\n## [Unreleased]\n\n- Next\n\n[0.1.0]: https://example.com/0.1.0\n");

    expect($this->get('/docs/_vellum/raw/changelog.md')->getContent())
        ->toBe("# Changelog\n\n## [0.1.0] - 2026-09-11\n\n- First\n\n[0.1.0]: https://example.com/0.1.0\n");
});

it('points the changelog page at its markdown', function (): void {
    $response = $this->get('/docs/changelog')->assertOk();

    expect((string) $response->getContent())
        ->toContain('<link rel="alternate" type="text/markdown" href="http://localhost/docs/_vellum/raw/changelog.md">');
    $response->assertHeader('Link', '<http://localhost/docs/_vellum/raw/changelog.md>; rel="alternate"; type="text/markdown"');
    expect($response->baseResponse->getVary())->toContain('Accept');
});

it('negotiates the changelog page', function (): void {
    $response = $this->get('/docs/changelog', ['Accept' => 'text/markdown'])->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/markdown; charset=UTF-8')
        ->and($response->getContent())->toBe(CHANGELOG_PUBLISHED)
        ->and($response->baseResponse->getVary())->toContain('Accept');
});

it('has no changelog markdown when the changelog is off', function (): void {
    config()->set('vellum.changelog', null);

    $this->get('/docs/_vellum/raw/changelog.md')->assertNotFound();
    expect((string) $this->get('/docs/llms.txt')->getContent())->not->toContain('changelog');
});

it('lists the changelog where the sidebar has it', function (): void {
    $this->writeDoc('guide.md', "---\ntitle: Guide\n---\nBody");
    file_put_contents($this->docsPath().'/meta.json', json_encode([
        'pages' => ['index', ['title' => 'Changelog', 'slug' => 'changelog'], 'guide'],
    ]));

    $index = (string) $this->get('/docs/llms.txt')->getContent();

    expect($index)->toContain(implode("\n", [
        '- [Home](https://docs.example.com/docs/_vellum/raw/index.md)',
        '- [Changelog](https://docs.example.com/docs/_vellum/raw/changelog.md): Release notes',
        '- [Guide](https://docs.example.com/docs/_vellum/raw/guide.md)',
    ]))->not->toContain('## Optional');
});

it('puts a changelog the sidebar does not list last, under Optional', function (): void {
    $index = (string) $this->get('/docs/llms.txt')->getContent();
    $full = (string) $this->get('/docs/llms-full.txt')->getContent();

    expect($index)->toEndWith("## Optional\n\n- [Changelog](https://docs.example.com/docs/_vellum/raw/changelog.md): Release notes\n")
        ->and($full)->toEndWith(implode("\n", [
            LlmsTxt::SEPARATOR,
            'Title: Changelog',
            'URL: https://docs.example.com/docs/changelog',
            LlmsTxt::SEPARATOR,
            '',
            CHANGELOG_PUBLISHED,
            '',
        ]))
        ->not->toContain('Not shipped yet');
});

it('names no version on the changelog in llms-full.txt', function (): void {
    config()->set('vellum.versions', ['enabled' => true, 'latest' => 'v2', 'list' => ['v2'], 'labels' => []]);
    $this->writeDoc('v2/index.md', "---\ntitle: Home\n---\nHi");

    $full = (string) $this->get('/docs/llms-full.txt')->getContent();

    expect($full)->toContain("Title: Home\nURL: https://docs.example.com/docs\nVersion: v2\n")
        ->toContain("Title: Changelog\nURL: https://docs.example.com/docs/changelog\n".LlmsTxt::SEPARATOR);
});

it('dates the changelog from its last commit', function (): void {
    $this->skipWithoutGit();

    file_put_contents($this->docsPath().'/meta.json', json_encode([
        'pages' => ['index', ['title' => 'Changelog', 'slug' => 'changelog']],
    ]));
    $this->git($this->changelogDir, ['init', '-q']);
    $this->commitAll($this->changelogDir, 'Release.', '2026-09-13T12:00:00+00:00');

    $this->get('/docs/_vellum/raw/changelog.md')->assertHeader('Last-Modified', 'Sun, 13 Sep 2026 12:00:00 GMT');
    $this->get('/docs/changelog', ['Accept' => 'text/markdown'])->assertHeader('Last-Modified', 'Sun, 13 Sep 2026 12:00:00 GMT');

    expect((string) $this->get('/docs/llms-full.txt')->getContent())
        ->toContain("URL: https://docs.example.com/docs/changelog\nUpdated: 2026-09-13T12:00:00+00:00\n");

    expect((string) $this->get('/docs/sitemap.xml')->getContent())
        ->toContain("<loc>https://docs.example.com/docs/changelog</loc>\n        <lastmod>2026-09-13T12:00:00+00:00</lastmod>");
});

it('exports the changelog markdown', function (): void {
    $out = sys_get_temp_dir().'/vellum-tests/export-changelog-md-'.$this->fixtureId();

    if (is_dir($out)) {
        $this->deleteDirectory($out);
    }

    config()->set('vellum.export.out', $out);

    $this->artisan('vellum:export')->assertSuccessful();

    expect(file_get_contents($out.'/docs/_vellum/raw/changelog.md'))->toBe(CHANGELOG_PUBLISHED)
        ->and((string) file_get_contents($out.'/llms.txt'))->toContain('/docs/_vellum/raw/changelog.md');

    $this->deleteDirectory($out);
});
