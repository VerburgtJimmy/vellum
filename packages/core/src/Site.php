<?php

declare(strict_types=1);

namespace Vellum;

use Vellum\Site\Checks;
use Vellum\Site\Components;
use Vellum\Site\Search;
use Vellum\Site\Versions;
use Vellum\Support\Paths;

/**
 * One docs site: everything core reads from config('vellum'), read once.
 *
 * The service provider builds it from config and every class that needs a
 * setting is handed this object, or the part of it that it needs. Nothing
 * below it reads global config, so a site can be built for a test, or a
 * second one in the same app, without touching config at all.
 *
 * The defaults here are the ones each setting had where it used to be read,
 * not the ones in config/vellum.php: Laravel merges a published config file
 * one level deep, so a published `openapi` block without `title` must still
 * get the title the code always fell back to.
 */
final readonly class Site
{
    /**
     * @param  array<string, mixed>  $config  The whole config array, kept for the
     *                                        parts core passes along without reading,
     *                                        such as the OpenAPI block.
     */
    public function __construct(
        public string $contentPath,
        public string $routePrefix,
        public string $cachePath,
        public string $appUrl,
        public bool $isLocal,
        public Versions $versions,
        public Components $components,
        public Checks $checks,
        public Search $search,
        public ?string $changelogPath,
        public bool $changelogUnreleased,
        private array $config = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $config  What config('vellum') holds.
     * @param  string  $appUrl  config('app.url'), for telling external links apart.
     * @param  bool  $isLocal  Whether pages compile on request and follow edits.
     */
    public static function fromArray(array $config, string $appUrl = '', bool $isLocal = false): self
    {
        /** @var array<string, mixed> $config */
        $route = self::section($config, 'route');
        $cache = self::section($config, 'cache');
        $changelog = $config['changelog'] ?? null;

        return new self(
            contentPath: self::path($config['path'] ?? null),
            routePrefix: trim(self::string($route['prefix'] ?? 'docs'), '/'),
            cachePath: self::path($cache['path'] ?? null),
            appUrl: $appUrl,
            isLocal: $isLocal,
            versions: Versions::fromArray(self::section($config, 'versions')),
            components: Components::fromArray(self::section($config, 'components')),
            checks: Checks::fromArray(self::section($config, 'checks')),
            search: Search::fromArray(self::section($config, 'search')),
            changelogPath: self::changelogPath($changelog),
            changelogUnreleased: is_array($changelog) && (bool) ($changelog['unreleased'] ?? false),
            config: $config,
        );
    }

    /**
     * A config block as it was given, for a part of Vellum that reads its own
     * settings, such as the API reference.
     *
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        return self::section($this->config, $key);
    }

    /**
     * The changelog may be configured as a bare path, or as a block with one.
     */
    private static function changelogPath(mixed $configured): ?string
    {
        $file = is_array($configured) ? ($configured['path'] ?? null) : $configured;

        return is_string($file) && $file !== '' ? Paths::absolute($file) : null;
    }

    private static function path(mixed $value): string
    {
        $value = self::string($value);

        return $value === '' ? '' : Paths::absolute($value);
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param  array<array-key, mixed>  $config
     * @return array<string, mixed>
     */
    private static function section(array $config, string $key): array
    {
        $value = $config[$key] ?? [];

        /** @var array<string, mixed> */
        return is_array($value) ? $value : [];
    }
}
