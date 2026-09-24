<?php

declare(strict_types=1);

use Illuminate\Support\Facades\URL;
use Vellum\Cache\FragmentCache;
use Vellum\Content\Document;
use Vellum\Search\SearchDriver;

it('builds all documents via artisan', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('guides/one.md', "---\ntitle: One\n---\nOne");

    $this->artisan('vellum:build')
        ->expectsOutputToContain('Compiled 2 documents')
        ->assertSuccessful();

    expect(is_file($this->cachePath().'/index.php'))->toBeTrue()
        ->and(is_file($this->cachePath().'/guides/one.php'))->toBeTrue();
});

it('warns once when the configured preset was removed in 0.5', function (): void {
    config()->set('vellum.theme.preset', 'catppuccin');
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('guides/one.md', "---\ntitle: One\n---\nOne");

    $this->artisan('vellum:build')
        ->expectsOutputToContain('Colour preset "catppuccin" was removed in 0.5')
        ->expectsOutputToContain('neutral, ocean, laravel')
        ->assertSuccessful();
});

it('stays quiet for a preset that still ships', function (): void {
    config()->set('vellum.theme.preset', 'ocean');
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->artisan('vellum:build')
        ->doesntExpectOutputToContain('was removed in 0.5')
        ->assertSuccessful();
});

it('clears the cache via artisan', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->artisan('vellum:build')->assertSuccessful();
    expect(is_file($this->cachePath().'/index.php'))->toBeTrue();

    $this->artisan('vellum:clear')
        ->expectsOutputToContain('Vellum cache cleared')
        ->assertSuccessful();

    expect(is_file($this->cachePath().'/index.php'))->toBeFalse();
});

it('clears the island fragment cache via artisan', function (): void {
    $document = new Document(
        slug: 'index',
        title: 'Home',
        html: '<p>Hi</p>',
        headings: [],
        frontmatter: [],
        path: '/tmp/index.md',
        mtime: 1,
    );

    $cache = new FragmentCache;
    $hits = 0;
    $resolve = function () use (&$hits): string {
        $hits++;

        return 'frag-'.$hits;
    };

    $cache->remember($document, $resolve);

    $this->artisan('vellum:clear')->assertSuccessful();

    expect($cache->remember($document, $resolve))->toBe('frag-2')
        ->and($hits)->toBe(2);
});

it('clears vellum caches from optimize:clear', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->artisan('vellum:build')->assertSuccessful();
    expect(is_file($this->cachePath().'/index.php'))->toBeTrue();

    $this->artisan('optimize:clear')->assertSuccessful();

    expect(is_file($this->cachePath().'/index.php'))->toBeFalse();
});

it('installs config stubs and public assets', function (): void {
    $configTarget = config_path('vellum.php');
    $publicTarget = public_path('vendor/vellum');
    $hadConfig = is_file($configTarget);

    if ($hadConfig) {
        unlink($configTarget);
    }

    if (is_dir($publicTarget)) {
        $this->deleteDirectory($publicTarget);
    }

    $this->artisan('vellum:install')
        ->expectsOutputToContain('Vellum installed successfully')
        ->assertSuccessful();

    expect(is_file($configTarget))->toBeTrue()
        ->and(is_file($this->docsPath().'/index.md'))->toBeTrue()
        ->and(is_file($publicTarget.'/vellum.css'))->toBeTrue()
        ->and(is_file($publicTarget.'/vellum.js'))->toBeTrue();

    $original = file_get_contents($this->docsPath().'/index.md');
    file_put_contents($this->docsPath().'/index.md', "custom\n");

    $this->artisan('vellum:install')->assertSuccessful();

    expect(file_get_contents($this->docsPath().'/index.md'))->toBe("custom\n");

    $this->artisan('vellum:install', ['--force' => true])->assertSuccessful();

    expect(file_get_contents($this->docsPath().'/index.md'))->not->toBe("custom\n")
        ->and(file_get_contents($this->docsPath().'/index.md'))->toBe($original);

    if (! $hadConfig && is_file($configTarget)) {
        unlink($configTarget);
    }

    if (is_dir($publicTarget)) {
        $this->deleteDirectory($publicTarget);
    }
});

