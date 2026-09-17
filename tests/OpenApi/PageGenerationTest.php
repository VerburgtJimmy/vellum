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
                'get' => ['tags' => ['Pets'], 'summary' => 'List pets', 'responses' => ['200' => ['description' => 'OK']]],
                'post' => ['tags' => ['Pets'], 'summary' => 'Create a pet'],
            ],
            '/pets/{petId}' => [
                'delete' => ['tags' => ['Pets'], 'summary' => 'Delete a pet', 'deprecated' => true],
            ],
            '/orders' => [
                'get' => ['tags' => ['Orders'], 'summary' => 'List orders'],
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
        'icon' => null,
        'group_by' => 'tag',
        'samples' => ['curl'],
        'base_url' => null,
    ], $config));

    return $path;
}

beforeEach(function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
});

it('writes one page per group plus an overview', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    $documents = ContentRepository::fromConfig()->buildAll();
    $slugs = array_map(static fn ($document): string => $document->slug, $documents);

    expect($slugs)->toContain('api', 'api/pets', 'api/orders', 'api/other')
        // One page per group, never one per endpoint.
        ->and(array_filter($slugs, static fn (string $slug): bool => str_starts_with($slug, 'api')))
        ->toHaveCount(4);
});

it('serves the generated pages', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());
    ContentRepository::fromConfig()->buildAll();

    $this->get('/docs/api')->assertOk()->assertSee('API reference');

    $html = (string) $this->get('/docs/api/pets')->assertOk()->getContent();

    expect($html)->toContain('id="get-pets"')
        ->toContain('id="post-pets"')
        ->toContain('id="delete-pets-petid"')
        ->toContain('Everything about pets.')
        ->toContain('>GET<')
        ->toContain('Deprecated');
});

it('lists every operation in the page table of contents', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    $pets = collect(ContentRepository::fromConfig()->buildAll())->firstWhere('slug', 'api/pets');
    $ids = array_column($pets->headings, 'id');

    expect($ids)->toBe(['get-pets', 'post-pets', 'delete-pets-petid'])
        ->and(array_column($pets->headings, 'text'))->toBe(['List pets', 'Create a pet', 'Delete a pet']);
});

it('keeps operation ids stable across rebuilds', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    $first = collect(ContentRepository::fromConfig()->buildAll())->firstWhere('slug', 'api/pets');

    // A second build of the same spec, through a fresh repository.
    $second = collect(ContentRepository::fromConfig()->buildAll())->firstWhere('slug', 'api/pets');

    expect(array_column($second->headings, 'id'))->toBe(array_column($first->headings, 'id'));
});

it('groups by first path segment when asked', function (): void {
    enableOpenApi($this->cachePath(), apiSpec(), ['group_by' => 'path']);

    $slugs = array_map(
        static fn ($document): string => $document->slug,
        ContentRepository::fromConfig()->buildAll(),
    );

    expect($slugs)->toContain('api/pets', 'api/orders', 'api/status')
        ->and($slugs)->not->toContain('api/other');
});

it('orders groups by the spec tag list and puts untagged last', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    $slugs = array_values(array_filter(
        array_map(static fn ($document): string => $document->slug, ContentRepository::fromConfig()->buildAll()),
        static fn (string $slug): bool => str_starts_with($slug, 'api/'),
    ));

    expect($slugs)->toBe(['api/pets', 'api/orders', 'api/other']);
});

it('adds a sidebar group under the written docs', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();
    $tree = $repository->navigation();

    $last = $tree[count($tree) - 1];

    expect($last['type'])->toBe('folder')
        ->and($last['title'])->toBe('API reference')
        ->and(array_column($last['children'], 'title'))->toBe(['Overview', 'Pets', 'Orders', 'Other'])
        ->and(array_column($last['children'], 'href'))->toBe([
            '/docs/api', '/docs/api/pets', '/docs/api/orders', '/docs/api/other',
        ])
        // The badge is the endpoint count; the overview is not a group.
        ->and(array_column($last['children'], 'badge'))->toBe([null, 3, 1, 1]);
});

it('finds an individual endpoint in search', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();

    $index = json_decode((string) $repository->store()->getSearchIndex(), true);
    $byTitle = collect($index['documents'])->keyBy('title');

    expect($byTitle)->toHaveKey('DELETE /pets/{petId}')
        ->and($byTitle['DELETE /pets/{petId}']['url'])->toBe('/docs/api/pets#delete-pets-petid')
        ->and($byTitle['GET /orders']['url'])->toBe('/docs/api/orders#get-orders')
        // And the group pages are still indexed in their own right.
        ->and($byTitle)->toHaveKey('Pets');
});

it('puts parameter names in the search body so a field name finds its endpoint', function (): void {
    enableOpenApi($this->cachePath(), apiSpec(['paths' => ['/pets' => ['get' => [
        'parameters' => [['name' => 'breedFilter', 'in' => 'query']],
    ]]]]));

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();

    $index = json_decode((string) $repository->store()->getSearchIndex(), true);
    $entry = collect($index['documents'])->firstWhere('title', 'GET /pets');

    expect($entry['content'])->toContain('breedFilter');
});

it('does nothing when openapi is off', function (): void {
    $slugs = array_map(
        static fn ($document): string => $document->slug,
        ContentRepository::fromConfig()->buildAll(),
    );

    expect($slugs)->toBe(['']);
    $this->get('/docs/api')->assertNotFound();
});

it('gives colliding group names distinct slugs', function (): void {
    enableOpenApi($this->cachePath(), [
        'openapi' => '3.0.0',
        'info' => ['title' => 'Two stores'],
        // Different names, one slug.
        'tags' => [['name' => 'Pet store'], ['name' => 'Pet-store']],
        'paths' => [
            '/a' => ['get' => ['tags' => ['Pet store']]],
            '/b' => ['get' => ['tags' => ['Pet-store']]],
        ],
    ]);

    $slugs = array_values(array_filter(
        array_map(static fn ($document): string => $document->slug, ContentRepository::fromConfig()->buildAll()),
        static fn (string $slug): bool => str_starts_with($slug, 'api/'),
    ));

    expect($slugs)->toBe(['api/pet-store', 'api/pet-store-2']);
});
