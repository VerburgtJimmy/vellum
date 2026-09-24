---
title: Configuration
description: Every config key and frontmatter field. The names have been frozen since 0.5.
---

Published as `config/vellum.php`. **These names have been frozen since 0.5.** Later releases add keys; none is renamed before 1.0.

## Site and routing

| Key | Default | Purpose |
| --- | --- | --- |
| `name` | `env('APP_NAME')` | Site name in the header and titles |
| `path` | `resource_path('docs')` | Markdown root |
| `route.prefix` | `docs` | URL prefix |
| `route.middleware` | `['web']` | Route middleware |
| `route.domain` | `null` | Optional domain. Canonical links and the sitemap use it as their host, with the scheme from `app.url` |
| `repo` | `null` | Base URL for "Edit on GitHub" |
| `logo` | `null` | SVG path or Blade view in the header |
| `links` | `[]` | Extra links in the sidebar footer (`label`, `href`, optional `icon: github`) |
| `layout.search` | `sidebar` | `sidebar` or `header` |
| `fonts` | `null` | HTML injected into the layout head |
| `checks.references` | `true` | Warn at build time about links and images that point at nothing |
| `checks.strict` | `false` | Turn those warnings into a failed build |
| `checks.search` | `true` | Ask the questions in `questions.yml`, if the docs have one, at build time. See [Answers](/docs/answers#checking-search-in-ci) |
| `checks.search_min` | `env('VELLUM_SEARCH_MIN', 0.0)` | The share of those questions that must find their answer before the build passes. `0` reports without failing |
| `agents.llms_txt` | `true` | Serve `llms.txt` and `llms-full.txt` under the docs prefix. See [Page actions](/docs/page-actions#for-agents) |
| `agents.llms_txt_root` | `true` | Also serve both at `/llms.txt` and `/llms-full.txt`, unless the app routes those paths itself |
| `agents.content_negotiation` | `true` | Serve a page's raw Markdown when the request's `Accept` header prefers `text/markdown` |
| `agents.answer` | `true` | Serve `{prefix}/_vellum/answer?q=`, one question answered as JSON. See [Answers](/docs/answers#the-answer-endpoint) |
| `cache.path` | `storage_path('framework/vellum')` | Compile cache |
| `export.out` | `public_path('docs-static')` | Static export directory |
| `export.base_url` | `/` | Prefix inside the export |

## Theme

See [Theming](/docs/theming) for the presets, the accent, and the contrast target.

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

Frozen page keys:

| Key | Purpose |
| --- | --- |
| `title` | Page title (falls back to the first heading or the file name) |
| `description` | Meta description and prev/next cards |
| `slug` | Override the URL slug |
| `order` | Sort among siblings when `meta.json` does not list pages |
| `full` | Hide the table of contents column |
| `access` | `guest`, `auth`, or a gate name. See [Gating](/docs/gating). |
| `updated` | Last-updated date, `2026-09-17` or ISO 8601 (`2026-09-17T10:00:00+02:00`). Without it, the file's last git commit date is used, when the docs are in a full git clone. File modification times are never used |
| `questions` | Questions the page answers, as a list. Search matches a reader's question against them, alongside the ones Vellum derives from headings, commands and config keys |
| `aliases` | Other names readers use for the page's subject, as a list. A search containing one also matches the page title and the other aliases |

Folder `meta.json`: `title`, `defaultOpen`, `pages`, `access`. See [Navigation](/docs/writing/navigation). `_meta.md` can set `access` (and the usual matter) for the folder.

Unknown keys are stored and ignored. Do not rely on that as an API.
