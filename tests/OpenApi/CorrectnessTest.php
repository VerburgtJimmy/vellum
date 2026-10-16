<?php

declare(strict_types=1);

use Vellum\Content\ContentRepository;
use Vellum\Content\Document;
use Vellum\OpenApi\SchemaView;
use Vellum\OpenApi\Servers;

require_once __DIR__.'/helpers.php';

function ships(array $overrides = []): array
{
    return array_replace_recursive([
        'openapi' => '3.1.0',
        'info' => ['title' => 'Harbor'],
        'servers' => [['url' => 'https://{region}.api.harbor.test/{version}', 'variables' => [
            'region' => ['default' => 'eu', 'enum' => ['eu', 'us'], 'description' => 'Where your account lives.'],
            'version' => ['default' => 'v2'],
        ]]],
        'components' => ['schemas' => ['Ship' => [
            'type' => 'object',
            'required' => ['id', 'name', 'secret'],
            'properties' => [
                'id' => ['type' => 'integer', 'readOnly' => true],
                'name' => ['type' => 'string', 'example' => 'Aurora'],
                'secret' => ['type' => 'string', 'writeOnly' => true],
                'crew' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'badge' => ['type' => 'string', 'readOnly' => true],
                    'role' => ['type' => 'string'],
                ]]],
            ],
        ]]],
        'paths' => ['/ships' => ['post' => [
            'tags' => ['Ships'],
            'operationId' => 'registerShip',
            'requestBody' => ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Ship']]]],
            'responses' => ['201' => ['description' => 'Created', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Ship']]]]],
        ]]],
    ], $overrides);
}

function shipPage(): Document
{
    return collect(ContentRepository::fromConfig()->buildAll())->firstWhere('slug', 'api/ships/register-ship');
}

it('leaves what the server sets out of a request, and what it never sends back out of a response', function (): void {
    enableOpenApi($this->cachePath(), ships(), ['samples' => ['curl']]);

    $page = shipPage();
    $body = substr($page->html, (int) strpos($page->html, 'id="request-body"'), (int) strpos($page->html, 'id="responses"') - (int) strpos($page->html, 'id="request-body"'));
    $response = substr($page->html, (int) strpos($page->html, 'id="responses"'));

    expect($body)->toContain('id="body-name"')->toContain('id="body-secret"')->toContain('id="body-crew-role"')
        ->not->toContain('id="body-id"')->not->toContain('id="body-crew-badge"')
        ->and($response)->toContain('id="response-201-id"')->toContain('id="response-201-crew-badge"')
        ->not->toContain('id="response-201-secret"');

    // Nor in what the playground fills in, and nor required there.
    preg_match('/data-vellum-playground="([^"]+)"/', $page->html, $match);
    $schema = json_decode(html_entity_decode($match[1]), true)['bodies'][0]['schema'];

    expect(array_keys($schema['properties']))->not->toContain('id')
        ->and($schema['required'])->toBe(['name', 'secret']);
});

it('fills in a server\'s variables, and offers each value the spec allows', function (): void {
    enableOpenApi($this->cachePath(), ships(), ['samples' => ['curl']]);

    $repository = ContentRepository::fromConfig();
    $page = shipPage();

    expect(html_entity_decode(strip_tags($page->html)))->toContain("'https://eu.api.harbor.test/v2/ships'");

    preg_match('/data-vellum-playground="([^"]+)"/', $page->html, $match);

    expect(json_decode(html_entity_decode($match[1]), true)['servers'])->toBe(['https://eu.api.harbor.test/v2', 'https://us.api.harbor.test/v2'])
        // The forwarding route may reach exactly those.
        ->and($repository->find('api')->frontmatter['openapi_servers'])->toBe(['https://eu.api.harbor.test/v2', 'https://us.api.harbor.test/v2'])
        ->and($repository->find('api')->html)->toContain('Where your account lives.');
});

it('never makes up an address from a variable with no list of values', function (): void {
    expect(Servers::urls(['url' => 'https://{host}/api', 'variables' => ['host' => ['default' => 'api.example.com']]]))
        ->toBe(['https://api.example.com/api'])
        ->and(Servers::urls(['url' => 'https://{a}{b}.test', 'variables' => [
            'a' => ['default' => '0', 'enum' => range(0, 9)],
            'b' => ['default' => '0', 'enum' => range(0, 9)],
        ]]))->toHaveCount(16);
});

it('says which property picks a variant, and gives its value in the example', function (): void {
    enableOpenApi($this->cachePath(), [
        'openapi' => '3.2.0',
        'info' => ['title' => 'Harbor'],
        'components' => ['schemas' => [
            'ByShip' => ['type' => 'object', 'properties' => ['kind' => ['type' => 'string'], 'shipId' => ['type' => 'integer']]],
            'Reserved' => ['type' => 'object', 'properties' => ['kind' => ['type' => 'string'], 'reason' => ['type' => 'string']]],
        ]],
        'paths' => ['/berths' => ['put' => [
            'tags' => ['Berths'],
            'operationId' => 'assign',
            'requestBody' => ['content' => ['application/json' => ['schema' => [
                'oneOf' => [['$ref' => '#/components/schemas/ByShip'], ['$ref' => '#/components/schemas/Reserved']],
                'discriminator' => ['propertyName' => 'kind', 'mapping' => ['ship' => '#/components/schemas/ByShip'], 'defaultMapping' => '#/components/schemas/Reserved'],
            ]]]],
        ]]],
    ], ['samples' => ['curl']]);

    $html = collect(ContentRepository::fromConfig()->buildAll())->firstWhere('slug', 'api/berths/assign')->html;
    $text = html_entity_decode(strip_tags($html));

    expect($html)->toContain('<code>kind</code> says which of these it is.')
        ->toContain('<code class="vellum-api-variant-value">kind: ship</code>')
        // Without a mapping, the value is the schema's own name.
        ->toContain('<code class="vellum-api-variant-value">kind: Reserved</code>')
        ->toMatch('/kind: Reserved<\\/code>\\s*<span class="vellum-api-variant-default">default<\\/span>/')
        ->and($text)->toContain('"kind": "ship"');
});

it('reads no discriminator where a schema names no property', function (): void {
    expect(SchemaView::discriminator(['oneOf' => [['type' => 'string']], 'discriminator' => []]))->toBeNull();
});
