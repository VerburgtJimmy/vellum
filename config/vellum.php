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
    | search: run the questions in questions.yml, if the docs have one, against
    | the index the build just wrote, and report how many find their answer in
    | the top five results.
    |
    | search_min: the share of those questions that must find their answer, from
    | 0 to 1, before the build is allowed to pass. 0 reports without failing.
    | VELLUM_SEARCH_MIN sets it, so CI can demand more than a local build does.
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
    | answer: serve {prefix}/_vellum/answer?q=, which runs the same search the
    | browser runs and returns the answer and the top sections as JSON, for a
    | client that cannot run it. It sees only what the caller may see.
    |
    */
    'agents' => [
        'llms_txt' => true,
        'llms_txt_root' => true,
        'content_negotiation' => true,
        'answer' => true,
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
        'model_path' => env('VELLUM_MODEL_PATH', storage_path('vellum/models')),
        'common_tokens' => 1000,

        /*
        | How sure search has to be of its top result before it shows it as an
        | answer card above the results, from 0 to 1. At the default, about one
        | question in five gets a card, and on Vellum's own question sets 22 of
        | 25 cards named the right section; lower it for more cards and more of
        | them wrong.
        */
        'card_threshold' => 0.75,

        /*
        | Optional: have a language model write extra questions for each
        | section at build time. It runs in vellum:build only, never when a
        | page is served. Answers are cached under docs/.vellum/questions and
        | are meant to be committed, so a deploy or CI needs no key.
        |
        | provider: anthropic, openai, or null (the default: no model).
        | model: defaults to claude-haiku-4-5 for anthropic; name one for openai.
        | key: VELLUM_LLM_KEY, or the provider's own ANTHROPIC_API_KEY or
        | OPENAI_API_KEY. A key is only ever read once a provider is set.
        */
        'llm' => [
            'provider' => env('VELLUM_LLM_PROVIDER'),
            'model' => env('VELLUM_LLM_MODEL'),
            'key' => env('VELLUM_LLM_KEY') ?: env('ANTHROPIC_API_KEY') ?: env('OPENAI_API_KEY'),
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
