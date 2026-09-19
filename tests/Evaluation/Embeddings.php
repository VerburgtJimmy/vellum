<?php

declare(strict_types=1);

namespace Vellum\Tests\Evaluation;

use RuntimeException;

/**
 * Reads rows of a Model2Vec safetensors table by seeking, so the file is never
 * loaded whole.
 */
final class Embeddings
{
    /** @var resource */
    private $handle;

    private int $offset;

    public readonly int $rows;

    public readonly int $dims;

    public function __construct(string $path)
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Cannot open {$path}");
        }

        $this->handle = $handle;
        $length = unpack('P', (string) fread($handle, 8))[1];
        /** @var array{embeddings: array{dtype: string, shape: array{int, int}, data_offsets: array{int, int}}} $header */
        $header = json_decode((string) fread($handle, $length), true, flags: JSON_THROW_ON_ERROR);
        $table = $header['embeddings'];

        if ($table['dtype'] !== 'F32') {
            throw new RuntimeException("Unsupported dtype {$table['dtype']}");
        }

        [$this->rows, $this->dims] = $table['shape'];
        $this->offset = 8 + $length + $table['data_offsets'][0];
    }

    /**
     * @param  iterable<int>  $ids
     * @return array<int, list<float>>
     */
    public function rows(iterable $ids): array
    {
        $rows = [];

        foreach ($ids as $id) {
            fseek($this->handle, $this->offset + $id * $this->dims * 4);
            $rows[$id] = array_values(unpack('g*', (string) fread($this->handle, $this->dims * 4)));
        }

        return $rows;
    }
}
