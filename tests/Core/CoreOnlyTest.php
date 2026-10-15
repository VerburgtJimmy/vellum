<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vellum\Content\ContentRepository;
use Vellum\Markdown\Islands\MarkdownPipeline;
use Vellum\Tests\Core\BootsCoreOnly;

uses(BootsCoreOnly::class);

it('registers no routes of its own', function (): void {
    expect(Route::has('vellum.docs'))->toBeFalse()
        ->and(collect(Route::getRoutes()->getRoutes())->filter(
            static fn ($route): bool => str_starts_with((string) $route->getName(), 'vellum.'),
        ))->toBeEmpty();
});

it('renders every Markdown component with the views core ships', function (): void {
    $html = (new MarkdownPipeline)->render(<<<'MD'
:::note[Heads up]
Hi
:::

:::tabs
::tab[One]
First
::
::tab[Two]
Second
::
:::

:::steps
### Install
Run it.
:::

:::cards
::card[Guide](/guide)
:::
MD);

    expect($html)->toContain('vellum-callout vellum-callout-note')
        ->and($html)->toContain('vellum-steps')
        ->and($html)->toContain('vellum-card')
        ->and($html)->toContain('role="tablist"')
        ->and($html)->toMatch('/role="tabpanel"[^>]*hidden/')
        ->and($html)->not->toContain('x-data')
        ->and($html)->not->toContain('vellumTabs(');
});

it('builds the docs, and hands back a page with its components rendered', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n\n## Deploy\n\n<x-vellum::callout type=\"tip\">Run the build.</x-vellum::callout>\n");

    $this->artisan('vellum:build')->assertSuccessful();

    $docs = ContentRepository::fromConfig();
    $page = $docs->find('');

    expect($page?->title)->toBe('Home')
        ->and($page->headings[0]['id'] ?? null)->toBe('deploy')
        ->and($docs->render($page))->toContain('vellum-callout vellum-callout-tip')
        ->and($docs->navigation())->not->toBeEmpty();
});

it('lets an app replace a component view by publishing it', function (): void {
    $this->publishViews(['components/callout.blade.php' => '<aside class="app-callout">{!! $slot !!}</aside>']);

    expect((new MarkdownPipeline)->render(":::note\nHi\n:::"))->toContain('<aside class="app-callout">');
});

it('builds an API reference from a spec with the views core ships', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $spec = $this->cachePath().'/spec.json';
    file_put_contents($spec, json_encode([
        'openapi' => '3.1.0',
        'info' => ['title' => 'Pets'],
        'servers' => [['url' => 'https://api.example.com']],
        'paths' => ['/pets' => ['post' => [
            'operationId' => 'createPet',
            'summary' => 'Create a pet',
            'requestBody' => ['content' => ['application/json' => ['schema' => [
                'type' => 'object',
                'required' => ['name'],
                'properties' => ['name' => ['type' => 'string']],
            ]]]],
            'responses' => ['201' => ['description' => 'Created', 'content' => ['application/json' => ['schema' => [
                'type' => 'object',
                'properties' => ['id' => ['type' => 'integer']],
            ]]]]],
        ]]],
    ], JSON_THROW_ON_ERROR));

    config()->set('vellum.openapi', ['enabled' => true, 'spec' => $spec, 'scramble' => false, 'samples' => ['curl', 'php']]);

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();
    $page = $repository->find('api/other/create-pet');

    expect($page)->not->toBeNull();

    $html = $repository->render($page);

    expect($html)->toContain('<span class="vellum-api-method" data-method="post">POST</span>')
        ->toContain('class="vellum-api-field-name"><a class="vellum-api-field-link" href="#body-name">name</a><span')
        // The samples are the docs' own code tabs.
        ->toContain('vellum-tabs-code')
        ->toContain('https://api.example.com/pets')
        ->not->toContain('x-data');
});
