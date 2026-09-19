<?php

declare(strict_types=1);

namespace Vellum\Answers;

use RuntimeException;
use Vellum\Semantic\FileVectorCache;
use Vellum\Semantic\ModelDownloader;
use Vellum\Semantic\Pruner;
use Vellum\Semantic\Quantizer;
use Vellum\Semantic\SafetensorsTable;
use Vellum\Semantic\SemanticBuilder;
use Vellum\Semantic\SemanticSet;
use Vellum\Semantic\WordPieceTokenizer;

/**
 * Builds the semantic set for one docs version from its answer index, with
 * every section tagged by the access level that may see it.
 */
final class SemanticIndexer
{
    public const VOCAB_BITS = 4;

    public const SECTION_BITS = 8;

    public function __construct(
        private readonly string $modelDirectory,
        private readonly string $cacheDirectory,
        private readonly int $commonTokens = 1000,
    ) {}

    public static function fromConfig(string $cacheDirectory): self
    {
        $model = (string) config('vellum.answers.model', 'potion-base-8M');
        $root = (string) config('vellum.answers.model_path', storage_path('vellum/models'));

        return new self(rtrim($root, '/').'/'.basename($model), $cacheDirectory, (int) config('vellum.answers.common_tokens', 1000));
    }

    public function modelDirectory(): string
    {
        return $this->modelDirectory;
    }

    public function hasModel(): bool
    {
        return is_file($this->modelDirectory.'/tokenizer.json') && is_file($this->modelDirectory.'/model.safetensors');
    }

    /**
     * @return array{set: SemanticSet, sections: int, encoded: int}
     */
    public function build(AnswerIndex $index): array
    {
        if (! $this->hasModel()) {
            throw new RuntimeException("No model in {$this->modelDirectory}. Run php artisan vellum:model.");
        }

        $inputs = array_map(static fn (array $record): array => [
            'id' => $record['id'],
            'text' => AnswerIndex::text($record),
            'group' => $record['access'],
        ], $index->sections);

        $builder = new SemanticBuilder(
            WordPieceTokenizer::fromTokenizerJson($this->modelDirectory.'/tokenizer.json'),
            new SafetensorsTable($this->modelDirectory.'/model.safetensors'),
            new Pruner($this->commonTokens),
            new Quantizer(self::VOCAB_BITS),
            new Quantizer(self::SECTION_BITS),
            new FileVectorCache($this->cacheDirectory.'/vectors.ser'),
            basename($this->modelDirectory),
            $this->attribution(),
        );

        $set = $builder->build($inputs);
        $set->save($this->cacheDirectory);

        return ['set' => $set, 'sections' => count($inputs), 'encoded' => $builder->encoded];
    }

    private function attribution(): string
    {
        $path = $this->modelDirectory.'/'.ModelDownloader::MANIFEST;
        $manifest = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $repository = is_array($manifest) ? (string) ($manifest['repository'] ?? '') : '';
        $licence = is_array($manifest) ? (string) ($manifest['licence'] ?? '') : '';
        $name = $repository !== '' ? $repository : basename($this->modelDirectory);

        return sprintf(
            'Vocabulary vectors derived from %s%s, https://huggingface.co/%s, pruned and quantised by Vellum.',
            $name,
            $licence !== '' ? ' ('.strtoupper($licence).' licence)' : '',
            str_contains($name, '/') ? $name : 'minishlab/'.$name,
        );
    }
}
