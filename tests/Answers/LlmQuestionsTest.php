<?php

declare(strict_types=1);

use Vellum\Answers\AnswerIndex;
use Vellum\Answers\Llm\AnthropicWriter;
use Vellum\Answers\Llm\LlmQuestions;
use Vellum\Answers\Llm\OpenAiWriter;
use Vellum\Answers\Llm\Transport;
use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;

/**
 * A Transport that answers from a queue and records what it was asked, batch
 * by batch.
 */
final class FakeTransport extends Transport
{
    /** @var list<array{url: string, headers: array<string, string>, body: array<string, mixed>}> */
    public array $requests = [];

    /** @var list<int> */
    public array $batches = [];

    /**
     * @param  list<array{status: int, headers?: array<string, string>, body: array<string, mixed>}>  $responses
     */
    public function __construct(private array $responses = []) {}

    public function postMany(array $requests): array
    {
        $this->batches[] = count($requests);
        $responses = [];

        foreach ($requests as $request) {
            $this->requests[] = $request;
            $response = array_shift($this->responses) ?? ['status' => 200, 'body' => []];
            $responses[] = ['headers' => [], ...$response];
        }

        return $responses;
    }
}

function anthropicAnswer(array $questions): array
{
    return ['status' => 200, 'body' => ['stop_reason' => 'end_turn', 'content' => [
        ['type' => 'thinking', 'thinking' => ''],
        ['type' => 'text', 'text' => json_encode(['questions' => $questions])],
    ]]];
}

/**
 * Sections enough to fill $count batches of LlmQuestions::BATCH.
 */
function pageOf(int $sections): string
{
    $markdown = "---\ntitle: Home\n---\nIntro.\n";

    for ($i = 1; $i < $sections; $i++) {
        $markdown .= "\n## Heading {$i}\n\nBody {$i}.\n";
    }

    return $markdown;
}

beforeEach(function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nVellum is a docs package.\n");
    config(['vellum.name' => 'Acme']);

    $this->index = function (): AnswerIndex {
        $repository = new ContentRepository(contentPath: $this->docsPath(), store: new CompiledStore($this->cachePath()));

        return AnswerIndex::build($repository->buildAll(), $repository);
    };
    $this->cacheDirectory = fn (): string => $this->docsPath().'/'.LlmQuestions::DIRECTORY;
});

it('asks Claude with a schema and keeps five questions', function (): void {
    $transport = new FakeTransport([anthropicAnswer(['One?', 'Two?', ' ', 'Three?', 'Four?', 'Five?', 'Six?'])]);
    $llm = new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('key-123'), 'Acme', $transport);

    $result = $llm->for(($this->index)());
    $request = $transport->requests[0];

    expect($request['url'])->toBe('https://api.anthropic.com/v1/messages')
        ->and($request['headers'])->toBe(['x-api-key' => 'key-123', 'anthropic-version' => '2023-06-01'])
        ->and($request['body']['model'])->toBe('claude-haiku-4-5')
        ->and($request['body'])->not->toHaveKey('fallbacks')
        ->and($request['body']['output_config'])->not->toHaveKey('effort')
        ->and($request['body']['output_config']['format']['type'])->toBe('json_schema')
        ->and($request['body']['messages'][0]['content'])->toContain('documentation for Acme')
        ->and($request['body']['messages'][0]['content'])->toContain('Vellum is a docs package.')
        ->and($result['questions']['#'])->toBe(['One?', 'Two?', 'Three?', 'Four?', 'Five?'])
        ->and($result)->toMatchArray(['cached' => 0, 'written' => 1, 'missing' => 0, 'failures' => [], 'stopped' => false]);
});

it('sends effort and fallbacks only to the models that take them', function (): void {
    $opus = new FakeTransport([anthropicAnswer(['Q?'])]);
    $haiku = new FakeTransport([anthropicAnswer(['Q?'])]);

    // Separate caches, or the second writer would read the first one's answer.
    (new LlmQuestions(($this->cacheDirectory)().'/opus', new AnthropicWriter('k', 'claude-opus-5'), 'A', $opus))->for(($this->index)());
    (new LlmQuestions(($this->cacheDirectory)().'/haiku', new AnthropicWriter('k', 'claude-haiku-4-5'), 'A', $haiku))->for(($this->index)());

    expect($opus->requests[0]['body']['fallbacks'])->toBe('default')
        ->and($opus->requests[0]['headers'])->toHaveKey('anthropic-beta')
        ->and($opus->requests[0]['body']['output_config']['effort'])->toBe('low')
        ->and($haiku->requests[0]['body'])->not->toHaveKey('fallbacks')
        ->and($haiku->requests[0]['headers'])->not->toHaveKey('anthropic-beta')
        ->and($haiku->requests[0]['body']['output_config'])->not->toHaveKey('effort');
});

