<?php

declare(strict_types=1);

use Vellum\Content\ContentRepository;
use Vellum\OpenApi\ExampleGenerator;
use Vellum\OpenApi\SchemaView;
use Vellum\OpenApi\Stream;

require_once __DIR__.'/helpers.php';

function tracking(array $media, string $type = 'text/event-stream'): array
{
    return [
        'openapi' => '3.2.0',
        'info' => ['title' => 'Harbor'],
        'servers' => [['url' => 'https://api.harbor.test']],
        'components' => ['schemas' => ['Position' => ['type' => 'object', 'properties' => ['lat' => ['type' => 'number', 'example' => 51.9]]]]],
        'paths' => ['/track' => ['get' => [
            'tags' => ['Ships'],
            'operationId' => 'track',
            'summary' => 'Track a ship',
            'responses' => ['200' => ['description' => 'Positions', 'content' => [$type => $media]]],
        ]]],
    ];
}

function trackingPage(string $cachePath, array $spec): array
{
    enableOpenApi($cachePath, $spec);
    $repository = ContentRepository::fromConfig();
    $repository->buildAll();
    $document = $repository->find('api/ships/track');

    return [$repository->render($document), $document];
}

it('knows a stream by its media type, or by the itemSchema that says it is one', function (): void {
    expect(Stream::item('text/event-stream', []))->toBe('event')
        ->and(Stream::item('Text/Event-Stream; charset=utf-8', []))->toBe('event')
        ->and(Stream::item('application/jsonl', []))->toBe('line')
        ->and(Stream::item('application/x-ndjson', []))->toBe('line')
        ->and(Stream::item('application/json-seq', []))->toBe('record')
        ->and(Stream::item('multipart/mixed', ['itemSchema' => ['type' => 'object']]))->toBe('item')
        ->and(Stream::item('application/json', []))->toBeNull();
});

it('writes an event the way it travels', function (): void {
    $media = ['itemSchema' => ['type' => 'object', 'properties' => [
        'event' => ['type' => 'string', 'const' => 'position'],
        'id' => ['type' => 'string', 'example' => '7'],
        'data' => ['type' => 'string', 'contentMediaType' => 'application/json', 'contentSchema' => ['$ref' => '#/x', 'type' => 'object', 'properties' => ['lat' => ['type' => 'number', 'example' => 51.9]]]],
    ]]];

    expect(Stream::example($media, 'event'))->toBe("event: position\nid: 7\ndata: {\"lat\":51.9}");
});

it('makes up no id or retry for an event', function (): void {
    $media = ['itemSchema' => ['type' => 'object', 'properties' => [
        'event' => ['type' => 'string', 'example' => 'tick'],
        'id' => ['type' => 'string'],
        'retry' => ['type' => 'integer'],
        'data' => ['type' => 'string', 'example' => 'one'],
    ]]];

    expect(Stream::example($media, 'event'))->toBe("event: tick\ndata: one");
});

it('splits data over lines, and takes a value with no event fields as the data', function (): void {
    expect(Stream::example(['example' => ['data' => "one\ntwo"]], 'event'))->toBe("data: one\ndata: two")
        ->and(Stream::example(['schema' => ['type' => 'object', 'properties' => ['n' => ['type' => 'integer', 'example' => 1]]]], 'event'))->toBe('data: {"n":1}');
});

it('writes each item of a list on a line of its own', function (): void {
    $media = ['itemSchema' => ['type' => 'object'], 'examples' => ['two' => ['dataValue' => [['n' => 1], ['n' => 2]]]]];

    expect(Stream::example($media, 'line'))->toBe("{\"n\":1}\n{\"n\":2}");
});

it('leaves a stream the spec wrote out as it is', function (): void {
    $text = "event: a\ndata: 1\n\nevent: b\ndata: 2\n";

    expect(Stream::example(['examples' => ['one' => ['serializedValue' => $text]]], 'event'))->toBe(rtrim($text))
        ->and(Stream::example(['example' => $text], 'event'))->toBe(rtrim($text));
});

it('shows one item of a stream, and its example as it travels', function (): void {
    [$html, $document] = trackingPage($this->cachePath(), tracking(['itemSchema' => [
        'type' => 'object',
        'properties' => [
            'event' => ['const' => 'position'],
            'data' => ['type' => 'string', 'contentMediaType' => 'application/json', 'contentSchema' => ['$ref' => '#/components/schemas/Position']],
        ],
    ]]));

    expect($html)->toContain('Sent as a stream, one event at a time. Each event:')
        ->toContain('id="response-200-event"')
        ->toContain('id="response-200-data-lat"')
        ->toContain('application/json')
        ->toContain('Holds')
        ->toContain('data: {&quot;lat&quot;:51.9}');

    expect($document->markdown)->toContain('Sent as a stream of events')->toContain('lat');
});

