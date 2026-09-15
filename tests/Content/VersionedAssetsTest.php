<?php

declare(strict_types=1);

use Vellum\Content\ContentRepository;

beforeEach(function (): void {
    config()->set('vellum.versions.enabled', true);
    config()->set('vellum.versions.latest', 'v2');
    config()->set('vellum.versions.list', ['v2', 'v1']);

    $this->writeDoc('v2/index.md', "---\ntitle: Home v2\n---\nHi");
    $this->writeDoc('v1/index.md', "---\ntitle: Home v1\n---\nHi");
    $this->writeDoc('v2/assets/logo.svg', '<svg width="24" height="24"></svg>');
    $this->writeDoc('v1/assets/logo.svg', '<svg width="12" height="12"></svg>');
});

it('resolves an image against the version folder it lives in', function (): void {
    $this->writeDoc('v2/guide.md', "---\ntitle: Guide\n---\n![Logo](assets/logo.svg)");

    $document = ContentRepository::fromConfig()->find('guide', 'v2');

    expect($document)->not->toBeNull()
        ->and($document->html)->toContain('/docs/_vellum/files/v2/assets/logo.svg');
});

it('keeps each version pointing at its own copy of an asset', function (): void {
    $this->writeDoc('v2/guide.md', "---\ntitle: Guide\n---\n![Logo](assets/logo.svg)");
    $this->writeDoc('v1/guide.md', "---\ntitle: Guide\n---\n![Logo](assets/logo.svg)");

    $repository = ContentRepository::fromConfig();

    $v2 = $repository->find('guide', 'v2');
    $v1 = $repository->find('guide', 'v1');

    expect($v2->html)->toContain('/docs/_vellum/files/v2/assets/logo.svg')
        ->and($v1->html)->toContain('/docs/_vellum/files/v1/assets/logo.svg');
});

it('reads dimensions from the versioned copy of an image', function (): void {
    $this->writeDoc('v2/guide.md', "---\ntitle: Guide\n---\n![Logo](assets/logo.svg)");
    $this->writeDoc('v1/guide.md', "---\ntitle: Guide\n---\n![Logo](assets/logo.svg)");

    $repository = ContentRepository::fromConfig();

    expect($repository->find('guide', 'v2')->html)->toContain('width="24"')
        ->and($repository->find('guide', 'v1')->html)->toContain('width="12"');
});

it('serves the versioned asset over the content file route', function (): void {
    $this->writeDoc('v2/guide.md', "---\ntitle: Guide\n---\n![Logo](assets/logo.svg)");

    ContentRepository::fromConfig()->find('guide', 'v2');

    $this->get('/docs/_vellum/files/v2/assets/logo.svg')->assertOk();
});

it('leaves absolute and remote image urls alone', function (): void {
    $this->writeDoc('v2/remote.md', "---\ntitle: Remote\n---\n![A](https://example.com/a.png)\n\n![B](data:image/gif;base64,R0lGOD)");

    $html = ContentRepository::fromConfig()->find('remote', 'v2')->html;

    expect($html)->toContain('https://example.com/a.png')
        ->and($html)->toContain('data:image/gif;base64,R0lGOD')
        ->and($html)->not->toContain('_vellum/files/v2/https');
});
