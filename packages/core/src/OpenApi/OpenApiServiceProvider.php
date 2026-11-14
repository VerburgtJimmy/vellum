<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Vellum\Site;

/**
 * Adds the API reference to the docs: its settings, and the source that
 * turns a spec into pages.
 */
final class OpenApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Settings::class, static fn (Application $app): Settings => Settings::fromSite($app->make(Site::class)));
        $this->app->tag([ReferenceSource::class], 'vellum.page-sources');
    }
}
