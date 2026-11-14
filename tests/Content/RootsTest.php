<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Vellum\Content\ContentRepository;

/*
 * A top-level folder whose meta.json says `root` is a sidebar of its own,
 * with a switcher between it and the docs' own sidebar.
 */

beforeEach(function (): void {
    $this->writeDoc('meta.json', json_encode(['title' => 'Handbook', 'pages' => ['index', 'install', 'guides', 'api-docs']], JSON_THROW_ON_ERROR));
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nWelcome.");
    $this->writeDoc('install.md', "---\ntitle: Install\n---\nInstall it.");
    $this->writeDoc('guides/meta.json', json_encode(['title' => 'Guides', 'root' => true, 'description' => 'Task-focused walkthroughs', 'pages' => ['index', 'deploy']], JSON_THROW_ON_ERROR));
    $this->writeDoc('guides/index.md', "---\ntitle: Guides\n---\nAll guides.");
    $this->writeDoc('guides/deploy.md', "---\ntitle: Deploy\n---\nShip it.");
    $this->writeDoc('api-docs/meta.json', json_encode(['title' => 'Internals', 'root' => true], JSON_THROW_ON_ERROR));
    $this->writeDoc('api-docs/index.md', "---\ntitle: Internals\n---\nHow it works.");
});

it('gives a root folder a sidebar of its own, and leaves it out of the docs\' own', function (): void {
    $repository = ContentRepository::fromConfig();
    $repository->buildAll();

    expect(array_column($repository->sidebar('guides/deploy'), 'title'))->toBe(['Guides', 'Deploy'])
        ->and(array_column($repository->sidebar('install'), 'title'))->toBe(['Home', 'Install'])
        ->and(array_column($repository->sidebar('api-docs'), 'title'))->toBe(['Internals']);
});

it('lists the docs and every root in the switcher, with the one the page is in marked', function (): void {
    $repository = ContentRepository::fromConfig();
    $repository->buildAll();

    expect($repository->sections('guides/deploy'))->toBe([
        ['key' => '', 'title' => 'Handbook', 'description' => null, 'href' => '/docs', 'current' => false],
        ['key' => 'guides', 'title' => 'Guides', 'description' => 'Task-focused walkthroughs', 'href' => '/docs/guides', 'current' => true],
        ['key' => 'api-docs', 'title' => 'Internals', 'description' => null, 'href' => '/docs/api-docs', 'current' => false],
    ])->and(array_column($repository->sections('install'), 'current', 'key'))->toBe(['' => true, 'guides' => false, 'api-docs' => false]);
});

it('calls the docs\' own sidebar Docs when the top-level meta.json has no title', function (): void {
    $this->writeDoc('meta.json', '{}');

    expect(ContentRepository::fromConfig()->sections('')[0]['title'])->toBe('Docs');
});

it('keeps previous and next inside the sidebar the page is in', function (): void {
    $repository = ContentRepository::fromConfig();
    $repository->buildAll();

    expect($repository->adjacent('install')['next'])->toBeNull()
        ->and($repository->adjacent('guides')['previous'])->toBeNull()
        ->and($repository->adjacent('guides')['next']['slug'] ?? null)->toBe('guides/deploy')
        ->and($repository->adjacent('guides/deploy')['next'])->toBeNull();
});

it('starts the breadcrumbs of a page in a root at the root', function (): void {
    $repository = ContentRepository::fromConfig();
    $repository->buildAll();

    expect(array_column($repository->breadcrumbs($repository->find('guides/deploy')), 'title'))->toBe(['Guides', 'Deploy'])
        ->and(array_column($repository->breadcrumbs($repository->find('install')), 'title'))->toBe(['Docs', 'Install']);
});

it('shows a reader only the roots they may open', function (): void {
    Gate::define('staff', static fn ($user = null): bool => false);
    $this->writeDoc('api-docs/meta.json', json_encode(['title' => 'Internals', 'root' => true, 'access' => 'staff'], JSON_THROW_ON_ERROR));

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();

    expect(array_column($repository->sections('guides/deploy'), 'key'))->toBe(['', 'guides']);
});

it('renders the switcher on a page and marks the current sidebar', function (): void {
    $this->artisan('vellum:build')->assertSuccessful();

    $html = (string) $this->get('/docs/guides/deploy')->assertOk()->getContent();

    expect($html)->toContain('data-vellum-sections')
        ->toMatch('/href="\/docs\/guides"[^>]*aria-current="true"/')
        ->toContain('Handbook')
        ->toContain('Internals');
});

it('reports a root on a folder that is not at the top, and fails under --strict', function (): void {
    $this->writeDoc('guides/advanced/meta.json', json_encode(['root' => true], JSON_THROW_ON_ERROR));
    $this->writeDoc('guides/advanced/tuning.md', "---\ntitle: Tuning\n---\nFaster.");

    $this->artisan('vellum:build')
        ->expectsOutputToContain('Folder "guides/advanced" sets root in its meta.json')
        ->assertSuccessful();

    $this->artisan('vellum:build --strict')
        ->expectsOutputToContain('1 misplaced root')
        ->assertFailed();

    $repository = ContentRepository::fromConfig();

    // It stays an ordinary folder inside its root.
    expect(array_column($repository->sidebar('guides/deploy'), 'title'))->toBe(['Guides', 'Deploy', 'Advanced'])
        ->and(array_column($repository->sections('guides/advanced/tuning'), 'key'))->toBe(['', 'guides', 'api-docs']);
});

it('gives each version its own roots', function (): void {
    foreach (['v2', 'v1'] as $version) {
        $this->writeDoc("{$version}/index.md", "---\ntitle: Home {$version}\n---\nHi");
    }

    $this->writeDoc('v2/guides/meta.json', json_encode(['title' => 'Guides', 'root' => true], JSON_THROW_ON_ERROR));
    $this->writeDoc('v2/guides/deploy.md', "---\ntitle: Deploy\n---\nShip it.");
    config()->set('vellum.versions', ['enabled' => true, 'latest' => 'v2', 'list' => ['v2', 'v1']]);

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();

    expect(array_column($repository->sections('guides/deploy', 'v2'), 'key'))->toBe(['', 'guides'])
        ->and($repository->sections('', 'v1'))->toBe([])
        // v1 has no guides, so its switcher entry is v1's start page.
        ->and($repository->versionSwitcherData('guides/deploy', 'v2')['versionHrefs']['v1'])->toBe('/docs/v1');
});

it('lists every page of every root in the sitemap, llms.txt and the export', function (): void {
    $this->artisan('vellum:build')->assertSuccessful();

    $sitemap = (string) $this->get('/docs/sitemap.xml')->getContent();
    $llms = (string) $this->get('/docs/llms.txt')->getContent();

    foreach (['install', 'guides', 'guides/deploy', 'api-docs'] as $page) {
        expect($sitemap)->toContain("/docs/{$page}</loc>")
            ->and($llms)->toContain("/docs/_vellum/raw/{$page}.md");
    }

    $out = $this->cachePath().'/export';
    config()->set('vellum.export.out', $out);
    $this->artisan('vellum:export')->assertSuccessful();

    foreach (['install', 'guides', 'guides/deploy', 'api-docs'] as $page) {
        expect(is_file("{$out}/docs/{$page}/index.html"))->toBeTrue();
    }
});
