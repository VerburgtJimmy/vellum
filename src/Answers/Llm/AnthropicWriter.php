<?php

declare(strict_types=1);

namespace Vellum\Answers\Llm;

use RuntimeException;

/**
 * The Claude Messages API over plain HTTP, with the answer constrained to a
 * JSON schema.
 *
 * Two options are sent only to the models that accept them. Effort is low
 * where it applies, since this is routine extraction rather than reasoning,
 * but Haiku rejects the field. Server-side fallbacks, which retry a declined
 * request on the model Anthropic recommends instead of returning nothing, are
 * an Opus 5 and Fable feature.
 */
final class AnthropicWriter implements QuestionWriter
{
    public const DEFAULT_MODEL = 'claude-haiku-4-5';

    public function __construct(
        private readonly string $key,
        private readonly ?string $model = null,
        private readonly Transport $transport = new Transport,
    ) {}

    public function model(): string
    {
        return $this->model ?? self::DEFAULT_MODEL;
    }

    private static function takesEffort(string $model): bool
    {
        return ! str_starts_with($model, 'claude-haiku');
    }

    private static function takesFallbacks(string $model): bool
    {
        return str_starts_with($model, 'claude-opus-5') || str_starts_with($model, 'claude-fable');
    }

    public function questions(string $prompt): array
    {
        $model = $this->model();
        $headers = ['x-api-key' => $this->key, 'anthropic-version' => '2023-06-01'];
        $outputConfig = ['format' => ['type' => 'json_schema', 'schema' => Prompt::SCHEMA]];
        $body = ['model' => $model, 'max_tokens' => 16000];

        if (self::takesEffort($model)) {
            $outputConfig = ['effort' => 'low', ...$outputConfig];
        }

        if (self::takesFallbacks($model)) {
            $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
            $body['fallbacks'] = 'default';
        }

        $response = $this->transport->post('https://api.anthropic.com/v1/messages', $headers, [
            ...$body,
            'output_config' => $outputConfig,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $body = $response['body'];

        if ($response['status'] !== 200) {
            $message = is_array($body['error'] ?? null) ? (string) ($body['error']['message'] ?? '') : '';

            throw new RuntimeException("Anthropic API returned {$response['status']}".($message !== '' ? ": {$message}" : ''));
        }

        if (($body['stop_reason'] ?? null) === 'refusal') {
            throw new RuntimeException('The model declined this section');
        }

        foreach (is_array($body['content'] ?? null) ? $body['content'] : [] as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                return Prompt::parse($block['text']);
            }
        }

        throw new RuntimeException('The response held no text');
    }
}
