<?php

declare(strict_types=1);

namespace Vellum\Answers\Extractors;

/**
 * Small readers over compiled section HTML shared by the extractors.
 */
final class Html
{
    public static function text(string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace(['/\s+/', '/\s+([.,;:!?)])/'], [' ', '$1'], $text));
    }

    /**
     * Tables as rows of header => cell text.
     *
     * @return list<array{headers: list<string>, rows: list<array<string, string>>}>
     */
    public static function tables(string $html): array
    {
        preg_match_all('/<table\b.*?<\/table>/s', $html, $matches);
        $tables = [];

        foreach ($matches[0] as $table) {
            preg_match_all('/<th\b[^>]*>(.*?)<\/th>/s', $table, $heads);
            $headers = array_map(self::text(...), $heads[1]);
            preg_match_all('/<tr>\s*(<td\b.*?)<\/tr>/s', $table, $rowMatches);
            $rows = [];

            foreach ($rowMatches[1] as $row) {
                preg_match_all('/<td\b[^>]*>(.*?)<\/td>/s', $row, $cells);
                $values = [];

                foreach ($headers as $index => $header) {
                    $values[$header] = self::text($cells[1][$index] ?? '');
                }

                $rows[] = $values;
            }

            $tables[] = ['headers' => $headers, 'rows' => $rows];
        }

        return $tables;
    }

    /**
     * The first paragraph that is prose, not inside a callout or a list.
     */
    public static function firstParagraph(string $html): ?string
    {
        $html = (string) preg_replace('/<div\b[^>]*data-vellum-callout.*?<\/div>/s', '', $html);

        if (preg_match('/<p\b[^>]*>(.*?)<\/p>/s', $html, $match) !== 1) {
            return null;
        }

        $text = self::text($match[1]);

        return $text === '' ? null : $text;
    }

    /**
     * The first sentence worth quoting from the first paragraph: more than one
     * word ("Dimensions." is a label), and not a pointer elsewhere ("See Gating."). A sentence that ends
     * in a colon introduces what follows it, so the code block after the
     * paragraph comes along.
     *
     * @return array{sentence: string, code?: string, language?: string}|null
     */
    public static function leadSentence(string $html): ?array
    {
        $html = (string) preg_replace('/<div\b[^>]*data-vellum-callout.*?<\/div>/s', '', $html);

        // A code block is wrapped in the vellum-code frame, header first.
        if (preg_match('/<p\b[^>]*>(.*?)<\/p>\s*(<div class="vellum-code"[^>]*>(?:(?!<pre\b|<p\b).)*<pre><code class="language-([\w-]+)">(.*?)<\/code><\/pre>)?/s', $html, $match) !== 1) {
            return null;
        }

        foreach (self::sentences(self::text($match[1])) as $sentence) {
            if (str_word_count($sentence) < 2 || preg_match('/^See\s/', $sentence) === 1) {
                continue;
            }

            $answer = ['sentence' => $sentence];

            if (str_ends_with($sentence, ':') && ($match[4] ?? '') !== '') {
                $answer['code'] = trim(html_entity_decode(strip_tags($match[4]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $answer['language'] = $match[3];
            }

            return $answer;
        }

        return null;
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
}
