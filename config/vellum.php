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
    | search: run the questions in questions.yml, if the docs have one,
    | against the search index the build just wrote, and report how many find
    | their answer in the top five results.
    |
    | search_min: the share of those questions, from 0 to 1, that must find
    | their answer for the build to pass. 0 reports without failing. Set it
    | with VELLUM_SEARCH_MIN to require more in CI than in a local build.
    |
    */
    'checks' => [
        'references' => true,
        'strict' => false,
        'search' => true,
        'search_min' => (float) env('VELLUM_SEARCH_MIN', 0.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agents
    |--------------------------------------------------------------------------
    |
    | llms_txt: serve {prefix}/llms.txt, an index of every public page linking
    | to its raw Markdown, and {prefix}/llms-full.txt, all of that Markdown in
    | one file. vellum:export writes both at the export root.
    |
    | llms_txt_root: also serve both at /llms.txt and /llms-full.txt, where
    | agents look first. Skipped for a path the app already routes itself.
    |
    | content_negotiation: answer a docs page request whose Accept header
    | prefers text/markdown with the page's raw Markdown instead of HTML.
    | Page responses then send Vary: Accept so caches keep the two apart.
    |
    | search: serve {prefix}/_vellum/search?q=, which runs the same search as
    | the browser and returns the top sections as JSON, for clients such as
    | agents and scripts. Results only include pages the caller may open.
    |
    */
    'agents' => [
        'llms_txt' => true,
        'llms_txt_root' => true,
        'content_negotiation' => true,
        'search' => true,
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
    | driver: "builtin" (default) or "scout". The built-in driver searches in
    | the browser, needs no service and works on every host, including static
    | exports. Use Scout with Meilisearch or Typesense (requires
    | laravel/scout); it searches whole pages on the server. vellum:export
    | always uses the built-in driver. "minisearch" is accepted as the old
    | name of "builtin".
    |
    | The built-in driver reads its index from a package route, filtered for
    | the current user and cached per visibility set.
    |
    */
    'search' => [
        'enabled' => true,
        'hotkey' => 'k',
        'driver' => env('VELLUM_SEARCH_DRIVER', 'builtin'),
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
