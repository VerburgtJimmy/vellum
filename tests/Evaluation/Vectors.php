<?php

declare(strict_types=1);

namespace Vellum\Tests\Evaluation;

/**
 * Mean pooling, cosine, PCA by subspace iteration, and per-row int8 quantisation.
 */
final class Vectors
{
    /**
     * Mean of the rows for $ids, L2-normalised. Unknown ids are skipped, as Model2Vec does.
     *
     * @param  list<int>  $ids
     * @param  array<int, list<float>>  $table
     * @return list<float>|null
     */
    public static function pool(array $ids, array $table, int $dims): ?array
    {
        $sum = array_fill(0, $dims, 0.0);
        $n = 0;

        foreach ($ids as $id) {
            if (! isset($table[$id])) {
                continue;
            }

            foreach ($table[$id] as $i => $value) {
                $sum[$i] += $value;
            }

            $n++;
        }

        return $n === 0 ? null : self::normalize($sum);
    }

    /**
     * @param  list<float>  $vector
     * @return list<float>
     */
    public static function normalize(array $vector): array
    {
        $norm = sqrt(array_sum(array_map(static fn (float $v): float => $v * $v, $vector)));

        return $norm == 0.0 ? $vector : array_map(static fn (float $v): float => $v / $norm, $vector);
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public static function dot(array $a, array $b): float
    {
        $sum = 0.0;

        foreach ($a as $i => $value) {
            $sum += $value * $b[$i];
        }

        return $sum;
    }

    /**
     * Fit PCA on $rows and return [mean, components (k rows of d)].
     *
     * @param  array<int, list<float>>  $rows
     * @return array{0: list<float>, 1: list<list<float>>, 2: float}
     */
    public static function pca(array $rows, int $k, int $iterations = 30): array
    {
        $d = count(reset($rows));
        $n = count($rows);
        $mean = array_fill(0, $d, 0.0);

        foreach ($rows as $row) {
            foreach ($row as $i => $value) {
                $mean[$i] += $value / $n;
            }
        }

        $cov = array_fill(0, $d, array_fill(0, $d, 0.0));

        foreach ($rows as $row) {
            $c = [];

            foreach ($row as $i => $value) {
                $c[$i] = $value - $mean[$i];
            }

            for ($i = 0; $i < $d; $i++) {
                $ci = $c[$i];
                $line = &$cov[$i];

                for ($j = $i; $j < $d; $j++) {
                    $line[$j] += $ci * $c[$j];
                }

                unset($line);
            }
        }

        for ($i = 0; $i < $d; $i++) {
            for ($j = 0; $j < $i; $j++) {
                $cov[$i][$j] = $cov[$j][$i];
            }
        }

        mt_srand(7);
        $basis = [];

        for ($c = 0; $c < $k; $c++) {
            $basis[] = array_map(static fn (): float => mt_rand() / mt_getrandmax() - 0.5, range(1, $d));
        }

        for ($iteration = 0; $iteration < $iterations; $iteration++) {
            $next = [];

            foreach ($basis as $vector) {
                $product = [];

                for ($i = 0; $i < $d; $i++) {
                    $product[$i] = self::dot($cov[$i], $vector);
                }

                $next[] = $product;
            }

            $basis = self::orthonormalize($next);
        }

        $variance = 0.0;

        foreach ($basis as $index => $vector) {
            $max = 0.0;

            foreach ($vector as $value) {
                if (abs($value) > abs($max)) {
                    $max = $value;
                }
            }

            if ($max < 0) {
                $basis[$index] = $vector = array_map(static fn (float $v): float => -$v, $vector);
            }

            $product = [];

            for ($i = 0; $i < $d; $i++) {
                $product[$i] = self::dot($cov[$i], $vector);
            }

            $variance += self::dot($vector, $product) / $n;
        }

        return [$mean, $basis, $variance];
    }

    /**
     * @param  list<float>  $row
     * @param  list<float>  $mean
     * @param  list<list<float>>  $components
     * @return list<float>
     */
    public static function project(array $row, array $mean, array $components): array
    {
        $centered = [];

        foreach ($row as $i => $value) {
            $centered[$i] = $value - $mean[$i];
        }

        return array_map(static fn (array $component): float => self::dot($component, $centered), $components);
    }

    /**
     * Round-trip through int8 with a per-row scale.
     *
     * @param  list<float>  $row
     * @return list<float>
     */
    public static function int8(array $row): array
    {
        $max = max(array_map('abs', $row));

        if ($max == 0.0) {
            return $row;
        }

        $scale = $max / 127;

        return array_map(static fn (float $v): float => ((float) (int) round($v / $scale)) * $scale, $row);
    }

    /**
     * @param  list<list<float>>  $vectors
     * @return list<list<float>>
     */
    private static function orthonormalize(array $vectors): array
    {
        $basis = [];

        foreach ($vectors as $vector) {
            foreach ($basis as $done) {
                $projection = self::dot($vector, $done);

                foreach ($vector as $i => $value) {
                    $vector[$i] = $value - $projection * $done[$i];
                }
            }

            $basis[] = self::normalize($vector);
        }

        return $basis;
    }
}
