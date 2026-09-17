<?php

declare(strict_types=1);

use Vellum\Exceptions\InvalidSpecException;
use Vellum\OpenApi\RefResolver;
use Vellum\OpenApi\SpecParser;

/**
 * @var list<string>
 */
$written = [];

function specFile(string $name, string $contents): string
{
    $directory = sys_get_temp_dir().'/vellum-tests/openapi';

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $path = $directory.'/'.uniqid('', false).'-'.$name;
    file_put_contents($path, $contents);

    return $path;
}

afterEach(function (): void {
    $directory = sys_get_temp_dir().'/vellum-tests/openapi';

    foreach (glob($directory.'/*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
});

it('parses a JSON 3.0 document', function (): void {
    $path = specFile('api.json', json_encode([
        'openapi' => '3.0.3',
        'info' => ['title' => 'Pets', 'version' => '1.2.0', 'description' => 'A **pet** store.'],
        'servers' => [['url' => 'https://api.example.com/v1/']],
        'paths' => [
            '/pets' => [
                'get' => ['summary' => 'List pets', 'tags' => ['Pets'], 'responses' => ['200' => ['description' => 'OK']]],
                'post' => ['summary' => 'Create a pet', 'tags' => ['Pets']],
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    $spec = (new SpecParser)->parseFile($path);

    expect($spec->version)->toBe('3.0.3')
        ->and($spec->title())->toBe('Pets')
        ->and($spec->apiVersion())->toBe('1.2.0')
        ->and($spec->description())->toBe('A **pet** store.')
        ->and($spec->serverUrl())->toBe('https://api.example.com/v1')
        ->and($spec->operations)->toHaveCount(2)
        ->and($spec->operations[0]->method)->toBe('GET')
        ->and($spec->operations[0]->path)->toBe('/pets')
        ->and($spec->operations[0]->tags)->toBe(['Pets'])
        ->and($spec->warnings)->toBe([]);
});

it('parses a YAML 3.1 document', function (): void {
    $path = specFile('api.yaml', <<<'YAML'
    openapi: 3.1.0
    info:
      title: Orders
      version: "2"
    paths:
      /orders/{id}:
        get:
          summary: Show an order
          deprecated: true
          responses:
            "200":
              description: OK
    YAML);

    $spec = (new SpecParser)->parseFile($path);

    expect($spec->version)->toBe('3.1.0')
        ->and($spec->title())->toBe('Orders')
        ->and($spec->operations)->toHaveCount(1)
        ->and($spec->operations[0]->deprecated)->toBeTrue()
        ->and($spec->operations[0]->headingId())->toBe('get-orders-id');
});

it('inlines local refs', function (): void {
    $path = specFile('api.json', json_encode([
        'openapi' => '3.0.0',
        'paths' => [
            '/pets' => ['get' => ['responses' => ['200' => [
                'description' => 'OK',
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Pet']]],
            ]]]],
        ],
        'components' => ['schemas' => ['Pet' => [
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string']],
        ]]],
    ], JSON_THROW_ON_ERROR));

    $spec = (new SpecParser)->parseFile($path);
    $schema = $spec->operations[0]->responses['200']['content']['application/json']['schema'];

    expect($schema['type'])->toBe('object')
        ->and($schema['properties']['name']['type'])->toBe('string')
        ->and($schema)->not->toHaveKey('$ref');
});

it('lets keys beside a ref win over the target', function (): void {
    $path = specFile('api.json', json_encode([
        'openapi' => '3.1.0',
        'paths' => ['/pets' => ['get' => ['responses' => ['200' => [
            'description' => 'OK',
            'content' => ['application/json' => ['schema' => [
                '$ref' => '#/components/schemas/Pet',
                'description' => 'The pet you asked for',
            ]]],
        ]]]]],
        'components' => ['schemas' => ['Pet' => [
            'type' => 'object',
            'description' => 'A pet',
        ]]],
    ], JSON_THROW_ON_ERROR));

    $spec = (new SpecParser)->parseFile($path);
    $schema = $spec->operations[0]->responses['200']['content']['application/json']['schema'];

    expect($schema['description'])->toBe('The pet you asked for')
        ->and($schema['type'])->toBe('object');
});

it('merges allOf into the schema that declared it', function (): void {
    $path = specFile('api.json', json_encode([
        'openapi' => '3.0.0',
        'paths' => ['/pets' => ['get' => ['responses' => ['200' => [
            'description' => 'OK',
            'content' => ['application/json' => ['schema' => [
                'description' => 'A named pet',
                'allOf' => [
                    ['$ref' => '#/components/schemas/Base'],
                    ['type' => 'object', 'properties' => ['email' => ['type' => 'string']], 'required' => ['email']],
                ],
            ]]],
        ]]]]],
        'components' => ['schemas' => ['Base' => [
            'type' => 'object',
            'description' => 'The base',
            'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']],
            'required' => ['id'],
        ]]],
    ], JSON_THROW_ON_ERROR));

    $spec = (new SpecParser)->parseFile($path);
    $schema = $spec->operations[0]->responses['200']['content']['application/json']['schema'];

    expect($schema)->not->toHaveKey('allOf')
        ->and(array_keys($schema['properties']))->toBe(['id', 'name', 'email'])
        ->and($schema['required'])->toBe(['id', 'email'])
        ->and($schema['type'])->toBe('object')
        // The declaring schema wins, so a wrapper can relabel what it extends.
        ->and($schema['description'])->toBe('A named pet');
});

it('stops a circular schema at its second appearance', function (): void {
    $path = specFile('api.json', json_encode([
        'openapi' => '3.0.0',
        'paths' => ['/comments' => ['get' => ['responses' => ['200' => [
            'description' => 'OK',
            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Comment']]],
        ]]]]],
        'components' => ['schemas' => ['Comment' => [
            'type' => 'object',
            'properties' => [
                'body' => ['type' => 'string'],
                'replies' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Comment']],
            ],
        ]]],
    ], JSON_THROW_ON_ERROR));

    $spec = (new SpecParser)->parseFile($path);
    $schema = $spec->operations[0]->responses['200']['content']['application/json']['schema'];
    $nested = $schema['properties']['replies']['items'];

    expect($schema['properties']['body']['type'])->toBe('string')
        ->and($nested)->toHaveKey(RefResolver::RECURSIVE)
        ->and($nested[RefResolver::RECURSIVE])->toBe('#/components/schemas/Comment')
        ->and($nested)->not->toHaveKey('properties');
});

it('warns about a remote ref instead of failing', function (): void {
    $path = specFile('api.json', json_encode([
        'openapi' => '3.0.0',
        'paths' => ['/pets' => ['get' => ['responses' => ['200' => [
            'description' => 'OK',
            'content' => ['application/json' => ['schema' => ['$ref' => 'shared.yaml#/Pet']]],
        ]]]]],
    ], JSON_THROW_ON_ERROR));

    $spec = (new SpecParser)->parseFile($path);
    $schema = $spec->operations[0]->responses['200']['content']['application/json']['schema'];

    expect($schema['type'])->toBe('object')
        ->and($spec->warnings)->toHaveCount(1)
        ->and($spec->warnings[0])->toContain('shared.yaml#/Pet');
});

it('resolves a pointer with an escaped slash', function (): void {
    $path = specFile('api.json', json_encode([
        'openapi' => '3.0.0',
        'paths' => [
            '/pets' => ['get' => [
                'summary' => 'List pets',
                'responses' => ['200' => ['description' => 'OK']],
            ]],
            '/pets/mirror' => ['get' => ['$ref' => '#/paths/~1pets/get']],
        ],
    ], JSON_THROW_ON_ERROR));

    $spec = (new SpecParser)->parseFile($path);
    $mirror = collect($spec->operations)->firstWhere('path', '/pets/mirror');

    expect($mirror->summary)->toBe('List pets');
});

it('applies path-level parameters to every method, and lets a method replace one', function (): void {
    $path = specFile('api.json', json_encode([
        'openapi' => '3.0.0',
        'paths' => ['/pets/{id}' => [
            'parameters' => [
                ['name' => 'id', 'in' => 'path', 'required' => true, 'description' => 'Shared'],
                ['name' => 'trace', 'in' => 'header'],
            ],
            'get' => [
                'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'description' => 'Own']],
                'responses' => ['200' => ['description' => 'OK']],
            ],
            'delete' => ['responses' => ['204' => ['description' => 'Gone']]],
        ]],
    ], JSON_THROW_ON_ERROR));

    $spec = (new SpecParser)->parseFile($path);
    $get = collect($spec->operations)->firstWhere('method', 'GET');
    $delete = collect($spec->operations)->firstWhere('method', 'DELETE');

    expect($get->parameters)->toHaveCount(2)
        ->and($get->parametersByLocation()['path'][0]['description'])->toBe('Own')
        ->and($get->parametersByLocation()['header'][0]['name'])->toBe('trace')
        ->and($delete->parameters)->toHaveCount(2)
        ->and($delete->parametersByLocation()['path'][0]['description'])->toBe('Shared');
});

it('titles an operation by summary, then operationId, then the request line', function (): void {
    $path = specFile('api.json', json_encode([
        'openapi' => '3.0.0',
        'paths' => [
            '/a' => ['get' => ['summary' => 'Summary wins', 'operationId' => 'aGet']],
            '/b' => ['get' => ['operationId' => 'bGet']],
            '/c' => ['get' => []],
        ],
    ], JSON_THROW_ON_ERROR));

    $titles = array_map(
        static fn ($operation): string => $operation->title(),
        (new SpecParser)->parseFile($path)->operations,
    );

    expect($titles)->toBe(['Summary wins', 'bGet', 'GET /c']);
});

it('gives an operation a heading id that survives a summary rewrite', function (): void {
    $spec = fn (string $summary): string => json_encode([
        'openapi' => '3.0.0',
        'paths' => ['/pets/{petId}/toys' => ['get' => ['summary' => $summary]]],
    ], JSON_THROW_ON_ERROR);

    $before = (new SpecParser)->parseFile(specFile('a.json', $spec('First wording')));
    $after = (new SpecParser)->parseFile(specFile('b.json', $spec('Completely different')));

    expect($before->operations[0]->headingId())->toBe('get-pets-petid-toys')
        ->and($after->operations[0]->headingId())->toBe($before->operations[0]->headingId());
});

it('rejects a spec that is not usable', function (string $contents, string $expected): void {
    $path = specFile('api.json', $contents);

    expect(fn () => (new SpecParser)->parseFile($path))
        ->toThrow(InvalidSpecException::class, $expected);
})->with([
    'no version' => ['{"paths":{}}', 'missing an "openapi" version string'],
    'swagger 2' => ['{"swagger":"2.0","openapi":"2.0","paths":{}}', 'Vellum supports 3.0 and 3.1'],
    'no paths' => ['{"openapi":"3.0.0","info":{"title":"X"}}', 'missing a "paths" object'],
    'paths not an object' => ['{"openapi":"3.0.0","paths":"nope"}', 'missing a "paths" object'],
    'empty file' => ['', 'the file is empty'],
    'broken json' => ['{"openapi": ', 'Unable to parse'],
]);

it('names the file when a local ref points nowhere', function (): void {
    $path = specFile('api.json', json_encode([
        'openapi' => '3.0.0',
        'paths' => ['/pets' => ['get' => ['responses' => ['200' => [
            'description' => 'OK',
            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Missing']]],
        ]]]]],
    ], JSON_THROW_ON_ERROR));

    expect(fn () => (new SpecParser)->parseFile($path))
        ->toThrow(InvalidSpecException::class, '#/components/schemas/Missing');
});

it('reports the file it could not read', function (): void {
    expect(fn () => (new SpecParser)->parseFile('/no/such/spec.json'))
        ->toThrow(InvalidSpecException::class, '/no/such/spec.json');
});
