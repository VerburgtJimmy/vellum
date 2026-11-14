<?php

declare(strict_types=1);

namespace Vellum\Site;

/**
 * What vellum:build checks, and how hard it fails.
 */
final readonly class Checks
{
    public function __construct(
        public bool $references = true,
        public bool $strict = false,
        public bool $search = true,
        public float $searchMin = 0.0,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $minimum = $config['search_min'] ?? 0.0;

        return new self(
            references: (bool) ($config['references'] ?? true),
            strict: (bool) ($config['strict'] ?? false),
            search: (bool) ($config['search'] ?? true),
            searchMin: is_numeric($minimum) ? (float) $minimum : 0.0,
        );
    }
}
