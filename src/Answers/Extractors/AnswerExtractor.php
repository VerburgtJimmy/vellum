<?php

declare(strict_types=1);

namespace Vellum\Answers\Extractors;

/**
 * Pulls a short, quotable answer out of a section's compiled HTML. Answers are
 * always the docs' own words; nothing is generated.
 */
interface AnswerExtractor
{
    /**
     * @param  array{title: string, own: string, html: string, description?: string}  $section
     * @return array<string, mixed>|null with at least 'type'
     */
    public function extract(array $section): ?array;
}
