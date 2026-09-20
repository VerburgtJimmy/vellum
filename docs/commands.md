---
title: Commands
description: The five artisan commands Vellum adds, and when each one runs.
---

| Command | When you run it |
| --- | --- |
| `vellum:install` | Once, after requiring the package. |
| `vellum:build` | Every deploy. |
| `vellum:index` | When only the search index needs rebuilding. |
| `vellum:clear` | To drop the compiled cache. |
| `vellum:export` | To produce a static site. |

## vellum:install

```bash
php artisan vellum:install [--force]
```

Publishes three things: `config/vellum.php`, starter Markdown into `vellum.path`
(`resources/docs` by default), and the compiled CSS and JS into `public/vendor/vellum`.

Existing files are left alone, so running it twice will not overwrite your work. Assets
are always refreshed, since they are Vellum's to own.

`--force` overwrites the published config and the starter Markdown. Use it after a
package upgrade when you want the new stubs, and diff before you commit.

## vellum:build

```bash
php artisan vellum:build [--docs-version=v1]
```

Compiles every Markdown file into the cache at `vellum.cache.path`, then rebuilds the
navigation tree and the search index. **Run this in your deploy**, next to
`config:cache` and `route:cache`.

In `local`, pages recompile on request when the file changes, so you do not need to run
this while writing.

The command fails rather than shipping something broken when two files claim the same
URL, when a directive name is not one Vellum knows, or when a component or value tag
cannot be resolved. It warns, without failing, when a page is shadowed by the changelog
route.

`--docs-version` limits the run to a single version folder.

## vellum:index

```bash
php artisan vellum:index [--docs-version=v1]
```

Rebuilds the answer index and the semantic file without recompiling pages. `vellum:build`
already does this, so reach for `vellum:index` when the index is the only thing that is
stale.

When `search.driver` is `scout`, this also pushes records to Scout. It fails with a clear
message if Scout is configured but not installed. See [Search](/docs/search).

## vellum:clear

```bash
php artisan vellum:clear
```

Removes the compiled pages, the navigation tree, the search index and the rendered
component fragments. The next request rebuilds what it needs.

This also runs as part of `optimize:clear`, so a normal Laravel cache clear will not
leave Vellum's caches behind.

## vellum:export

```bash
php artisan vellum:export [--out=path/to/dir]
```

Writes a complete static site: HTML for every page, the assets, raw Markdown, the
search index, a changelog feed if one is configured, and a `404.html` at the root.

Gated pages are omitted and each drop is logged. Value tags are resolved at export time.
See [Export](/docs/export).

## A deploy

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan vellum:build
```

`vellum:build` writes plain PHP files that OPcache can hold, so pages are served without
touching the Markdown again. Point `vellum.cache.path` somewhere writable and persistent;
the default is `storage/framework/vellum`.
