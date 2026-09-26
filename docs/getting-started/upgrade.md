---
title: Upgrade
description: Upgrading within 0.6, from 0.5 to 0.6, and from 0.2 to the 0.5 freeze.
---

0.5 froze the keys in `config/vellum.php` and the page frontmatter names. Later releases add keys without renaming any, and no key will be removed before 1.0.

The requirements have been PHP 8.4+ and Laravel 11, 12, or 13 since 0.2.

## Within 0.6

Patch releases need no changes to your config or content. After updating to 0.6.3 or later, run `vellum:build` once so the cached sidebar picks up the corrected access rules and compiled pages move to their new folder. Outside `local`, the site now serves only the pages the last build compiled, so a page added without a build returns a 404 until the next one.

If you export into the same directory each time, delete it once before your first export with 0.6.3 or later. From then on, each export removes the files the previous one wrote and this one did not.

Since 0.6.4, a folder's `title` can be set in `_meta.md` as well as `meta.json`.

## 0.5 to 0.6

No changes to your config or content are required. Update the package and republish the config to pick up the new keys:

```bash
composer update jimmyverburgt/vellum
php artisan vendor:publish --tag=vellum-config
```

`vendor:publish` skips a `config/vellum.php` that already exists. Add `--force` to overwrite it, then restore your own values from version control.

`vellum:build` now checks internal links, images and heading levels. It warns about links to pages or headings that do not exist, missing images, and headings that skip a level. On a docs set that has been edited for a while, the first run can print a page of warnings about problems that existed before the upgrade.

The warnings do not fail the build. They are controlled by the new `checks` keys, shown here with their defaults. Set `strict` to `true` to make them fail the build, which is useful in CI:

```php
'checks' => [
    'references' => true,
    'strict' => false,
],
```

`vellum:build --strict` does the same for a single run. Warnings about skipped heading levels never fail the build, even in strict mode.

A sitemap listing every public page is now served at `{prefix}/sitemap.xml`. Add it to `robots.txt`, and disallow the raw Markdown routes, which serve a second copy of every page:

```
Sitemap: https://example.com/docs/sitemap.xml
Disallow: /docs/_vellum/
```

`vellum:export` also writes a `sitemap.xml` at the root of the export. See [Search engines](/docs/seo).

The table of contents now highlights every heading whose section is on screen, where it used to highlight only the last heading scrolled past. This needs no action.

## 0.2 to 0.5

### Update the package

```bash
composer update jimmyverburgt/vellum
php artisan vendor:publish --tag=vellum-config
```

`vendor:publish` skips a `config/vellum.php` that already exists, so add `--force`, then diff the new file against your committed version. Keep your values and take the new keys.

### Keys added after 0.2

| Key | Default | Why |
| --- | --- | --- |
| `search.driver` | `minisearch` | `minisearch` or `scout`. The live MiniSearch index is served by a route that filters it for each reader. |
| `search.scout.index` | `vellum` | Scout index name, used with the `scout` driver. |
| `components.namespaces` | `['vellum']` | Blade prefixes allowed as `<x-…>` in Markdown. |
| `components.allowlist` | empty | Keys that the `env`, `config` and `route` tags may read. An empty list allows none. |
| `changelog.path` | `CHANGELOG.md` | A Keep a Changelog file. `null` turns off the changelog page and feed. |
| `changelog.unreleased` | `false` | Shows `[Unreleased]` on the HTML page. The feed never includes it. |
| `versions.labels` | `[]` | Switcher text per slug, such as `1.x (LTS)` or `Next`. The folder and URL still use the slug. |
| `theme.accent` | `null` | A brand colour applied to any preset, as one value or a light and dark pair. |

Already in 0.2 and still valid: `layout.search` (`sidebar` or `header`), `theme.preset`, `theme.primary`, `theme.radius`, `theme.default`.

### Colour presets

0.5 has three presets: `neutral`, `ocean` and `laravel`. These nine were removed: `black`, `vitepress`, `dusk`, `catppuccin`, `purple`, `solar`, `emerald`, `ruby`, `aspen`.

If you used one, the site falls back to `neutral` and `vellum:build` prints a warning naming the preset you set. Most of the removed presets only changed the accent colour, which `theme.accent` now sets on any preset:

```php
'theme' => [
    'preset' => 'neutral',
    'accent' => '#7c3aed',
],
```

The remaining presets were retuned to meet WCAG AA (4.5:1) for body text, secondary text, links and button labels in light and dark mode. See [Theming](/docs/theming).

### Version URLs

When `versions.enabled` is true, the latest version is now served at `/docs/...` with no version segment, and `/docs/{latest}/...` redirects there with a 301. Older versions stay at `/docs/v1/...`. The switcher labels the latest version "Latest" unless `versions.labels` gives it another label.

Update inbound links to use the unprefixed URLs. The changelog stays at `/docs/changelog`.

### Frontmatter

The new `access` key takes `guest` (the default, matching 0.2), `auth`, or a gate name. Access set in a folder's `meta.json` or `_meta.md` applies to everything below it. See [Gating](/docs/gating).

`title`, `description`, `slug`, `order` and `full` are unchanged.

### Search

The default is still in-browser MiniSearch. Set `VELLUM_SEARCH_DRIVER=scout` only if you already run Laravel Scout. `vellum:export` always writes MiniSearch JSON.

### Markdown plus components

0.3 added `<x-…>` islands, `:::` directives and value tags. Use `:::` directives for callouts, tabs, steps and cards where you can. See [Components](/docs/components) and [Extending](/docs/extending).

### Commands

`vellum:index` rebuilds the search index, and the Scout index when that driver is on. `vellum:build` still compiles the Markdown.
