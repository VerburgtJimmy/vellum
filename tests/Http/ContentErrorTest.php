<?php

declare(strict_types=1);

use Vellum\Exceptions\UnknownDirectiveException;
use Vellum\Markdown\Islands\MarkdownPipeline;

it('names the directive when one has no renderer', function (): void {
    expect(fn () => (new MarkdownPipeline)->render(":::ntoe\nOops\n:::"))
        ->toThrow(UnknownDirectiveException::class, 'Unknown docs directive [:::ntoe]');
});

it('names the file the unknown directive was found in', function (): void {
    $path = $this->writeDoc('broken.md', "---\ntitle: Broken\n---\n\n:::ntoe\nOops\n:::");

    $this->withoutExceptionHandling();

    try {
        $this->get('/docs/broken');

        $this->fail('Expected an UnknownDirectiveException.');
    } catch (UnknownDirectiveException $exception) {
        expect($exception->getMessage())->toContain(':::ntoe')
            ->and($exception->getMessage())->toContain($path)
            ->and($exception->directive)->toBe('ntoe')
            ->and($exception->docsFile)->toBe($path);
    }
});

it('still renders every directive that does have a renderer', function (string $markdown, string $expected): void {
    expect((new MarkdownPipeline)->render($markdown))->toContain($expected);
})->with([
    'callout' => [":::note\nHi\n:::", 'vellum-callout'],
    'callout alias' => [":::success\nHi\n:::", 'vellum-callout'],
    'tabs' => [":::tabs\n::tab[One]\nHi\n::\n:::", 'vellum-tabs'],
    'steps' => [":::steps\n### One\nHi\n:::", 'vellum-steps'],
    'cards' => [":::cards\n::card[One](/a)\n:::", 'vellum-card'],
]);

it('renders a branded error page instead of a blank 500 in production', function (): void {
    config()->set('app.debug', false);
    $this->writeDoc('broken.md', "---\ntitle: Broken\n---\n\n:::ntoe\nOops\n:::");

    $response = $this->get('/docs/broken');

    $response->assertStatus(500)
        ->assertSee('This page could not be rendered', false);

    expect($response->getContent())->not->toContain(':::ntoe')
        ->and($response->getContent())->not->toContain($this->docsPath());
});

it('shows the reason and the file when debug is on', function (): void {
    config()->set('app.debug', true);
    $path = $this->writeDoc('broken.md', "---\ntitle: Broken\n---\n\n:::ntoe\nOops\n:::");

    $response = $this->get('/docs/broken');

    $response->assertStatus(500)
        ->assertSee('This page could not be rendered', false)
        ->assertSee(':::ntoe', false)
        ->assertSee($path, false);
});

it('renders the error page for bad frontmatter', function (): void {
    config()->set('app.debug', false);
    $this->writeDoc('bad.md', "---\ntitle: [unclosed\n---\nBody");

    $this->get('/docs/bad')
        ->assertStatus(500)
        ->assertSee('This page could not be rendered', false);
});

it('renders the error page for an unknown component', function (): void {
    config()->set('app.debug', false);
    $this->writeDoc('missing.md', "---\ntitle: Missing\n---\n\n<x-vellum::nope>Hi</x-vellum::nope>");

    $this->get('/docs/missing')
        ->assertStatus(500)
        ->assertSee('This page could not be rendered', false);
});

it('leaves json requests to the framework handler', function (): void {
    config()->set('app.debug', false);
    $this->writeDoc('broken.md', "---\ntitle: Broken\n---\n\n:::ntoe\nOops\n:::");

    $response = $this->getJson('/docs/broken');

    expect($response->getContent())->not->toContain('This page could not be rendered');
});

it('keeps serving the other pages when one fails to compile on a cold cache', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('fine.md', "---\ntitle: Fine\n---\nStill here");
    $this->writeDoc('broken.md', "---\ntitle: Broken\n---\n\n:::ntoe\nOops\n:::");

    $this->get('/docs/fine')->assertOk()->assertSee('Still here', false)->assertDontSee('Broken', false);
    $this->get('/docs/broken')->assertStatus(500);
});
