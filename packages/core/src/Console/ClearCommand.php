<?php

declare(strict_types=1);

namespace Vellum\Console;

use Illuminate\Console\Command;
use Vellum\Cache\CompiledStore;
use Vellum\Cache\FragmentCache;
use Vellum\Site;

/**
 * Deletes the compiled Vellum cache directory.
 */
final class ClearCommand extends Command
{
    protected $signature = 'vellum:clear';

    protected $description = 'Clear the compiled Vellum documentation and fragment caches';

    public function handle(Site $site): int
    {
        $store = new CompiledStore($site->cachePath);
        $store->clear();
        (new FragmentCache)->clear();

        $this->info('Vellum cache cleared.');

        return self::SUCCESS;
    }
}
