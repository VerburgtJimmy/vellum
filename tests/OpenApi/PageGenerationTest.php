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

it('writes one page per operation, foldered by tag, plus an overview', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    $slugs = array_map(static fn ($document): string => $document->slug, ContentRepository::fromConfig()->buildAll());

    expect($slugs)->toContain(
        'api',
        'api/pets/list-pets',
        'api/pets/create-pet',
        // No operationId in the spec, so the request line names the page.
        'api/pets/delete-pets-petid',
        'api/orders/list-orders',
        'api/other/get-status',
    )->and(array_filter($slugs, static fn (string $slug): bool => str_starts_with($slug, 'api')))->toHaveCount(6);
});

it('serves the overview and every operation page', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());
    ContentRepository::fromConfig()->buildAll();

    $overview = (string) $this->get('/docs/api')->assertOk()->getContent();

    expect($overview)->toContain('API reference')
        ->toContain('Everything about pets.')
        ->toContain('/docs/api/pets/list-pets');

    $this->get('/docs/api/pets/list-pets')->assertOk()->assertSee('List pets');
    $this->get('/docs/api/pets/delete-pets-petid')->assertOk()->assertSee('Deprecated');
});

it('gives an operation page the sections it actually has', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    $page = collect(ContentRepository::fromConfig()->buildAll())->firstWhere('slug', 'api/pets/list-pets');

    expect(array_column($page->headings, 'id'))->toBe(['responses'])
        // No parameters and no body on this one, so no headings for them.
        ->and($page->full)->toBeTrue();
});

it('keeps operation urls stable across rebuilds', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    $slugs = static fn (): array => array_map(
        static fn ($document): string => $document->slug,
        ContentRepository::fromConfig()->buildAll(),
    );

    expect($slugs())->toBe($slugs());
});

it('groups by first path segment when asked', function (): void {
    enableOpenApi($this->cachePath(), apiSpec(), ['group_by' => 'path']);

    $slugs = array_map(
        static fn ($document): string => $document->slug,
        ContentRepository::fromConfig()->buildAll(),
    );

    expect($slugs)->toContain('api/pets/list-pets', 'api/orders/list-orders', 'api/status/get-status')
        ->and($slugs)->not->toContain('api/other/get-status');
});

it('orders groups by the spec tag list and puts untagged last', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    $folders = array_values(array_unique(array_map(
        static fn (string $slug): string => implode('/', array_slice(explode('/', $slug), 0, 2)),
        array_filter(
            array_map(static fn ($document): string => $document->slug, ContentRepository::fromConfig()->buildAll()),
            static fn (string $slug): bool => str_starts_with($slug, 'api/'),
        ),
    )));

    expect($folders)->toBe(['api/pets', 'api/orders', 'api/other']);
});

it('adds a sidebar group under the written docs', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();
    $tree = $repository->navigation();

    $last = $tree[count($tree) - 1];
    $children = $last['children'];

    expect($last['type'])->toBe('folder')
        ->and($last['title'])->toBe('API reference')
        ->and($children[0]['title'])->toBe('Overview')
        ->and(array_column(array_slice($children, 1), 'title'))->toBe(['Pets', 'Orders', 'Other']);

    $pets = $children[1];

    expect(array_column($pets['children'], 'title'))->toBe(['List pets', 'Create a pet', 'Delete a pet'])
        // The method is the badge, which is how a reference is scanned.
        ->and(array_column($pets['children'], 'badge'))->toBe(['GET', 'POST', 'DELETE'])
        ->and(array_column($pets['children'], 'href'))->toBe([
            '/docs/api/pets/list-pets',
            '/docs/api/pets/create-pet',
            '/docs/api/pets/delete-pets-petid',
        ]);
});

it('finds an individual endpoint in search', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();

    $index = json_decode((string) $repository->store()->getSearchIndex(), true);
    $byTitle = collect($index['documents'])->keyBy('title');

    // Each operation is a page now, so it is indexed like any other page
    // rather than needing an entry synthesised for it.
    expect($byTitle)->toHaveKey('Delete a pet')
        ->and($byTitle['Delete a pet']['url'])->toBe('/docs/api/pets/delete-pets-petid')
        ->and($byTitle['List orders']['url'])->toBe('/docs/api/orders/list-orders');
});

it('puts parameter names in the search body so a field name finds its endpoint', function (): void {
    enableOpenApi($this->cachePath(), apiSpec(['paths' => ['/pets' => ['get' => [
        'parameters' => [['name' => 'breedFilter', 'in' => 'query', 'schema' => ['type' => 'string']]],
    ]]]]));

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();

    $index = json_decode((string) $repository->store()->getSearchIndex(), true);
    $entry = collect($index['documents'])->firstWhere('title', 'List pets');

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

    expect($slugs)->toBe(['api/pet-store/get-a', 'api/pet-store-2/get-b']);
});

it('renames the untagged group', function (): void {
    enableOpenApi($this->cachePath(), apiSpec(), ['untagged_label' => 'General']);

    $repository = ContentRepository::fromConfig();
    $documents = $repository->buildAll();
    $slugs = array_map(static fn ($document): string => $document->slug, $documents);

    expect($slugs)->toContain('api/general/get-status')
        ->and($slugs)->not->toContain('api/other/get-status');

    $tree = $repository->navigation();

    expect(array_column($tree[count($tree) - 1]['children'], 'title'))
        ->toBe(['Overview', 'Pets', 'Orders', 'General']);
});

it('records the method and path on the page itself', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    $page = collect(ContentRepository::fromConfig()->buildAll())->firstWhere('slug', 'api/pets/delete-pets-petid');

    expect($page->frontmatter['openapi_method'])->toBe('DELETE')
        ->and($page->frontmatter['openapi_path'])->toBe('/pets/{petId}')
        ->and($page->frontmatter['openapi'])->toBeTrue();
});

it('serves API pages at the unversioned URL and redirects the latest prefix', function (): void {
    config()->set('vellum.versions', [
        'enabled' => true,
        'latest' => 'v2',
        'list' => ['v2', 'v1'],
        'labels' => [],
    ]);
    enableOpenApi($this->cachePath(), apiSpec());

    $this->writeDoc('v2/index.md', "---\ntitle: Home\n---\nTwo");
    $this->writeDoc('v1/index.md', "---\ntitle: Home\n---\nOne");

    ContentRepository::fromConfig()->buildAll();

    // Latest is served without a version segment.
    $this->get('/docs/api')->assertOk();
    $this->get('/docs/api/pets/list-pets')->assertOk();

    // The prefixed latest URL redirects to it, as any latest page does.
    $this->get('/docs/v2/api')->assertRedirect('/docs/api');
    $this->get('/docs/v2/api/pets/list-pets')->assertRedirect('/docs/api/pets/list-pets');

    // The spec is not versioned, so it does not appear under older versions.
    $this->get('/docs/v1/api')->assertNotFound();
});
