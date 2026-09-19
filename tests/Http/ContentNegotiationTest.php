<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Vellum\Http\RawMarkdown;

const CHROME_ACCEPT = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7';

it('answers Accept: text/markdown with the raw markdown', function (): void {
    $source = "---\ntitle: Deploy\n---\n# Deploy\n\nShip it.\n";
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('guides/deploy.md', $source);

    $response = $this->get('/docs/guides/deploy', ['Accept' => 'text/markdown'])->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/markdown; charset=UTF-8')
        ->and($response->getContent())->toBe($source)
        ->and($response->getContent())->toBe($this->get('/docs/_vellum/raw/guides/deploy.md')->getContent());
});

it('negotiates the docs index too', function (): void {
    $source = "---\ntitle: Home\n---\nHi\n";
    $this->writeDoc('index.md', $source);

    expect($this->get('/docs', ['Accept' => 'text/markdown'])->assertOk()->getContent())->toBe($source);
});

it('keeps serving html to a browser', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $response = $this->get('/docs', ['Accept' => CHROME_ACCEPT])->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/html; charset=UTF-8');
});

it('404s a gated page asked for as markdown, like the raw route', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('secret.md', "---\ntitle: Secret\naccess: auth\n---\nPrivate words");

    $response = $this->get('/docs/secret', ['Accept' => 'text/markdown'])->assertNotFound();

    expect((string) $response->getContent())->not->toContain('Private words');
    $this->get('/docs/_vellum/raw/secret.md')->assertNotFound();

    $this->actingAs(new User);

    expect((string) $this->get('/docs/secret', ['Accept' => 'text/markdown'])->assertOk()->getContent())
        ->toContain('Private words');
});

it('404s a missing page asked for as markdown', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->get('/docs/missing', ['Accept' => 'text/markdown'])->assertNotFound();
});

it('names the version on negotiated markdown', function (): void {
    config()->set('vellum.versions', ['enabled' => true, 'latest' => 'v2', 'list' => ['v2', 'v1'], 'labels' => []]);
    $this->writeDoc('v2/index.md', "---\ntitle: Home\n---\nTwo");
    $this->writeDoc('v1/index.md', "---\ntitle: Home\n---\nOne");

    $this->get('/docs', ['Accept' => 'text/markdown'])->assertOk()->assertHeader('X-Vellum-Docs-Version', 'v2');
    $this->get('/docs/v1', ['Accept' => 'text/markdown'])->assertOk()->assertHeader('X-Vellum-Docs-Version', 'v1');
});

it('varies the compressed html response on Accept and Accept-Encoding', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $response = $this->get('/docs', ['Accept' => CHROME_ACCEPT, 'Accept-Encoding' => 'gzip'])->assertOk();

    expect($response->headers->get('Content-Encoding'))->toBe('gzip')
        ->and($response->baseResponse->getVary())->toContain('Accept')
        ->and($response->baseResponse->getVary())->toContain('Accept-Encoding');
});

it('varies the markdown response on Accept', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $response = $this->get('/docs', ['Accept' => 'text/markdown', 'Accept-Encoding' => 'gzip'])->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/markdown; charset=UTF-8')
        ->and($response->baseResponse->getVary())->toContain('Accept');
});

it('varies the 404 on Accept', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    expect($this->get('/docs/missing')->assertNotFound()->baseResponse->getVary())->toContain('Accept')
        ->and($this->get('/docs/missing', ['Accept' => 'text/markdown'])->baseResponse->getVary())->toContain('Accept');
});

it('serves html and no Vary: Accept when negotiation is off', function (): void {
    config()->set('vellum.agents.content_negotiation', false);
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $response = $this->get('/docs', ['Accept' => 'text/markdown', 'Accept-Encoding' => 'gzip'])->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/html; charset=UTF-8')
        ->and($response->baseResponse->getVary())->toBe(['Accept-Encoding']);
});

it('reads the Accept header the way the docs describe', function (string $accept, bool $markdown): void {
    $request = Request::create('/docs', server: ['HTTP_ACCEPT' => $accept]);

    expect(RawMarkdown::preferredBy($request))->toBe($markdown);
})->with([
    'plain markdown' => ['text/markdown', true],
    'markdown with a charset' => ['text/markdown; charset=utf-8', true],
    'upper case' => ['Text/Markdown', true],
    'markdown ranked above html' => ['text/html;q=0.5, text/markdown', true],
    'markdown before html at equal quality' => ['text/markdown, text/html', true],
    'markdown next to a wildcard' => ['text/markdown, */*', true],
    'wildcard before markdown' => ['*/*, text/markdown', true],
    'plain text fallback' => ['text/markdown, text/plain;q=0.8', true],
    'html before markdown at equal quality' => ['text/html, text/markdown', false],
    'html ranked above markdown' => ['text/markdown;q=0.5, text/html', false],
    'markdown refused' => ['text/markdown;q=0, text/plain', false],
    'wildcard only' => ['*/*', false],
    'text wildcard only' => ['text/*', false],
    'no header' => ['', false],
    'chrome' => [CHROME_ACCEPT, false],
    'firefox' => ['text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', false],
]);
