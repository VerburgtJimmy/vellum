<?php

declare(strict_types=1);

/*
 * Core reads config in one place: the service provider builds a Vellum\Site
 * from it and hands that down. A class that read config on its own would
 * tie itself to the one site an app's config describes, so a test or a
 * second site could not build it without changing global config.
 */

it('reads config only in the service provider and the path resolver', function (): void {
    $root = dirname(__DIR__, 2).'/packages/core/src';
    $allowed = ['CoreServiceProvider.php', 'Support/Paths.php'];
    $reads = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        $relative = substr($file->getPathname(), strlen($root) + 1);

        if ($file->getExtension() !== 'php' || in_array($relative, $allowed, true)) {
            continue;
        }

        // Code only: a comment may say where a value comes from.
        $code = '';

        foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        foreach (explode("\n", $code) as $number => $line) {
            if (preg_match('/(?<![\w>$:])config\s*\(|\bConfig::/', $line) === 1) {
                $reads[] = $relative.':'.($number + 1).' '.trim($line);
            }
        }
    }

    expect($reads)->toBe([]);
});
