<?php

declare(strict_types=1);

use Vellum\Content\ContentRepository;
use Vellum\OpenApi\Grouper;
use Vellum\OpenApi\Samples\PhpGenerator;
use Vellum\OpenApi\Samples\SampleRequest;
use Vellum\OpenApi\Spec;
use Vellum\OpenApi\SpecParser;

require_once __DIR__.'/helpers.php';

/**
 * What OpenAPI 3.2 adds, read where Vellum has a use for it.
 */
function spec32(array $overrides = []): array
{
    return array_replace_recursive([
        'openapi' => '3.2.0',
        'info' => ['title' => 'Harbor'],
        'paths' => ['/ships' => ['get' => ['tags' => ['Ships'], 'operationId' => 'listShips', 'summary' => 'List ships']]],
    ], $overrides);
}

function parse32(array $spec): Spec
{
    return (new SpecParser)->parse(json_encode($spec, JSON_THROW_ON_ERROR), 'harbor.json');
}

/** Folder titles of the reference's sidebar, nested as they are shown. */
function folderTree(array $nodes): array
{
    $out = [];

    foreach ($nodes as $node) {
        if (($node['type'] ?? null) === 'folder') {
            $out[$node['title']] = folderTree($node['children'] ?? []);
        }
    }

    return $out;
}

it('reads a 3.2 document', function (): void {
    expect(parse32(spec32())->operations)->toHaveCount(1);
});

it('reads a QUERY operation, and the methods an API makes up for itself', function (): void {
    $spec = parse32(spec32(['paths' => ['/ships/search' => [
        'query' => ['summary' => 'Search ships', 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object']]]]],
        'additionalOperations' => [
            'PURGE' => ['summary' => 'Purge the cache'],
            'Lock' => ['summary' => 'Lock'],
            // Repeats a method with a field of its own, so the field wins.
            'post' => ['summary' => 'Not this one'],
            'BAD METHOD' => ['summary' => 'Not a method'],
        ],
    ]]]));

    $methods = array_map(fn ($operation): string => $operation->method.' '.$operation->title(), $spec->operations);

    expect($methods)->toBe(['GET List ships', 'QUERY Search ships', 'PURGE Purge the cache', 'Lock Lock']);
});

it('writes a QUERY request in PHP through send(), which has a method for any verb', function (): void {
    $sample = (new PhpGenerator)->generate(new SampleRequest(method: 'QUERY', url: 'https://api.test/ships', headers: [], query: [], body: ['type' => 'tug']));

    expect($sample)->toContain("->send('QUERY', 'https://api.test/ships', ['json' => [")
        ->toContain("'type' => 'tug'");
});

it('reads a form-encoded query string as the query parameters it is made of', function (): void {
    $spec = parse32(spec32(['paths' => ['/ships' => ['get' => ['parameters' => [[
        'name' => 'filter',
        'in' => 'querystring',
        'content' => ['application/x-www-form-urlencoded' => ['schema' => [
            'type' => 'object',
            'required' => ['type'],
            'properties' => ['type' => ['type' => 'string', 'enum' => ['tug', 'ferry']], 'limit' => ['type' => 'integer']],
        ]]],
    ]]]]]]));

    $parameters = $spec->operations[0]->parameters;

    expect(array_map(fn (array $parameter): array => [$parameter['name'], $parameter['in'], $parameter['required']], $parameters))
        ->toBe([['type', 'query', true], ['limit', 'query', false]]);
});

it('keeps a query string of another kind as one parameter', function (): void {
    $spec = parse32(spec32(['paths' => ['/ships' => ['get' => ['parameters' => [[
        'name' => 'q',
        'in' => 'querystring',
        'content' => ['application/jsonpath' => ['schema' => ['type' => 'string']]],
    ]]]]]]));

    expect($spec->operations[0]->parameters[0])->toMatchArray(['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string']]);
});

