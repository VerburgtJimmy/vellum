<?php

declare(strict_types=1);

it('builds all documents via artisan', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('guides/one.md', "---\ntitle: One\n---\nOne");

    $this->artisan('vellum:build')
        ->expectsOutputToContain('Compiled 2 documents')
        ->assertSuccessful();

    expect(is_file($this->cachePath().'/index.php'))->toBeTrue()
        ->and(is_file($this->cachePath().'/guides/one.php'))->toBeTrue();
});

it('clears the cache via artisan', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->artisan('vellum:build')->assertSuccessful();
    expect(is_file($this->cachePath().'/index.php'))->toBeTrue();

    $this->artisan('vellum:clear')
        ->expectsOutputToContain('Vellum cache cleared')
        ->assertSuccessful();

    expect(is_file($this->cachePath().'/index.php'))->toBeFalse();
});
