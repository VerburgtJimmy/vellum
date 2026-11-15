<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Vellum\Site;

/**
 * Adds the API reference to the docs: its settings, the source that turns a
 * spec into pages, and the views those pages are written with.
 */
final class OpenApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Settings::class, static fn (Application $app): Settings => Settings::fromSite($app->make(Site::class)));
        $this->app->tag([ReferenceSource::class], 'vellum.page-sources');
    }

    public function boot(): void
    {
        // Under core's view namespace, so a page's views stay vellum::openapi.*
        // and a copy published to resources/views/vendor/vellum/openapi/ still
        // takes their place.
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'vellum');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'vellum-openapi');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/vellum-openapi'),
            ], 'vellum-lang');
        }
    }
}
