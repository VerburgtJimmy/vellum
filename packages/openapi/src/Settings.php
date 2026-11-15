<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Vellum\Site;
use Vellum\Support\Slug;

/**
 * The `openapi` block of a site's config, read once.
 *
 * It also says where the reference pages live among the docs. Every
 * generated slug opens with one prefix, so the compiled store holds the
 * reference beside the Markdown pages and an endpoint can never take the
 * slug of a page someone wrote.
 */
final readonly class Settings
{
    /**
     * @param  list<string>|null  $watch  Directories whose changes mean Scramble's spec changed. Null watches app/ and routes/.
     * @param  array<array-key, mixed>  $samples
     * @param  string  $cachePath  Where a spec exported from Scramble is written. Empty uses storage/framework/vellum.
     */
    public function __construct(
        public bool $enabled = false,
        public ?string $spec = null,
        public bool $scramble = true,
        public ?array $watch = null,
        public string $prefix = 'api',
        public bool $separate = true,
        public ?string $title = null,
        public string $groupBy = 'tag',
        public ?string $untaggedLabel = null,
        public array $samples = ['curl'],
        public ?string $baseUrl = null,
        public bool $playground = true,
        public bool $proxy = false,
        public bool $proxyPrivate = false,
        public string $access = 'guest',
        public string $cachePath = '',
    ) {}

    public static function fromSite(Site $site): self
    {
        return self::fromArray($site->get('openapi'), $site->cachePath);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config, string $cachePath = ''): self
    {
        $spec = $config['spec'] ?? null;
        $watch = $config['watch'] ?? null;
        $prefix = $config['prefix'] ?? 'api';
        $prefix = is_string($prefix) ? Slug::from(trim($prefix, '/')) : '';
        $title = $config['title'] ?? null;
        $label = $config['untagged_label'] ?? null;
        $samples = $config['samples'] ?? ['curl'];
        $baseUrl = $config['base_url'] ?? null;
        $access = $config['access'] ?? null;

        return new self(
            enabled: (bool) ($config['enabled'] ?? false),
            spec: is_string($spec) && $spec !== '' ? $spec : null,
            scramble: (bool) ($config['scramble'] ?? true),
            watch: is_array($watch) ? array_values(array_filter($watch, 'is_string')) : null,
            prefix: $prefix === '' ? 'api' : $prefix,
            separate: ($config['sidebar'] ?? 'separate') !== 'docs',
            title: is_string($title) && trim($title) !== '' ? $title : null,
            groupBy: ($config['group_by'] ?? null) === 'path' ? 'path' : 'tag',
            untaggedLabel: is_string($label) && trim($label) !== '' ? $label : null,
            samples: is_array($samples) ? $samples : ['curl'],
            baseUrl: is_string($baseUrl) && $baseUrl !== '' ? $baseUrl : null,
            playground: (bool) ($config['playground'] ?? true),
            proxy: (bool) ($config['proxy'] ?? false),
            proxyPrivate: (bool) ($config['proxy_private'] ?? false),
            access: is_string($access) && trim($access) !== '' ? trim($access) : 'guest',
            cachePath: $cachePath,
        );
    }

    /**
     * Whether a slug belongs to the reference.
     */
    public function owns(string $slug): bool
    {
        $slug = trim($slug, '/');

        return $slug === $this->prefix || str_starts_with($slug, $this->prefix.'/');
    }

    /**
     * Whether a reader can send a request from the reference.
     */
    public function playgroundOn(): bool
    {
        return $this->enabled && $this->playground;
    }
}
