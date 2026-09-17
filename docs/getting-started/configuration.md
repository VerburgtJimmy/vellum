---
title: Configuration
description: Config keys and frontmatter, frozen since 0.5.
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

## OpenAPI

Added in 0.6, off by default. Renders an OpenAPI 3.0 or 3.1 spec as reference pages under the docs site.

| Key | Default | Purpose |
| --- | --- | --- |
| `openapi.enabled` | `false` | Turn the reference pages on |
| `openapi.spec` | `null` | Absolute path to a `.json` or `.yaml` spec. Wins over Scramble |
| `openapi.scramble` | `true` | Export a spec from `dedoc/scramble` when it is installed and `spec` is null |
| `openapi.mount` | `docs` | `docs` nests the reference in the docs site; `standalone` gives it its own root and sidebar |
| `openapi.prefix` | `api` | Path segment for the reference |
| `openapi.title` | `API reference` | Sidebar group and overview heading |
| `openapi.icon` | `null` | Icon for the sidebar group |
| `openapi.group_by` | `tag` | `tag`, or `path` to group by first path segment |
| `openapi.untagged_label` | `Other` | Page name for operations with no tag |
| `openapi.samples` | `['curl', 'php', 'javascript']` | Sample languages, in tab order |
| `openapi.base_url` | `null` | Overrides `servers[0].url` in samples |

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
