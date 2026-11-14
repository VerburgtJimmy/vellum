<?php

declare(strict_types=1);

namespace Vellum\Site;

/**
 * Which Blade components a page may use, and which app values the value tags
 * may print.
 */
final readonly class Components
{
    /**
     * @param  list<string>  $namespaces  `app` stands for components without one.
     * @param  array{env: list<string>, config: list<string>, route: list<string>}  $allowlist
     * @param  array<string, mixed>  $config  The block as configured, for cache keys.
     */
    public function __construct(
        public array $namespaces = ['vellum'],
        public array $allowlist = ['env' => [], 'config' => [], 'route' => []],
        public array $config = [],
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $namespaces = $config['namespaces'] ?? ['vellum'];
        $allowlist = is_array($config['allowlist'] ?? null) ? $config['allowlist'] : [];

        return new self(
            namespaces: is_array($namespaces)
                ? array_values(array_map('strval', array_filter($namespaces, static fn (mixed $name): bool => is_string($name) || is_int($name))))
                : ['vellum'],
            allowlist: [
                'env' => self::keys($allowlist['env'] ?? null),
                'config' => self::keys($allowlist['config'] ?? null),
                'route' => self::keys($allowlist['route'] ?? null),
            ],
            config: $config,
        );
    }

    /**
     * @return list<string>
     */
    private static function keys(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_filter($value, static fn (mixed $key): bool => is_string($key) && $key !== ''))
            : [];
    }
}
