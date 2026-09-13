<?php

declare(strict_types=1);

namespace Vellum\Console;

use Illuminate\Console\Command;
use Vellum\Cache\CompiledStore;
use Vellum\Cache\FragmentCache;

/**
 * Deletes the compiled Vellum cache directory.
 */
final class ClearCommand extends Command
{
    protected $signature = 'vellum:clear';

    protected $description = 'Clear the compiled Vellum documentation and fragment caches';

    public function handle(): int
    {
        $store = new CompiledStore((string) config('vellum.cache.path'));
        $store->clear();
        (new FragmentCache)->clear();

        $this->info('Vellum cache cleared.');

        return self::SUCCESS;
    }
}
