<?php

declare(strict_types=1);

use Vellum\Answers\AnswerIndex;
use Vellum\Answers\SemanticIndexer;
use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;

/*
 * Holds the semantic build to the 0.7 budget on Vellum's own docs with the real
 * model. Skipped unless VELLUM_MODELS points at a directory holding
 * potion-base-8M (php artisan vellum:model --path=...).
 */
it('stays within the size and time budget on Vellum\'s own docs', function (): void {
    $repository = new ContentRepository(contentPath: (string) realpath(__DIR__.'/../../docs'), store: new CompiledStore($this->cachePath()));
    $documents = $repository->buildAll();
    $indexer = new SemanticIndexer(rtrim((string) getenv('VELLUM_MODELS'), '/').'/potion-base-8M', $this->cachePath().'/semantic');

    $started = microtime(true);
    $result = $indexer->build(AnswerIndex::build($documents, $repository));
    $seconds = microtime(true) - $started;
    $gzipped = strlen((string) gzencode($result['set']->forGroups(['guest']), 9));

    expect($result['encoded'])->toBe($result['sections'])
        ->and($gzipped)->toBeLessThanOrEqual(600 * 1024)
        ->and($seconds)->toBeLessThan(5.0);
})->skip(fn (): bool => ! is_file(rtrim((string) getenv('VELLUM_MODELS'), '/').'/potion-base-8M/model.safetensors'), 'Set VELLUM_MODELS to a directory holding potion-base-8M.');
