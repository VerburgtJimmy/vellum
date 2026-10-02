<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Vellum\Markdown\MarkdownRenderer;

it('gives a card the description written after its link, and leaves one without it as it was', function (): void {
    $html = (new MarkdownRenderer)->render(":::cards\n::card[Install](/docs/install) Require the package and publish the stubs.\n::card[Theming](/docs/theming)\n:::");

    expect($html)->toContain('<span class="vellum-card-title">Install</span><span class="vellum-card-description">Require the package and publish the stubs.</span>')
        ->and($html)->toContain('<span class="vellum-card-title">Theming</span><span class="vellum-card-arrow"')
        ->and($html)->not->toContain('data-vellum-card-describe');
});

it('describes cards by their pages when the group asks, as the navigation has them and only to readers who may see them', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n:::cards describe=\"pages\"\n::card[Guide](/docs/guide)\n::card[Secret](/docs/secret)\n::card[Laravel](https://laravel.com/docs)\n::card[Own](/docs/guide) Written by hand.\n:::\n");
    $this->writeDoc('guide.md', "---\ntitle: Guide\ndescription: How to get going.\n---\nHi");
    $this->writeDoc('secret.md', "---\ntitle: Secret\ndescription: The launch date.\naccess: auth\n---\nNope");

    $guest = (string) $this->get('/docs')->assertOk()->getContent();

    expect($guest)->toContain('<span class="vellum-card-title">Guide</span><span class="vellum-card-description">How to get going.</span>')
        ->and($guest)->toContain('<span class="vellum-card-title">Laravel</span><span class="vellum-card-description">laravel.com</span>')
        ->and($guest)->toContain('<span class="vellum-card-title">Own</span><span class="vellum-card-description">Written by hand.</span>')
        ->and($guest)->toContain('<span class="vellum-card-title">Secret</span><span class="vellum-card-arrow"')
        ->and($guest)->not->toContain('The launch date.')
        ->and($guest)->not->toContain('data-vellum-card-describe');

    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Ada']));

    expect((string) $this->get('/docs')->getContent())->toContain('The launch date.');

    // Only the linked page changes; the card follows it without its own page being rebuilt.
    $this->writeDoc('guide.md', "---\ntitle: Guide\ndescription: A newer summary.\n---\nHi");
    $this->artisan('vellum:build', ['--docs-version' => null])->assertSuccessful();

    expect((string) $this->get('/docs')->getContent())->toContain('A newer summary.');
});
