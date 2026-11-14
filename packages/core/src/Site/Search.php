<?php

declare(strict_types=1);

namespace Vellum\Site;

use Vellum\Search\SearchDriver;

/**
 * Which search driver answers the search box.
 */
final readonly class Search
{
    public function __construct(
        public string $driver = SearchDriver::BUILTIN,
        public string $scoutIndex = 'vellum',
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $driver = $config['driver'] ?? SearchDriver::BUILTIN;
        $driver = is_string($driver) && $driver !== '' ? $driver : SearchDriver::BUILTIN;
        $scout = is_array($config['scout'] ?? null) ? $config['scout'] : [];
        $index = $scout['index'] ?? 'vellum';

        return new self(
            // The name the built-in driver had while it used that library,
            // kept working so a published config keeps working.
            driver: $driver === SearchDriver::BUILTIN_ALIAS ? SearchDriver::BUILTIN : $driver,
            scoutIndex: is_string($index) && $index !== '' ? $index : 'vellum',
        );
    }

    public function isScout(): bool
    {
        return $this->driver === 'scout';
    }
}
