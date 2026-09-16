<?php

declare(strict_types=1);

$archive = static function (): array {
    $root = dirname(__DIR__);
    $command = 'cd '.escapeshellarg($root).' && git archive HEAD --format=tar --worktree-attributes 2>/dev/null | tar -t 2>/dev/null';
    exec($command, $lines, $status);

    return $status === 0 ? $lines : [];
};

it('keeps development material out of the released archive', function () use ($archive): void {
    $files = $archive();

    expect($files)->not->toBeEmpty('git archive produced nothing; is this a git checkout?');

    $unwanted = [
        'tests/',
        '.github/',
        'scripts/',
        'composer.lock',
        'package-lock.json',
        'phpstan.neon',
        'phpunit.xml',
        'pint.json',
        'testbench.yaml',
        'vite.config.js',
        'docs/screenshots/',
        'CONTRIBUTING.md',
    ];

    $shipped = [];

    foreach ($unwanted as $path) {
        foreach ($files as $entry) {
            if (str_starts_with($entry, $path)) {
                $shipped[] = $path;
                break;
            }
        }
    }

    expect($shipped)->toBe([], 'Shipped into vendor/ but not needed: '.implode(', ', $shipped));
});

it('still ships everything the package needs at runtime', function () use ($archive): void {
    $files = $archive();

    expect($files)->not->toBeEmpty();

    $required = [
        'composer.json',
        'LICENSE.md',
        'config/vellum.php',
        'routes/web.php',
        'src/VellumServiceProvider.php',
        'resources/dist/vellum.css',
        'resources/dist/vellum.js',
        'resources/views/layouts/docs.blade.php',
        'resources/stubs/index.md',
        'docs/index.md',
    ];

    $missing = array_values(array_filter(
        $required,
        static fn (string $path): bool => ! in_array($path, $files, true),
    ));

    expect($missing)->toBe([], 'Missing from the released archive: '.implode(', ', $missing));
});
