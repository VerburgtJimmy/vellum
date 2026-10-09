<?php

declare(strict_types=1);

use Vellum\Answers\AnswerIndex;
use Vellum\Answers\Ranker;
use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;
use Vellum\Exceptions\DuplicateSlugException;
use Vellum\OpenApi\OpenApiPages;

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

beforeEach(function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->localRepository = fn (): ContentRepository => new ContentRepository(
        contentPath: $this->docsPath(),
        store: new CompiledStore($this->cachePath()),
        isLocal: true,
        openApi: new OpenApiPages,
    );
});

it('writes one page per operation, foldered by tag, plus an overview', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    expect(apiSlugs(ContentRepository::fromConfig()))->toBe([
        'api',
        'api/pets/list-pets',
        'api/pets/create-pet',
        // No operationId in the spec, so the request line names the page.
        'api/pets/delete-pets-petid',
        'api/orders/list-orders',
        'api/other/get-status',
    ]);
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

    // No parameters and no body on this one, so no headings for them.
    expect(array_column($page->headings, 'id'))->toBe(['responses']);
});

it('keeps operation urls stable across rebuilds', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    expect(apiSlugs(ContentRepository::fromConfig()))->toBe(apiSlugs(ContentRepository::fromConfig()));
});

it('groups by first path segment when asked', function (): void {
    enableOpenApi($this->cachePath(), apiSpec(), ['group_by' => 'path']);

    expect(apiSlugs(ContentRepository::fromConfig()))
        ->toContain('api/pets/list-pets', 'api/orders/list-orders', 'api/status/get-status')
        ->not->toContain('api/other/get-status');
});

it('adds a sidebar group under the written docs, in the order of the spec tag list', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();
    $tree = $repository->navigation();

    $last = $tree[count($tree) - 1];
    $children = $last['children'];

    expect(array_column($tree, 'title'))->toBe(['Home', 'API reference'])
        ->and($last['type'])->toBe('folder')
        ->and($children[0]['title'])->toBe('Overview')
        // Untagged goes last, wherever it came in the spec.
        ->and(array_column(array_slice($children, 1), 'title'))->toBe(['Pets', 'Orders', 'Other']);

    $pets = $children[1];

    expect(array_column($pets['children'], 'title'))->toBe(['List pets', 'Create a pet', 'Delete a pet'])
        // The method sits beside the title, which is how a reference is scanned.
        ->and(array_column($pets['children'], 'method'))->toBe(['GET', 'POST', 'DELETE'])
        ->and(array_column($pets['children'], 'deprecated'))->toBe([false, false, true])
        ->and(array_column($pets['children'], 'href'))->toBe([
            '/docs/api/pets/list-pets',
            '/docs/api/pets/create-pet',
            '/docs/api/pets/delete-pets-petid',
        ]);
});

it('shows the method in the sidebar', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());
    ContentRepository::fromConfig()->buildAll();

    expect((string) $this->get('/docs/api/pets/list-pets')->assertOk()->getContent())
        ->toMatch('/<span class="vellum-nav-method" data-method="delete"[^>]*>DELETE<\/span>/');
});

it('finds an endpoint in search, by its title and by a parameter name', function (): void {
    enableOpenApi($this->cachePath(), apiSpec(['paths' => ['/pets' => ['get' => [
        'parameters' => [['name' => 'breedFilter', 'in' => 'query', 'schema' => ['type' => 'string']]],
    ]]]]));

    $repository = ContentRepository::fromConfig();
    $ranker = new Ranker(AnswerIndex::build($repository->buildAll(), $repository));

    expect($ranker->search('delete a pet')[0]['record']['url'])->toStartWith('/docs/api/pets/delete-pets-petid')
        ->and($ranker->search('breedFilter')[0]['record']['url'])->toStartWith('/docs/api/pets/list-pets');
});

it('does nothing when openapi is off', function (): void {
    expect(apiSlugs(ContentRepository::fromConfig()))->toBe([]);

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

    expect(apiSlugs(ContentRepository::fromConfig()))->toBe(['api', 'api/pet-store/get-a', 'api/pet-store-2/get-b']);
});

it('renames the untagged group', function (): void {
    enableOpenApi($this->cachePath(), apiSpec(), ['untagged_label' => 'General']);

    $repository = ContentRepository::fromConfig();

    expect(apiSlugs($repository))->toContain('api/general/get-status')->not->toContain('api/other/get-status');

    $tree = $repository->navigation();

    expect(array_column($tree[count($tree) - 1]['children'], 'title'))
        ->toBe(['Overview', 'Pets', 'Orders', 'General']);
});

