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
 * A Transport that answers from a queue and records what it was asked.
 */
final class FakeTransport extends Transport
{
    /** @var list<array{url: string, headers: array<string, string>, body: array<string, mixed>}> */
    public array $requests = [];

    /**
     * @param  list<array{status: int, body: array<string, mixed>}>  $responses
     */
    public function __construct(private array $responses = []) {}

    public function post(string $url, array $headers, array $body): array
    {
        $this->requests[] = ['url' => $url, 'headers' => $headers, 'body' => $body];

        return array_shift($this->responses) ?? ['status' => 200, 'body' => []];
    }
}

function anthropicAnswer(array $questions): array
{
    return ['status' => 200, 'body' => ['stop_reason' => 'end_turn', 'content' => [
        ['type' => 'thinking', 'thinking' => ''],
        ['type' => 'text', 'text' => json_encode(['questions' => $questions])],
    ]]];
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

it('asks Claude with a schema, low effort and fallbacks, and keeps five questions', function (): void {
    $transport = new FakeTransport([anthropicAnswer(['One?', 'Two?', ' ', 'Three?', 'Four?', 'Five?', 'Six?'])]);
    $llm = new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('key-123', null, $transport), 'Acme');

    $result = $llm->for(($this->index)());
    $request = $transport->requests[0];

    expect($request['url'])->toBe('https://api.anthropic.com/v1/messages')
        ->and($request['headers'])->toBe([
            'x-api-key' => 'key-123',
            'anthropic-version' => '2023-06-01',
            'anthropic-beta' => 'server-side-fallback-2026-07-01',
        ])
        ->and($request['body']['model'])->toBe('claude-opus-5')
        ->and($request['body']['fallbacks'])->toBe('default')
        ->and($request['body']['output_config']['effort'])->toBe('low')
        ->and($request['body']['output_config']['format']['type'])->toBe('json_schema')
        ->and($request['body']['messages'][0]['content'])->toContain('documentation for Acme')
        ->and($request['body']['messages'][0]['content'])->toContain('Vellum is a docs package.')
        ->and($result['questions']['#'])->toBe(['One?', 'Two?', 'Three?', 'Four?', 'Five?'])
        ->and($result)->toMatchArray(['cached' => 0, 'written' => 1, 'missing' => 0, 'failures' => []]);
});

it('sends the named model to OpenAI with a strict schema', function (): void {
    $transport = new FakeTransport([['status' => 200, 'body' => ['choices' => [['message' => ['content' => json_encode(['questions' => ['A?']])]]]]]]);
    $llm = new LlmQuestions(($this->cacheDirectory)(), new OpenAiWriter('sk-test', 'some-model', $transport), 'Acme');

    $result = $llm->for(($this->index)());

    expect($transport->requests[0]['url'])->toBe('https://api.openai.com/v1/chat/completions')
        ->and($transport->requests[0]['headers'])->toBe(['Authorization' => 'Bearer sk-test'])
        ->and($transport->requests[0]['body']['model'])->toBe('some-model')
        ->and($transport->requests[0]['body']['response_format']['json_schema']['strict'])->toBeTrue()
        ->and($result['questions']['#'])->toBe(['A?']);
});

it('writes a cache file the next build reads without calling anything', function (): void {
    $transport = new FakeTransport([anthropicAnswer(['Cached one?'])]);
    $llm = new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('key', null, $transport), 'Acme');
    $llm->for(($this->index)());

    $files = glob(($this->cacheDirectory)().'/*.json');

    expect($files)->toHaveCount(1)
        ->and(json_decode((string) file_get_contents($files[0]), true))->toMatchArray([
            'section' => '#',
            'model' => 'claude-opus-5',
            'questions' => ['Cached one?'],
        ]);

    $second = new FakeTransport([]);
    $result = (new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('key', null, $second), 'Acme'))->for(($this->index)());

    expect($second->requests)->toBe([])
        ->and($result)->toMatchArray(['cached' => 1, 'written' => 0, 'missing' => 0]);
});

it('uses the cache and counts what is missing when there is no key', function (): void {
    $llm = new LlmQuestions(($this->cacheDirectory)(), null, 'Acme');
    $result = $llm->for(($this->index)());

    expect($result)->toMatchArray(['cached' => 0, 'written' => 0, 'missing' => 1])
        ->and($llm->hasWriter())->toBeFalse();
});

it('reports a section the model failed on without stopping the build', function (): void {
    $transport = new FakeTransport([
        ['status' => 429, 'body' => ['error' => ['message' => 'slow down']]],
    ]);
    $result = (new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('key', null, $transport), 'Acme'))->for(($this->index)());

    expect($result['written'])->toBe(0)
        ->and($result['failures'])->toBe(['#: Anthropic API returned 429: slow down'])
        ->and(glob(($this->cacheDirectory)().'/*.json'))->toBe([]);
});

it('keeps nothing from a refusal or an answer that is not the agreed JSON', function (): void {
    $refusal = new FakeTransport([['status' => 200, 'body' => ['stop_reason' => 'refusal', 'stop_details' => ['category' => 'cyber']]]]);
    $garbled = new FakeTransport([['status' => 200, 'body' => ['content' => [['type' => 'text', 'text' => 'Sure! Here you go.']]]]]);

    expect((new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('k', null, $refusal), 'A'))->for(($this->index)())['failures'][0])->toContain('declined')
        ->and((new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('k', null, $garbled), 'A'))->for(($this->index)())['failures'][0])->toContain('not the expected JSON');
});

it('drops cached questions for sections that no longer exist', function (): void {
    $llm = new LlmQuestions(($this->cacheDirectory)(), new AnthropicWriter('k', null, new FakeTransport([anthropicAnswer(['Q?'])])), 'A');
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
