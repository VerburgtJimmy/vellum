<?php

declare(strict_types=1);

namespace Vellum\Answers\Questions;

use Vellum\Answers\Sections;

/**
 * A warning or danger callout usually describes a failure, so it answers
 * "why does {its first words} fail".
 */
final class CalloutQuestions implements QuestionGenerator
{
    public function generate(array $section): array
    {
        preg_match_all('/data-vellum-callout="(?:warning|danger)"[^>]*>(.*?)<\/div>/s', $section['html'], $callouts);
        $questions = [];

        foreach ($callouts[1] as $callout) {
            $words = array_slice(preg_split('/\s+/', Sections::text(str_replace('<', ' <', $callout))) ?: [], 0, 4);
            $phrase = trim(implode(' ', $words), ' .,:;');

            if ($phrase !== '') {
                $questions[] = "why does {$phrase} fail";
            }
        }

        return $questions;
    }
}
