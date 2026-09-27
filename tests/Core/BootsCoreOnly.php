<?php

declare(strict_types=1);

namespace Vellum\Tests\Core;

use Vellum\CoreServiceProvider;

/**
 * Boots core on its own, the way an app that requires only
 * jimmyverburgt/vellum-core has it.
 */
trait BootsCoreOnly
{
    protected function getPackageProviders($app): array
    {
        return [
            CoreServiceProvider::class,
        ];
    }
}
