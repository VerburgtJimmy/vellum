---
title: Configuration
description: Config keys that shape a Vellum site.
---

Published as `config/vellum.php`.

## Content and routing

| Key | Default | Purpose |
| --- | --- | --- |
| `name` | `env('APP_NAME')` | Site name in the header and titles |
| `path` | `resource_path('docs')` | Markdown root |
| `route.prefix` | `docs` | URL prefix |
| `repo` | `null` | Base URL for "Edit on GitHub" |

## Changelog

```php
'changelog' => [
    'path' => env('VELLUM_CHANGELOG', base_path('CHANGELOG.md')),
    'unreleased' => false,
],
```

`path` is a Keep a Changelog file. Set it to `null` to disable the page and feed.

The page is `/docs/changelog`. The Atom feed is `/docs/changelog.atom`. `[Unreleased]` stays in the source file for authors. The feed never includes it. The HTML page shows it only when `unreleased` is `true`.

Headings may be `## [1.2.0] - 2026-09-13` or `## 1.2.0`.

## Components

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

`namespaces` is the list of Blade prefixes allowed as `<x-…>` in Markdown. Add `''` or `'app'` to allow unprefixed host components such as `<x-alert>`.

`allowlist` is the only way `<x-vellum::env />`, `config`, and `route` resolve. Empty lists refuse every key.

See [Extending](/docs/extending) for registering your own components.

## Versions

```php
'versions' => [
    'enabled' => false,
    'latest' => 'v2',
    'list' => ['v2', 'v1'],
],
```

When enabled, Markdown lives in version folders (`docs/v2/`, `docs/v1/`). The `latest` folder is served at `/docs/...` with no version in the URL. Other folders are at `/docs/v1/...`. Visiting `/docs/v2/...` redirects to the unprefixed URL with HTTP 301. The switcher labels `latest` as **Latest**. Changelog stays at `/docs/changelog` for every version. Do not detect versions from git tags.

## Search

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

`minisearch` is the default. It needs no extra services and works on every host. The live index is `/docs/_vellum/search.json`, filtered for the current user and cached per visibility set (guest, auth, and per-gate combinations). Responses send an ETag (index hash plus visibility) so `must-revalidate` can 304. It is not a public immutable file.

`scout` is opt-in for people who already run Meilisearch or Typesense and want heading-level relevance at scale. Install Laravel Scout (`composer require laravel/scout`) and set `VELLUM_SEARCH_DRIVER=scout`. The same visibility filter runs at query time. `vellum:build` and `vellum:index` sync Scout when that driver is on.

`vellum:export` always writes MiniSearch JSON, regardless of `driver`. Gated pages are dropped from the exported index.

## Access

```yaml
---
title: Billing
access: auth
---
```

`access` is `guest` (default), `auth`, or a Laravel gate name. It is resolved once per page into the same visibility set search uses, then applied to the sidebar, the page route, raw Markdown, and search.

Folder access inherits downward. Set it on `meta.json` or `_meta.md`. A page's own `access` wins.

```json
{
  "title": "Billing",
  "access": "auth"
}
```

```yaml
---
access: auth
---
```

Guests and users who fail the gate get a 404. `vellum:export` runs as a guest: gated pages are omitted, and each one is logged.

## Theme

```php
'theme' => [
    'preset' => 'neutral',
    'primary' => null,
    'radius' => '0.5rem',
    'default' => 'system',
],
```

Presets: `neutral`, `black`, `vitepress`, `dusk`, `catppuccin`, `ocean`, `purple`, `solar`, `emerald`, `ruby`, `aspen`.
