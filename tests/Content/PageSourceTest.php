<?php

declare(strict_types=1);

use Vellum\Answers\AnswerIndex;
use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;
use Vellum\Content\PageSource;
use Vellum\Content\PageSourceFactory;
use Vellum\CoreServiceProvider;
use Vellum\Site;

/**
 * A source of two generated pages under /guides. Takes pages away when told
 * to, the way a spec loses an endpoint.
 */
final class FakeGuides implements PageSource
{
    /** @var list<string> */
    public array $slugs = ['guides/first', 'guides/second'];

    public bool $separate = false;

    public function name(): string
    {
        return 'guides';
    }

    public function signature(): ?string
    {
        return implode(',', $this->slugs);
    }

    public function documents(?string $version, Closure $href): array
    {
        return array_map(fn (string $slug): Document => new Document(
            slug: $slug,
            title: ucfirst(basename($slug)).' guide',
            html: '<p>The '.basename($slug).' guide explains harbour pilotage.</p>',
            headings: [],
            frontmatter: ['access' => 'guest', 'guides_signature' => $this->signature()],
            path: '/generated/'.$slug,
            mtime: 1,
            version: $version,
            markdown: 'The '.basename($slug).' guide explains harbour pilotage.',
        ), $this->slugs);
    }

    public function owns(string $slug): bool
    {
        return str_starts_with(trim($slug, '/'), 'guides/');
    }

    public function isCurrent(Document $document): bool
    {
        return ($document->frontmatter['guides_signature'] ?? null) === $this->signature();
    }

    public function navigation(?string $version, Closure $href): ?array
    {
        return [
            'type' => 'folder',
            'title' => 'Guides',
            'icon' => null,
            'defaultOpen' => false,
            'children' => array_map(static fn (Document $document): array => [
                'type' => 'page',
                'slug' => $document->slug,
                'title' => $document->title,
                'description' => null,
                'icon' => null,
                'href' => $href($document->slug),
                'access' => 'guest',
                'updated' => null,
            ], $this->documents($version, $href)),
        ];
    }

    public function separate(): bool
    {
        return $this->separate;
    }

    public function warnings(): array
    {
        return [];
    }
}

beforeEach(function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nWelcome.");
    $this->guides = new FakeGuides;
    $this->repository = fn (bool $isLocal = false): ContentRepository => new ContentRepository(
        site: $this->site(isLocal: $isLocal),
        store: new CompiledStore($this->cachePath()),
        sources: [$this->guides],
    );
});

it('puts a source\'s pages in the sidebar, the compiled store and search', function (): void {
    $repository = ($this->repository)();
    $documents = $repository->buildAll();

    $folder = collect($repository->navigation())->firstWhere('title', 'Guides');

    expect(array_column($folder['children'], 'href'))->toBe(['/docs/guides/first', '/docs/guides/second'])
        ->and($folder['section'])->toBe('guides')
        ->and($repository->store()->exists('guides/first'))->toBeTrue()
        ->and($repository->find('guides/second')?->title)->toBe('Second guide');

    $pages = array_column(AnswerIndex::build($documents, $repository)->sections, 'url');

    expect($pages)->toContain('/docs/guides/first')->toContain('/docs/guides/second');
});

it('prunes a page once its source stops returning it', function (): void {
    ($this->repository)()->buildAll();
    $this->guides->slugs = ['guides/first'];

    $repository = ($this->repository)();
    $repository->buildAll();

    expect($repository->store()->exists('guides/second'))->toBeFalse()
        ->and($repository->find('guides/second'))->toBeNull()
        ->and(array_column(collect($repository->navigation())->firstWhere('title', 'Guides')['children'], 'slug'))->toBe(['guides/first']);
});

it('stops serving a removed page locally without a build', function (): void {
    ($this->repository)(isLocal: true)->buildAll();
    $this->guides->slugs = ['guides/first'];

    expect(($this->repository)(isLocal: true)->find('guides/second'))->toBeNull()
        ->and(($this->repository)(isLocal: true)->find('guides/first')?->title)->toBe('First guide');
});

it('gives a source that asks for it a sidebar of its own', function (): void {
    $this->guides->separate = true;
    $repository = ($this->repository)();
    $repository->buildAll();

    expect(array_column($repository->sidebar('guides/first'), 'slug'))->toBe(['guides/first', 'guides/second'])
        ->and(array_column($repository->sidebar(''), 'title'))->toBe(['Home'])
        ->and(array_column($repository->sections('guides/first'), 'current', 'key'))->toBe(['docs' => false, 'guides' => true]);
});

it('reads a manifest written before sources had names, and rebuilds', function (): void {
    $repository = ($this->repository)(isLocal: true);
    $repository->buildAll();
    $store = $repository->store();
    $manifest = $store->getManifest();

    $store->putManifest(['directory_hash' => $manifest['directory_hash'], 'version' => null, 'complete' => true, 'openapi' => 'file:/old.json:1:2']);

    expect($store->getManifest()['sources'])->toBe([])
        ->and(array_column(collect(($this->repository)(isLocal: true)->navigation())->firstWhere('title', 'Guides')['children'], 'slug'))
        ->toBe(['guides/first', 'guides/second'])
        ->and($store->getManifest()['sources'])->toBe(['guides' => 'guides/first,guides/second']);
});

it('collects sources from factories tagged in the container', function (): void {
    $guides = $this->guides;

    app()->instance('test.guides', new class($guides) implements PageSourceFactory
    {
        public function __construct(private readonly FakeGuides $guides) {}

        public function forSite(Site $site): ?PageSource
        {
            return $this->guides;
        }
    });
    app()->tag(['test.guides'], 'vellum.page-sources');

    $repository = CoreServiceProvider::repository(app(), $this->site());
    $repository->buildAll();

    expect($repository->find('guides/first')?->title)->toBe('First guide');
});
