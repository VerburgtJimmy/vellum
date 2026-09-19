<?php

declare(strict_types=1);

namespace Vellum\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Vellum\Semantic\ModelDownloader;

/**
 * Fetches the static embedding model search answers are built from.
 */
final class ModelCommand extends Command
{
    protected $signature = 'vellum:model
        {--model= : Hugging Face model, owner/name or a minishlab name (default answers.model)}
        {--path= : Directory models are stored under (default answers.model_path)}
        {--force : Download again even when the files are already present}';

    protected $description = 'Download the static embedding model Vellum builds search answers from';

    public function handle(ModelDownloader $downloader): int
    {
        $model = (string) ($this->option('model') ?: config('vellum.answers.model', 'potion-base-8M'));
        $root = (string) ($this->option('path') ?: config('vellum.answers.model_path', storage_path('vellum/models')));
        $directory = rtrim($root, '/').'/'.basename($model);

        try {
            $result = $downloader->fetch($model, $directory, (bool) $this->option('force'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(($result['fetched'] ? 'Fetched ' : 'Already present: ').$result['repository']);
        $this->line('  into '.$result['directory']);
        $this->line('  licence: '.$result['licence']);

        foreach ($result['files'] as $file => $bytes) {
            $this->line(sprintf('  %s  %.1f MB', $file, $bytes / 1_048_576));
        }

        return self::SUCCESS;
    }
}
