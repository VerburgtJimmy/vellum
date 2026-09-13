<?php

declare(strict_types=1);

it('builds all documents via artisan', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('guides/one.md', "---\ntitle: One\n---\nOne");

    $this->artisan('vellum:build')
        ->expectsOutputToContain('Compiled 2 documents')
        ->assertSuccessful();

    expect(is_file($this->cachePath().'/index.php'))->toBeTrue()
        ->and(is_file($this->cachePath().'/guides/one.php'))->toBeTrue();
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

it('exports versioned pages and unversioned redirects', function (): void {
    $out = sys_get_temp_dir().'/vellum-tests/export-versions-'.$this->fixtureId();

    if (is_dir($out)) {
        $this->deleteDirectory($out);
    }

    config()->set('vellum.export.out', $out);
    config()->set('vellum.versions.enabled', true);
    config()->set('vellum.versions.latest', 'v2');
    config()->set('vellum.versions.list', ['v2', 'v1']);

    $this->writeDoc('v2/index.md', "---\ntitle: V2 Home\n---\nVersion two");
    $this->writeDoc('v2/guides/auth.md', "---\ntitle: Auth\n---\nAuth v2");
    $this->writeDoc('v1/index.md', "---\ntitle: V1 Home\n---\nVersion one");

    $this->artisan('vellum:export')->assertSuccessful();

    $versioned = $out.'/docs/v2/guides/auth/index.html';
    $redirect = $out.'/docs/guides/auth/index.html';
    $versionedIndex = $out.'/docs/v2/index.html';
    $redirectIndex = $out.'/docs/index.html';

    expect(is_file($versioned))->toBeTrue()
        ->and(is_file($redirect))->toBeTrue()
        ->and(is_file($versionedIndex))->toBeTrue()
        ->and(is_file($redirectIndex))->toBeTrue();

    $versionedHtml = file_get_contents($versioned);
    $redirectHtml = file_get_contents($redirect);

    expect($versionedHtml)->not->toBeFalse()
        ->and($versionedHtml)->toContain('Auth v2')
        ->and($versionedHtml)->toContain('data-vellum-version-switcher')
        ->and($versionedHtml)->toContain('../../../../vendor/vellum/vellum.css')
        ->and($redirectHtml)->not->toBeFalse()
        ->and($redirectHtml)->toContain('http-equiv="refresh"')
        ->and($redirectHtml)->toContain('../../v2/guides/auth/');

    $this->deleteDirectory($out);
});
