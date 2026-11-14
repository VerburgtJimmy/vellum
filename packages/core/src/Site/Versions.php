<?php

declare(strict_types=1);

namespace Vellum\Site;

/**
 * Which version folders the docs have, which one is the latest, and what the
 * switcher calls each. Folders and URLs keep the folder name.
 */
final readonly class Versions
{
    /**
     * @param  list<string>  $list  Newest first.
     * @param  array<string, string>  $labels
     */
    public function __construct(
        public bool $enabled = false,
        public ?string $latest = null,
        public array $list = [],
        public array $labels = [],
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $latest = $config['latest'] ?? null;
        $labels = [];

        foreach (is_array($config['labels'] ?? null) ? $config['labels'] : [] as $version => $label) {
            $label = is_string($label) ? trim($label) : '';

            if ($label !== '') {
                $labels[(string) $version] = $label;
            }
        }

        return new self(
            enabled: (bool) ($config['enabled'] ?? false),
            latest: is_scalar($latest) ? (string) $latest : null,
            list: array_values(array_map('strval', array_filter((array) ($config['list'] ?? []), 'is_scalar'))),
            labels: $labels,
        );
    }

    /**
     * The latest version, or null when versioning is off, even if a 'latest'
     * is still sitting in config. URLs are built from this, and a version
     * segment the router will not match is worse than none.
     */
    public function current(): ?string
    {
        return $this->enabled ? $this->latest : null;
    }

    /**
     * The switcher's name for a version: its label, else the folder name.
     * That a version is the latest is said by kind(), not by its name.
     */
    public function label(string $version): string
    {
        return $this->labels[$version] ?? $version;
    }

    /**
     * @param  list<string>  $versions
     * @return array<string, string>
     */
    public function labelsFor(array $versions): array
    {
        $labels = [];

        foreach ($versions as $version) {
            $labels[$version] = $this->label($version);
        }

        return $labels;
    }

    /**
     * Where a version stands: `latest`, `unreleased` or `older`. The list
     * runs newest first, so a version before the latest is not released yet.
     *
     * @return 'latest'|'unreleased'|'older'
     */
    public function kind(string $version): string
    {
        if ($version === $this->latest) {
            return 'latest';
        }

        $position = array_search($version, $this->list, true);
        $latestPosition = $this->latest === null ? false : array_search($this->latest, $this->list, true);

        return $position !== false && $latestPosition !== false && $position < $latestPosition ? 'unreleased' : 'older';
    }
}
