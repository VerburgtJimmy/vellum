<?php

declare(strict_types=1);

use Vellum\Support\Paths;

it('reads relative config paths from the app root, not the working directory', function (): void {
    config()->set('vellum.path', 'docs');
    config()->set('vellum.changelog.path', 'CHANGELOG.md');
    config()->set('vellum.cache.path', '/var/cache/vellum');

    Paths::resolveConfig();

    expect(config('vellum.path'))->toBe(base_path('docs'))
        ->and(config('vellum.changelog.path'))->toBe(base_path('CHANGELOG.md'))
        ->and(config('vellum.cache.path'))->toBe('/var/cache/vellum')
        ->and(Paths::absolute('C:\\docs'))->toBe('C:\\docs');
});
