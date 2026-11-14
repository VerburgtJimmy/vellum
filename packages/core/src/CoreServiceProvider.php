<?php

declare(strict_types=1);

namespace Vellum;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Vellum\Console\BuildCommand;
use Vellum\Console\ClearCommand;
use Vellum\Console\IndexCommand;
use Vellum\Content\ContentRepository;
use Vellum\Markdown\Islands\MarkdownPipeline;
use Vellum\OpenApi\OpenApiPages;
use Vellum\OpenApi\PageBuilder;
use Vellum\OpenApi\Settings;
use Vellum\Support\Paths;

/**
 * Registers Vellum's config, the views of its Markdown components, and the
 * commands that build, index and clear the docs. No routes: serving the docs
 * is the full package's job, or the app's.
 *
 * This is where config is read. The site is built from it here and handed to
 * everything else, so nothing below reads config on its own. The bindings are
 * scoped, not singletons: a worker that serves many requests (Octane, a queue)
 * starts each one with a fresh repository, since a repository keeps the
 * navigation it has loaded.
 */
final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/vellum.php', 'vellum');

        $this->app->scoped(Site::class, self::site(...));
        $this->app->scoped(Settings::class, static fn (Application $app): Settings => Settings::fromSite($app->make(Site::class)));
        $this->app->scoped(ContentRepository::class, static fn (Application $app): ContentRepository => self::repository($app, $app->make(Site::class)));
    }

    /**
     * The site the app's config describes.
     */
    public static function site(Application $app): Site
    {
        $config = $app->make('config');
        $vellum = $config->get('vellum', []);
        $appUrl = $config->get('app.url', '');

        return Site::fromArray(
            is_array($vellum) ? $vellum : [],
            is_scalar($appUrl) ? (string) $appUrl : '',
            $app->environment('local'),
        );
    }

    /**
     * A repository for a site, with the API reference when the site has one.
     */
    public static function repository(Application $app, Site $site): ContentRepository
    {
        $settings = Settings::fromSite($site);

        return new ContentRepository(
            site: $site,
            openApi: $settings->enabled ? new OpenApiPages(
                $settings,
                builder: new PageBuilder($app->make('view'), $settings, new MarkdownPipeline(components: $site->components)),
            ) : null,
        );
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
