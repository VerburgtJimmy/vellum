<?php

declare(strict_types=1);

namespace Vellum\Answers\Llm;

use RuntimeException;

/**
 * OpenAI Chat Completions over plain HTTP, with a strict JSON schema. There is
 * no default model: name one in answers.llm.model.
 */
final class OpenAiWriter implements QuestionWriter
{
    public function __construct(
        private readonly string $key,
        private readonly string $model,
    ) {}

    public function model(): string
    {
        return $this->model;
    }

    public function request(string $prompt): array
    {
        return [
            'url' => 'https://api.openai.com/v1/chat/completions',
            'headers' => ['Authorization' => 'Bearer '.$this->key],
            'body' => [
                'model' => $this->model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => ['name' => 'section_questions', 'strict' => true, 'schema' => Prompt::SCHEMA],
                ],
            ],
        ];
    }

    public function parse(array $response): array
    {
        $body = $response['body'];

        if ($response['status'] !== 200) {
            $message = is_array($body['error'] ?? null) ? (string) ($body['error']['message'] ?? '') : '';

            throw new RuntimeException("OpenAI API returned {$response['status']}".($message !== '' ? ": {$message}" : ''));
        }

        $content = $body['choices'][0]['message']['content'] ?? null;

        if (! is_string($content)) {
            throw new RuntimeException('The response held no message');
        }

        return Prompt::parse($content);
    }
}
