<?php

declare(strict_types=1);

namespace Vellum\Answers;

/**
 * Groups of terms a reader may use for the same thing. A query that contains
 * one term of a group, as a whole phrase, is expanded with the rest of it.
 */
final class Synonyms
{
    /** @var array<string, list<string>> term => the other terms of its groups */
    private array $expansions = [];

    /**
     * @param  list<list<string>>  $groups
     */
    public function __construct(array $groups)
    {
        foreach ($groups as $group) {
            $terms = array_values(array_unique(array_filter(array_map(self::normalize(...), $group), static fn (string $t): bool => $t !== '')));

            foreach ($terms as $term) {
                $this->expansions[$term] = array_values(array_unique([
                    ...($this->expansions[$term] ?? []),
                    ...array_values(array_diff($terms, [$term])),
                ]));
            }
        }
    }

    /**
     * The built-in list, plus a group per page from its title and the aliases
     * in its frontmatter.
     *
     * @param  list<array{title: string, aliases: list<string>}>  $pages
     */
    public static function withPages(array $pages): self
    {
        /** @var list<list<string>> $groups */
        $groups = require dirname(__DIR__, 2).'/resources/synonyms.php';

        foreach ($pages as $page) {
            if ($page['aliases'] !== []) {
                $groups[] = [$page['title'], ...$page['aliases']];
            }
        }

        return new self($groups);
    }

    /**
     * Lowercase, punctuation to spaces, single-spaced. Dots, dashes, slashes
     * and underscores stay, so ".env", "route.prefix" and "dark-mode" survive.
     */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = (string) preg_replace('/[^\p{L}\p{N}.\-\/_]+/u', ' ', $text);
        $text = (string) preg_replace('/(?<![\p{L}\p{N}])[.\-\/_]+|[.\-\/_]+(?![\p{L}\p{N}])/u', ' ', ' '.$text.' ');

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Terms to add to a query: every group member of every term the query
     * contains as a phrase, longest phrases first. Terms already in the query
     * are not repeated.
     *
     * @return list<string>
     */
    public function expand(string $query): array
    {
        $normalized = ' '.self::normalize($query).' ';
        $added = [];

        foreach ($this->expansions as $term => $others) {
            if (! str_contains($normalized, ' '.$term.' ')) {
                continue;
            }

            foreach ($others as $other) {
                if (! str_contains($normalized, ' '.$other.' ')) {
                    $added[$other] = true;
                }
            }
        }

        $terms = array_map('strval', array_keys($added));
        usort($terms, static fn (string $a, string $b): int => strlen($b) <=> strlen($a) ?: strcmp($a, $b));

        return $terms;
    }
}
