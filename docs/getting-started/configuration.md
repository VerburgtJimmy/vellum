---
title: Configuration
description: Every config key and frontmatter field, all frozen since 0.5.
---

`vellum:install` publishes the config to `config/vellum.php`. The key names have been frozen since 0.5. Later releases can add keys, but none will be renamed before 1.0.

## Site and routing

| Key | Default | Purpose |
| --- | --- | --- |
| `name` | `env('APP_NAME', 'Docs')` | Site name, shown in the header and page titles |
| `path` | `env('VELLUM_PATH', resource_path('docs'))` | Root folder of the Markdown files. A relative path is read from the app root |
| `route.prefix` | `docs` | URL prefix |
| `route.middleware` | `['web']` | Route middleware |
| `route.domain` | `null` | Optional domain for the docs routes. Canonical links and the sitemap use it as their host, with the scheme from `app.url` |
| `repo` | `null` | Base URL for the "Edit on GitHub" link |
| `logo` | `null` | Path to an SVG file, or a Blade view name, for the site logo |
| `links` | `[]` | Extra links, each with `label`, `href` and an optional `icon: github`. Shown in the sidebar footer, or in the header when `layout.search` is `header` |
| `layout.search` | `sidebar` | Where the search field sits: `sidebar` or `header` |
| `fonts` | `null` | HTML added to the layout's head, such as a font stylesheet link |
| `checks.references` | `true` | Warn during `vellum:build` about links and images that point at nothing |
| `checks.strict` | `false` | Fail the build when there are reference warnings |
| `checks.search` | `true` | Ask the questions in `questions.yml`, if the docs have one, during `vellum:build`. See [Answers](/docs/answers#checking-search-in-ci) |
| `checks.search_min` | `env('VELLUM_SEARCH_MIN', 0.0)` | The share of those questions that must find their answer for the build to pass. `0` reports without failing |
| `agents.llms_txt` | `true` | Serve `llms.txt` and `llms-full.txt` under the docs prefix. See [Page actions](/docs/page-actions#for-agents) |
| `agents.llms_txt_root` | `true` | Also serve both at `/llms.txt` and `/llms-full.txt`, unless the app already routes those paths |
| `agents.content_negotiation` | `true` | Serve a page's raw Markdown when the request's `Accept` header prefers `text/markdown` |
| `agents.answer` | `true` | Serve `{prefix}/_vellum/answer?q=`, which answers one question as JSON. See [Answers](/docs/answers#the-answer-endpoint) |
| `cache.path` | `storage_path('framework/vellum')` | Directory for compiled pages |
| `export.out` | `public_path('docs-static')` | Output directory for `vellum:export` |
| `export.base_url` | `/` | URL prefix used inside the export |

## Theme

See [Theming](/docs/theming) for the presets, the accent colour and the contrast target.

```php
'theme' => [
    'preset' => 'neutral',
    'primary' => null,
    'accent' => null,
    'radius' => '0.5rem',
    'default' => 'system',
],
```

## Versions

See [Versions](/docs/versions).

```php
'versions' => [
    'enabled' => false,
    'latest' => 'v2',
    'list' => ['v2', 'v1'],
    'labels' => [],
],
```

## Search

See [Search](/docs/search).

```php
'search' => [
    'enabled' => true,
    'hotkey' => 'k',
    'driver' => env('VELLUM_SEARCH_DRIVER', 'builtin'),
    'scout' => [
        'index' => 'vellum',
    ],
],
```

## Answers

Search that finds a section from a question in the reader's own words. Everything is built in `vellum:build`; no model runs when someone searches.

```php
'answers' => [
    'enabled' => true,
    'semantic' => true,
    'model' => 'potion-base-8M',
    'model_path' => storage_path('vellum/models'),
    'common_tokens' => 1000,
    'card_threshold' => 0.75,
],
```

| Key | Default | Purpose |
| --- | --- | --- |
| `answers.enabled` | `true` | Build the answers data at all |
| `answers.semantic` | `true` | Use the embedding model. `false` keeps search lexical |
| `answers.model` | `potion-base-8M` | Model2Vec model on the Hugging Face hub, fetched with `php artisan vellum:model` |
| `answers.model_path` | `env('VELLUM_MODEL_PATH', storage_path('vellum/models'))` | Where the model is stored. Keep it outside `cache.path`, which `vellum:clear` empties |
| `answers.common_tokens` | `1000` | Everyday words shipped beyond the ones your docs use |
| `answers.card_threshold` | `0.75` | How sure search must be of its top result before it shows an answer card |
| `answers.llm.provider` | `null` | `anthropic`, `openai`, or `null`. With a provider set, `vellum:build` asks the model for five more questions per section |
| `answers.llm.model` | `null` | Defaults to `claude-haiku-4-5` for `anthropic`. The `openai` provider has no default; name one |
| `answers.llm.key` | `VELLUM_LLM_KEY`, else `ANTHROPIC_API_KEY` or `OPENAI_API_KEY` | API key, read only when a provider is set. Without one the build uses the cached questions and says how many are missing |

Generated questions are cached under `docs/.vellum/questions`, keyed by the section they came from. Commit that directory and a deploy never needs the key. The model is only ever called by `vellum:build`. See [Answers](/docs/answers) for what it costs and what it buys.

Without the model on disk, `vellum:build` warns and carries on without the semantic signal.

## Components

See [Value tags](/docs/value-tags) and [Extending](/docs/extending).

```php
'components' => [
    'namespaces' => ['vellum'],
    'allowlist' => [
        'env' => [],
        'config' => [],
        'route' => [],
    ],
],
```

## Changelog

See [Release notes](/docs/releases).

```php
'changelog' => [
    'path' => env('VELLUM_CHANGELOG', base_path('CHANGELOG.md')),
    'unreleased' => false,
],
```

## Frontmatter

Pages accept these frontmatter keys, which are also frozen:

| Key | Purpose |
| --- | --- |
| `title` | Page title. Falls back to the first heading, then the file name |
| `description` | Meta description, also shown on the prev/next cards |
| `slug` | Overrides the URL slug |
| `order` | Position among sibling pages when `meta.json` does not list them |
| `full` | Hides the table of contents column |
| `access` | `guest`, `auth`, or a gate name. See [Gating](/docs/gating). |
| `updated` | Last-updated date, `2026-09-17` or ISO 8601 (`2026-09-17T10:00:00+02:00`). Without it, the file's last git commit date is used, when the docs are in a full git clone. File modification times are never used |
| `questions` | Questions the page answers, as a list. Search matches a reader's question against them, alongside the ones Vellum derives from headings, commands and config keys |
| `aliases` | Other names readers use for the page's subject, as a list. A search containing one also matches the page title and the other aliases |

A folder's `meta.json` accepts `title`, `defaultOpen`, `pages` and `access`. A `_meta.md` file can set the folder's `access` or title in frontmatter instead. See [Navigation](/docs/writing/navigation).

Unknown keys are stored but ignored. Do not treat that behaviour as an API.
