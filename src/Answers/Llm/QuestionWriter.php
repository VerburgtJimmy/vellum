<?php

declare(strict_types=1);

namespace Vellum\Answers\Llm;

/**
 * Turns a prompt into a request, and a response into questions. Splitting it
 * this way lets the caller run several requests at once and retry the ones
 * worth retrying. Used by vellum:build only; nothing that serves a request
 * reaches this code.
 *
 * @phpstan-import-type Request from Transport
 * @phpstan-import-type Response from Transport
 */
interface QuestionWriter
{
    /**
     * @return Request
     */
    public function request(string $prompt): array;

    /**
     * @param  Response  $response
     * @return list<string>
     *
     * @throws \RuntimeException when the response holds no usable questions
     */
    public function parse(array $response): array;

    public function model(): string;
}
