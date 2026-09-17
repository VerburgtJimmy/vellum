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
            'untagged_label' => 'Other', 'samples' => ['curl'], 'base_url' => null,
        ]);

        $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
        ContentRepository::fromConfig()->buildAll();

        return (string) $this->get('/docs/api/pets')->assertOk()->getContent();
    };
});

it('leads with the request line, then the summary', function (): void {
    $html = ($this->render)(layoutSpec());

    expect($html)
        ->toContain('data-method="patch"')
        ->toContain('>PATCH<')
        // Path parameters are marked so the reader sees a slot, not a literal.
        ->toContain('/pets/<span class="vellum-api-param">{petId}</span>')
        ->toContain('<h2 id="patch-pets-petid"')
        ->toContain('Update a pet')
        ->toContain('vellum-api-deprecated')
        ->toContain('<strong>some</strong>');
});

it('splits request and response into two columns', function (): void {
    $html = ($this->render)(layoutSpec());

    expect($html)
        ->toContain('vellum-api-columns')
        ->toContain('vellum-api-column-request')
        ->toContain('vellum-api-column-detail');
});

it('groups parameters by where they go', function (): void {
    $html = ($this->render)(layoutSpec());

    expect($html)->toContain('Path parameters')
        ->toContain('Query parameters')
        ->toContain('>petId</code>')
        ->toContain('string · uuid')
        // A false default has to read as "false", not as nothing at all.
        ->toContain('Default <code>false</code>');
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

it('tabs responses by status and colours them by class', function (): void {
    $html = ($this->render)(layoutSpec());

    expect($html)->toContain('data-status="2xx"')
        ->toContain('data-status="4xx"')
        ->toContain('No such pet');
});

it('explains the security scheme without making the reader look it up', function (): void {
    $html = ($this->render)(layoutSpec());

    expect($html)->toContain('vellum-api-scheme')
        ->toContain('bearer')
        ->toContain('Send a bearer token in the Authorization header.');
});

it('renders the schema tree with nesting and a repeat marker', function (): void {
    $html = ($this->render)(layoutSpec());

    expect($html)
        ->toContain('>address</code>')
        ->toContain('>Address<')
        // Nested object opens to its own fields.
        ->toContain('>line1</code>')
        ->toContain('One of')
        ->toContain('string | null')
        ->toContain('array of Pet')
        ->toContain('Repeats Pet');
});

it('does not put a tooltip inside a paragraph', function (): void {
    $html = ($this->render)(layoutSpec());

    // A div inside a p is closed by the parser and the layout falls apart.
    expect($html)->not->toMatch('~<p class="vellum-api-security">~');
});
