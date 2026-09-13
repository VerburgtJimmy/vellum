<?php

declare(strict_types=1);

use Vellum\Cache\CompiledStore;
use Vellum\Content\Access;
use Vellum\Content\ContentRepository;

it('inherits folder access from meta.json until a page overrides it', function (): void {
    $this->writeDoc('meta.json', json_encode(['access' => 'auth', 'pages' => ['index', 'billing']], JSON_THROW_ON_ERROR));
    $this->writeDoc('index.md', "---\ntitle: Home\naccess: guest\n---\nHi");
    $this->writeDoc('billing/meta.json', json_encode(['access' => 'auth'], JSON_THROW_ON_ERROR));
    $this->writeDoc('billing/invoices.md', "---\ntitle: Invoices\n---\nSecret");
    $this->writeDoc('public.md', "---\ntitle: Public\naccess: guest\n---\nOpen");

    $repository = new ContentRepository(
        contentPath: $this->docsPath(),
        store: new CompiledStore($this->cachePath()),
    );
    $documents = $repository->buildAll();
    $bySlug = collect($documents)->keyBy(fn ($document) => $document->slug);

    expect($bySlug['']->access())->toBe('guest')
        ->and($bySlug['public']->access())->toBe('guest')
        ->and($bySlug['billing/invoices']->access())->toBe('auth');
});

it('inherits folder access from _meta.md', function (): void {
    $this->writeDoc('billing/_meta.md', "---\naccess: auth\n---\n");
    $this->writeDoc('billing/invoices.md', "---\ntitle: Invoices\n---\nSecret");

    $access = (new Access)->forPage(
        [],
        $this->docsPath().'/billing/invoices.md',
        $this->docsPath(),
    );

    expect($access)->toBe('auth');
});

it('prefers _meta.md over meta.json in the same folder', function (): void {
    $this->writeDoc('billing/meta.json', json_encode(['access' => 'auth'], JSON_THROW_ON_ERROR));
    $this->writeDoc('billing/_meta.md', "---\naccess: guest\n---\n");
    $this->writeDoc('billing/invoices.md', "---\ntitle: Invoices\n---\nOpen");

    $access = (new Access)->forPage(
        [],
        $this->docsPath().'/billing/invoices.md',
        $this->docsPath(),
    );

    expect($access)->toBe('guest');
});
