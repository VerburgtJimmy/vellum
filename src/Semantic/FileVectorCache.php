<?php

declare(strict_types=1);

namespace Vellum\Semantic;

/**
 * A VectorCache in one serialized file.
 */
final class FileVectorCache implements VectorCache
{
    /** @var array<string, array{ids: list<int>, vector: list<float>|null}> */
    private array $stored = [];

    /** @var array<string, array{ids: list<int>, vector: list<float>|null}> */
    private array $used = [];

    public int $hits = 0;

    public function __construct(private readonly string $path)
    {
        if (is_file($path)) {
            $stored = unserialize((string) file_get_contents($path), ['allowed_classes' => false]);
            $this->stored = is_array($stored) ? $stored : [];
        }
    }

    public function get(string $key): ?array
    {
        $entry = $this->stored[$key] ?? null;

        if ($entry !== null) {
            $this->used[$key] = $entry;
            $this->hits++;
        }

        return $entry;
    }

    public function put(string $key, array $entry): void
    {
        $this->used[$key] = $entry;
    }

    public function flush(): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $temporary = $this->path.'.'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($temporary, serialize($this->used));
        rename($temporary, $this->path);
        $this->stored = $this->used;
    }
}