it('exports documents assets and search index for a static host', function (): void {
    $out = sys_get_temp_dir().'/vellum-tests/export-'.$this->fixtureId();

    if (is_dir($out)) {
        $this->deleteDirectory($out);
    }

    config()->set('vellum.export.out', $out);
    config()->set('vellum.export.base_url', '/');

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHello export");
    $this->writeDoc('guides/one.md', "---\ntitle: One\n---\nGuide body");
    $this->writeDoc('assets/diagram.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    $this->artisan('vellum:export')
        ->expectsOutputToContain('Exported')
        ->assertSuccessful();

    $index = $out.'/docs/index.html';
    $guide = $out.'/docs/guides/one/index.html';
    $asset = $out.'/docs/_vellum/files/assets/diagram.svg';
    $css = $out.'/vendor/vellum/vellum.css';
    $search = $out.'/docs/_vellum/search.json';

    expect(is_file($index))->toBeTrue()
        ->and(is_file($guide))->toBeTrue()
        ->and(is_file($asset))->toBeTrue()
        ->and(is_file($css))->toBeTrue()
        ->and(is_file($search))->toBeTrue();

    $html = file_get_contents($index);
    $guideHtml = file_get_contents($guide);

    expect($html)->not->toBeFalse()
        ->and($html)->toContain('Hello export')
        ->and($html)->toContain('../vendor/vellum/vellum.css')
        ->and($guideHtml)->not->toBeFalse()
        ->and($guideHtml)->toContain('Guide body')
        ->and($guideHtml)->toContain('../../../vendor/vellum/vellum.css')
        ->and($guideHtml)->toContain('../../../vendor/vellum/vellum.js');

    $rawIndex = $out.'/docs/_vellum/raw/index.md';
    $rawGuide = $out.'/docs/_vellum/raw/guides/one.md';

    expect(is_file($rawIndex))->toBeTrue()
        ->and(is_file($rawGuide))->toBeTrue()
        ->and(file_get_contents($rawIndex))->toContain('Hello export')
        ->and(file_get_contents($rawGuide))->toContain('Guide body')
        ->and($html)->toContain('_vellum/raw/index.md');

    $this->deleteDirectory($out);
});

it('exports only the files the asset route would serve', function (): void {
    $out = sys_get_temp_dir().'/vellum-tests/export-files-'.$this->fixtureId();

    if (is_dir($out)) {
        $this->deleteDirectory($out);
    }

    config()->set('vellum.export.out', $out);

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('assets/diagram.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    $this->writeDoc('guide.pdf', '%PDF-1.4');
    $this->writeDoc('.env', 'APP_KEY=secret');
    $this->writeDoc('.vellum/questions/abc.json', '{"questions": []}');
    $this->writeDoc('questions.yml', '- q: test');
    $this->writeDoc('notes.docx', 'draft');

    $this->artisan('vellum:export')->assertSuccessful();

    $files = $out.'/docs/_vellum/files';

    expect(is_file($files.'/assets/diagram.svg'))->toBeTrue()
        ->and(is_file($files.'/guide.pdf'))->toBeTrue()
        ->and(file_exists($files.'/.env'))->toBeFalse()
        ->and(file_exists($files.'/.vellum'))->toBeFalse()
        ->and(file_exists($files.'/questions.yml'))->toBeFalse()
        ->and(file_exists($files.'/notes.docx'))->toBeFalse();

    $this->deleteDirectory($out);
});

it('exports the latest version unprefixed and older versions under /docs/{version}', function (): void {
    $out = sys_get_temp_dir().'/vellum-tests/export-versions-'.$this->fixtureId();

    if (is_dir($out)) {
        $this->deleteDirectory($out);
    }

    config()->set('app.url', 'http://example.com');
    URL::forceRootUrl('http://example.com');
    config()->set('vellum.export.out', $out);
    config()->set('vellum.versions.enabled', true);
    config()->set('vellum.versions.latest', 'v2');
    config()->set('vellum.versions.list', ['v2', 'v1']);

    $this->writeDoc('v2/index.md', "---\ntitle: V2 Home\n---\nVersion two");
    $this->writeDoc('v2/guides/auth.md', "---\ntitle: Auth\n---\nAuth v2");
    $this->writeDoc('v1/index.md', "---\ntitle: V1 Home\n---\nVersion one");

    $this->artisan('vellum:export')->assertSuccessful();

    $latest = $out.'/docs/guides/auth/index.html';
    $redirect = $out.'/docs/v2/guides/auth/index.html';
    $latestIndex = $out.'/docs/index.html';
    $redirectIndex = $out.'/docs/v2/index.html';
    $older = $out.'/docs/v1/index.html';

    expect(is_file($latest))->toBeTrue()
        ->and(is_file($redirect))->toBeTrue()
        ->and(is_file($latestIndex))->toBeTrue()
        ->and(is_file($redirectIndex))->toBeTrue()
        ->and(is_file($older))->toBeTrue();

    $latestHtml = file_get_contents($latest);
    $redirectHtml = file_get_contents($redirect);
    $olderHtml = file_get_contents($older);

    expect($latestHtml)->not->toBeFalse()
        ->and($latestHtml)->toContain('Auth v2')
        ->and($latestHtml)->toContain('data-vellum-version-switcher')
        ->and($latestHtml)->toContain('Latest')
        ->and($latestHtml)->toContain('../../../vendor/vellum/vellum.css')
        ->and($redirectHtml)->not->toBeFalse()
        ->and($redirectHtml)->toContain('http-equiv="refresh"')
        ->and($redirectHtml)->toContain('../../../guides/auth/')
        ->and($redirectHtml)->toContain('<link rel="canonical" href="http://example.com/docs/guides/auth">')
        ->and($olderHtml)->not->toBeFalse()
        ->and($olderHtml)->toContain('Version one');

    $this->deleteDirectory($out);
});

it('drops auth-only pages from the exported MiniSearch index', function (): void {
    $out = sys_get_temp_dir().'/vellum-tests/export-search-'.$this->fixtureId();

    if (is_dir($out)) {
        $this->deleteDirectory($out);
    }

    config()->set('vellum.export.out', $out);
    config()->set('vellum.search.driver', 'scout');

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHello export");
    $this->writeDoc('secret.md', "---\ntitle: Secret\naccess: auth\n---\nNope");

    $this->artisan('vellum:export')
        ->expectsOutputToContain('Dropped gated page: secret (access: auth)')
        ->assertSuccessful();

    $search = $out.'/docs/_vellum/search.json';
    $html = file_get_contents($out.'/docs/index.html');

    expect(is_file($search))->toBeTrue()
        ->and($html)->not->toBeFalse()
        ->and(is_file($out.'/docs/secret/index.html'))->toBeFalse()
        ->and(is_file($out.'/docs/_vellum/raw/secret.md'))->toBeFalse();

    /** @var array{driver: string, documents: list<array{title: string}>} $payload */
    $payload = json_decode((string) file_get_contents($search), true, 512, JSON_THROW_ON_ERROR);

    expect($payload['driver'])->toBe('minisearch')
        ->and(collect($payload['documents'])->pluck('title')->all())->toContain('Home')
        ->and(collect($payload['documents'])->pluck('title')->all())->not->toContain('Secret')
        ->and($html)->toContain('data-vellum-search-driver="minisearch"')
        ->and($html)->toContain('_vellum/search-');

    $this->deleteDirectory($out);
});

it('rebuilds the MiniSearch index via vellum:index', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->artisan('vellum:index')
        ->expectsOutputToContain('MiniSearch index rebuilt')
        ->assertSuccessful();

    expect(is_file($this->cachePath().'/search-index.json'))->toBeTrue();
});

it('fails vellum:index when scout is configured without laravel/scout', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    config()->set('vellum.search.driver', 'scout');

    SearchDriver::$scoutTrait = 'Laravel\\Scout\\Missing';

    try {
        expect(fn () => $this->artisan('vellum:index'))
            ->toThrow(RuntimeException::class, 'laravel/scout is not installed');
    } finally {
        SearchDriver::$scoutTrait = 'Laravel\\Scout\\Searchable';
    }
});

