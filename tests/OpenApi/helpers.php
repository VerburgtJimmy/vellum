<?php

declare(strict_types=1);

use Vellum\Content\ContentRepository;

function apiSpec(array $overrides = []): array
{
    return array_replace_recursive([
        'openapi' => '3.0.3',
        'info' => ['title' => 'Petstore', 'version' => '1.0.0', 'description' => 'A store that sells pets.'],
        'servers' => [['url' => 'https://api.example.com', 'description' => 'Production']],
        'tags' => [
            ['name' => 'Pets', 'description' => 'Everything about pets.'],
            ['name' => 'Orders', 'description' => 'Buying them.'],
        ],
        'components' => ['securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer']]],
        'paths' => [
            '/pets' => [
                'get' => ['tags' => ['Pets'], 'operationId' => 'listPets', 'summary' => 'List pets', 'responses' => ['200' => ['description' => 'OK']]],
                'post' => ['tags' => ['Pets'], 'operationId' => 'createPet', 'summary' => 'Create a pet'],
            ],
            '/pets/{petId}' => [
                'delete' => ['tags' => ['Pets'], 'summary' => 'Delete a pet', 'deprecated' => true],
            ],
            '/orders' => [
                'get' => ['tags' => ['Orders'], 'operationId' => 'listOrders', 'summary' => 'List orders'],
            ],
            '/status' => [
                'get' => ['summary' => 'Service status'],
            ],
        ],
    ], $overrides);
}

function enableOpenApi(string $cachePath, array $spec, array $config = []): string
{
    $path = $cachePath.'/spec-'.uniqid('', false).'.json';
    file_put_contents($path, json_encode($spec, JSON_THROW_ON_ERROR));

    config()->set('vellum.openapi', array_replace([
        'enabled' => true,
        'spec' => $path,
        'scramble' => false,
        'prefix' => 'api',
        'title' => 'API reference',
        'samples' => ['curl'],
    ], $config));

    return $path;
}

function apiSlugs(ContentRepository $repository): array
{
    return array_values(array_filter(
        array_map(static fn ($document): string => $document->slug, $repository->buildAll()),
        static fn (string $slug): bool => $slug === 'api' || str_starts_with($slug, 'api/'),
    ));
}
