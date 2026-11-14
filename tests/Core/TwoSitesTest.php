<?php

declare(strict_types=1);

use Vellum\Content\ContentRepository;
use Vellum\Site;
use Vellum\Tests\Core\BootsCoreOnly;

uses(BootsCoreOnly::class);

/*
 * Nothing in core reads config, so two sites can live in one process: each
 * repository builds, stores and links its pages from the site it was given.
 */

it('builds and renders two sites in one process without touching config', function (): void {
    $root = $this->cachePath().'/two-sites';
    $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
    $configBefore = config()->all();

    $make = static function (string $name, string $prefix) use ($root, $png): ContentRepository {
        $docs = "{$root}/{$name}/docs";
        mkdir($docs, 0755, true);
        file_put_contents("{$docs}/index.md", "---\ntitle: {$name} home\n---\nWelcome to {$name}.\n\n![Dot](dot.png)\n\n<x-vellum::callout type=\"note\">From {$name}</x-vellum::callout>\n");
        file_put_contents("{$docs}/guide.md", "---\ntitle: {$name} guide\n---\nThe guide.\n");
        file_put_contents("{$docs}/dot.png", $png);

        return new ContentRepository(Site::fromArray([
            'path' => $docs,
            'route' => ['prefix' => $prefix],
            'cache' => ['path' => "{$root}/{$name}/cache"],
        ]));
    };

    $handbook = $make('Handbook', 'handbook');
    $manual = $make('Manual', 'help/manual');

    $handbook->buildAll();
    $manual->buildAll();

    $handbookHome = $handbook->find('');
    $manualHome = $manual->find('');

    expect($handbookHome?->title)->toBe('Handbook home')
        ->and($manualHome?->title)->toBe('Manual home');

    $handbookHtml = $handbook->render($handbookHome);
    $manualHtml = $manual->render($manualHome);

    // Each page is its own site's, with links and files under its own prefix.
    expect($handbookHtml)->toContain('Welcome to Handbook.')
        ->toContain('From Handbook')
        ->toContain('src="/handbook/_vellum/files/dot.png"')
        ->not->toContain('Manual')
        ->and($manualHtml)->toContain('Welcome to Manual.')
        ->toContain('From Manual')
        ->toContain('src="/help/manual/_vellum/files/dot.png"')
        ->not->toContain('Handbook');

    expect(array_column($handbook->navigation(), 'href'))->toBe(['/handbook/guide', '/handbook'])
        ->and(array_column($manual->navigation(), 'href'))->toBe(['/help/manual/guide', '/help/manual'])
        ->and($handbook->adjacent('guide')['next']['href'] ?? null)->toBe('/handbook')
        ->and($manual->adjacent('guide')['next']['href'] ?? null)->toBe('/help/manual');

    // Each wrote to its own cache, and neither changed the app's config.
    expect(is_file("{$root}/Handbook/cache/nav.php"))->toBeTrue()
        ->and(is_file("{$root}/Manual/cache/nav.php"))->toBeTrue()
        ->and(config()->all())->toBe($configBefore);
});
