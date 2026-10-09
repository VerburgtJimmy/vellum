<?php

declare(strict_types=1);

use Vellum\Answers\AnswerIndex;
use Vellum\Answers\Ranker;
use Vellum\Answers\Sections;
use Vellum\Content\ContentRepository;

require_once __DIR__.'/helpers.php';

/**
 * The data an operation's page carries for the playground.
 *
 * @return array<string, mixed>|null
 */
function playgroundData(string $cachePath, array $spec, array $config = [], string $slug = 'api/pets/create-pet'): ?array
{
    enableOpenApi($cachePath, $spec, $config);

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();
    $html = $repository->render($repository->find($slug));

    if (preg_match('/<button type="button" class="vellum-api-try" data-vellum-playground="([^"]*)" hidden>Try it<\/button>/', $html, $match) !== 1) {
        return null;
    }

    return json_decode(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5), true, 512, JSON_THROW_ON_ERROR);
}

beforeEach(function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
});

it('carries what the request is made of on a button that starts hidden', function (): void {
    $data = playgroundData($this->cachePath(), apiSpec(['paths' => ['/pets' => ['post' => [
        'parameters' => [
            ['name' => 'dryRun', 'in' => 'query', 'required' => true, 'description' => 'Check only.', 'schema' => ['type' => 'boolean', 'default' => false]],
            ['name' => 'X-Trace', 'in' => 'header', 'schema' => ['type' => 'string']],
        ],
        'requestBody' => ['content' => ['application/json' => ['schema' => [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                'name' => ['type' => 'string', 'example' => 'Rex', 'minLength' => 2],
                'kind' => ['type' => 'string', 'enum' => ['dog', 'cat']],
                'owner' => ['type' => 'object', 'properties' => ['email' => ['type' => 'string', 'format' => 'email']]],
            ],
        ]]]],
    ]]]]));

    expect($data['method'])->toBe('POST')
        ->and($data['path'])->toBe('/pets')
        ->and($data['title'])->toBe('Create a pet')
        ->and($data['servers'])->toBe(['https://api.example.com'])
        ->and($data['parameters'])->toBe([
            ['in' => 'query', 'name' => 'dryRun', 'required' => true, 'description' => 'Check only.', 'schema' => ['type' => 'boolean', 'default' => false], 'example' => false],
            ['in' => 'header', 'name' => 'X-Trace', 'required' => false, 'description' => null, 'schema' => ['type' => 'string'], 'example' => null],
        ])
        ->and($data['bodies'][0]['contentType'])->toBe('application/json')
        ->and($data['bodies'][0]['example']['name'])->toBe('Rex')
        // Only what a form needs: a length limit is the API's to enforce.
        ->and($data['bodies'][0]['schema'])->toBe([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'kind' => ['type' => 'string', 'enum' => ['dog', 'cat']],
                'owner' => ['type' => 'object', 'properties' => ['email' => ['type' => 'string', 'format' => 'email']], 'required' => []],
            ],
            'required' => ['name'],
        ]);
});

it('reduces every security scheme to a way a browser can send a credential', function (): void {
    $data = playgroundData($this->cachePath(), apiSpec([
        'components' => ['securitySchemes' => [
            'basic' => ['type' => 'http', 'scheme' => 'basic'],
            'key' => ['type' => 'apiKey', 'in' => 'query', 'name' => 'api_key', 'description' => 'From your dashboard.'],
            'oauth' => ['type' => 'oauth2', 'flows' => []],
        ]],
        'paths' => ['/pets' => ['post' => ['security' => [['bearer' => []], ['basic' => []], ['key' => [], 'oauth' => ['read']]]]]],
    ]));

    expect($data['security'])->toBe([
        ['name' => 'bearer', 'type' => 'bearer', 'description' => null],
        ['name' => 'basic', 'type' => 'basic', 'description' => null],
        ['name' => 'key', 'type' => 'apiKey', 'in' => 'query', 'key' => 'api_key', 'description' => 'From your dashboard.'],
        // The reader gets a token elsewhere and pastes it.
        ['name' => 'oauth', 'type' => 'bearer', 'description' => null],
    ]);
});

it('asks for no credential where the operation says it needs none', function (): void {
    $data = playgroundData($this->cachePath(), apiSpec([
        'security' => [['bearer' => []]],
        'paths' => ['/pets' => ['post' => ['security' => []]]],
    ]));

    expect($data['security'])->toBe([]);
});

it('stops where a schema repeats itself, and edits what it cannot lay out as JSON', function (): void {
    $data = playgroundData($this->cachePath(), apiSpec([
        'components' => ['schemas' => ['Pet' => ['type' => 'object', 'properties' => [
            'name' => ['type' => 'string'],
            'parent' => ['$ref' => '#/components/schemas/Pet'],
            'home' => ['oneOf' => [['type' => 'string'], ['type' => 'object']]],
        ]]]],
        'paths' => ['/pets' => ['post' => ['requestBody' => ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Pet']]]]]]],
    ]));

    $properties = $data['bodies'][0]['schema']['properties'];

    expect($properties['name'])->toBe(['type' => 'string'])
        ->and($properties['home'])->toBe(['type' => 'json'])
        ->and(json_encode($properties['parent']))->toContain('"type":"json"')
        ->and(strlen((string) json_encode($data)))->toBeLessThan(2000);
});

it('offers the server the samples use first, then the others', function (): void {
    $data = playgroundData($this->cachePath(), apiSpec([
        'servers' => [['url' => 'https://api.example.com'], ['url' => 'https://sandbox.example.com/']],
    ]), ['base_url' => 'https://staging.example.com/']);

    expect($data['servers'])->toBe(['https://staging.example.com', 'https://api.example.com', 'https://sandbox.example.com']);
});

it('keeps a value from the spec inside the attribute it is carried in', function (): void {
    enableOpenApi($this->cachePath(), apiSpec(['paths' => ['/pets' => ['post' => [
        'summary' => 'Create "><script>alert(1)</script>',
        'operationId' => 'createPet',
    ]]]]));

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();

    expect($repository->render($repository->find('api/pets/create-pet')))->not->toContain('<script>alert(1)</script>');
});

it('has no button and loads no script when the playground is off', function (): void {
    expect(playgroundData($this->cachePath(), apiSpec(), ['playground' => false]))->toBeNull()
        ->and((string) $this->get('/docs/api/pets/create-pet')->assertOk()->getContent())
        ->not->toContain('data-vellum-playground');
});

it('names its script on the page and serves it', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());
    ContentRepository::fromConfig()->buildAll();

    $html = (string) $this->get('/docs/api/pets/create-pet')->assertOk()->getContent();

    expect($html)->toMatch('/data-vellum-playground-src="[^"]*\/vendor\/vellum\/vellum-playground\.js\?v=[0-9a-f]{8}"/');

    $script = $this->get(route('vellum.assets.playground', ['v' => 'test']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');

    expect((string) $script->getContent())->toContain('showModal');
});

it('does not name the script on a site without a reference', function (): void {
    expect((string) $this->get('/docs')->assertOk()->getContent())->not->toContain('data-vellum-playground-src');
});

it('keeps the button out of what search reads', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());

    $repository = ContentRepository::fromConfig();
    $documents = $repository->buildAll();
    $page = collect($documents)->firstWhere('slug', 'api/pets/create-pet');

    expect($page->html)->toContain('>Try it</button>')
        ->and(Sections::text($page->html))->toContain('POST /pets')->not->toContain('Try it')
        ->and((new Ranker(AnswerIndex::build($documents, $repository)))->search('create a pet')[0]['record']['url'])
        ->toStartWith('/docs/api/pets/create-pet');
});
