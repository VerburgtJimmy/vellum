<?php

declare(strict_types=1);

namespace Vellum\Semantic;

/**
 * Cosine search over a fixed set of normalised vectors.
 */
final class Index
{
    /** @var array<string, list<float>> */
    private array $vectors = [];

    /**
     * @param  array<string, list<float>>  $vectors  id => vector
     */
    public function __construct(array $vectors)
    {
        foreach ($vectors as $id => $vector) {
            $this->vectors[(string) $id] = Encoder::normalize($vector);
        }
    }

    /**
     * @param  list<float>  $query
     * @return array<string, float> id => cosine, best first
     */
    public function search(array $query, ?int $limit = null): array
    {
        $query = Encoder::normalize($query);
        $scores = [];

        foreach ($this->vectors as $id => $vector) {
            $scores[$id] = Encoder::dot($query, $vector);
        }

        arsort($scores);

        return $limit === null ? $scores : array_slice($scores, 0, $limit, true);
    }
}
