<?php

declare(strict_types=1);

namespace Vellum\Answers\Questions;

/**
 * Artisan and Composer commands in shell code blocks: "how do i build",
 * "what does vellum:build do", "how do i install acme/package".
 */
final class CommandQuestions implements QuestionGenerator
{
    public function generate(array $section): array
    {
        preg_match_all('/<pre><code class="language-(?:bash|sh|shell|zsh)">(.*?)<\/code><\/pre>/s', $section['html'], $blocks);
        $questions = [];

        foreach ($blocks[1] as $block) {
            foreach (explode("\n", html_entity_decode(strip_tags($block), ENT_QUOTES | ENT_HTML5, 'UTF-8')) as $line) {
                $line = trim((string) preg_replace('/\s+/', ' ', $line));

                if (preg_match('/^php artisan ([\w:-]+)/', $line, $m) === 1) {
                    $position = strrpos($m[1], ':');
                    $verb = $position === false ? $m[1] : substr($m[1], $position + 1);
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
}
