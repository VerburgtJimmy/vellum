<?php

declare(strict_types=1);

/*
 | These keys and the frontmatter names are frozen until 1.0. Change the
 | values in your app, but do not rename the keys.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Site name
    |--------------------------------------------------------------------------
    |
    | Shown in the docs header and appended to each page's <title>.
    |
    */
    'name' => env('APP_NAME', 'Docs'),

    /*
    |--------------------------------------------------------------------------
    | Content path
    |--------------------------------------------------------------------------
    |
    | Absolute path to the directory that holds your Markdown docs.
    |
    */
    'path' => env('VELLUM_PATH', resource_path('docs')),

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    */
    'route' => [
        'prefix' => 'docs',
        'middleware' => ['web'],
        'domain' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Versions
    |--------------------------------------------------------------------------
    |
    | When enabled, each version has its own folder (e.g. v2/, v1/). The
    | latest version is served at /docs/... without a version segment and
    | the others at /docs/{version}/...; /docs/{latest}/... redirects to the
    | unprefixed URL. The order of "list" is the switcher order. "labels"
    | only changes the switcher text; folders and URLs use the list slug.
    | The latest version is shown as "Latest" unless it has a label.
    |
    */
    'versions' => [
        'enabled' => false,
        'latest' => 'v2',
        'list' => ['v2', 'v1'],
        'labels' => [
            // 'v1' => '1.x (LTS)',
            // 'next' => 'Next',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Repository edit URL
    |--------------------------------------------------------------------------
    |
    | Base URL of the docs folder in your repository, used for the "Edit on
    | GitHub" link.
    | Example: https://github.com/org/repo/edit/main/resources/docs
    |
    */
    'repo' => null,

    /*
    |--------------------------------------------------------------------------
    | Logo
    |--------------------------------------------------------------------------
    |
    | Path to an SVG file, or the name of a Blade view, shown in the header.
    |
    */
    'logo' => null,

    /*
    |--------------------------------------------------------------------------
    | Header links
    |--------------------------------------------------------------------------
    |
    | Extra links shown in the docs header.
    | Example: ['label' => 'GitHub', 'href' => 'https://github.com/...', 'icon' => 'github']
    |
    */
    'links' => [],

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | search: "sidebar" puts search at the top of the sidebar and removes
    | the top header (Fumadocs style). "header" keeps a top bar with the
    | search field in it.
    |
    */
    'layout' => [
        'search' => env('VELLUM_LAYOUT_SEARCH', 'sidebar'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    */
    'theme' => [
        // neutral (default), ocean, laravel
        'preset' => 'neutral',
        // oklch hue for --primary on the neutral preset (null keeps the default)
        'primary' => null,
        // Brand accent for any preset: one colour, or
        // ['light' => '#...', 'dark' => '#...']. Button label colour is derived.
        'accent' => null,
        'radius' => '0.5rem',
        // light | dark | system
        'default' => 'system',
    ],

    /*
    |--------------------------------------------------------------------------
    | Build checks
    |--------------------------------------------------------------------------
    |
    | references: warn during vellum:build about links and images whose
    | target does not exist. Broken references produce no error at runtime.
    |
    | strict: fail the build when a reference is broken, the same as passing
    | --strict. Useful when you cannot add the flag to the build command,
    | for example in CI or a deploy script.
    |
    */
    'checks' => [
        'references' => true,
        'strict' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fonts
    |--------------------------------------------------------------------------
    |
    | Optional HTML (e.g. a <link> tag) injected into the docs layout head.
    |
    */
    'fonts' => null,

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | driver: "minisearch" (default) or "scout". MiniSearch needs no setup
    | and works on every host, including static exports. Use Scout with
    | Meilisearch or Typesense (requires laravel/scout). vellum:export
    | always writes a MiniSearch index, whatever this is set to.
    |
    | The live MiniSearch index is served by a package route, filtered for
    | the current user and cached per visibility set.
    |
    */
    'search' => [
        'enabled' => true,
        'hotkey' => 'k',
        'driver' => env('VELLUM_SEARCH_DRIVER', 'minisearch'),
        'scout' => [
            'index' => 'vellum',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Components
    |--------------------------------------------------------------------------
    |
    | namespaces: Blade component prefixes allowed in Markdown as <x-...>.
    | Add '' or 'app' to allow your app's unprefixed components (<x-alert>).
    |
    | allowlist: the keys the env, config and route value tags may read
    | (e.g. <x-vellum::env />). An empty list allows no keys, so the tag is
    | refused.
    |
    */
    'components' => [
        'namespaces' => ['vellum'],
        'allowlist' => [
            'env' => [],
            'config' => [],
            'route' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Changelog
    |--------------------------------------------------------------------------
    |
    | path: a Keep a Changelog file, rendered at /docs/changelog with an
    | Atom feed at /docs/changelog.atom. Set to null to disable both.
    |
    | unreleased: show the [Unreleased] section on the HTML page. The feed
    | never includes it.
    |
    */
    'changelog' => [
        'path' => env('VELLUM_CHANGELOG', base_path('CHANGELOG.md')),
        'unreleased' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Compile cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'path' => storage_path('framework/vellum'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Static export
    |--------------------------------------------------------------------------
    */
    'export' => [
        'out' => public_path('docs-static'),
        'base_url' => '/',
    ],
];
