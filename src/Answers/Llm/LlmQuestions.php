<?php

declare(strict_types=1);

namespace Vellum\Answers\Llm;

use RuntimeException;
use Vellum\Answers\AnswerIndex;

/**
 * Build-time question expansion. Questions are cached under the docs directory
 * by the hash of the section they came from, so the file can be committed and
 * a build (or CI) without a key uses what is already there. Nothing here runs
 * when a page is served.
 */
final class LlmQuestions
{
    public const DIRECTORY = '.vellum/questions';

    /**
     * Stop calling after this many failures in a row. A bad key or a rate limit
     * fails for every section, and there is no point paying for all of them.
     */
    public const GIVE_UP_AFTER = 5;

    /** @var array<string, true> */
    private array $used = [];

    public function __construct(
        private readonly string $cacheDirectory,
        private readonly ?QuestionWriter $writer,
        private readonly string $site,
    ) {}

    /**
     * Null when no provider is configured, which is the default.
     */
    public static function fromConfig(): ?self
    {
        $provider = (string) config('vellum.answers.llm.provider', '');

        if (trim($provider) === '') {
            return null;
        }

        $key = (string) config('vellum.answers.llm.key', '');
        $model = (string) config('vellum.answers.llm.model', '');
        $writer = match ($provider) {
            'anthropic' => $key === '' ? null : new AnthropicWriter($key, $model === '' ? null : $model),
            'openai' => $key === '' ? null : new OpenAiWriter($key, $model !== '' ? $model : throw new RuntimeException('answers.llm.model has no default for the openai provider. Name a model.')),
            default => throw new RuntimeException("answers.llm.provider is {$provider}; it is anthropic, openai, or null."),
        };

        return new self(
            rtrim((string) config('vellum.path'), '/').'/'.self::DIRECTORY,
            $writer,
            (string) config('vellum.name', 'the documentation'),
        );
    }

    public function hasWriter(): bool
    {
        return $this->writer !== null;
    }

    /**
     * @return array{questions: array<string, list<string>>, cached: int, written: int, missing: int, failures: list<string>, stopped: bool}
     */
    public function for(AnswerIndex $index): array
    {
        $questions = [];
        $cached = 0;
        $written = 0;
        $missing = 0;
        $failures = [];
        $consecutive = 0;

        foreach ($index->sections as $record) {
            $key = self::key($record);
            $this->used[$key] = true;
            $stored = $this->read($key);

            if ($stored !== null) {
                $questions[$record['id']] = $stored;
                $cached++;

                continue;
            }

            if ($this->writer === null || $consecutive >= self::GIVE_UP_AFTER) {
                $missing++;

                continue;
            }

            try {
                $written++;
                $generated = $this->writer->questions(Prompt::for($this->site, $record['title'], $record['heading'], $record['text']));
                $this->write($key, $record['id'], $generated);
                $questions[$record['id']] = $generated;
                $consecutive = 0;
            } catch (RuntimeException $exception) {
                $written--;
                $consecutive++;
                $failures[] = $record['id'].': '.$exception->getMessage();
            }
        }

        return [
            'questions' => $questions,
            'cached' => $cached,
            'written' => $written,
            'missing' => $missing,
            'failures' => $failures,
            'stopped' => $consecutive >= self::GIVE_UP_AFTER,
        ];
    }

    /**
     * Drop cached questions for sections that no longer exist. Only safe after
     * a build of every version.
     */
    public function prune(): int
    {
        $removed = 0;

        foreach (glob($this->cacheDirectory.'/*.json') ?: [] as $file) {
            if (! isset($this->used[basename($file, '.json')])) {
                unlink($file);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * @param  array{title: string, heading: string, text: string}  $record
     */
    private static function key(array $record): string
    {
        return hash('xxh128', implode("\0", [Prompt::VERSION, $record['title'], $record['heading'], $record['text']]));
    }

    /**
     * @return list<string>|null
     */
    private function read(string $key): ?array
    {
        $path = $this->cacheDirectory.'/'.$key.'.json';
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($data) || ! is_array($data['questions'] ?? null)) {
            return null;
        }

        $questions = [];

        foreach ($data['questions'] as $question) {
            if (is_string($question) && trim($question) !== '') {
                $questions[] = trim($question);
            }
        }

        return $questions;
    }

    /**
     * @param  list<string>  $questions
     */
    private function write(string $key, string $id, array $questions): void
    {
        if (! is_dir($this->cacheDirectory)) {
            mkdir($this->cacheDirectory, 0755, true);
        }

        file_put_contents($this->cacheDirectory.'/'.$key.'.json', json_encode([
            'section' => $id,
            'model' => $this->writer?->model(),
            'questions' => $questions,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }
}
