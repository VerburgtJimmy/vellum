<?php

declare(strict_types=1);

namespace Vellum\Cache;

use Illuminate\Support\Facades\Cache;
use Vellum\Content\Document;

/**
 * App-cache of Blade-rendered island HTML. Busted by generation, document, config, and app version.
 */
final class FragmentCache
{
    private const GENERATION = 'vellum:fragment-generation';

    private const PREFIX = 'vellum:fragment:';

    /**
     * @param  callable(): string  $resolver
     */
    public function remember(Document $document, callable $resolver): string
    {
        $key = $this->key($document);
        $cached = Cache::get($key);

        if (is_string($cached)) {
            return $cached;
        }

        $html = $resolver();
        Cache::forever($key, $html);

        return $html;
    }

    public function clear(): void
    {
        $generation = (int) Cache::get(self::GENERATION, 0);
        Cache::forever(self::GENERATION, $generation + 1);
    }

    private function key(Document $document): string
    {
        $payload = json_encode([
            $document->html,
            $document->toArray()['islands'],
            config('vellum.components'),
            config('app.version'),
            app()->version(),
            $this->allowlistedSnapshot(),
        ], JSON_THROW_ON_ERROR);

        return self::PREFIX.$this->generation().':'.hash('xxh128', $payload);
    }

    private function generation(): int
    {
        return (int) Cache::get(self::GENERATION, 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function allowlistedSnapshot(): array
    {
        /** @var array<string, mixed> $allowlist */
        $allowlist = config('vellum.components.allowlist', []);
        $snapshot = [];

        foreach ($this->stringList($allowlist['env'] ?? null) as $key) {
            $value = getenv($key);
            $snapshot['env'][$key] = $value === false ? ($_ENV[$key] ?? null) : $value;
        }

        foreach ($this->stringList($allowlist['config'] ?? null) as $key) {
            $snapshot['config'][$key] = config($key);
        }

        foreach ($this->stringList($allowlist['route'] ?? null) as $key) {
            try {
                $snapshot['route'][$key] = app('url')->route($key, [], false);
            } catch (\Throwable) {
                $snapshot['route'][$key] = null;
            }
        }

        return $snapshot;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $keys = [];

        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $keys[] = $item;
            }
        }

        return $keys;
    }
}
