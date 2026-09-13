<?php

declare(strict_types=1);

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
    | When enabled, docs live under version folders (e.g. v2/, v1/). Requests
    | without a version segment redirect to the latest version.
    |
    */
    'versions' => [
        'enabled' => false,
        'latest' => 'v2',
        'list' => ['v2', 'v1'],
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
        // Fumadocs palettes: neutral (default), black, vitepress, dusk,
        // catppuccin, ocean, purple, solar, emerald, ruby, aspen
        'preset' => 'neutral',
        // oklch hue override for --primary on Neutral (null keeps the default)
        'primary' => null,
        'radius' => '0.5rem',
        // light | dark | system
        'default' => 'system',
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
    */
    'search' => [
        'enabled' => true,
        'hotkey' => 'k',
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
