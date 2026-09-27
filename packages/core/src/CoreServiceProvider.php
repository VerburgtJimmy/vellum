<?php

declare(strict_types=1);

namespace Vellum;

use Illuminate\Support\ServiceProvider;
use Vellum\Console\BuildCommand;
use Vellum\Console\ClearCommand;
use Vellum\Console\IndexCommand;
use Vellum\Support\Paths;

/**
 * Registers Vellum's config, the views of its Markdown components, and the
 * commands that build, index and clear the docs. No routes: serving the docs
 * is the full package's job, or the app's.
 */
final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/vellum.php', 'vellum');
    }

    public function boot(): void
    {
        Paths::resolveConfig();
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'vellum');

        if ($this->app->runningInConsole()) {
            $this->commands([
                BuildCommand::class,
                ClearCommand::class,
                IndexCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/vellum.php' => config_path('vellum.php'),
            ], 'vellum-config');

            $this->optimizes(clear: 'vellum:clear');
        }
    }
}
