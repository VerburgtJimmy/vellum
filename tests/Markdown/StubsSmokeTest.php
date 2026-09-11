<?php

declare(strict_types=1);

use Vellum\Markdown\MarkdownRenderer;

it('renders every stub markdown file without exceptions', function (): void {
    $stubs = dirname(__DIR__, 2).'/resources/stubs';
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($stubs, FilesystemIterator::SKIP_DOTS),
    );

    $renderer = new MarkdownRenderer(contentPath: $stubs);
    $count = 0;

    foreach ($files as $file) {
        /** @var SplFileInfo $file */
        if (! $file->isFile() || $file->getExtension() !== 'md') {
            continue;
        }

        $markdown = file_get_contents($file->getPathname());
        expect($markdown)->not->toBeFalse();

        $html = $renderer->render((string) $markdown);

        expect($html)->toBeString()->not->toBe('');
        $count++;
    }

    expect($count)->toBeGreaterThan(0);
});
