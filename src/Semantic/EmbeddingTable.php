<?php

declare(strict_types=1);

namespace Vellum\Semantic;

/**
 * One vector per vocabulary id.
 */
interface EmbeddingTable
{
    /**
     * @return positive-int
     */
    public function dims(): int;

    /**
     * @param  iterable<int>  $ids
     * @return array<int, list<float>>
     */
    public function rows(iterable $ids): array;
}