it('marks a stream a spec only knows by its media type', function (): void {
    [$html] = trackingPage($this->cachePath(), tracking(['schema' => ['type' => 'string']], 'application/x-ndjson'));

    expect($html)->toContain('Sent as a stream, one line at a time. Each line:');
});

it('asks for the stream, and has curl show it as it arrives', function (): void {
    [$html] = trackingPage($this->cachePath(), tracking(['itemSchema' => ['type' => 'object']]));
    preg_match('/data-vellum-playground="([^"]*)"/', $html, $match);
    $data = json_decode(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5), true, 512, JSON_THROW_ON_ERROR);

    expect($data['accept'])->toBe('text/event-stream')
        ->and(html_entity_decode(strip_tags($html)))->toContain('curl -N -X GET')->toContain('Accept: text/event-stream');
});

it('asks for JSON from everything else', function (): void {
    [$html] = trackingPage($this->cachePath(), tracking(['schema' => ['type' => 'object']], 'application/json'));
    preg_match('/data-vellum-playground="([^"]*)"/', $html, $match);
    $data = json_decode(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5), true, 512, JSON_THROW_ON_ERROR);

    expect($data['accept'])->toBeNull()->and(strip_tags($html))->not->toContain('curl -N');
});

it('keeps readOnly and writeOnly to their side in an item', function (): void {
    [$html] = trackingPage($this->cachePath(), tracking(['itemSchema' => ['type' => 'object', 'properties' => [
        'shown' => ['type' => 'string'],
        'secret' => ['type' => 'string', 'writeOnly' => true],
    ]]]));

    expect($html)->toContain('response-200-shown')->not->toContain('response-200-secret');
});

it('reads a const as the one value there is', function (): void {
    expect(ExampleGenerator::fromSchema(['const' => 'position']))->toBe('position')
        ->and(SchemaView::enumValues(['const' => 'position']))->toBe(['position'])
        ->and(SchemaView::type(['const' => 'position']))->toBe('string')
        ->and(SchemaView::type(['const' => 3]))->toBe('integer');
});

it('renders a description as Markdown, and leaves its own HTML out', function (): void {
    $spec = tracking(['schema' => ['type' => 'object', 'properties' => [
        'kind' => ['type' => 'string', 'description' => 'See `kind` and **more** at [the guide](https://example.com/guide).'],
        'plain' => ['type' => 'string', 'description' => 'One & only, <b>raw</b>.'],
        'sentence' => ['type' => 'string', 'description' => 'A plain sentence.'],
    ]]], 'application/json');
    $spec['paths']['/track']['get']['responses']['200']['description'] = 'The `Ship` that moved.';

    [$html] = trackingPage($this->cachePath(), $spec);

    expect($html)->toContain('See <code data-vellum-inline-code="">kind</code> and <strong>more</strong>')
        ->toContain('href="https://example.com/guide"')
        ->toContain('<span class="vellum-api-panel-title">The <code data-vellum-inline-code="">Ship</code> that moved.</span>')
        ->toContain('<div class="vellum-api-field-description"><p>A plain sentence.</p></div>')
        ->not->toContain('<b>raw</b>');
});

it('gives a description no way to run anything', function (): void {
    [$html] = trackingPage($this->cachePath(), tracking(['schema' => ['type' => 'object', 'properties' => [
        'a' => ['type' => 'string', 'description' => '[click](javascript:alert(1)) <script>alert(2)</script> <img src=x onerror=alert(3)>'],
    ]]], 'application/json'));

    expect($html)->not->toContain('javascript:alert')->not->toContain('<script>alert')->not->toContain('onerror=alert');
});

it('leaves Markdown marks out of a playground tooltip', function (): void {
    $spec = tracking(['schema' => ['type' => 'object']], 'application/json');
    $spec['paths']['/track']['get']['parameters'] = [['name' => 'ship', 'in' => 'query', 'description' => 'The `id`, **always**, from [the list](/ships).', 'schema' => ['type' => 'string']]];

    [$html] = trackingPage($this->cachePath(), $spec);
    preg_match('/data-vellum-playground="([^"]*)"/', $html, $match);
    $data = json_decode(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5), true, 512, JSON_THROW_ON_ERROR);

    expect($data['parameters'][0]['description'])->toBe('The id, always, from the list.');
});
