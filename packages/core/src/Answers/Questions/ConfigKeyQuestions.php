<?php

declare(strict_types=1);

namespace Vellum\Answers\Questions;

/**
 * Config keys: dotted inline code in prose (`checks.strict`) and the first
 * column of any table headed "Key". File names (meta.json) are not keys.
 */
final class ConfigKeyQuestions implements QuestionGenerator
{
    public function generate(array $section): array
    {
        $questions = [];

        foreach (self::keys($section['html']) as $key) {
            $questions[] = "what does {$key} do";
            $questions[] = "how do i change {$key}";
            $questions[] = "default of {$key}";
        }

        return $questions;
    }

    /**
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

        $keys = array_filter($keys, static fn (string $key): bool => preg_match('/\.(json|md|php|xml|txt|ya?ml)$/', $key) !== 1);

        return array_values(array_unique($keys));
    }
}
