<?php

declare(strict_types=1);

namespace Vellum\Semantic;

/**
 * Token ids and pooled vectors per section text, so an incremental build only
 * tokenises and encodes what changed.
 */
interface VectorCache
{
    /**
     * @return array{ids: list<int>, vector: list<float>|null}|null
     */
    public function get(string $key): ?array;

    /**
     * @param  array{ids: list<int>, vector: list<float>|null}  $entry
     */
    public function put(string $key, array $entry): void;

    /**
     * Persist, dropping entries this build did not touch.
     */
    public function flush(): void;
}
