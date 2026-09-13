<?php

declare(strict_types=1);

it('hides unreleased from the changelog page and feed by default', function (): void {
    $path = sys_get_temp_dir().'/vellum-tests/changelog-'.$this->fixtureId().'.md';
    file_put_contents($path, <<<'MD'
# Changelog

Project notes.

## [Unreleased]

- Not shipped yet

## [0.2.0] - 2026-09-13

### Added

- Second release

## [0.1.0] - 2026-09-11

- First release
MD);
    config()->set('vellum.changelog', [
        'path' => $path,
        'unreleased' => false,
    ]);
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->get('/docs/changelog')
        ->assertOk()
        ->assertSee('data-vellum-changelog', false)
        ->assertDontSee('id="unreleased"', false)
        ->assertDontSee('Not shipped yet', false)
        ->assertSee('id="0.2.0"', false)
        ->assertSee('Second release', false)
        ->assertSee('Atom feed', false)
        ->assertSee('application/atom+xml', false);

    $feed = $this->get('/docs/changelog.atom')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/atom+xml; charset=UTF-8');

    $xml = $feed->getContent();
    expect($xml)->toContain('0.2.0')
        ->and($xml)->toContain('0.1.0')
        ->and($xml)->toContain('Second release')
        ->and($xml)->not->toContain('Not shipped yet')
        ->and($xml)->not->toContain('Unreleased');

    unlink($path);
});

it('shows unreleased on the changelog page when enabled, but never in the feed', function (): void {
    $path = sys_get_temp_dir().'/vellum-tests/changelog-'.$this->fixtureId().'.md';
    file_put_contents($path, <<<'MD'
# Changelog

## [Unreleased]

- Not shipped yet

## [0.1.0] - 2026-09-11

- First release
MD);
    config()->set('vellum.changelog', [
        'path' => $path,
        'unreleased' => true,
    ]);
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->get('/docs/changelog')
        ->assertOk()
        ->assertSee('id="unreleased"', false)
        ->assertSee('Not shipped yet', false)
        ->assertSee('id="0.1.0"', false);

    $xml = $this->get('/docs/changelog.atom')->assertOk()->getContent();
    expect($xml)->toContain('0.1.0')
        ->and($xml)->not->toContain('Not shipped yet')
        ->and($xml)->not->toContain('Unreleased');

    unlink($path);
});

it('exports the changelog page and atom feed', function (): void {
    $out = sys_get_temp_dir().'/vellum-tests/export-changelog-'.$this->fixtureId();

    if (is_dir($out)) {
        $this->deleteDirectory($out);
    }

    $path = sys_get_temp_dir().'/vellum-tests/changelog-export-'.$this->fixtureId().'.md';
    file_put_contents($path, <<<'MD'
# Changelog

## [0.1.0] - 2026-09-11

- Shipped
MD);
    config()->set('vellum.changelog', ['path' => $path, 'unreleased' => false]);
    config()->set('vellum.export.out', $out);
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->artisan('vellum:export')->assertSuccessful();

    expect(is_file($out.'/docs/changelog/index.html'))->toBeTrue()
        ->and(is_file($out.'/docs/changelog.atom'))->toBeTrue()
        ->and(file_get_contents($out.'/docs/changelog/index.html'))->toContain('0.1.0')
        ->and(file_get_contents($out.'/docs/changelog.atom'))->toContain('Shipped');

    unlink($path);
    $this->deleteDirectory($out);
});

it('falls through to a docs changelog page when the file changelog is disabled', function (): void {
    config()->set('vellum.changelog', null);
    $this->writeDoc('changelog.md', "---\ntitle: Docs changelog\n---\nFrom markdown");

    $this->get('/docs/changelog')
        ->assertOk()
        ->assertSee('Docs changelog', false)
        ->assertSee('From markdown', false)
        ->assertDontSee('data-vellum-changelog', false);

    $this->get('/docs/changelog.atom')->assertNotFound();
});
