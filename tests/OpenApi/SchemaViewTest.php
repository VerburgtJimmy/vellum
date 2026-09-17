<?php

declare(strict_types=1);

use Vellum\OpenApi\SchemaView;
use Vellum\OpenApi\SpecParser;

/**
 * Resolve a schema the way a page would see it: through the parser, so $ref,
 * allOf and the recursion guard have all already run.
 *
 * @return array<string, mixed>
 */
function resolvedSchema(array $components, array $schema): array
{
    $spec = (new SpecParser)->parse(json_encode([
        'openapi' => '3.1.0',
        'components' => ['schemas' => $components],
        'paths' => ['/x' => ['get' => ['responses' => ['200' => [
            'description' => 'OK',
            'content' => ['application/json' => ['schema' => $schema]],
        ]]]]],
    ], JSON_THROW_ON_ERROR), 'memory.json');

    return $spec->operations[0]->responses['200']['content']['application/json']['schema'];
}

it('types a scalar, a format, an enum and a nullable', function (): void {
    $schema = resolvedSchema([], ['type' => 'object', 'properties' => [
        'name' => ['type' => 'string'],
        'id' => ['type' => 'string', 'format' => 'uuid'],
        'state' => ['type' => 'string', 'enum' => ['on', 'off']],
        'nickname' => ['type' => ['string', 'null']],
        'legacy' => ['type' => 'string', 'nullable' => true],
    ]]);

    $types = array_column(SchemaView::properties($schema), 'type', 'name');

    expect($types)->toBe([
        'name' => 'string',
        'id' => 'string · uuid',
        'state' => 'string · enum',
        'nickname' => 'string | null',
        'legacy' => 'string | null',
    ]);
});

it('names an array of a referenced schema', function (): void {
    $schema = resolvedSchema(
        ['Pet' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]]],
        ['type' => 'object', 'properties' => [
            'pets' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Pet']],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
        ]],
    );

    $properties = SchemaView::properties($schema);
    $types = array_column($properties, 'type', 'name');

    expect($types['pets'])->toBe('array of Pet')
        ->and($types['tags'])->toBe('array of string')
        // An array of objects opens to show the object's own fields.
        ->and(array_column($properties, 'expandable', 'name'))->toBe(['pets' => true, 'tags' => false]);
});

it('puts required fields first', function (): void {
    $schema = resolvedSchema([], [
        'type' => 'object',
        'required' => ['b'],
        'properties' => ['a' => ['type' => 'string'], 'b' => ['type' => 'string'], 'c' => ['type' => 'string']],
    ]);

    expect(array_column(SchemaView::properties($schema), 'name'))->toBe(['b', 'a', 'c']);
});

it('reads oneOf as a choice between named alternatives', function (): void {
    $schema = resolvedSchema(
        ['Card' => ['type' => 'object', 'properties' => ['pan' => ['type' => 'string']]]],
        ['type' => 'object', 'properties' => ['method' => ['oneOf' => [
            ['$ref' => '#/components/schemas/Card'],
            ['type' => 'string'],
        ]]]],
    );

    $method = SchemaView::properties($schema)[0];

    expect($method['type'])->toBe('Card or string')
        ->and(array_column(SchemaView::variants($method['schema']), 'label'))->toBe(['Card', 'string']);
});

it('marks a schema that contains itself, directly or through an array', function (): void {
    $schema = resolvedSchema(
        ['Node' => [
            'type' => 'object',
            'properties' => [
                'parent' => ['$ref' => '#/components/schemas/Node'],
                'children' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Node']],
            ],
        ]],
        ['$ref' => '#/components/schemas/Node'],
    );

    $properties = SchemaView::properties($schema);

    foreach ($properties as $property) {
        expect(SchemaView::isRecursive($property['schema']))
            ->toBeTrue("{$property['name']} should be marked as repeating")
            // Nothing to open: the repeat is the whole story.
            ->and($property['expandable'])->toBeFalse();
    }

    expect(array_column($properties, 'type', 'name'))->toBe([
        'parent' => 'Node',
        'children' => 'array of Node',
    ]);
});

it('renders values a reader can see', function (): void {
    expect(SchemaView::literal(false))->toBe('false')
        ->and(SchemaView::literal(true))->toBe('true')
        ->and(SchemaView::literal(null))->toBe('null')
        ->and(SchemaView::literal(0))->toBe('0')
        ->and(SchemaView::literal('draft'))->toBe('draft')
        ->and(SchemaView::literal(['a']))->toBe('["a"]');
});
