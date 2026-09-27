<?php

declare(strict_types=1);

/*
 * jimmyverburgt/vellum-core ships on its own, so nothing under packages/core
 * may lean on the site around it: no class from src/, and none of the site's
 * routes, views or scripts.
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

it('uses no class of the full package', function () use ($files, $root): void {
    $site = array_map(
        static fn (string $path): string => 'Vellum\\'.str_replace('/', '\\', substr($path, strlen($root.'/src/'), -4)),
        $files($root.'/src', '.php'),
    );

    $leaks = [];

    foreach ([...$files($root.'/packages/core/src', '.php'), ...$files($root.'/packages/core/resources', '.php')] as $path) {
        $source = (string) file_get_contents($path);

        foreach ($site as $class) {
            if (preg_match('/\b'.preg_quote($class, '/').'\b/', $source) === 1) {
                $leaks[] = substr($path, strlen($root) + 1).' uses '.$class;
            }
        }
    }

    expect($leaks)->toBe([]);
});

it('names none of the full package\'s routes, views or scripts', function () use ($files, $root): void {
    $patterns = [
        'a vellum route' => "/route\\(\\s*'vellum\\./",
        'a layout, page or chrome view' => '/vellum::(layouts|pages|components\.(docs|ui))\b/',
        'an Alpine directive' => '/\b(x-data|x-show|x-cloak|x-on:|@click)\b/',
    ];

    $leaks = [];

    foreach ([...$files($root.'/packages/core/src', '.php'), ...$files($root.'/packages/core/resources', '.php')] as $path) {
        $source = (string) file_get_contents($path);

        foreach ($patterns as $what => $pattern) {
            if (preg_match($pattern, $source) === 1) {
                $leaks[] = substr($path, strlen($root) + 1).' names '.$what;
            }
        }
    }

    expect($leaks)->toBe([]);
});

it('requires everything it imports', function () use ($root): void {
    $core = json_decode((string) file_get_contents($root.'/packages/core/composer.json'), true);
    $full = json_decode((string) file_get_contents($root.'/composer.json'), true);

    expect(array_diff_key($core['require'], $full['require']))->toBe([], 'The full package must require whatever core does.')
        ->and($full['replace'])->toBe(['jimmyverburgt/vellum-core' => 'self.version'])
        ->and($full['autoload']['psr-4']['Vellum\\'])->toContain('packages/core/src/');
});
