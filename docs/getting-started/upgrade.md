---
title: Upgrade
description: Upgrading from 0.7 to 0.8, from 0.6 to 0.7, within 0.6, from 0.5 to 0.6, and from 0.2 to the 0.5 freeze.
---

0.5 froze the keys in `config/vellum.php` and the page frontmatter names. Later releases add keys without renaming any, and no key will be removed before 1.0.

The requirements have been PHP 8.4+ and Laravel 11, 12, or 13 since 0.2.

## 0.7 to 0.8

No changes to your content are required. Update the package and rebuild:

```bash
composer update jimmyverburgt/vellum
php artisan vellum:build
```

The update also installs `jimmyverburgt/vellum-core`, which now holds the engine Vellum runs on. Nothing changes in how you install or use the full package. See [Core on its own](/docs/core) for what core is.

Run `vellum:build` even outside a deploy. Compiled pages keep the HTML they were built with, and most components have new markup. On pages compiled by 0.7, the heading buttons stop copying links, the code buttons lose their check mark, and callouts, tabs and cards do not match the new stylesheet until the pages are rebuilt.

### What readers will notice

0.8 redesigns the details of every page, with no change to how you write them. The ones most likely to surprise:

- A shell block without a title is shown as a terminal with a `$` before each command. If you typed `$` yourself, it now shows twice, so remove yours. See [Terminal commands](/docs/writing/code-blocks#terminal-commands).
- Callouts are no longer boxes, and each names its type on its first line.
- The table of contents has a new default style, `window`. See [Table of contents](/docs/theming#table-of-contents) to keep `line`.
- With versions on, the switcher shows the latest version by its slug with a "Latest" tag, where it used to say "Latest" alone. Give the version a name in `versions.labels` if the slug is not what readers should see. Pages of any other version start with a notice. See [Versions](/docs/versions).
- Corners that ignored `theme.radius` now follow it, so a radius you changed also changes code blocks, tables, tabs and images.

### Config

`layout.toc` is new. A config file published before 0.8 has a `layout` array without it, and a published array replaces the package's whole, so `VELLUM_LAYOUT_TOC` is ignored until the key is in your file. Add it, or republish the config:

```php
'layout' => [
    'search' => env('VELLUM_LAYOUT_SEARCH', 'sidebar'),
    'toc' => env('VELLUM_LAYOUT_TOC', 'window'),
],
```

`vellum:build` no longer warns about a colour preset removed in 0.5. The site still falls back to `neutral`.

### Views you overrode

A view you copied from 0.7 into `resources/views/vendor/vellum/` and changed needs attention:

- The sidebar, the layout and every `ui` view named a class that has moved, so a 0.7 copy fails with "Class not found". Change the namespace in your copy, or copy the 0.8 view again. `Vellum\Support\Theme`, `Color`, `Assets`, `Cn` and `NavTree` are now in `Vellum\View`.
- A copy of the `callout`, `card`, `step` or `tabs` view has the old markup and will not match the new stylesheet. Copy it again from 0.8.
- A component view you published to `resources/views/vendor/vellum/components/` now also applies to the `:::` form of that component, not only the `<x-vellum::…>` tag. See [Change a built-in's markup](/docs/extending#change-a-built-ins-markup).
- The page actions moved into the line under the title. An override of `components/docs/page-actions.blade.php` still works, and `components/docs/page-meta.blade.php` is the view for the whole line.

### CSS and scripts that target Vellum's markup

- **Callouts:** the glyph is inside `.vellum-callout-title`, which is now always present. The border and background are gone, apart from a tint on warnings and dangers.
- **Tabs:** every group is a bordered card. The label row has a `.vellum-tabs-underline` element, and the group a `data-vellum-tabs-sliding` attribute once its script runs.
- **Code blocks:** a block without a title, other than a shell block, has no `.vellum-code-header`. Its language is in `.vellum-code-actions > .vellum-code-lang`. Select a kind with `data-vellum-code-kind`, which is `file`, `terminal` or `snippet`.
- **Copy buttons:** they are marked `data-vellum-copy-code` and `data-vellum-heading-copy`, without Alpine attributes.
- **Cards:** a card is a grid, with a `.vellum-card-arrow` and, when it has one, a `.vellum-card-description`.
- **Tables:** cells carry `data-label`. Below 40rem the table, its rows and its cells are `display: block`, and the header row is visually hidden.
- **Images:** an image on a line of its own has `data-vellum-image-block`, and its paragraph or figure is a padded, bordered mat.
- **Steps:** a step title under a section heading is one level below it, so a step under `## Setup` is an `h3`. The step's number is an `<a>`.
- **Previous and next:** the classes are `.vellum-pagination`, `-prev`, `-next`, `-label`, `-title` and `-description`. Previous has no box and no description.
- **Last updated:** the `<time>` moved from the breadcrumb row into `[data-vellum-page-meta]`.

### PHP that uses Vellum's classes

- `Vellum\Support\Theme`, `Color`, `Assets`, `Cn` and `NavTree` are now in `Vellum\View`, and `Vellum\Support\LlmsTxt`, `Vellum\Support\Sitemap` and `Vellum\Changelog\ChangelogFeed` are in `Vellum\Http`.
- `VersionLabel::for()` returns the slug for the latest version, not "Latest". `VersionLabel::kind()` says whether a version is the latest, older or unreleased.
- Errors in the docs, such as an unknown directive, implement `Vellum\Exceptions\ContentError`.

## 0.6 to 0.7

No changes to your config or content are required. Update the package, run `vellum:build`, and republish the config if you want the new keys in your file:

```bash
composer update jimmyverburgt/vellum
php artisan vellum:build
```

Search now returns sections instead of whole pages and no longer uses MiniSearch. The default `search.driver` is `builtin`; a config that still says `minisearch` keeps working. The built-in driver no longer serves `{prefix}/_vellum/search.json`, which only matters if something outside Vellum fetched that file. The Scout driver is unchanged. See [Search](/docs/search).

0.7 also adds `llms.txt`, content negotiation and a JSON search endpoint for agents, all on by default under the new `agents` keys. See [Page actions](/docs/page-actions#for-agents) to turn any of them off.

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

If you used one, the site falls back to `neutral`. Most of the removed presets only changed the accent colour, which `theme.accent` now sets on any preset:

```php
'theme' => [
    'preset' => 'neutral',
    'accent' => '#7c3aed',
],
```

The remaining presets were retuned to meet WCAG AA (4.5:1) for body text, secondary text, links and button labels in light and dark mode. See [Theming](/docs/theming).

### Version URLs

When `versions.enabled` is true, the latest version is now served at `/docs/...` with no version segment, and `/docs/{latest}/...` redirects there with a 301. Older versions stay at `/docs/v1/...`. Since 0.8 the switcher shows the latest version by its slug, or its `versions.labels` label, with a "Latest" tag. Before that it was labelled "Latest" alone.

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
