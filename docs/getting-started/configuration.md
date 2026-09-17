---
title: Configuration
description: Frozen config keys and frontmatter for Vellum 0.5.
---

Published as `config/vellum.php`. **0.5 freezes these names.** Add values, do not rename keys.

## Site and routing

| Key | Default | Purpose |
| --- | --- | --- |
| `name` | `env('APP_NAME')` | Site name in the header and titles |
| `path` | `resource_path('docs')` | Markdown root |
| `route.prefix` | `docs` | URL prefix |
| `route.middleware` | `['web']` | Route middleware |
| `route.domain` | `null` | Optional domain |
| `repo` | `null` | Base URL for "Edit on GitHub" |
| `logo` | `null` | SVG path or Blade view in the header |
| `links` | `[]` | Extra links in the sidebar footer (`label`, `href`, optional `icon: github`) |
| `layout.search` | `sidebar` | `sidebar` or `header` |
| `fonts` | `null` | HTML injected into the layout head |
| `checks.references` | `true` | Warn at build time about links and images that point at nothing |
| `checks.strict` | `false` | Turn those warnings into a failed build |
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

Frozen page keys:

| Key | Purpose |
| --- | --- |
| `title` | Page title (falls back to the first heading or the file name) |
| `description` | Meta description and prev/next cards |
| `slug` | Override the URL slug |
| `order` | Sort among siblings when `meta.json` does not list pages |
| `full` | Hide the table of contents column |
| `access` | `guest`, `auth`, or a gate name. See [Gating](/docs/gating). |

Folder `meta.json`: `title`, `defaultOpen`, `pages`, `access`. See [Navigation](/docs/writing/navigation). `_meta.md` can set `access` (and the usual matter) for the folder.

Unknown keys are stored and ignored. Do not rely on that as an API.