it('republishes the config only when --force is given', function (): void {
    $configTarget = config_path('vellum.php');
    $hadConfig = is_file($configTarget);
    $backup = $hadConfig ? file_get_contents($configTarget) : null;

    // A valid config, so a failure here cannot leave the skeleton app unbootable.
    $edited = "<?php\n\nreturn ['name' => 'Edited by hand'];\n";

    try {
        file_put_contents($configTarget, $edited);

        $this->artisan('vellum:install')
            ->expectsOutputToContain('use --force to overwrite')
            ->assertSuccessful();

        expect(file_get_contents($configTarget))->toBe($edited);

        $this->artisan('vellum:install', ['--force' => true])
            ->expectsOutputToContain('Published config')
            ->assertSuccessful();

        expect(file_get_contents($configTarget))
            ->toBe(file_get_contents(dirname(__DIR__, 2).'/config/vellum.php'));
    } finally {
        if ($backup !== null) {
            file_put_contents($configTarget, $backup);
        } elseif (is_file($configTarget)) {
            unlink($configTarget);
        }

        $publicTarget = public_path('vendor/vellum');

        if (is_dir($publicTarget)) {
            $this->deleteDirectory($publicTarget);
        }
    }
});

it('warns when a docs page is shadowed by the changelog route', function (): void {
    // Outside the docs directory, where a real changelog.path points. Inside it,
    // CHANGELOG.md is also discovered as a page and collides with changelog.md on
    // a case-sensitive filesystem.
    $changelog = sys_get_temp_dir().'/vellum-tests/CHANGELOG-'.$this->fixtureId().'.md';
    file_put_contents($changelog, "# Changelog\n\n## [1.0.0] - 2026-01-02\n\n- Shipped\n");
    config()->set('vellum.changelog.path', $changelog);

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('changelog.md', "---\ntitle: My Changelog\n---\nMine");

    $this->artisan('vellum:build')
        ->expectsOutputToContain('shadowed by the changelog route')
        ->assertSuccessful();

    unlink($changelog);
});