it('sends the named model to OpenAI with a strict schema', function (): void {
    $transport = new FakeTransport([['status' => 200, 'body' => ['choices' => [['message' => ['content' => json_encode(['questions' => ['A?']])]]]]]]);
    $llm = new LlmQuestions(($this->cacheDirectory)(), new OpenAiWriter('sk-test', 'some-model'), 'Acme', $transport);

    $result = $llm->for(($this->index)());

    expect($transport->requests[0]['url'])->toBe('https://api.openai.com/v1/chat/completions')
        ->and($transport->requests[0]['headers'])->toBe(['Authorization' => 'Bearer sk-test'])
        ->and($transport->requests[0]['body']['model'])->toBe('some-model')
        ->and($transport->requests[0]['body']['response_format']['json_schema']['strict'])->toBeTrue()
        ->and($result['questions']['#'])->toBe(['A?']);
});

it('writes a cache file the next build reads without calling anything', function (): void {
    $transport = new FakeTransport([anthropicAnswer(['Cached one?'])]);
    $llm = new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('key'), 'Acme', $transport);
    $llm->for(($this->index)());

    $files = glob(($this->cacheDirectory)().'/*.json');

    expect($files)->toHaveCount(1)
        ->and(json_decode((string) file_get_contents($files[0]), true))->toMatchArray([
            'section' => '#',
            'model' => 'claude-haiku-4-5',
            'questions' => ['Cached one?'],
        ]);

    $second = new FakeTransport([]);
    $result = (new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('key'), 'Acme', $second))->for(($this->index)());

    expect($second->requests)->toBe([])
        ->and($result)->toMatchArray(['cached' => 1, 'written' => 0, 'missing' => 0]);
});

it('uses the cache and counts what is missing when there is no key', function (): void {
    $llm = new LlmQuestions(($this->cacheDirectory)(), null, 'Acme');
    $result = $llm->for(($this->index)());

    expect($result)->toMatchArray(['cached' => 0, 'written' => 0, 'missing' => 1])
        ->and($llm->hasWriter())->toBeFalse();
});

it('retries a rate limit, waiting as long as the server asks', function (): void {
    $transport = new FakeTransport([
        ['status' => 429, 'headers' => ['retry-after' => '7'], 'body' => ['error' => ['message' => 'slow down']]],
        anthropicAnswer(['After the wait?']),
    ]);
    $waits = [];

    $result = (new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('k'), 'A', $transport, function (float $seconds) use (&$waits): void {
        $waits[] = $seconds;
    }))->for(($this->index)());

    expect($result['written'])->toBe(1)
        ->and($result['failures'])->toBe([])
        ->and($result['questions']['#'])->toBe(['After the wait?'])
        ->and($waits)->toBe([7.0])
        ->and($transport->requests)->toHaveCount(2);
});

it('backs off 1 then 2 seconds without a Retry-After, then gives the section up', function (): void {
    $transport = new FakeTransport(array_fill(0, 3, ['status' => 429, 'body' => ['error' => ['message' => 'slow down']]]));
    $waits = [];

    $result = (new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('k'), 'A', $transport, function (float $seconds) use (&$waits): void {
        $waits[] = $seconds;
    }))->for(($this->index)());

    expect($transport->requests)->toHaveCount(LlmQuestions::ATTEMPTS)
        ->and($waits)->toBe([1.0, 2.0])
        ->and($result['failures'])->toBe(['#: Anthropic API returned 429: slow down'])
        ->and($result['written'])->toBe(0)
        ->and(glob(($this->cacheDirectory)().'/*.json'))->toBe([]);
});

