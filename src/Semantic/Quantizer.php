<?php

declare(strict_types=1);

namespace Vellum\Semantic;

use InvalidArgumentException;

/**
 * Symmetric int8 or int4 quantisation with one float32 scale per row. A record
 * is the scale followed by the values; int4 packs two per byte, high nibble
 * first, stored with an offset of 8.
 */
final class Quantizer
{
    private readonly int $levels;

    public function __construct(public readonly int $bits)
    {
        if ($bits !== 8 && $bits !== 4) {
            throw new InvalidArgumentException("Quantisation is 4 or 8 bits, not {$bits}");
        }

        $this->levels = $bits === 8 ? 127 : 7;
    }

    public function recordBytes(int $dims): int
    {
        return 4 + ($this->bits === 8 ? $dims : intdiv($dims + 1, 2));
    }

    /**
     * @param  list<float>  $vector
     */
    public function pack(array $vector): string
    {
        $max = 0.0;

        foreach ($vector as $value) {
            $max = max($max, abs($value));
        }

        $scale = $max > 0.0 ? $max / $this->levels : 1.0;
        $values = array_map(fn (float $v): int => (int) max(-$this->levels, min($this->levels, round($v / $scale))), $vector);

        if ($this->bits === 8) {
            return pack('g', $scale).pack('c*', ...$values);
        }

        $bytes = '';

        for ($i = 0, $n = count($values); $i < $n; $i += 2) {
            $bytes .= chr((($values[$i] + 8) << 4) | (($values[$i + 1] ?? 0) + 8));
        }

        return pack('g', $scale).$bytes;
    }

    /**
     * @return list<float>
     */
    public function unpack(string $record, int $dims): array
    {
        /** @var array{1: float} $head */
        $head = unpack('g', $record);
        $scale = $head[1];
        $values = [];

        if ($this->bits === 8) {
            /** @var array<int, int> $bytes */
            $bytes = unpack('c*', substr($record, 4, $dims));

            foreach ($bytes as $value) {
                $values[] = $value * $scale;
            }

            return $values;
        }

        for ($i = 0; $i < $dims; $i++) {
            $byte = ord($record[4 + intdiv($i, 2)]);
            $values[] = ((($i % 2 === 0) ? $byte >> 4 : $byte & 0x0F) - 8) * $scale;
        }

        return $values;
    }
}
