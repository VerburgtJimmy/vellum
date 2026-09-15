<?php

declare(strict_types=1);

use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;

beforeEach(function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->outsideFile = dirname($this->docsPath()).'/outside-'.$this->fixtureId().'.md';
    file_put_contents($this->outsideFile, "---\ntitle: Outside\n---\nSECRETBODY");

    $this->escapedCompiled = dirname($this->cachePath()).'/outside-'.$this->fixtureId().'.php';

    if (is_file($this->escapedCompiled)) {
        unlink($this->escapedCompiled);
    }
});

afterEach(function (): void {
    if (is_file($this->escapedCompiled)) {
        unlink($this->escapedCompiled);
    }

    if (is_file($this->outsideFile)) {
        unlink($this->outsideFile);
    }
});

$traversals = [
    'literal' => '../outside-%s',
    'encoded slash' => '..%%2Foutside-%s',
    'encoded dots' => '%%2e%%2e/outside-%s',
    'fully encoded' => '%%2e%%2e%%2Foutside-%s',
    'nested' => 'guides/../../outside-%s',
    'trailing' => 'guides/../../outside-%s/..',
];

it('refuses to serve a document outside the content root', function (string $template): void {
    $slug = sprintf($template, $this->fixtureId());

    $this->get('/docs/'.$slug)->assertNotFound();
})->with($traversals);

it('refuses to serve raw markdown outside the content root', function (string $template): void {
    $slug = sprintf($template, $this->fixtureId());

    $this->get('/docs/_vellum/raw/'.$slug.'.md')->assertNotFound();
})->with($traversals);

it('never writes a compiled file outside the cache directory', function (): void {
    $slug = '../outside-'.$this->fixtureId();

    $this->get('/docs/'.$slug)->assertNotFound();

    expect(is_file($this->escapedCompiled))->toBeFalse();

});

it('does not leak the file body of a traversal target', function (): void {
    $response = $this->get('/docs/..%2Foutside-'.$this->fixtureId());

    expect($response->getContent())->not->toContain('SECRETBODY');
});

it('still resolves ordinary nested slugs', function (): void {
    $this->writeDoc('guides/deploy.md', "---\ntitle: Deploy\n---\nShip it");

    $this->get('/docs/guides/deploy')->assertOk()->assertSee('Ship it', false);
});

it('refuses an unsafe slug in the repository', function (string $slug): void {
    expect(ContentRepository::fromConfig()->find($slug))->toBeNull();
})->with([
    '../outside',
    'a/../../outside',
    './index',
    'guides//deploy',
    "index\0.md",
]);

it('refuses to build a compiled path for an unsafe slug', function (): void {
    $store = new CompiledStore($this->cachePath());

    expect(fn () => $store->pathFor('../escape'))
        ->toThrow(InvalidArgumentException::class);
});

it('still builds a compiled path for the index and nested slugs', function (): void {
    $store = new CompiledStore($this->cachePath());

    expect($store->pathFor(''))->toEndWith('index.php')
        ->and($store->pathFor('guides/deploy'))->toEndWith('guides'.DIRECTORY_SEPARATOR.'deploy.php');
});
