<?php

declare(strict_types=1);

namespace Vellum\Semantic;

use RuntimeException;

/**
 * A float32 embedding table in a safetensors file, read row by row with seeks
 * so a large model is never loaded into memory whole.
 */
final class SafetensorsTable
{
    /** @var resource */
    private $handle;

    private int $offset;

    public readonly int $rows;

    /** @var positive-int */
    public readonly int $dims;

    public function __construct(string $path, string $tensor = 'embeddings')
    {
        $handle = is_file($path) ? fopen($path, 'rb') : false;

        if ($handle === false) {
            throw new RuntimeException("Cannot open {$path}");
        }

        $this->handle = $handle;
        $prefix = (string) fread($handle, 8);

        if (strlen($prefix) !== 8) {
            throw new RuntimeException("{$path} is not a safetensors file");
        }

        /** @var array{1: int} $unpacked */
        $unpacked = unpack('P', $prefix);
        $length = $unpacked[1];
        $header = $length > 0 && $length < 100_000_000 ? json_decode((string) fread($handle, $length), true) : null;

        if (! is_array($header)) {
            throw new RuntimeException("{$path} is not a safetensors file");
        }

        $table = $header[$tensor] ?? null;

        if (! is_array($table) || ($table['dtype'] ?? null) !== 'F32' || count($table['shape'] ?? []) !== 2) {
            throw new RuntimeException("{$path} has no two-dimensional F32 tensor named {$tensor}");
        }

        [$rows, $dims] = array_map('intval', $table['shape']);

        if ($rows < 1 || $dims < 1) {
            throw new RuntimeException("{$path} has an empty tensor named {$tensor}");
        }

        $this->rows = $rows;
        $this->dims = $dims;
        $this->offset = 8 + $length + (int) $table['data_offsets'][0];
    }

    public function __destruct()
    {
        fclose($this->handle);
    }

    /**
     * @param  iterable<int>  $ids
     * @return array<int, list<float>>
     */
    public function rows(iterable $ids): array
    {
        $rows = [];

        foreach ($ids as $id) {
            if ($id < 0 || $id >= $this->rows) {
                throw new RuntimeException("Row {$id} is outside a table of {$this->rows}");
            }

            fseek($this->handle, $this->offset + $id * $this->dims * 4);
            /** @var array<int, float> $values */
            $values = unpack('g*', (string) fread($this->handle, $this->dims * 4));
            $rows[$id] = array_values($values);
        }

        return $rows;
    }
}
