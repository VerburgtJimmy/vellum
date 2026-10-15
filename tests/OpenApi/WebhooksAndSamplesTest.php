<?php

declare(strict_types=1);

use Vellum\Content\ContentRepository;
use Vellum\Content\Document;
use Vellum\OpenApi\Samples\PythonGenerator;
use Vellum\OpenApi\Samples\SampleRequest;

require_once __DIR__.'/helpers.php';

function harbor(array $overrides = []): array
{
    return array_replace_recursive([
        'openapi' => '3.1.0',
        'info' => ['title' => 'Harbor'],
        'security' => [['bearer' => []]],
        'components' => ['securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer']]],
        'paths' => ['/ships' => ['post' => [
            'tags' => ['Ships'],
            'operationId' => 'registerShip',
            'summary' => 'Register a ship',
            'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]]]]],
        ]]],
        'webhooks' => ['shipArrived' => ['post' => [
            'operationId' => 'shipArrived',
            'summary' => 'Ship arrived',
            'requestBody' => ['content' => ['application/json' => ['schema' => [
                'type' => 'object',
                'properties' => ['event' => ['type' => 'string', 'example' => 'ship.arrived']],
            ]]]],
            'responses' => ['200' => ['description' => 'Received.']],
        ]]],
    ], $overrides);
}

function pageOf(string $slug): Document
{
    return collect(ContentRepository::fromConfig()->buildAll())->firstWhere('slug', $slug);
}

it('gives each webhook a page in a folder of its own, after the operations', function (): void {
    enableOpenApi($this->cachePath(), harbor());

    $repository = ContentRepository::fromConfig();
    $tree = $repository->navigation();

    expect(apiSlugs($repository))->toBe(['api', 'api/ships/register-ship', 'api/webhooks/ship-arrived'])
        ->and(array_column($tree[count($tree) - 1]['children'], 'title'))->toBe(['Overview', 'Ships', 'Webhooks']);
});

it('shows a webhook as a request that arrives, with its payload and no way to send it', function (): void {
    enableOpenApi($this->cachePath(), harbor(), ['samples' => ['curl', 'python']]);

    $html = pageOf('api/webhooks/ship-arrived')->html;

    expect($html)->toContain('<span class="vellum-api-kind">Webhook</span>')
        ->toContain('<h2 id="payload"')
        ->toContain('vellum-api-example-label">Payload</p>')
        ->toContain('ship.arrived')
        // Nothing to send: no samples and no playground.
        ->not->toContain('vellum-api-try')
        ->not->toContain('curl')
        // The spec's security is for calling the API, not for being called by it.
        ->not->toContain('id="authorization"');
});

it('lists the callbacks an operation makes, with what they carry and what to answer', function (): void {
    enableOpenApi($this->cachePath(), harbor(['paths' => ['/ships' => ['post' => ['callbacks' => ['inspected' => [
        '{$request.body#/callbackUrl}' => ['post' => [
            'summary' => 'Inspection done',
            'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['passed' => ['type' => 'boolean']]]]]],
            'responses' => ['204' => ['description' => 'Received.']],
        ]],
    ]]]]]]));

    $page = pageOf('api/ships/register-ship');

    expect($page->html)->toContain('<h2 id="callbacks"')
        ->toContain('{$request.body#/callbackUrl}')
        ->toContain('Inspection done')
        ->toContain('id="callback-1-passed"')
        ->toContain('data-status="2xx">204</span>')
        ->and($page->headings)->toContain(['id' => 'callbacks', 'text' => 'Callbacks', 'level' => 2])
        ->and($page->markdown)->toContain("## Callbacks\n\n### Inspection done");
});

it('puts the samples a spec writes itself first, in place of the generated one in the same language', function (): void {
    enableOpenApi($this->cachePath(), harbor(['paths' => ['/ships' => ['post' => ['x-codeSamples' => [
        ['lang' => 'curl', 'label' => 'cURL', 'source' => "curl https://api.harbor.test/ships --json @ship.json\n"],
        ['lang' => 'Go', 'source' => 'client.Ships.Register(ctx, ship)'],
        ['lang' => 'ruby', 'source' => ''],
    ]]]]]), ['samples' => ['curl', 'php']]);

    // As text: the highlighter splits code into spans.
    $html = html_entity_decode(strip_tags(pageOf('api/ships/register-ship')->html));

    expect($html)->toContain('--json @ship.json')
        ->toContain('client.Ships.Register(ctx, ship)')
        ->toContain('Http::withToken')
        // The written curl stands in for the generated one, and an empty sample is no sample.
        ->not->toContain('curl -X POST')
        ->not->toContain('ruby');

    // Written samples first, in their order, then the generated ones.
    expect(strpos($html, '--json @ship.json'))->toBeLessThan(strpos($html, 'client.Ships.Register'))
        ->and(strpos($html, 'client.Ships.Register'))->toBeLessThan(strpos($html, 'Http::withToken'));
});

it('writes a Python sample whose every value is one string and nothing else', function (): void {
    $sample = (new PythonGenerator)->generate(new SampleRequest(
        method: 'POST',
        url: "https://api.test/ships'); import os #",
        headers: ['Authorization' => "Bearer it's\\", 'Content-Type' => 'application/json'],
        body: ['name' => "O'Brien\n", 'ok' => true, 'none' => null, 'list' => [1, 2.5]],
    ));

    expect($sample)->toStartWith("import requests\n\nresponse = requests.post(\n")
        ->toContain("    'https://api.test/ships\\'); import os #',")
        ->toContain("'Authorization': 'Bearer it\\'s\\\\',")
        ->toContain("'name': 'O\\'Brien\\n',")
        ->toContain("'ok': True,")
        ->toContain("'none': None,")
        // requests writes the Content-Type of a json= body itself.
        ->not->toContain('Content-Type');
});

it('writes a Python request for a method requests has no function for', function (): void {
    expect((new PythonGenerator)->generate(new SampleRequest(method: 'QUERY', url: 'https://api.test/s')))
        ->toContain("requests.request(\n    'QUERY',\n    'https://api.test/s',\n)");
});
