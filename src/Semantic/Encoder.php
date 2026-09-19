<?php

declare(strict_types=1);

namespace Vellum\Semantic;

/**
 * Model2Vec encoding: the mean of the token vectors, L2-normalised. Tokens with
 * no row (unknown, or pruned away) are skipped, as Model2Vec skips [UNK].
 */
final class Encoder
{
    /**
     * @param  list<int>  $ids
     * @param  array<int, list<float>>  $rows
     * @return list<float>|null null when no token has a row
     */
    public static function pool(array $ids, array $rows, int $dims): ?array
    {
        $sum = array_fill(0, $dims, 0.0);
        $count = 0;

        foreach ($ids as $id) {
            if (! isset($rows[$id])) {
                continue;
            }

            foreach ($rows[$id] as $i => $value) {
                $sum[$i] += $value;
            }

            $count++;
        }

        return $count === 0 ? null : self::normalize(array_values($sum));
    }

    /**
     * @param  list<float>  $vector
     * @return list<float>
     */
    public static function normalize(array $vector): array
    {
        $norm = 0.0;

        foreach ($vector as $value) {
            $norm += $value * $value;
        }

        $norm = sqrt($norm);

        return $norm > 0.0 ? array_map(static fn (float $v): float => $v / $norm, $vector) : $vector;
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public static function dot(array $a, array $b): float
    {
        $sum = 0.0;

        foreach ($a as $i => $value) {
            $sum += $value * ($b[$i] ?? 0.0);
        }

        return $sum;
    }
}
