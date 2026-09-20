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
    | Build checks
    |--------------------------------------------------------------------------
    |
    | references: warn during vellum:build about links and images that point
    | at nothing. Both fail silently at runtime, so the build is the only
    | place they are cheap to catch.
    |
    | strict: turn those warnings into a failed build. Worth switching on in
    | CI, which cannot pass --strict to whatever the deploy script runs.
    |
    */
    'checks' => [
        'references' => true,
        'strict' => false,
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
    */
    'agents' => [
        'llms_txt' => true,
        'llms_txt_root' => true,
        'content_negotiation' => true,
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
    | Answers
    |--------------------------------------------------------------------------
    |
    | Search that understands a question phrased in the reader's words, built
    | once in vellum:build from a small static embedding model. Nothing runs a
    | model at request time. Fetch the model with php artisan vellum:model;
    | without it the build warns and search stays lexical.
    |
    | semantic: false keeps the answers features without the embedding model.
    | model: the Model2Vec model on the Hugging Face hub.
    | model_path: where vellum:model stores it. Keep it out of cache.path,
    | which vellum:clear empties.
    | common_tokens: everyday words kept in the shipped vocabulary beyond the
    | ones your docs use, so a reader's own words still carry meaning.
    |
    */
    'answers' => [
        'enabled' => true,
        'semantic' => true,
        'model' => 'potion-base-8M',
        'model_path' => storage_path('vellum/models'),
        'common_tokens' => 1000,

        /*
        | Optional: have a language model write extra questions for each
        | section at build time. It runs in vellum:build only, never when a
        | page is served. Answers are cached under docs/.vellum/questions and
        | are meant to be committed, so a deploy or CI needs no key.
        |
        | provider: anthropic, openai, or null (the default: no model).
        | model: defaults to claude-opus-5 for anthropic; name one for openai.
        */
        'llm' => [
            'provider' => env('VELLUM_LLM_PROVIDER'),
            'model' => env('VELLUM_LLM_MODEL'),
            'key' => env('VELLUM_LLM_KEY'),
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