it('records the method and path on the page itself', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    $page = collect(ContentRepository::fromConfig()->buildAll())->firstWhere('slug', 'api/pets/delete-pets-petid');

    expect($page->frontmatter['openapi_method'])->toBe('DELETE')
        ->and($page->frontmatter['openapi_path'])->toBe('/pets/{petId}')
        ->and($page->frontmatter['openapi_deprecated'])->toBeTrue()
        ->and($page->generated())->toBeTrue();
});

it('belongs to the latest version only', function (): void {
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
    $this->get('/docs/v2/api/pets/list-pets')->assertRedirect('/docs/api/pets/list-pets');

    // An older version has no reference: the spec describes the API as it is.
    $this->get('/docs/v1/api')->assertNotFound();
    expect(array_column(ContentRepository::fromConfig()->navigation('v1'), 'title'))->toBe(['Home']);
});

it('serves a generated page as Markdown, not as the spec it came from', function (): void {
    enableOpenApi($this->cachePath(), apiSpec(['paths' => ['/pets' => ['post' => [
        'description' => 'Adds a pet to the store.',
        'parameters' => [['name' => 'dry|run', 'in' => 'query', 'description' => "Check\nonly.", 'schema' => ['type' => 'boolean']]],
        'requestBody' => ['content' => ['application/json' => ['schema' => [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'What to call it.'],
                'owner' => ['type' => 'object', 'properties' => ['email' => ['type' => 'string']]],
            ],
        ]]]],
    ]]]]));
    ContentRepository::fromConfig()->buildAll();

    $markdown = (string) $this->get('/docs/_vellum/raw/api/pets/create-pet.md')->assertOk()->getContent();

    expect($markdown)->toStartWith("# Create a pet\n\n`POST /pets`\n\nAdds a pet to the store.")
        ->toContain("## Query parameters\n\n| Name | Type | Required | Description |")
        // One line per row, and a pipe in a value does not start a cell.
        ->toContain('| `dry\|run` | boolean | No | Check only. |')
        ->toContain('| `name` | string | Yes | What to call it. |')
        ->toContain('| `owner.email` | string | No |  |')
        ->toContain("cURL:\n\n```bash\ncurl -X POST 'https://api.example.com/pets'")
        ->not->toContain('"openapi"');
});

it('does not let a description from the spec render a Blade component', function (): void {
    $this->registerFixtureComponents();
    enableOpenApi($this->cachePath(), apiSpec(['info' => [
        'description' => "Hello.\n\n<x-alert>From the spec</x-alert>\n\n:::note\nA built-in directive is only markup.\n:::\n\n<script>alert(1)</script>",
    ]]));
    ContentRepository::fromConfig()->buildAll();

    $html = (string) $this->get('/docs/api')->assertOk()->getContent();

    expect($html)->toContain('Hello.')
        // Where a docs page would render the component, the tag is dropped
        // or written out as text. Either way it is not the component.
        ->not->toContain('data-test-alert')
        ->toContain('A built-in directive is only markup.')
        ->not->toContain('<script>alert(1)</script>');
});

it('refuses a spec whose page would take the slug of a written one', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());
    $this->writeDoc('api/pets/list-pets.md', "---\ntitle: Mine\n---\nWritten.");

    ContentRepository::fromConfig()->buildAll();
})->throws(DuplicateSlugException::class);

it('still serves a written page that lives under the same prefix', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());
    $this->writeDoc('api/guide.md', "---\ntitle: Using the API\n---\nStart here.");
    ContentRepository::fromConfig()->buildAll();

    $this->get('/docs/api/guide')->assertOk()->assertSee('Start here.');

    expect(($this->localRepository)()->find('api/guide')?->title)->toBe('Using the API');
});

it('gates the reference with one setting', function (): void {
    enableOpenApi($this->cachePath(), apiSpec(), ['access' => 'auth']);
    ContentRepository::fromConfig()->buildAll();

    $this->get('/docs/api/pets/list-pets')->assertNotFound();
    $this->get('/docs/_vellum/raw/api/pets/list-pets.md')->assertNotFound();

    expect((string) $this->get('/docs')->assertOk()->getContent())->not->toContain('/docs/api/pets/list-pets');
});

