<?php

declare(strict_types=1);

/*
 | 0.5 freezes these keys. Add values in the host app; do not rename
 | keys or frontmatter names until 1.0.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Site name
    |--------------------------------------------------------------------------
    |
    | Shown in the header and used as a fallback document title prefix.
    |
    */
    'name' => env('APP_NAME', 'Docs'),

    /*
    |--------------------------------------------------------------------------
    | Content path
    |--------------------------------------------------------------------------
    |
    | Absolute path to the Markdown documentation root.
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
    | When enabled, docs live under version folders (e.g. v2/, v1/). The
    | latest version is served at /docs/... with no version segment. Other
    | versions are at /docs/v1/.... /docs/{latest}/... redirects to the
    | unprefixed URL. List order is switcher order. Optional labels
    | override the switcher text (folder and URL stay the list slug).
    | Unlabelled latest is "Latest".
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
    | Base URL for "Edit on GitHub" links, pointing at the docs folder.
    | Example: https://github.com/org/repo/edit/main/resources/docs
    |
    */
    'repo' => null,

    /*
    |--------------------------------------------------------------------------
    | Logo
    |--------------------------------------------------------------------------
    |
    | Path to an SVG file or a Blade view name rendered in the header.
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
    | search: header keeps the current top bar with the search field;
    | sidebar places search at the top of the sidebar (Fumadocs style)
    | and removes the top header.
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
        // oklch hue override for --primary on Neutral (null keeps the default)
        'primary' => null,
        // Brand accent applied to every preset: one colour, or
        // ['light' => '#...', 'dark' => '#...']. The label colour is derived.
        'accent' => null,
        'radius' => '0.5rem',
        // light | dark | system
        'default' => 'system',
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAPI
    |--------------------------------------------------------------------------
    |
    | Renders an OpenAPI 3.0 or 3.1 spec as reference pages under the docs
    | site. Vellum presents a spec; it does not generate one. Point 'spec' at
    | a file, or leave it null and let Scramble export one when it is
    | installed. Pages mount at {route.prefix}/{openapi.prefix}.
    |
    | group_by: tag (default) or path, which groups by first path segment.
    | samples: languages for the request examples, in tab order.
    | base_url: overrides servers[0].url in those samples.
    |
    */
    'openapi' => [
        'enabled' => false,
        'spec' => null,
        'scramble' => true,
        'prefix' => 'api',
        'title' => 'API reference',
        'icon' => null,
        'group_by' => 'tag',
        'samples' => ['curl', 'php', 'javascript'],
        'base_url' => null,
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
    | driver: minisearch (default) or scout. MiniSearch is zero-setup and
    | works on every host, including static export. Scout is opt-in for
    | Meilisearch or Typesense (composer require laravel/scout). Export
    | always writes MiniSearch JSON, regardless of this setting.
    |
    | The live MiniSearch index is a package route, filtered for the current
    | user and cached per visibility set. It is not a public static asset.
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
    | namespaces: Blade prefixes allowed in Markdown as <x-...>. Default is
    | vellum. Add '' or 'app' to allow unprefixed host components (<x-alert>).
    | allowlist: keys permitted on <x-vellum::env />, config, and route.
    | Empty lists refuse those value tags.
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
    | path: Keep a Changelog file. Rendered at /docs/changelog with an Atom
    | feed at /docs/changelog.atom. Set path to null to disable both.
    | unreleased: show [Unreleased] on the HTML page. The feed never includes it.
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
