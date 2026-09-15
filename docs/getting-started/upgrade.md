---
title: Upgrade from 0.2 to 0.5
description: One-page path from Vellum 0.2 to the 0.5 stability freeze.
---

0.5 freezes `config/vellum.php` keys and page frontmatter. Do not rename keys after this unless you wait for 1.0.

PHP 8.4+ and Laravel 11, 12, or 13 did not change.

## Update the package

```bash
composer update jimmyverburgt/vellum
php artisan vendor:publish --tag=vellum-config
```

Diff the published file against yours. Keep your values; take the new keys.

## Keys added after 0.2

| Key | Default | Why |
| --- | --- | --- |
| `search.driver` | `minisearch` | MiniSearch or Scout. Live MiniSearch is a filtered route, not a public file. |
| `search.scout.index` | `vellum` | Scout index name when that driver is on. |
| `components.namespaces` | `['vellum']` | Blade prefixes allowed as `<x-…>` in Markdown. |
| `components.allowlist` | empty | Keys for `env`, `config`, and `route` tags. Empty refuses every key. |
| `changelog.path` | `CHANGELOG.md` | Keep a Changelog file. `null` disables the page and feed. |
| `changelog.unreleased` | `false` | Show `[Unreleased]` on the HTML page. The feed never includes it. |
| `versions.labels` | `[]` | Switcher text per slug (`1.x (LTS)`, `Next`). Folder and URL stay the slug. |

Already in 0.2 and still valid: `layout.search` (`sidebar` or `header`), `theme.preset`, `theme.primary`, `theme.radius`, `theme.default`.

## Colour presets

0.5 ships three presets: `neutral`, `ocean` and `laravel`. These nine were removed: `black`, `vitepress`, `dusk`, `catppuccin`, `purple`, `solar`, `emerald`, `ruby`, `aspen`.

Nothing breaks if you were using one. The site falls back to `neutral`, and `vellum:build` warns once with the name you set. Most of those presets existed to change a single accent colour; `theme.accent` now does that on any preset:

```php
'theme' => [
    'preset' => 'neutral',
    'accent' => '#7c3aed',
],
```

The presets that stayed were retuned to clear WCAG AA (4.5:1) for body text, secondary text, links and button labels in both modes. See [Theming](/docs/theming).

## Version URLs

If `versions.enabled` is true, the latest folder is now `/docs/...` with no version segment. `/docs/{latest}/...` **301**s to the unprefixed URL. Older versions stay at `/docs/v1/...`. The switcher shows **Latest** for the latest slug unless you set `versions.labels`.

Update inbound links. Changelog stays at `/docs/changelog`.

## Frontmatter

`access` is new: `guest` (default, 0.2 behaviour), `auth`, or a gate name. Folder `meta.json` / `_meta.md` inherit downward. See [Gating](/docs/gating).

Unchanged: `title`, `description`, `slug`, `icon`, `order`, `full`.

## Search

Default stays in-browser MiniSearch. Set `VELLUM_SEARCH_DRIVER=scout` only if you already run Laravel Scout. `vellum:export` always writes MiniSearch JSON.

## Markdown plus components

`<x-…>` islands, `:::` directives, and value tags shipped in 0.3. Prefer `:::` for callouts, tabs, steps, and cards. See [Components](/docs/components) and [Extending](/docs/extending).

## Commands

`vellum:index` rebuilds the search compile cache (and Scout when that driver is on). `vellum:build` still compiles Markdown.
