<?php

declare(strict_types=1);

namespace Vellum\Answers;

/**
 * What a section is called, and what it talks about.
 *
 * Its names are its own heading (or the page title, for a page's opening
 * section) and the aliases its frontmatter lists: a query carrying one of
 * those is asking for this section. Its terms are what it writes in inline
 * code, which many sections mention without being about.
 */
final class Aliases
{
    /**
     * @param  array{own: string, title: string, html: string, aliases?: list<string>}  $section
     * @return list<string>
     */
    public static function names(array $section): array
    {
        return self::normalized([
            $section['own'] !== '' ? $section['own'] : $section['title'],
            ...($section['aliases'] ?? []),
        ]);
    }

    /**
     * @param  array{html: string}  $section
     * @return list<string>
     */
    public static function terms(array $section): array
    {
        $prose = (string) preg_replace('/<pre\b.*?<\/pre>/s', '', $section['html']);
        preg_match_all('/<code\b[^>]*>([^<]{2,40})<\/code>/', $prose, $codes);

        return self::normalized(array_map(
            static fn (string $code): string => html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $codes[1],
        ));
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private static function normalized(array $values): array
    {
        $seen = [];

        foreach ($values as $value) {
            $normalized = Synonyms::normalize($value);

            if ($normalized !== '') {
                $seen[$normalized] = true;
            }
        }

        return array_map('strval', array_keys($seen));
    }
}