it('does not warn about a changelog page when the changelog route is off', function (): void {
    config()->set('vellum.changelog', null);

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('changelog.md', "---\ntitle: My Changelog\n---\nMine");

    $this->artisan('vellum:build')
        ->doesntExpectOutputToContain('shadowed by the changelog route')
        ->assertSuccessful();
});

it('exports a 404 page at the root with root-relative assets', function (): void {
    $out = sys_get_temp_dir().'/vellum-tests/export-404-'.$this->fixtureId();

    if (is_dir($out)) {
        $this->deleteDirectory($out);
    }

    config()->set('vellum.export.out', $out);
    config()->set('vellum.export.base_url', '/');

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHello");

    $this->artisan('vellum:export')->assertSuccessful();

    $path = $out.'/404.html';

    expect(is_file($path))->toBeTrue();

    $html = (string) file_get_contents($path);

    // Root-relative, because a host serves this file for URLs at any depth and
    // "../vendor/..." would resolve differently for each one.
    expect($html)->toContain('Page not found')
        ->and($html)->toContain('<meta name="robots" content="noindex">')
        ->and($html)->toContain('"/vendor/vellum/vellum.css')
        ->and($html)->not->toContain('"./vendor/vellum/')
        ->and($html)->not->toContain('"../vendor/vellum/');

    $this->deleteDirectory($out);
});

it('points exported 404 assets at an absolute base url when one is set', function (): void {
    $out = sys_get_temp_dir().'/vellum-tests/export-404-base-'.$this->fixtureId();

    if (is_dir($out)) {
        $this->deleteDirectory($out);
    }

    config()->set('vellum.export.out', $out);
    config()->set('vellum.export.base_url', '/handbook/');

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHello");

    $this->artisan('vellum:export')->assertSuccessful();

    expect((string) file_get_contents($out.'/404.html'))
        ->toContain('"/handbook/vendor/vellum/vellum.css');

    $this->deleteDirectory($out);
});

it('writes a sitemap at the export root using the export base url', function (): void {
    $out = sys_get_temp_dir().'/vellum-tests/export-sitemap-'.$this->fixtureId();

    if (is_dir($out)) {
        $this->deleteDirectory($out);
    }

    config()->set('app.url', 'https://app.example.com');
    config()->set('vellum.export.out', $out);
    // The export is served from somewhere else, so its base url wins.
    config()->set('vellum.export.base_url', 'https://static.example.com');

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('guides/one.md', "---\ntitle: One\n---\nBody");
    $this->writeDoc('secret.md', "---\ntitle: Secret\naccess: auth\n---\nPrivate");

    $this->artisan('vellum:export')->assertSuccessful();

    $sitemap = $out.'/sitemap.xml';

    expect(is_file($sitemap))->toBeTrue();

    $xml = (string) file_get_contents($sitemap);

    expect($xml)
        ->toContain('<loc>https://static.example.com/docs</loc>')
        ->toContain('<loc>https://static.example.com/docs/guides/one</loc>')
        ->not->toContain('app.example.com')
        ->not->toContain('secret');

    $this->deleteDirectory($out);
});

it('skips the export sitemap when no origin is configured', function (): void {
    $out = sys_get_temp_dir().'/vellum-tests/export-nositemap-'.$this->fixtureId();

    if (is_dir($out)) {
        $this->deleteDirectory($out);
    }

    config()->set('app.url', '');
    config()->set('vellum.export.out', $out);
    config()->set('vellum.export.base_url', '/');

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->artisan('vellum:export')
        ->expectsOutputToContain('Skipped sitemap.xml')
        ->assertSuccessful();

    expect(is_file($out.'/sitemap.xml'))->toBeFalse();

    $this->deleteDirectory($out);
});

it('keeps canonical and og:url absolute in an export, matching the sitemap', function (string $baseUrl): void {
    $out = sys_get_temp_dir().'/vellum-tests/export-canonical-'.$this->fixtureId();

    if (is_dir($out)) {
        $this->deleteDirectory($out);
    }

    config()->set('app.url', 'https://example.com');
    config()->set('vellum.export.out', $out);
    config()->set('vellum.export.base_url', $baseUrl);

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('guides/one.md', "---\ntitle: One\n---\nGuide body");

    $this->artisan('vellum:export')->assertSuccessful();

    $html = (string) file_get_contents($out.'/docs/guides/one/index.html');

    expect($html)->toContain('<link rel="canonical" href="https://example.com/docs/guides/one">')
        ->and($html)->toContain('<meta property="og:url" content="https://example.com/docs/guides/one">')
        ->and((string) file_get_contents($out.'/sitemap.xml'))->toContain('<loc>https://example.com/docs/guides/one</loc>');
})->with(['/', 'https://example.com']);
