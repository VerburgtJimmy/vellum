<?php

declare(strict_types=1);

namespace Vellum\Semantic;

use RuntimeException;

/**
 * An embedding table held in memory, for small models and tests.
 */
final class ArrayTable implements EmbeddingTable
{
    /**
     * @param  array<int, list<float>>  $rows
     * @param  positive-int  $dims
     */
    public function __construct(private readonly array $rows, private readonly int $dims) {}

    public function dims(): int
    {
        return $this->dims;
    }

    public function rows(iterable $ids): array
    {
        $rows = [];

        foreach ($ids as $id) {
            $rows[$id] = $this->rows[$id] ?? throw new RuntimeException("Row {$id} is not in the table");
        }

        return $rows;
    }
}