it('does not retry a request that will fail the same way again', function (): void {
    $transport = new FakeTransport([['status' => 401, 'body' => ['error' => ['message' => 'API key is invalid.']]]]);

    $result = (new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('k'), 'A', $transport, fn () => null))->for(($this->index)());

    expect($transport->requests)->toHaveCount(1)
        ->and($result['failures'][0])->toContain('401');
});

it('asks for five sections at a time', function (): void {
    $this->writeDoc('index.md', pageOf(12));
    $transport = new FakeTransport(array_fill(0, 12, anthropicAnswer(['Q?'])));

    $result = (new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('k'), 'A', $transport))->for(($this->index)());

    expect($transport->batches)->toBe([5, 5, 2])
        ->and($result['written'])->toBe(12);
});

it('reports progress every 25 sections', function (): void {
    $this->writeDoc('index.md', pageOf(30));
    $transport = new FakeTransport(array_fill(0, 30, anthropicAnswer(['Q?'])));
    $progress = [];

    (new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('k'), 'A', $transport))->for(($this->index)(), function (int $done, int $total) use (&$progress): void {
        $progress[] = "{$done}/{$total}";
    });

    expect($progress)->toBe(['25/30']);
});

it('keeps nothing from a refusal or an answer that is not the agreed JSON', function (): void {
    $refusal = new FakeTransport([['status' => 200, 'body' => ['stop_reason' => 'refusal', 'stop_details' => ['category' => 'cyber']]]]);
    $garbled = new FakeTransport([['status' => 200, 'body' => ['content' => [['type' => 'text', 'text' => 'Sure! Here you go.']]]]]);

    expect((new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('k'), 'A', $refusal))->for(($this->index)())['failures'][0])->toContain('declined')
        ->and((new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('k'), 'A', $garbled))->for(($this->index)())['failures'][0])->toContain('not the expected JSON');
});

it('stops asking after five failures in a row', function (): void {
    $this->writeDoc('index.md', pageOf(12));
    $transport = new FakeTransport(array_fill(0, 60, ['status' => 500, 'body' => []]));

    $result = (new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('k'), 'A', $transport, fn () => null))->for(($this->index)());

    expect($transport->batches)->toBe([5, 5, 5])
        ->and($result['failures'])->toHaveCount(LlmQuestions::GIVE_UP_AFTER)
        ->and($result['stopped'])->toBeTrue()
        ->and($result['missing'])->toBe(7);
});

it('drops cached questions for sections that no longer exist', function (): void {
    $llm = new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('k'), 'A', new FakeTransport([anthropicAnswer(['Q?'])]));
    $llm->for(($this->index)());
    file_put_contents(($this->cacheDirectory)().'/deadbeef.json', '{"questions": []}');

    expect($llm->prune())->toBe(1)
        ->and(glob(($this->cacheDirectory)().'/*.json'))->toHaveCount(1);
});

it('reads the provider from config, and refuses one it does not know', function (): void {
    expect(LlmQuestions::fromConfig())->toBeNull();

    config(['vellum.answers.llm.provider' => 'anthropic', 'vellum.answers.llm.key' => '']);

    expect(LlmQuestions::fromConfig()?->hasWriter())->toBeFalse();

    config(['vellum.answers.llm.provider' => 'openai', 'vellum.answers.llm.key' => 'sk', 'vellum.answers.llm.model' => '']);

    expect(fn () => LlmQuestions::fromConfig())->toThrow(RuntimeException::class, 'no default for the openai provider');

    config(['vellum.answers.llm.provider' => 'ollama']);

    expect(fn () => LlmQuestions::fromConfig())->toThrow(RuntimeException::class, 'it is anthropic, openai, or null');
});

it('never lets a request reach the model: no controller, view or script mentions it', function (): void {
    $offenders = [];

    foreach ([__DIR__.'/../../src/Http', __DIR__.'/../../resources/views', __DIR__.'/../../resources/js'] as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            // Answers\Llm, its writers, or either API host. LlmsTxt is a different feature.
            if ($file->isFile() && preg_match('#Answers\\\\Llm|LlmQuestions|AnthropicWriter|OpenAiWriter|api\.anthropic\.com|api\.openai\.com#i', (string) file_get_contents($file->getPathname())) === 1) {
                $offenders[] = $file->getPathname();
            }
        }
    }

    expect($offenders)->toBe([]);
});
