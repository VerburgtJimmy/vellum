<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
});

it('serves an asset from the content directory', function (): void {
    $this->writeDoc('assets/logo.svg', '<svg width="10" height="10"></svg>');

    $this->get('/docs/_vellum/files/assets/logo.svg')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=86400, must-revalidate, public');
});

it('does not serve the asset route with an immutable cache header', function (): void {
    $this->writeDoc('assets/logo.svg', '<svg width="10" height="10"></svg>');

    $response = $this->get('/docs/_vellum/files/assets/logo.svg')->assertOk();

    expect($response->headers->get('Cache-Control'))->not->toContain('immutable');
});

it('refuses to serve content files that are not assets', function (string $path, string $contents): void {
    $this->writeDoc($path, $contents);

    $this->get('/docs/_vellum/files/'.$path)->assertNotFound();
})->with([
    'gated markdown' => ['secret.md', "---\ntitle: Secret\naccess: auth\n---\nTOPSECRET"],
    'public markdown' => ['guides/deploy.md', "---\ntitle: Deploy\n---\nShip"],
    'alternate markdown extension' => ['notes.markdown', '# Notes'],
    'folder meta' => ['billing/meta.json', '{"access":"auth"}'],
    'folder meta markdown' => ['billing/_meta.md', "---\naccess: auth\n---"],
    'dotfile' => ['.env', 'APP_KEY=secret'],
    'dotfile in a folder' => ['billing/.env', 'APP_KEY=secret'],
    'file in a dot folder' => ['.git/config', '[core]'],
    'extensionless file' => ['Makefile', 'all:'],
]);

it('does not leak a gated page body through the asset route', function (): void {
    $this->writeDoc('secret.md', "---\ntitle: Secret\naccess: auth\n---\nTOPSECRET");

    $response = $this->get('/docs/_vellum/files/secret.md');

    expect($response->getContent())->not->toContain('TOPSECRET');
});

it('still refuses traversal through the asset route', function (): void {
    $this->get('/docs/_vellum/files/..%2F..%2Fcomposer.json')->assertNotFound();
});
