<?php

declare(strict_types=1);

namespace Vellum\Tests\Evaluation;

/**
 * Deterministic question generators from section 4 of the 0.7 plan: headings,
 * shell commands, config keys, warning callouts, and frontmatter questions.
 */
final class Questions
{
    /**
     * @param  array{title: string, own: string, html: string, questions: list<string>}  $section
     * @return list<string>
     */
    public static function for(array $section): array
    {
        $questions = [
            ...self::heading($section['own'] !== '' ? $section['own'] : $section['title']),
            ...self::commands($section['html']),
            ...self::keys($section['html']),
            ...self::callouts($section['html']),
            ...$section['questions'],
        ];

        return array_values(array_unique(array_map(static fn (string $q): string => mb_strtolower(trim($q)), $questions)));
    }

    /**
     * @return list<string>
     */
    public static function heading(string $heading): array
    {
        $heading = mb_strtolower(trim($heading));

        if ($heading === '') {
            return [];
        }

        $first = explode(' ', $heading)[0];

        // A lone -ing word is usually a noun (Gating, Theming), so only phrases count.
        if (str_ends_with($first, 'ing') && mb_strlen($first) > 4 && str_contains($heading, ' ')) {
            return ["how do i {$heading}"];
        }

        return ["what is {$heading}", "where do i configure {$heading}"];
    }

    /**
     * @return list<string>
     */
    public static function commands(string $html): array
    {
        preg_match_all('/<pre><code class="language-(?:bash|sh|shell|zsh)">(.*?)<\/code><\/pre>/s', $html, $blocks);
        $questions = [];

        foreach ($blocks[1] as $block) {
            foreach (explode("\n", html_entity_decode(strip_tags($block), ENT_QUOTES | ENT_HTML5, 'UTF-8')) as $line) {
                $line = trim((string) preg_replace('/\s+/', ' ', $line));

                if (preg_match('/^php artisan ([\w:-]+)/', $line, $m) === 1) {
                    $verb = str_contains($m[1], ':') ? substr($m[1], strrpos($m[1], ':') + 1) : $m[1];
                    $questions[] = "how do i {$verb}";
                    $questions[] = "what does {$m[1]} do";
                } elseif (preg_match('/^composer (require|update|install)(?:\s+([^\s-]\S*))?/', $line, $m) === 1) {
                    $package = $m[2] ?? '';
                    $questions[] = trim("how do i {$m[1]} {$package}");
                    $questions[] = $m[1] === 'require' ? trim("how do i install {$package}") : "how do i {$m[1]}";
                }
            }
        }

        return $questions;
    }

    /**
     * Dotted inline keys anywhere, plus the first column of tables headed "Key".
     *
     * @return list<string>
     */
    public static function keys(string $html): array
    {
        $prose = (string) preg_replace('/<pre\b.*?<\/pre>/s', '', $html);
        preg_match_all('/<code\b[^>]*>([a-z_]+(?:\.[a-z_]+)+)<\/code>/', $prose, $inline);
        $keys = $inline[1];

        preg_match_all('/<table\b.*?<\/table>/s', $prose, $tables);

        foreach ($tables[0] as $table) {
            if (preg_match('/<th[^>]*>\s*Key\s*<\/th>/i', $table) !== 1) {
                continue;
            }

            preg_match_all('/<tr>\s*<td[^>]*>\s*<code\b[^>]*>([a-zA-Z_.]+)<\/code>/', $table, $rows);
            array_push($keys, ...$rows[1]);
        }

        $questions = [];

        // meta.json and _meta.md are files, not keys.
        $keys = array_filter($keys, static fn (string $key): bool => preg_match('/\.(json|md|php|xml|txt|ya?ml)$/', $key) !== 1);

        foreach (array_unique($keys) as $key) {
            $questions[] = "what does {$key} do";
            $questions[] = "how do i change {$key}";
            $questions[] = "default of {$key}";
        }

        return $questions;
    }

    /**
     * @return list<string>
     */
    public static function callouts(string $html): array
    {
        preg_match_all('/data-vellum-callout="(?:warning|danger)"[^>]*>(.*?)<\/div>/s', $html, $callouts);
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
