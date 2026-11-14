<?php

declare(strict_types=1);

namespace Vellum\Content;

use Vellum\Site;

/**
 * Makes a site's source of generated pages. Register one in the container
 * with the `vellum.page-sources` tag, and every repository built for a site
 * asks it for that site's source.
 */
interface PageSourceFactory
{
    /**
     * The source for this site, or null when the site has none of these
     * pages, such as an API reference that is switched off.
     */
    public function forSite(Site $site): ?PageSource;
}
