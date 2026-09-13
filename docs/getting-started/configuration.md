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
