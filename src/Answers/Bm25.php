<?php

declare(strict_types=1);

namespace Vellum\Answers;

/**
 * BM25+ over weighted fields with prefix matching. The constants are
 * MiniSearch's defaults (k1 1.2, b 0.7, delta 0.5, prefix weight 0.375), which
 * is what Vellum's search used before this. The browser runs the same
 * algorithm on the same numbers, so a result ranks the same in both.
 */
final class Bm25
{
    private const K1 = 1.2;

    private const B = 0.7;

    private const DELTA = 0.5;

    private const PREFIX_WEIGHT = 0.375;

    /** @var array<string, array<string, array<int, int>>> field => term => doc => tf */
    private array $postings = [];

    /** @var array<string, array<int, int>> */
    private array $lengths = [];

    /** @var array<string, float> */
    private array $average = [];

    private int $count;

    /**
     * @param  list<array<string, string>>  $documents
     * @param  array<string, float>  $boosts  field => boost
     */
    public function __construct(array $documents, private readonly array $boosts)
    {
        $this->count = count($documents);

        foreach ($boosts as $field => $boost) {
            $this->postings[$field] = [];
            $total = 0;

            foreach ($documents as $id => $document) {
                $terms = self::terms($document[$field] ?? '');
                $this->lengths[$field][$id] = count($terms);
                $total += count($terms);

                foreach ($terms as $term) {
                    $this->postings[$field][$term][$id] = ($this->postings[$field][$term][$id] ?? 0) + 1;
                }
            }

            $this->average[$field] = $this->count > 0 ? max(1, $total / $this->count) : 1;
        }
    }

    /**
     * @return list<string>
     */
    public static function terms(string $text): array
    {
        return array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text, 'UTF-8')) ?: [],
            static fn (string $term): bool => $term !== '',
        ));
    }

    /**
     * @param  array<string, float>  $weights  term => weight, an expanded term weighing less
     * @return array<int, float> doc => score, best first
     */
    public function searchTerms(array $weights): array
    {
        $scores = [];

        foreach ($weights as $query => $weight) {
            $query = (string) $query;
            foreach ($this->boosts as $field => $boost) {
                foreach ($this->postings[$field] as $term => $docs) {
                    $term = (string) $term;

                    if ($term === $query) {
                        $match = 1.0;
                    } elseif (str_starts_with($term, $query)) {
                        $length = mb_strlen($query);
                        $match = self::PREFIX_WEIGHT * $length / ($length + 0.3 * (mb_strlen($term) - $length));
                    } else {
                        continue;
                    }

                    $df = count($docs);
                    $idf = log(1 + ($this->count - $df + 0.5) / ($df + 0.5));

                    foreach ($docs as $id => $tf) {
                        $norm = 1 - self::B + self::B * $this->lengths[$field][$id] / $this->average[$field];
                        $scores[$id] = ($scores[$id] ?? 0.0)
                            + $weight * $boost * $match * $idf * (self::DELTA + $tf * (self::K1 + 1) / ($tf + self::K1 * $norm));
                    }
                }
            }
        }

        arsort($scores);

        return $scores;
    }

    /**
     * @return array<int, float> doc => score, best first
     */
    public function search(string $query): array
    {
        return $this->searchTerms(array_fill_keys(array_unique(self::terms($query)), 1.0));
    }
}
