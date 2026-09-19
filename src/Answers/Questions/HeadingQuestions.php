<?php

declare(strict_types=1);

namespace Vellum\Answers\Questions;

/**
 * "how do i ..." for a heading that starts with a verb in -ing, otherwise
 * "what is ..." and "where do i configure ...". A lone -ing word is usually a
 * noun (Gating, Theming), so it takes the second pair.
 */
final class HeadingQuestions implements QuestionGenerator
{
    public function generate(array $section): array
    {
        $heading = mb_strtolower(trim($section['own'] !== '' ? $section['own'] : $section['title']));

        if ($heading === '') {
            return [];
        }

        $first = explode(' ', $heading)[0];

        if (str_ends_with($first, 'ing') && mb_strlen($first) > 4 && str_contains($heading, ' ')) {
            return ["how do i {$heading}"];
        }

        return ["what is {$heading}", "where do i configure {$heading}"];
    }
}
