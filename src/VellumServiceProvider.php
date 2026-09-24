<?php

declare(strict_types=1);

namespace Vellum;

use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Vellum\Console\BuildCommand;
use Vellum\Console\ClearCommand;
use Vellum\Console\ExportCommand;
use Vellum\Console\IndexCommand;
use Vellum\Console\InstallCommand;
use Vellum\Console\ModelCommand;
use Vellum\Http\Controllers\AssetController;
use Vellum\Http\Middleware\CompressHtmlResponse;
use Vellum\Http\RootLlmsTxtRoutes;
use Vellum\Support\Paths;

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
        Paths::resolveConfig();
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'vellum');

        if ($this->app->runningInConsole()) {
            $this->commands([
                BuildCommand::class,
                ClearCommand::class,
                InstallCommand::class,
                IndexCommand::class,
                ExportCommand::class,
                ModelCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/vellum.php' => config_path('vellum.php'),
            ], 'vellum-config');

            $this->optimizes(clear: 'vellum:clear');
        }

        $this->registerAssetRoutes();
        $this->registerRoutes();
    }

    private function registerAssetRoutes(): void
    {
        // No web middleware: avoid session cookies on immutable hashed assets.
        Route::get('/vendor/vellum/vellum.css', [AssetController::class, 'css'])
            ->name('vellum.assets.css');
        Route::get('/vendor/vellum/vellum.js', [AssetController::class, 'js'])
            ->name('vellum.assets.js');
        Route::get('/vendor/vellum/vellum-search.js', [AssetController::class, 'search'])
            ->name('vellum.assets.search');
        Route::get('/vendor/vellum/vellum-semantic.js', [AssetController::class, 'semantic'])
            ->name('vellum.assets.semantic');
        Route::get('/vendor/vellum/vellum-anchor.js', [AssetController::class, 'anchor'])
            ->name('vellum.assets.anchor');
        Route::get('/vendor/vellum/vellum-focus.js', [AssetController::class, 'focus'])
            ->name('vellum.assets.focus');
    }

    private function registerRoutes(): void
    {
        $prefix = (string) config('vellum.route.prefix', 'docs');
        /** @var list<string> $middleware */
        $middleware = config('vellum.route.middleware', ['web']);
        $middleware[] = CompressHtmlResponse::class;
        $domain = config('vellum.route.domain');

        $router = Route::middleware($middleware)->prefix($prefix);

        if (is_string($domain) && $domain !== '') {
            $router->domain($domain);
        }

        $router->group(function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });

        // After boot, so the app's own routes are loaded and can be checked.
        // With cached routes, whatever was registered at cache time is what
        // the cache holds, so there is nothing to add.
        $this->app->booted(function (): void {
            if ($this->app instanceof CachesRoutes && $this->app->routesAreCached()) {
                return;
            }

            RootLlmsTxtRoutes::register($this->app->make(Router::class));
        });
    }
}
