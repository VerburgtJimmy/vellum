<?php

declare(strict_types=1);

/*
 * jimmyverburgt/vellum-core and jimmyverburgt/vellum-openapi ship on their
 * own, so nothing under packages/ may lean on the site around it: no class
 * from src/, and none of the site's routes, views or scripts. Core comes
 * before the API reference, so it uses nothing of that either.
 */

$files = static function (string $directory, string $suffix): array {
    $found = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (str_ends_with($file->getPathname(), $suffix)) {
            $found[] = $file->getPathname();
        }
    }

    sort($found);

    return $found;
};

$root = dirname(__DIR__, 2);

$packaged = static fn (): array => [
    ...$files($root.'/packages/core/src', '.php'),
    ...$files($root.'/packages/core/resources', '.php'),
    ...$files($root.'/packages/openapi/src', '.php'),
    ...$files($root.'/packages/openapi/resources', '.php'),
];

it('uses no class of the full package', function () use ($files, $root, $packaged): void {
    $site = array_map(
        static fn (string $path): string => 'Vellum\\'.str_replace('/', '\\', substr($path, strlen($root.'/src/'), -4)),
        $files($root.'/src', '.php'),
    );

    $leaks = [];

    foreach ($packaged() as $path) {
        $source = (string) file_get_contents($path);

        foreach ($site as $class) {
            if (preg_match('/\b'.preg_quote($class, '/').'\b/', $source) === 1) {
                $leaks[] = substr($path, strlen($root) + 1).' uses '.$class;
            }
        }
    }

    expect($leaks)->toBe([]);
});

it('names none of the full package\'s routes, views or scripts', function () use ($root, $packaged): void {
    $patterns = [
        'a vellum route' => "/route\\(\\s*'vellum\\./",
        'a layout, page or chrome view' => '/vellum::(layouts|pages|components\.(docs|ui))\b/',
        'an Alpine directive' => '/\b(x-data|x-show|x-cloak|x-on:|@click)\b/',
    ];

    $leaks = [];

    foreach ($packaged() as $path) {
        $source = (string) file_get_contents($path);

        foreach ($patterns as $what => $pattern) {
            if (preg_match($pattern, $source) === 1) {
                $leaks[] = substr($path, strlen($root) + 1).' names '.$what;
            }
        }
    }

    expect($leaks)->toBe([]);
});

it('keeps core free of the API reference', function () use ($files, $root): void {
    $leaks = [];

    foreach ([...$files($root.'/packages/core/src', '.php'), ...$files($root.'/packages/core/resources', '.php')] as $path) {
        if (preg_match('/Vellum\\\\OpenApi\\\\|vellum::openapi\./', (string) file_get_contents($path)) === 1) {
            $leaks[] = substr($path, strlen($root) + 1);
        }
    }

    expect($leaks)->toBe([]);
});

it('requires everything it imports', function () use ($root): void {
    $core = json_decode((string) file_get_contents($root.'/packages/core/composer.json'), true);
    $openApi = json_decode((string) file_get_contents($root.'/packages/openapi/composer.json'), true);
    $full = json_decode((string) file_get_contents($root.'/composer.json'), true);

    expect(array_diff_key($core['require'], $full['require']))->toBe([], 'The full package must require whatever core does.')
        ->and(array_diff_key($openApi['require'], $full['require'], ['jimmyverburgt/vellum-core' => true]))->toBe([], 'The full package must require whatever the API reference does.')
        ->and($openApi['require']['jimmyverburgt/vellum-core'])->toBe('self.version')
        ->and($full['replace'])->toBe(['jimmyverburgt/vellum-core' => 'self.version', 'jimmyverburgt/vellum-openapi' => 'self.version'])
        ->and($full['autoload']['psr-4']['Vellum\\'])->toContain('packages/core/src/')
        ->and($full['autoload']['psr-4']['Vellum\\OpenApi\\'])->toBe('packages/openapi/src/');
});
