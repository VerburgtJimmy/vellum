<?php

declare(strict_types=1);

namespace Vellum\Answers\Llm;

use RuntimeException;

/**
 * The Claude Messages API over plain HTTP, with the answer constrained to a
 * JSON schema. Effort is low: this is routine extraction, not reasoning.
 * Server-side fallbacks let a request a model declines be retried by the one
 * Anthropic recommends for it, instead of coming back empty.
 */
final class AnthropicWriter implements QuestionWriter
{
    public const DEFAULT_MODEL = 'claude-opus-5';

    public function __construct(
        private readonly string $key,
        private readonly ?string $model = null,
        private readonly Transport $transport = new Transport,
    ) {}

    public function model(): string
    {
        return $this->model ?? self::DEFAULT_MODEL;
    }

    public function questions(string $prompt): array
    {
        $response = $this->transport->post('https://api.anthropic.com/v1/messages', [
            'x-api-key' => $this->key,
            'anthropic-version' => '2023-06-01',
            'anthropic-beta' => 'server-side-fallback-2026-07-01',
        ], [
            'model' => $this->model(),
            'max_tokens' => 16000,
            'fallbacks' => 'default',
            'output_config' => [
                'effort' => 'low',
                'format' => ['type' => 'json_schema', 'schema' => Prompt::SCHEMA],
            ],
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
