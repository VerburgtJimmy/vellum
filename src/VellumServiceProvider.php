<?php

declare(strict_types=1);

namespace Vellum;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Factory;
use Vellum\Console\ExportCommand;
use Vellum\Console\InstallCommand;
use Vellum\Exceptions\ContentError;
use Vellum\Http\Controllers\AssetController;
use Vellum\Http\Middleware\CompressHtmlResponse;
use Vellum\Http\RootLlmsTxtRoutes;

/**
 * The docs site on top of core: routes, layout, theme, assets, and the
 * install and export commands.
 */
final class VellumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(CoreServiceProvider::class);
    }

    public function boot(): void
    {
        $this->registerViews();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                ExportCommand::class,
            ]);
        }

        $this->renderContentErrors();
        $this->registerAssetRoutes();
        $this->registerRoutes();
    }

    /**
     * The site's views join core's under the vellum namespace, ahead of them,
     * so a component both ship renders in the site's version. Copies the app
     * published under resources/views/vendor/vellum still come first.
     */
    private function registerViews(): void
    {
        $this->callAfterResolving('view', function (Factory $view): void {
            $view->prependNamespace('vellum', __DIR__.'/../resources/views');

            /** @var list<string> $paths */
            $paths = config('view.paths', []);

            foreach (array_reverse($paths) as $path) {
                if (is_dir($published = $path.'/vendor/vellum')) {
                    $view->prependNamespace('vellum', $published);
                }
            }
        });
    }

    /**
     * A mistake in the docs gets a Vellum-branded 500 instead of Laravel's
     * generic page. The reason and file show only with APP_DEBUG on, so
     * production never shows a reader a filesystem path.
     */
    private function renderContentErrors(): void
    {
        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if (! $handler instanceof Handler) {
                return;
            }

            $handler->renderable(function (ContentError $error, Request $request): ?Response {
                if ($request->expectsJson()) {
                    return null;
                }

                $debug = (bool) config('app.debug', false);
                $html = view()->file(__DIR__.'/../resources/views/pages/error.blade.php', [
                    'detail' => $debug ? $error->getMessage() : null,
                    'file' => $debug ? $error->contentFile() : null,
                ])->render();

                return new Response($html, 500, ['Content-Type' => 'text/html; charset=UTF-8']);
            });
        });
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
