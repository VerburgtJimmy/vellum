<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Vellum\Content\ContentRepository;
use Vellum\Support\Sitemap;

beforeEach(function (): void {
    config()->set('app.url', 'https://docs.example.com');
});

function locations(string $xml): array
{
    $document = simplexml_load_string($xml);

    expect($document)->not->toBeFalse();

    return array_map(static fn ($url): string => (string) $url->loc, iterator_to_array($document->url, false));
}

it('lists every page at the docs prefix', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('guides/deploy.md', "---\ntitle: Deploy\n---\nShip it");

    $response = $this->get('/docs/sitemap.xml')->assertOk();

    // Sidebar order, which without a meta.json puts the folder before the
    // index. Crawlers do not care about order; the set is what matters.
    expect($response->headers->get('content-type'))->toContain('application/xml')
        ->and(locations((string) $response->getContent()))->toEqualCanonicalizing([
            'https://docs.example.com/docs',
            'https://docs.example.com/docs/guides/deploy',
        ]);
});

it('follows the order meta.json gives the sidebar', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('zebra.md', "---\ntitle: Zebra\n---\nZ");
    $this->writeDoc('apple.md', "---\ntitle: Apple\n---\nA");
    file_put_contents($this->docsPath().'/meta.json', json_encode(['pages' => ['index', 'zebra', 'apple']]));

    expect(locations((string) $this->get('/docs/sitemap.xml')->getContent()))->toBe([
        'https://docs.example.com/docs',
        'https://docs.example.com/docs/zebra',
        'https://docs.example.com/docs/apple',
    ]);
});

it('leaves external meta.json links out', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    file_put_contents($this->docsPath().'/meta.json', json_encode(['pages' => [
        'index',
        ['title' => 'GitHub', 'href' => 'https://github.com/acme/app'],
        ['title' => 'CDN', 'href' => '//cdn.example.com/guide'],
    ]]));

    expect(locations((string) $this->get('/docs/sitemap.xml')->getContent()))->toBe([
        'https://docs.example.com/docs',
    ]);
});

it('agrees with the canonical each page declares', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('guides/deploy.md', "---\ntitle: Deploy\n---\nShip it");

    foreach (locations((string) $this->get('/docs/sitemap.xml')->getContent()) as $location) {
        $path = substr($location, strlen('https://docs.example.com'));
        $html = (string) $this->get($path)->assertOk()->getContent();

        expect($html)->toContain('<link rel="canonical" href="'.$location.'">');
    }
});

it('leaves gated pages out', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('secret.md', "---\ntitle: Secret\naccess: auth\n---\nPrivate");

    $locations = locations((string) $this->get('/docs/sitemap.xml')->getContent());

    expect($locations)->toBe(['https://docs.example.com/docs'])
        ->and(implode(' ', $locations))->not->toContain('secret');
});

it('leaves gated pages out even for a signed-in reader', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('secret.md', "---\ntitle: Secret\naccess: auth\n---\nPrivate");

    // The page is visible to this reader, but a sitemap is one public file.
    $this->actingAs(new User);

    expect($this->get('/docs/secret')->assertOk())->not->toBeNull();
    expect(locations((string) $this->get('/docs/sitemap.xml')->getContent()))
        ->toBe(['https://docs.example.com/docs']);
});

it('covers every version when versions are on', function (): void {
    config()->set('vellum.versions', ['enabled' => true, 'latest' => 'v2', 'list' => ['v2', 'v1'], 'labels' => []]);
    $this->writeDoc('v2/index.md', "---\ntitle: Home\n---\nTwo");
    $this->writeDoc('v1/index.md', "---\ntitle: Home\n---\nOne");

    $locations = locations((string) $this->get('/docs/sitemap.xml')->getContent());

    expect($locations)->toContain('https://docs.example.com/docs')
        ->and($locations)->toContain('https://docs.example.com/docs/v1');
});

it('404s rather than publish relative urls when app.url is not an origin', function (string $url): void {
    config()->set('app.url', $url);
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->get('/docs/sitemap.xml')->assertNotFound();

    expect(Sitemap::urls(ContentRepository::fromConfig()))->toBe([]);
})->with(['', 'localhost', '/docs']);

it('escapes urls it writes', function (): void {
    expect(Sitemap::render(['https://e.com/a?b=1&c=2']))
        ->toContain('<loc>https://e.com/a?b=1&amp;c=2</loc>');
});

it('leaves out a meta.json link to a gated page', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('internal/plan.md', "---\ntitle: Plan\naccess: auth\n---\nSecret plan");
    file_put_contents($this->docsPath().'/meta.json', json_encode([
        'pages' => ['index', ['title' => 'Acquisition plan', 'slug' => 'internal/plan']],
    ], JSON_THROW_ON_ERROR));

    expect(locations((string) $this->get('/docs/sitemap.xml')->getContent()))->toBe([
        'https://docs.example.com/docs',
    ]);
});
