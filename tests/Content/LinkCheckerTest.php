<?php

declare(strict_types=1);

use Vellum\Content\ContentRepository;
use Vellum\Content\LinkChecker;

function findings(object $test): array
{
    $repository = ContentRepository::fromConfig();

    return (new LinkChecker)->check($repository->buildAll(), $repository);
}

beforeEach(function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHome page");
});

it('passes a page whose links and images all resolve', function (): void {
    $this->writeDoc('guides/deploy.md', "---\ntitle: Deploy\n---\n## Ship it\n\nBody");
    $this->writeDoc('assets/diagram.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    $this->writeDoc('start.md', <<<'MD'
    ---
    title: Start
    ---
    ## Section one

    [Deploy](/docs/guides/deploy), [a heading there](/docs/guides/deploy#ship-it),
    [home](/docs), [this page](#section-one) and [outside](https://example.com).

    ![Diagram](assets/diagram.svg)
    MD);

    expect(findings($this))->toBe([]);
});

it('catches a link to a page that is not there', function (): void {
    $this->writeDoc('start.md', "---\ntitle: Start\n---\n[Gone](/docs/moved-away)");

    $found = findings($this);

    expect($found)->toHaveCount(1)
        ->and($found[0]['kind'])->toBe('link')
        ->and($found[0]['target'])->toBe('/docs/moved-away')
        ->and($found[0]['page'])->toBe('/docs/start');
});

it('catches a link to a heading that is not on the target page', function (): void {
    $this->writeDoc('guides/deploy.md', "---\ntitle: Deploy\n---\n## Ship it");
    $this->writeDoc('start.md', "---\ntitle: Start\n---\n[Stale](/docs/guides/deploy#rollback)");

    $found = findings($this);

    expect($found)->toHaveCount(1)
        ->and($found[0]['kind'])->toBe('anchor')
        ->and($found[0]['target'])->toBe('/docs/guides/deploy#rollback');
});

it('catches an anchor to a heading that is not on this page', function (): void {
    $this->writeDoc('start.md', "---\ntitle: Start\n---\n## Here\n\n[Nowhere](#there)");

    $found = findings($this);

    expect($found)->toHaveCount(1)
        ->and($found[0]['kind'])->toBe('anchor')
        ->and($found[0]['target'])->toBe('#there');
});

it('catches an image with no file behind it', function (): void {
    // The renderer leaves a src it cannot resolve exactly as written, so the
    // page compiles and the only symptom is a broken image in a browser.
    $this->writeDoc('start.md', "---\ntitle: Start\n---\n![Missing](assets/nope.png)");

    $found = findings($this);

    expect($found)->toHaveCount(1)
        ->and($found[0]['kind'])->toBe('image')
        ->and($found[0]['target'])->toBe('assets/nope.png');
});

it('leaves external links, mail links and data images alone', function (): void {
    $this->writeDoc('start.md', <<<'MD'
    ---
    title: Start
    ---
    [Site](https://example.com), [protocol relative](//example.com), [mail](mailto:a@example.com)

    <img src="data:image/gif;base64,R0lGOD" alt="Inline">
    MD);

    expect(findings($this))->toBe([]);
});

it('resolves a relative link against the page it is on', function (): void {
    $this->writeDoc('guides/deploy.md', "---\ntitle: Deploy\n---\nBody");
    $this->writeDoc('guides/start.md', "---\ntitle: Start\n---\n[Next](deploy) and [up](../)");

    expect(findings($this))->toBe([]);
});

it('knows the changelog is a route rather than a file', function (): void {
    $path = sys_get_temp_dir().'/vellum-tests/cl-'.$this->fixtureId().'.md';
    file_put_contents($path, "# Changelog\n\n## [0.1.0] - 2026-09-11\n\n- First\n");
    config()->set('vellum.changelog', ['path' => $path, 'unreleased' => false]);

    $this->writeDoc('start.md', "---\ntitle: Start\n---\n[Releases](/docs/changelog)");

    expect(findings($this))->toBe([]);

    unlink($path);
});

it('ignores the routes the package serves for raw markdown and assets', function (): void {
    $this->writeDoc('start.md', <<<'MD'
    ---
    title: Start
    ---
    [Source](/docs/_vellum/raw/start.md)
    MD);

    expect(findings($this))->toBe([]);
});

it('warns during a build and fails only when asked', function (): void {
    $this->writeDoc('start.md', "---\ntitle: Start\n---\n[Gone](/docs/moved-away)");

    $this->artisan('vellum:build')
        ->expectsOutputToContain('Broken link on /docs/start')
        ->assertSuccessful();

    $this->artisan('vellum:build', ['--strict' => true])
        ->expectsOutputToContain('1 broken reference')
        ->assertFailed();
});

it('takes strict from config, for a CI that cannot pass flags', function (): void {
    config()->set('vellum.checks.strict', true);
    $this->writeDoc('start.md', "---\ntitle: Start\n---\n[Gone](/docs/moved-away)");

    $this->artisan('vellum:build')->assertFailed();
});

it('can be switched off entirely', function (): void {
    config()->set('vellum.checks.references', false);
    $this->writeDoc('start.md', "---\ntitle: Start\n---\n[Gone](/docs/moved-away)");

    $this->artisan('vellum:build', ['--strict' => true])->assertSuccessful();
});

it('hears about a missing image from the renderer, not from the shape of the url', function (): void {
    $this->writeDoc('start.md', "---\ntitle: Start\n---\n![Missing](assets/nope.png)");

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();
    $reported = $repository->imageReport()->all();

    expect($reported)->toHaveCount(1)
        ->and($reported[0]['page'])->toBe('start')
        ->and($reported[0]['url'])->toBe('assets/nope.png')
        ->and($reported[0]['path'])->toEndWith('assets/nope.png');
});

it('keeps quiet in the report when an image resolves', function (): void {
    $this->writeDoc('assets/there.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    $this->writeDoc('start.md', "---\ntitle: Start\n---\n![There](assets/there.svg)");

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();

    expect($repository->imageReport()->all())->toBe([]);
});

it('does not carry a missing image from one build into the next', function (): void {
    $this->writeDoc('start.md', "---\ntitle: Start\n---\n![Missing](assets/nope.png)");

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();
    $repository->buildAll();

    expect($repository->imageReport()->all())->toHaveCount(1);
});

it('names the page a missing image is on, even across versions', function (): void {
    config()->set('vellum.versions', ['enabled' => true, 'latest' => 'v2', 'list' => ['v2', 'v1'], 'labels' => []]);
    $this->writeDoc('v2/index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('v1/old.md', "---\ntitle: Old\n---\n![Missing](assets/nope.png)");

    $repository = ContentRepository::fromConfig();
    $findings = (new LinkChecker)->check($repository->buildAll(), $repository);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['kind'])->toBe('image')
        ->and($findings[0]['page'])->toBe('/docs/v1/old');
});

it('reports a heading that skips a level, without failing the build', function (): void {
    $this->writeDoc('start.md', "---\ntitle: Start\n---\n## Two\n\n#### Four\n\nBody");

    $found = findings($this);

    expect($found)->toHaveCount(1)
        ->and($found[0]['kind'])->toBe('heading')
        ->and($found[0]['severity'])->toBe('notice')
        ->and($found[0]['message'])->toContain('h4 follows h2');

    // Said out loud, but not a reason to stop a deploy.
    $this->artisan('vellum:build', ['--strict' => true])
        ->expectsOutputToContain('Check heading on /docs/start')
        ->assertSuccessful();
});

it('accepts an outline that only ever goes one level deeper', function (): void {
    $this->writeDoc('start.md', "---\ntitle: Start\n---\n## Two\n\n### Three\n\n#### Four\n\n## Back to two\n\n### Three again");

    expect(findings($this))->toBe([]);
});

it('marks broken links as errors and heading skips as notices', function (): void {
    $this->writeDoc('start.md', "---\ntitle: Start\n---\n## Two\n\n#### Four\n\n[Gone](/docs/moved-away)");

    $severities = array_column(findings($this), 'severity', 'kind');

    expect($severities)->toEqualCanonicalizing(['link' => 'error', 'heading' => 'notice']);
});

it('reports a # heading under a frontmatter title, which renders a second h1', function (): void {
    $this->writeDoc('start.md', "---\ntitle: Start\n---\n# Getting started\n\n## Two\n\nBody");
    $this->writeDoc('plain.md', "# Plain\n\n## Two\n\nBody");

    $found = findings($this);

    expect($found)->toHaveCount(1)
        ->and($found[0]['page'])->toBe('/docs/start')
        ->and($found[0]['severity'])->toBe('notice')
        ->and($found[0]['message'])->toContain('two h1s');
});
