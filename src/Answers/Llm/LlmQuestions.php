<?php

declare(strict_types=1);

namespace Vellum\Answers\Llm;

use Closure;
use RuntimeException;
use Vellum\Answers\AnswerIndex;

/**
 * Build-time question expansion. Questions are cached under the docs directory
 * by the hash of the section they came from, so the file can be committed and
 * a build (or CI) without a key uses what is already there. Nothing here runs
 * when a page is served.
 *
 * Sections are asked for in batches, with a retry and a wait on the failures
 * worth retrying: a rate limit or a server error is a pause, not a loss.
 */
final class LlmQuestions
{
    public const DIRECTORY = '.vellum/questions';

    /**
     * Requests in flight at once.
     */
    public const BATCH = 5;

    /**
     * Tries per section, including the first.
     */
    public const ATTEMPTS = 3;

    /**
     * Stop calling after this many failures in a row. A bad key fails for every
     * section, and there is no point paying for all of them.
     */
    public const GIVE_UP_AFTER = 5;

    /**
     * Report progress every this many sections.
     */
    public const PROGRESS_EVERY = 25;

    /** @var array<string, true> */
    private array $used = [];

    public function __construct(
        private readonly string $cacheDirectory,
        private readonly ?QuestionWriter $writer,
        private readonly string $site,
        private readonly Transport $transport = new Transport,
        private readonly ?Closure $sleeper = null,
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
     * @param  (Closure(int, int): void)|null  $onProgress  called with done and total
     * @return array{questions: array<string, list<string>>, cached: int, written: int, missing: int, failures: list<string>, stopped: bool}
     */
    public function for(AnswerIndex $index, ?Closure $onProgress = null): array
    {
        $questions = [];
        $cached = 0;
        $pending = [];

        foreach ($index->sections as $record) {
            $key = self::key($record);
            $this->used[$key] = true;
            $stored = $this->read($key);

            if ($stored !== null) {
                $questions[$record['id']] = $stored;
                $cached++;

                continue;
            }

            $pending[] = ['key' => $key, 'record' => $record];
        }

        $total = count($index->sections);
        $done = $cached;
        $written = 0;
        $missing = 0;
        $failures = [];
        $consecutive = 0;
        $reported = 0;

        foreach (array_chunk($pending, self::BATCH) as $batch) {
            if ($this->writer === null || $consecutive >= self::GIVE_UP_AFTER) {
                $missing += count($batch);
                $done += count($batch);

                continue;
            }

            foreach ($this->ask($batch) as $outcome) {
                $done++;

                if (is_array($outcome['questions'])) {
                    $this->write($outcome['key'], $outcome['id'], $outcome['questions']);
                    $questions[$outcome['id']] = $outcome['questions'];
                    $written++;
                    $consecutive = 0;

                    continue;
                }

                $failures[] = $outcome['id'].': '.$outcome['error'];
                $consecutive++;
            }

            if ($onProgress !== null && intdiv($done, self::PROGRESS_EVERY) > $reported) {
                $reported = intdiv($done, self::PROGRESS_EVERY);
                $onProgress($done, $total);
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
     * One batch, retried while requests fail in a way that is worth retrying.
     *
     * @param  list<array{key: string, record: array{id: string, title: string, heading: string, text: string}}>  $batch
     * @return list<array{key: string, id: string, questions: list<string>|null, error: string}>
     */
    private function ask(array $batch): array
    {
        $writer = $this->writer;

        if ($writer === null) {
            return [];
        }

        $outcomes = [];
        $left = $batch;

        for ($attempt = 1; $attempt <= self::ATTEMPTS && $left !== []; $attempt++) {
            $site = $this->site;
            $responses = $this->transport->postMany(array_map(
                static fn (array $item): array => $writer->request(Prompt::for($site, $item['record']['title'], $item['record']['heading'], $item['record']['text'])),
                $left,
            ));

            $retry = [];
            $wait = 0.0;

            foreach ($left as $index => $item) {
                $response = $responses[$index] ?? ['status' => 0, 'headers' => [], 'body' => []];

                try {
                    $outcomes[] = ['key' => $item['key'], 'id' => $item['record']['id'], 'questions' => $writer->parse($response), 'error' => ''];
                } catch (RuntimeException $exception) {
                    if ($attempt < self::ATTEMPTS && self::worthRetrying($response['status'])) {
                        $retry[] = $item;
                        $wait = max($wait, self::backoff($attempt, $response['headers']['retry-after'] ?? null));

                        continue;
                    }

                    $outcomes[] = ['key' => $item['key'], 'id' => $item['record']['id'], 'questions' => null, 'error' => $exception->getMessage()];
                }
            }

            $left = $retry;

            if ($left !== [] && $wait > 0.0) {
                ($this->sleeper ?? static fn (float $seconds) => usleep((int) round($seconds * 1_000_000)))($wait);
            }
        }

        return $outcomes;
    }

    /**
     * A rate limit, a server error, or no response at all. A 400 or a 401 will
     * fail again the same way.
     */
    private static function worthRetrying(int $status): bool
    {
        return $status === 0 || $status === 408 || $status === 429 || $status >= 500;
    }

    /**
     * The server's own Retry-After when it sent one, else 1, 2, 4 seconds.
     */
    private static function backoff(int $attempt, ?string $retryAfter): float
    {
        if ($retryAfter !== null && is_numeric($retryAfter)) {
            return min(60.0, max(0.0, (float) $retryAfter));
        }

        return min(60.0, 2 ** ($attempt - 1));
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
