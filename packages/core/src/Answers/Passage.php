<?php

declare(strict_types=1);

namespace Vellum\Answers;

/**
 * The snippet a search result shows under its heading: up to three sentences
 * of the section's first paragraph of prose, skipping callouts.
 */
final class Passage
{
    /**
     * @param  array{html: string}  $section
     */
    public static function of(array $section, int $sentences = 3): ?string
    {
        $paragraph = self::firstParagraph($section['html']);

        return $paragraph === null ? null : implode(' ', array_slice(self::sentences($paragraph), 0, $sentences));
    }

    /**
     * Sentences, not split after common abbreviations or inside a version number.
     *
     * @return list<string>
     */
    public static function sentences(string $text): array
    {
        $guarded = (string) preg_replace('/\b(e\.g|i\.e|etc|vs)\./i', '$1<DOT>', $text);
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z`"\'(\[])/', $guarded) ?: [];

        return array_values(array_filter(array_map(static fn (string $s): string => trim(str_replace('<DOT>', '.', $s)), $parts), static fn (string $s): bool => $s !== ''));
    }

    private static function firstParagraph(string $html): ?string
    {
        $html = (string) preg_replace('/<div\b[^>]*data-vellum-callout.*?<\/div>/s', '', $html);

        if (preg_match('/<p\b[^>]*>(.*?)<\/p>/s', $html, $match) !== 1) {
            return null;
        }

        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $match[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace(['/\s+/', '/\s+([.,;:!?)])/'], [' ', '$1'], $text));

        return $text === '' ? null : $text;
    }
}
