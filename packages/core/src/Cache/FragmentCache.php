<?php

declare(strict_types=1);

namespace Vellum\Cache;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Cache;
use Vellum\Content\Document;
use Vellum\Markdown\Islands\Island;
use Vellum\Site\Components;

/**
 * App-cache of Blade-rendered island HTML. Busted by generation, document, config, and app version.
 * Only pages built from Vellum's own components are cached: a host component can
 * read the signed-in user, so its output belongs to one request, not to the page.
 */
final class FragmentCache
{
    private const GENERATION = 'vellum:fragment-generation';

    private const PREFIX = 'vellum:fragment:';

    public function __construct(
        private readonly Components $components = new Components,
    ) {}

    /**
     * @param  callable(): string  $resolver
     */
    public function remember(Document $document, callable $resolver): string
    {
        if (! self::cacheable($document->islands)) {
            return $resolver();
        }

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

    /**
     * @param  list<Island>  $islands
     */
    private static function cacheable(array $islands): bool
    {
        foreach ($islands as $island) {
            if (! str_starts_with($island->name, 'vellum::') || ! self::cacheable($island->children)) {
                return false;
            }
        }

        return true;
    }

    private function key(Document $document): string
    {
        $payload = json_encode([
            $document->html,
            $document->toArray()['islands'],
            $this->components->config,
            $this->appSettings()->get('app.version'),
            app()->version(),
            $this->allowlistedSnapshot(),
        ], JSON_THROW_ON_ERROR);

        return self::PREFIX.$this->generation().':'.hash('xxh128', $payload);
    }

    public function generation(): int
    {
        return (int) Cache::get(self::GENERATION, 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function allowlistedSnapshot(): array
    {
        $allowlist = $this->components->allowlist;
        $snapshot = [];

        foreach ($allowlist['env'] as $key) {
            $value = getenv($key);
            $snapshot['env'][$key] = $value === false ? ($_ENV[$key] ?? null) : $value;
        }

        // The app's own settings, which a value tag prints. Not Vellum's.
        foreach ($allowlist['config'] as $key) {
            $snapshot['config'][$key] = $this->appSettings()->get($key);
        }

        foreach ($allowlist['route'] as $key) {
            try {
                $snapshot['route'][$key] = app('url')->route($key, [], false);
            } catch (\Throwable) {
                $snapshot['route'][$key] = null;
            }
        }

        return $snapshot;
    }

    private function appSettings(): Repository
    {
        return app('config');
    }
}
