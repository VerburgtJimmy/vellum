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
    'driver' => env('VELLUM_SEARCH_DRIVER', 'minisearch'),
    'scout' => [
        'index' => 'vellum',
    ],
],
```

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

A folder's `meta.json` accepts `title`, `defaultOpen`, `pages` and `access`. A `_meta.md` file can set the folder's `access` or title in frontmatter instead. See [Navigation](/docs/writing/navigation).

Unknown keys are stored but ignored. Do not treat that behaviour as an API.
