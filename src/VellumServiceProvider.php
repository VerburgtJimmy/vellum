<?php

declare(strict_types=1);

namespace Vellum;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Vellum\Console\BuildCommand;
use Vellum\Console\ClearCommand;

/**
 * Registers Vellum config, views, routes, and Artisan commands.
 */
final class VellumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/vellum.php', 'vellum');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'vellum');

        if ($this->app->runningInConsole()) {
            $this->commands([
                BuildCommand::class,
                ClearCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/vellum.php' => config_path('vellum.php'),
            ], 'vellum-config');
        }

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $prefix = (string) config('vellum.route.prefix', 'docs');
        /** @var list<string> $middleware */
        $middleware = config('vellum.route.middleware', ['web']);
        $domain = config('vellum.route.domain');

        $router = Route::middleware($middleware)->prefix($prefix);

        if (is_string($domain) && $domain !== '') {
            $router->domain($domain);
        }

        $router->group(function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }
}
