<?php

declare(strict_types=1);

use Vellum\Content\ContentRepository;
use Vellum\View\Assets;

require_once __DIR__.'/helpers.php';

function operationPage(string $cachePath, array $operation, array $config = []): string
{
    enableOpenApi($cachePath, apiSpec(['paths' => ['/pets' => ['post' => $operation]]]), $config);

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();

    return $repository->render($repository->find('api/pets/create-pet'));
}

beforeEach(function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
});

it('opens with the request line, marked with its method', function (): void {
    $html = operationPage($this->cachePath(), ['deprecated' => true]);

    expect($html)->toContain('<div class="vellum-api-endpoint" data-method="post">')
        ->toContain('<span class="vellum-api-method" data-method="post">POST</span>')
        ->toContain('<code class="vellum-api-path">/pets</code>')
        ->toContain('<span class="vellum-api-deprecated">Deprecated</span>');
});

it('marks the parameters in a path', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());
    $repository = ContentRepository::fromConfig();
    $repository->buildAll();

    expect($repository->render($repository->find('api/pets/delete-pets-petid')))
        ->toContain('/pets/<span class="vellum-api-param">{petId}</span>');
});

it('does not repeat the paragraph the page already shows under its title', function (): void {
    $html = operationPage($this->cachePath(), ['description' => "Adds a pet.\n\nIt cannot be undone."]);

    expect($html)->toContain('<div class="vellum-api-description"><p>It cannot be undone.</p>')
        ->not->toContain('Adds a pet.');

    expect(operationPage($this->cachePath(), ['description' => 'Adds a pet.']))->not->toContain('vellum-api-description');
});

it('puts each section in a panel of its own', function (): void {
    $html = operationPage($this->cachePath(), [
        'security' => [['bearer' => []]],
        'parameters' => [['name' => 'dryRun', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'boolean']]],
        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                'name' => ['type' => 'string', 'minLength' => 2],
                'owner' => ['type' => 'object', 'properties' => ['email' => ['type' => 'string']]],
            ],
        ]]]],
        'responses' => [
            '201' => ['description' => 'Created.', 'content' => ['application/json' => ['schema' => [
                'type' => 'object', 'properties' => ['id' => ['type' => 'integer']],
            ]]]],
            '422' => ['description' => 'Invalid.'],
        ],
    ]);

    expect(preg_match_all('/class="vellum-api-panel(?: vellum-api-response)?"/', $html))->toBe(5)
        ->and($html)->toMatch('/<h2 id="query-parameters"[^>]*>Query parameters<\/h2>\s*<div class="vellum-api-panel">/')
        // The body's panel is headed by its media type, and says it is required.
        ->toMatch('/<div class="vellum-api-panel-head">\s*<code class="vellum-api-media">application\/json<\/code>\s*<span class="vellum-api-panel-note">Required<\/span>/')
        // A response's panel is headed by its status, what it means and its media type.
        ->toMatch('/<span class="vellum-api-status" data-status="2xx">201<\/span>\s*<span class="vellum-api-panel-title">Created\.<\/span>\s*<code class="vellum-api-media">application\/json<\/code>/')
        ->toMatch('/<span class="vellum-api-status" data-status="4xx">422<\/span>\s*<span class="vellum-api-panel-title">Invalid\.<\/span>\s*<\/div>\s*<\/div>/')
        ->toMatch('/vellum-api-field-name">name<span\s+class="vellum-api-marker"\s+data-required="true"/')
        ->toMatch('/vellum-api-field-name">owner<span\s+class="vellum-api-marker"\s+data-required="false"/')
        // A nested object opens without a script.
        ->toMatch('/<details class="vellum-api-nested"\s+open\s*>\s*<summary class="vellum-api-toggle">Properties<\/summary>/');
});

it('shows the request and each response as code tabs beside the documentation', function (): void {
    $html = operationPage($this->cachePath(), [
        'requestBody' => ['content' => ['application/json' => ['schema' => [
            'type' => 'object', 'properties' => ['name' => ['type' => 'string', 'example' => 'Rex']],
        ]]]],
        'responses' => ['201' => ['description' => 'Created.', 'content' => ['application/json' => ['schema' => [
            'type' => 'object', 'properties' => ['id' => ['type' => 'integer', 'example' => 7]],
        ]]]]],
    ]);

    preg_match('/<aside class="vellum-api-examples".*<\/aside>/s', $html, $aside);

    expect($aside[0] ?? '')->toContain('<p class="vellum-api-example-label">Request</p>')
        ->toContain('<p class="vellum-api-example-label">Response</p>')
        ->toContain('vellum-tabs-code')
        ->toContain('Rex')
        ->toContain('>201<')
        // After the documentation in the source, so read in order it is still a page.
        ->and(strpos($html, 'vellum-api-examples'))->toBeGreaterThan(strpos($html, 'id="responses"'));
});

it('has no examples column when there is nothing to show in it', function (): void {
    expect(operationPage($this->cachePath(), [], ['samples' => []]))->not->toContain('vellum-api-examples');
});

it('sets the examples beside the documentation where there is room, in place of the table of contents', function (): void {
    $css = (string) file_get_contents(Assets::cssPath());

    expect($css)->toMatch('/\.vellum-api-operation\{[^}]*grid-template-columns:minmax\(0,1fr\) minmax\(0,27rem\)/')
        ->toMatch('/div\[data-vellum-page-row\]:has\(\.vellum-api-operation\)>:is\(\[data-vellum-toc\],\[data-vellum-toc-placeholder\]\)\{display:none\}/')
        ->toMatch('/\.vellum-api-examples\{[^}]*position:sticky/');
});
