<?php

declare(strict_types=1);

namespace Vellum\Answers\Extractors;

/**
 * A page's frontmatter description, for its opening section when that section
 * has no prose of its own (a page that opens with a table or a code block).
 */
final class DescriptionAnswer implements AnswerExtractor
{
    public function extract(array $section): ?array
    {
        $description = trim($section['description'] ?? '');

        return $description === '' ? null : ['type' => 'sentence', 'sentence' => $description];
    }
}
