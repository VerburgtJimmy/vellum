<?php

declare(strict_types=1);

namespace Vellum\Answers\Questions;

/**
 * The questions an author lists under `questions:` in a page's frontmatter.
 * They belong to the page's opening section.
 */
final class FrontmatterQuestions implements QuestionGenerator
{
    public function generate(array $section): array
    {
        return $section['questions'];
    }
}
