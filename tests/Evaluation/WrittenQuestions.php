<?php

declare(strict_types=1);

namespace Vellum\Tests\Evaluation;

use Vellum\Answers\Llm\LlmQuestions;

/**
 * The questions a model wrote for this repository's own docs, read straight
 * from the committed cache. An evaluation that leaves these out measures a
 * different index than the one vellum:build ships.
 */
final class WrittenQuestions
{
    /**
     * @return array<string, list<string>> section id => questions
     */
    public static function all(): array
    {
        $cached = [];

        foreach (glob(dirname(__DIR__, 2).'/docs/'.LlmQuestions::DIRECTORY.'/*.json') ?: [] as $file) {
            $entry = json_decode((string) file_get_contents($file), true);

            if (is_array($entry) && is_string($entry['section'] ?? null) && is_array($entry['questions'] ?? null)) {
                $cached[$entry['section']] = array_map('strval', $entry['questions']);
            }
        }

        return $cached;
    }
}
