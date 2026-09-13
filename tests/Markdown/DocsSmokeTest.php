<?php

declare(strict_types=1);

use Vellum\Markdown\Islands\MarkdownPipeline;

it('renders every package doc without exceptions', function (): void {
    $docs = dirname(__DIR__, 2).'/docs';
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($docs, FilesystemIterator::SKIP_DOTS),
    );

    $pipeline = new MarkdownPipeline;
    $count = 0;

    foreach ($files as $file) {
        /** @var SplFileInfo $file */
        if (! $file->isFile() || $file->getExtension() !== 'md') {
            continue;
        }

        $markdown = file_get_contents($file->getPathname());
        expect($markdown)->not->toBeFalse();

        $html = $pipeline->render((string) $markdown);

        expect($html)->toBeString()->not->toBe('')
            ->and($html)->not->toContain('VELLUMISLAND');
        $count++;
    }

    expect($count)->toBeGreaterThan(0);
});
