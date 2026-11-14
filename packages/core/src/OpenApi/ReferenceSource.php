<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Vellum\Content\PageSource;
use Vellum\Content\PageSourceFactory;
use Vellum\Markdown\Islands\MarkdownPipeline;
use Vellum\Site;

/**
 * The API reference of a site whose config switches it on.
 */
final readonly class ReferenceSource implements PageSourceFactory
{
    public function __construct(
        private ViewFactory $view,
    ) {}

    public function forSite(Site $site): ?PageSource
    {
        $settings = Settings::fromSite($site);

        if (! $settings->enabled) {
            return null;
        }

        return new OpenApiPages(
            $settings,
            builder: new PageBuilder($this->view, $settings, new MarkdownPipeline(components: $site->components)),
        );
    }
}
