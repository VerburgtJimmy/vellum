<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vellum\Content\ContentRepository;
use Vellum\Tests\Core\BootsCoreWithOpenApi;

uses(BootsCoreWithOpenApi::class);

it('registers no routes of its own', function (): void {
    expect(collect(Route::getRoutes()->getRoutes())->filter(
        static fn ($route): bool => str_starts_with((string) $route->getName(), 'vellum.'),
    ))->toBeEmpty();
});

it('builds an API reference from a spec with the views the package ships', function (): void {
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
