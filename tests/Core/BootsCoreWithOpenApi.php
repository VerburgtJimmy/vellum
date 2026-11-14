<?php

declare(strict_types=1);

namespace Vellum\Tests\Core;

use Vellum\CoreServiceProvider;
use Vellum\OpenApi\OpenApiServiceProvider;

/**
 * Boots core with the API reference and nothing of the site, the way an app
 * that requires jimmyverburgt/vellum-core and jimmyverburgt/vellum-openapi
 * has it.
 */
trait BootsCoreWithOpenApi
{
    protected function getPackageProviders($app): array
    {
        return [
            CoreServiceProvider::class,
            OpenApiServiceProvider::class,
        ];
    }
}
