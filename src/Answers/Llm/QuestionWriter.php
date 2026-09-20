<?php

declare(strict_types=1);

namespace Vellum\Answers\Llm;

/**
 * Asks a language model for the questions a section answers. Used by
 * vellum:build only; nothing that serves a request reaches this code.
 */
interface QuestionWriter
{
    /**
     * @return list<string>
     */
    public function questions(string $prompt): array;

    public function model(): string;
}
