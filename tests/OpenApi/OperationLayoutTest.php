<?php

declare(strict_types=1);

use Vellum\Content\ContentRepository;

function layoutSpec(): array
{
    return [
        'openapi' => '3.1.0',
        'info' => ['title' => 'Petstore', 'version' => '1.0.0'],
        'servers' => [['url' => 'https://api.example.com']],
        'security' => [['bearer' => []]],
        'tags' => [['name' => 'Pets', 'description' => 'Everything about pets.']],
        'components' => [
            'securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer']],
            'schemas' => [
                'Address' => ['type' => 'object', 'required' => ['line1'], 'properties' => [
                    'line1' => ['type' => 'string'],
                    'country' => ['type' => 'string', 'enum' => ['NL', 'BE']],
                ]],
                'Pet' => ['type' => 'object', 'required' => ['name'], 'properties' => [
                    'name' => ['type' => 'string'],
                    'nickname' => ['type' => ['string', 'null']],
                    'address' => ['$ref' => '#/components/schemas/Address'],
                    'friends' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Pet']],
                ]],
            ],
        ],
        'paths' => ['/pets/{petId}' => ['patch' => [
            'tags' => ['Pets'],
            'operationId' => 'updatePet',
            'summary' => 'Update a pet',
            'description' => 'Changes **some** fields.',
            'deprecated' => true,
            'parameters' => [
                ['name' => 'petId', 'in' => 'path', 'required' => true, 'description' => 'The pet id', 'schema' => ['type' => 'string', 'format' => 'uuid']],
                ['name' => 'dryRun', 'in' => 'query', 'schema' => ['type' => 'boolean', 'default' => false]],
            ],
            'requestBody' => ['content' => [
                'application/json' => ['schema' => ['$ref' => '#/components/schemas/Pet']],
                'application/xml' => ['schema' => ['type' => 'string']],
            ]],
            'responses' => [
                '200' => ['description' => 'Updated', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Pet']]]],
                '404' => ['description' => 'No such pet'],
            ],
        ]]],
    ];
}

beforeEach(function (): void {
    // A closure declared here keeps the test case's binding, which a global
    // helper does not: cachePath() and writeDoc() are protected.
    $this->render = function (array $spec, int $parameterCount = 0): string {
        if ($parameterCount > 0) {
            $parameters = [];

            for ($i = 1; $i <= $parameterCount; $i++) {
                $parameters[] = ['name' => 'filter'.$i, 'in' => 'query', 'schema' => ['type' => 'string']];
            }

            $spec['paths']['/pets/{petId}']['patch']['parameters'] = $parameters;
        }

        $path = $this->cachePath().'/layout-'.uniqid('', false).'.json';
        file_put_contents($path, json_encode($spec, JSON_THROW_ON_ERROR));

        config()->set('vellum.openapi', [
            'enabled' => true, 'spec' => $path, 'scramble' => false, 'prefix' => 'api',
            'title' => 'API reference', 'icon' => null, 'group_by' => 'tag',
            'untagged_label' => 'Other', 'samples' => ['curl', 'php', 'javascript'], 'base_url' => null,
        ]);

        $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
        ContentRepository::fromConfig()->buildAll();

        return (string) $this->get('/docs/api/pets/update-pet')->assertOk()->getContent();
    };
});

it('leads with the request line', function (): void {
    $html = ($this->render)(layoutSpec());

    expect($html)
        ->toContain('data-method="patch"')
        ->toContain('>PATCH<')
        // Path parameters are marked so the reader sees a slot, not a literal.
        ->toContain('/pets/<span class="vellum-api-param">{petId}</span>')
        // The summary is the page's own h1, rendered by the layout.
        ->toContain('Update a pet')
        ->toContain('vellum-api-deprecated')
        ->toContain('<strong>some</strong>');
});

it('gives each operation its own page under its tag', function (): void {
    ($this->render)(layoutSpec());

    $this->get('/docs/api/pets/update-pet')->assertOk();
    // The old one-page-per-tag URL is gone.
    $this->get('/docs/api/pets')->assertNotFound();
});

it('drops the contents column so the two columns have room', function (): void {
    ($this->render)(layoutSpec());

    $document = ContentRepository::fromConfig()->find('api/pets/update-pet');

    expect($document)->not->toBeNull()
        ->and($document->full)->toBeTrue();
});

it('puts documentation on the left and examples on the right', function (): void {
    $html = ($this->render)(layoutSpec());

    expect($html)
        ->toContain('vellum-api-doc')
        ->toContain('vellum-api-examples')
        // Samples and response bodies are examples; schemas are documentation.
        ->toContain('vellum-api-samples')
        ->toContain('vellum-api-response-examples');
});

it('writes a sample for every configured language', function (): void {
    $html = ($this->render)(layoutSpec());

    // Samples are highlighted, so the source is split across spans. Read the
    // text the way a reader sees it rather than the markup around it.
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

    expect($html)->toContain('cURL')->toContain('JavaScript')
        ->and($text)->toContain('curl -X PATCH')
        ->and($text)->toContain('https://api.example.com/pets/{petId}')
        ->and($text)->toContain('Authorization: Bearer')
        ->and($text)->toContain('Http::withToken')
        ->and($text)->toContain('await fetch');
});

it('groups parameters by where they go', function (): void {
    $html = ($this->render)(layoutSpec());

    expect($html)->toContain('Path parameters')
        ->toContain('Query parameters')
        ->toContain('petId<span')
        // Format is a chip, not part of the type.
        ->toContain('Format</span> <code>uuid</code>')
        // A false default has to read as "false", not as nothing at all.
        ->toContain('Default</span> <code>false</code>');
});

it('collapses a parameter list longer than eight', function (): void {
    $short = ($this->render)(layoutSpec(), parameterCount: 8);
    $long = ($this->render)(layoutSpec(), parameterCount: 9);

    expect($short)->not->toContain('9 parameters')
        ->and($short)->not->toContain('vellum-api-param-group')
        ->and($long)->toContain('vellum-api-param-group')
        ->and($long)->toContain('9 parameters');
});

it('tabs the request body by content type', function (): void {
    $html = ($this->render)(layoutSpec());

    expect($html)->toContain('vellum-api-content-types')
        ->toContain('application/json')
        ->toContain('application/xml');
});

it('documents every response and colours it by status class', function (): void {
    $html = ($this->render)(layoutSpec());

    expect($html)->toContain('data-status="2xx"')
        ->toContain('data-status="4xx"')
        ->toContain('No such pet');
});

it('explains the security scheme without making the reader look it up', function (): void {
    $html = ($this->render)(layoutSpec());

    expect($html)->toContain('>bearer<')
        // The header to send, beside the prose explaining it.
        ->toContain('Authorization: Bearer &lt;token&gt;')
        ->toContain('Send a bearer token in the Authorization header.');
});

it('renders the schema tree with nesting and a repeat marker', function (): void {
    $html = ($this->render)(layoutSpec());

    expect($html)
        ->toContain('>address<span')
        ->toContain('>Address<')
        // Nested object opens to its own fields.
        ->toContain('>line1<span')
        ->toContain('Value in')
        ->toContain('string | null')
        ->toContain('array&lt;Pet&gt;')
        ->toContain('Repeats Pet')
        // Required and optional read from the name, as in the spec itself.
        ->toContain('data-required="true"')
        ->toContain('data-required="false"');
});

it('does not put a tooltip inside a paragraph', function (): void {
    $html = ($this->render)(layoutSpec());

    // A div inside a p is closed by the parser and the layout falls apart.
    expect($html)->not->toMatch('~<p class="vellum-api-security">~');
});
