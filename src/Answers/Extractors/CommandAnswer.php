<?php

declare(strict_types=1);

namespace Vellum\Answers\Extractors;

/**
 * The first shell code block, when it holds commands: the answer to "how do I
 * build" is the command to run.
 */
final class CommandAnswer implements AnswerExtractor
{
    public function extract(array $section): ?array
    {
        if (preg_match('/<pre><code class="language-(?:bash|sh|shell|zsh)">(.*?)<\/code><\/pre>/s', $section['html'], $match) !== 1) {
            return null;
        }

        $lines = array_values(array_filter(array_map(
            static fn (string $line): string => trim((string) preg_replace('/\s+/', ' ', $line)),
            explode("\n", html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        ), static fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#')));

        return $lines === [] ? null : ['type' => 'command', 'command' => implode("\n", $lines)];
    }
}
