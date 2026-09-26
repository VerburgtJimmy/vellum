<?php

declare(strict_types=1);

namespace Vellum\Support;

use Closure;

/**
 * The sidebar's page tree as HTML. A page shows the tree twice, in the
 * sidebar and in the mobile drawer, and on a large site rendering it is most
 * of the cost of the page. The second copy reuses the first.
 */
final class NavTree
{
    private static ?string $key = null;

    private static string $html = '';

    /**
     * @param  list<array<string, mixed>>  $navigation
     * @param  Closure(array<string, mixed>): bool  $containsActive
     */
    public static function render(array $navigation, ?string $activeSlug, Closure $containsActive): string
    {
        // One entry is enough: both copies are rendered in the same request,
        // one after the other. The key covers the tree itself, which differs
        // per reader, so a long-running worker never serves one reader's tree
        // to another.
        $key = hash('xxh128', (string) json_encode([$navigation, $activeSlug]));

        if ($key !== self::$key) {
            self::$html = view('vellum::components.docs.partials.nav-tree', [
                'nodes' => $navigation,
                'activeSlug' => $activeSlug,
                'containsActive' => $containsActive,
                'depth' => 0,
            ])->render();
            self::$key = $key;
        }

        return self::$html;
    }
}
