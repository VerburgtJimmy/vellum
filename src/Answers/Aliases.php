<?php

declare(strict_types=1);

namespace Vellum\Answers;

/**
 * The phrases a section can be found by exactly: its own heading, the terms
 * written in inline code in its prose, and, for a page's opening section, the
 * aliases its frontmatter lists.
 */
final class Aliases
{
    /**
     * @param  array{own: string, title: string, html: string, aliases?: list<string>}  $section
     * @return list<string>
     */
    public static function for(array $section): array
    {
        $aliases = [$section['own'] !== '' ? $section['own'] : $section['title'], ...($section['aliases'] ?? [])];
        $prose = (string) preg_replace('/<pre\b.*?<\/pre>/s', '', $section['html']);
        preg_match_all('/<code\b[^>]*>([^<]{2,40})<\/code>/', $prose, $codes);

        foreach ($codes[1] as $code) {
            $aliases[] = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $seen = [];

        foreach ($aliases as $alias) {
            $normalized = Synonyms::normalize($alias);

            if ($normalized !== '') {
                $seen[$normalized] = true;
            }
        }

        return array_map('strval', array_keys($seen));
    }
}
