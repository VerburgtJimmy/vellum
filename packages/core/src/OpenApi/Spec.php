<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

/**
 * A parsed, fully resolved OpenAPI document.
 *
 * Every $ref is already inlined, so callers read plain arrays. Warnings
 * collected during parsing travel with the spec rather than being printed
 * where they happen, because the build command is the only place that knows
 * how to show them.
 */
final readonly class Spec
{
    /**
     * @param  array<string, mixed>  $document
     * @param  list<Operation>  $operations
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $path,
        public string $version,
        public array $document,
        public array $operations,
        public array $warnings = [],
    ) {}

    public function title(): string
    {
        $title = $this->document['info']['title'] ?? null;

        return is_string($title) && trim($title) !== '' ? $title : 'API reference';
    }

    public function description(): ?string
    {
        $description = $this->document['info']['description'] ?? null;

        return is_string($description) && trim($description) !== '' ? $description : null;
    }

    public function apiVersion(): ?string
    {
        $version = $this->document['info']['version'] ?? null;

        return is_string($version) && trim($version) !== '' ? $version : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function servers(): array
    {
        $servers = $this->document['servers'] ?? [];

        if (! is_array($servers)) {
            return [];
        }

        return array_values(array_filter($servers, is_array(...)));
    }

    /**
     * The first server URL, which is what samples target unless overridden.
     */
    public function serverUrl(): ?string
    {
        foreach ($this->servers() as $server) {
            if (is_string($server['url'] ?? null) && $server['url'] !== '') {
                return rtrim($server['url'], '/');
            }
        }

        return null;
    }

    /**
     * Tag definitions keyed by name, for descriptions on group pages.
     *
     * @return array<string, array<string, mixed>>
     */
    public function tags(): array
    {
        $tags = $this->document['tags'] ?? [];
        $out = [];

        if (! is_array($tags)) {
            return [];
        }

        foreach ($tags as $tag) {
            if (is_array($tag) && is_string($tag['name'] ?? null)) {
                $out[$tag['name']] = $tag;
            }
        }

        return $out;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function securitySchemes(): array
    {
        $schemes = $this->document['components']['securitySchemes'] ?? [];

        if (! is_array($schemes)) {
            return [];
        }

        return array_filter($schemes, is_array(...));
    }

    /**
     * Security applied to every operation that does not override it.
     *
     * @return list<array<array-key, mixed>>
     */
    public function defaultSecurity(): array
    {
        $security = $this->document['security'] ?? [];

        if (! is_array($security)) {
            return [];
        }

        return array_values(array_filter($security, is_array(...)));
    }

    /**
     * Webhooks and callbacks are listed by name only in 0.6.
     *
     * @return list<string>
     */
    public function webhookNames(): array
    {
        $webhooks = $this->document['webhooks'] ?? [];

        if (! is_array($webhooks)) {
            return [];
        }

        return array_values(array_filter(array_keys($webhooks), is_string(...)));
    }
}
