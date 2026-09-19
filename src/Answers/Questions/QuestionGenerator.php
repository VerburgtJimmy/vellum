<?php

declare(strict_types=1);

namespace Vellum\Answers\Questions;

/**
 * Questions a section answers, derived from its structure at build time.
 *
 * @phpstan-type Section array{title: string, own: string, html: string, questions: list<string>}
 */
interface QuestionGenerator
{
    /**
     * @param  Section  $section
     * @return list<string>
     */
    public function generate(array $section): array;
}