it('nests a tag in its parent, names it by its summary, and keeps a parent with nothing of its own', function (): void {
    enableOpenApi($this->cachePath(), spec32([
        'tags' => [
            ['name' => 'fleet', 'summary' => 'Fleet'],
            ['name' => 'ships', 'summary' => 'Ships', 'parent' => 'fleet'],
            ['name' => 'crew', 'parent' => 'ships'],
            ['name' => 'empty', 'parent' => 'fleet'],
            ['name' => 'beta', 'kind' => 'badge'],
        ],
        'paths' => [
            '/ships' => ['get' => ['tags' => ['beta', 'ships'], 'operationId' => 'listShips']],
            '/crew' => ['get' => ['tags' => ['crew'], 'operationId' => 'listCrew']],
        ],
    ]));

    $repository = ContentRepository::fromConfig();
    $tree = $repository->navigation();

    // A badge is not where an operation lives, and a tag with no operations
    // anywhere under it is not listed.
    expect(folderTree($tree[count($tree) - 1]['children']))->toBe(['Fleet' => ['Ships' => ['crew' => []]]])
        ->and(apiSlugs($repository))->toBe(['api', 'api/ships/list-ships', 'api/crew/list-crew']);

    $overview = $repository->find('api');

    expect($overview->html)->toContain('<h2 id="group-fleet"')
        ->toContain('<h3 id="group-ships"')
        ->toContain('<h4 id="group-crew"')
        // Fleet has no operations of its own, so no list under it.
        ->and(substr_count($overview->html, 'class="vellum-api-index"'))->toBe(2);
});

it('reads Redoc tag groups as parents', function (): void {
    $spec = parse32(spec32([
        'openapi' => '3.1.0',
        'tags' => [['name' => 'Ships', 'x-displayName' => 'All ships']],
        'x-tagGroups' => [['name' => 'Fleet', 'tags' => ['Ships']]],
    ]));

    $groups = (new Grouper)->group($spec);

    expect(array_map(fn ($group): array => [$group->label(), $group->parent, $group->depth], $groups))
        ->toBe([['Fleet', null, 0], ['All ships', 'Fleet', 1]]);
});

it('does not loop on tags that name each other as parent', function (): void {
    $spec = parse32(spec32([
        'tags' => [['name' => 'Ships', 'parent' => 'Fleet'], ['name' => 'Fleet', 'parent' => 'Ships']],
    ]));

    expect(array_map(fn ($group): string => $group->name, (new Grouper)->group($spec)))->toContain('Ships');
});

it('takes the value of an example given as dataValue, for a body and for a parameter', function (): void {
    enableOpenApi($this->cachePath(), spec32(['paths' => ['/ships/{id}' => ['put' => [
        'tags' => ['Ships'],
        'operationId' => 'updateShip',
        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer'], 'examples' => ['one' => ['dataValue' => 7, 'serializedValue' => '7']]]],
        'requestBody' => ['content' => ['application/json' => [
            'schema' => ['type' => 'object'],
            'examples' => ['aurora' => ['dataValue' => ['name' => 'Aurora'], 'serializedValue' => '{"name":"Aurora"}']],
        ]]],
    ]]]]));

    $page = collect(ContentRepository::fromConfig()->buildAll())->firstWhere('slug', 'api/ships/update-ship');

    // The body in the samples, the parameter in the playground's starting values.
    expect($page->html)->toContain('Aurora')
        ->and(html_entity_decode($page->html))->toContain('"example":7');
});

it('names a server and marks a security scheme on its way out', function (): void {
    enableOpenApi($this->cachePath(), spec32([
        'servers' => [['url' => 'https://api.harbor.test', 'name' => 'production']],
        'components' => ['securitySchemes' => ['legacyKey' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-Key', 'deprecated' => true]]],
        'security' => [['legacyKey' => []]],
    ]));

    $repository = ContentRepository::fromConfig();
    $overview = $repository->find('api')->html;
    $operation = collect($repository->buildAll())->firstWhere('slug', 'api/ships/list-ships')->html;

    expect($overview)->toContain('<span class="vellum-api-field-type">production</span>')
        ->toContain('<span class="vellum-api-deprecated">Deprecated</span>')
        ->and($operation)->toContain('<span class="vellum-api-deprecated">Deprecated</span>');
});