it('follows the spec in local development without a build', function (): void {
    $path = enableOpenApi($this->cachePath(), apiSpec());

    $repository = ($this->localRepository)();

    expect($repository->find('api/pets/list-pets')?->title)->toBe('List pets')
        ->and($repository->find('api/orders/list-orders'))->not->toBeNull();

    // The spec changes: one endpoint is renamed, one is gone.
    $spec = apiSpec();
    $spec['paths']['/pets']['get']['summary'] = 'Every pet';
    unset($spec['paths']['/orders']);
    file_put_contents($path, json_encode($spec, JSON_THROW_ON_ERROR));
    touch($path, time() + 5);
    clearstatcache();

    $repository = ($this->localRepository)();

    expect($repository->find('api/pets/list-pets')?->title)->toBe('Every pet')
        ->and($repository->find('api/orders/list-orders'))->toBeNull()
        ->and(json_encode($repository->navigation()))->toContain('Every pet')->not->toContain('List orders');
});

it('does not read the spec again in local development while it is unchanged', function (): void {
    $path = enableOpenApi($this->cachePath(), apiSpec());
    ($this->localRepository)()->find('api');

    // Same size, same time, nothing a parser could read. Reading it throws.
    $time = filemtime($path);
    file_put_contents($path, str_repeat('!', (int) filesize($path)));
    touch($path, (int) $time);
    clearstatcache();

    $repository = ($this->localRepository)();

    expect($repository->find('api/pets/list-pets')?->title)->toBe('List pets')
        ->and(json_encode($repository->navigation()))->toContain('List orders');
});

it('describes a page by the opening paragraph of its description', function (): void {
    enableOpenApi($this->cachePath(), apiSpec(['paths' => ['/pets' => ['post' => [
        'description' => "Adds a pet to the *store*.\n\n:::warning\nIt cannot be undone.\n:::",
    ]]]]));

    $page = collect(ContentRepository::fromConfig()->buildAll())->firstWhere('slug', 'api/pets/create-pet');

    expect($page->description)->toBe('Adds a pet to the store.');
});

it('gives the reference a sidebar of its own, and a way between the two', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());
    $this->writeDoc('guide.md', "---\ntitle: Guide\n---\nRead me.");

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();

    expect(array_column($repository->sidebar('guide'), 'title'))->toBe(['Guide', 'Home'])
        ->and(array_column($repository->sidebar('api/pets/list-pets'), 'title'))->toBe(['Overview', 'Pets', 'Orders', 'Other'])
        ->and($repository->sections('api/pets/list-pets'))->toBe([
            ['key' => 'docs', 'title' => 'Docs', 'href' => '/docs', 'current' => false],
            ['key' => 'api', 'title' => 'API reference', 'href' => '/docs/api', 'current' => true],
        ])
        // The last page of the docs does not lead into the reference.
        ->and($repository->adjacent('')['next'])->toBeNull()
        ->and($repository->adjacent('api')['previous'])->toBeNull()
        ->and($repository->adjacent('api')['next']['slug'])->toBe('api/pets/list-pets');

    $docsPage = (string) $this->get('/docs/guide')->assertOk()->getContent();
    $apiPage = (string) $this->get('/docs/api/pets/list-pets')->assertOk()->getContent();

    expect($docsPage)->toContain('data-vellum-sections')
        ->toMatch('/<a\s+href="\/docs"\s+class="vellum-section"\s+aria-current="true"\s*>Docs<\/a>/')
        ->not->toContain('vellum-nav-method')
        ->and($apiPage)->toMatch('/<a\s+href="\/docs\/api"\s+class="vellum-section"\s+aria-current="true"\s*>API reference<\/a>/')
        ->toContain('vellum-nav-method')
        ->not->toContain('href="/docs/guide"');
});

it('lists the reference as a folder of the docs when asked', function (): void {
    enableOpenApi($this->cachePath(), apiSpec(), ['sidebar' => 'docs']);

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();

    expect(array_column($repository->sidebar('api/pets/list-pets'), 'title'))->toBe(['Home', 'API reference'])
        ->and($repository->sections('api/pets/list-pets'))->toBe([])
        ->and($repository->adjacent('api')['previous']['slug'])->toBe('');

    expect((string) $this->get('/docs')->assertOk()->getContent())
        ->not->toContain('data-vellum-sections')
        ->toContain('vellum-nav-method');
});

it('offers no way to a reference the reader may not open', function (): void {
    enableOpenApi($this->cachePath(), apiSpec(), ['access' => 'auth']);
    ContentRepository::fromConfig()->buildAll();

    expect((string) $this->get('/docs')->assertOk()->getContent())->not->toContain('data-vellum-sections');
});
