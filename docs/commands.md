---
title: Commands
description: The five Artisan commands Vellum adds, and when to run each one.
---

| Command | When you run it |
| --- | --- |
| `vellum:install` | Once, after requiring the package. |
| `vellum:build` | Every deploy. |
| `vellum:index` | When only the search index needs rebuilding. |
| `vellum:clear` | To delete the compiled cache. |
| `vellum:export` | To produce a static site. |

## vellum:install

```bash
php artisan vellum:install [--force]
```

Publishes `config/vellum.php`, copies starter Markdown into `vellum.path`
(`resources/docs` by default), and copies the compiled CSS and JavaScript into
`public/vendor/vellum`.

Config and Markdown files that already exist are skipped, so running the command again does
not overwrite your work. The CSS and JavaScript are always overwritten.

`--force` overwrites the published config and any starter Markdown files with the same
names. Use it after a package upgrade to get the new stubs, and review the diff before
you commit.

## vellum:build

```bash
php artisan vellum:build [--docs-version=v1] [--strict] [--check-search]
```

Compiles every Markdown file into the cache at `vellum.cache.path`, then rebuilds the
navigation tree and the search index. Run it in your deploy, alongside `config:cache`
and `route:cache`.

In `local`, a page recompiles on the next request after its file changes, so you do not
need to run this while writing. In every other environment the site serves what the last
build compiled. A page added since then returns a 404 until the next build, and the next
build removes pages whose files were deleted or renamed.

The command fails when two files resolve to the same URL, when a `:::` directive name is
not a shipped directive, or when a component or value tag cannot be resolved. It prints a
warning, and still succeeds, when a page has the same URL as the changelog route and is
hidden by it.

It also warns about internal links, images and heading anchors that point at nothing, and
about headings that skip a level. `--strict` makes the link and image warnings fail the
build for that run, the same as setting `checks.strict` to `true`. Heading-level warnings
never fail a build.

`--docs-version` limits the run to a single version folder.

`--strict` turns the warnings about links and images that point at nothing into a failed
build, and `checks.strict` does the same for a deploy script you cannot pass flags to.

`--check-search` asks the questions in `questions.yml`, if your docs have one, even when
`checks.search` is off. See [Answers](/docs/answers#checking-search-in-ci).

## vellum:index

```bash
php artisan vellum:index [--docs-version=v1]
```

Rebuilds the search index. To do that it compiles every page and the navigation tree the
same way `vellum:build` does, but it skips the build's link, image and heading checks.
`vellum:build` also rebuilds the index, so you only need `vellum:index` on its own when
the index is the only thing out of date.

When `search.driver` is `scout`, it also sends the records to Scout. If that driver is set
but `laravel/scout` is not installed, the command fails with an error saying so. See
[Search](/docs/search).

## vellum:clear

```bash
php artisan vellum:clear
```

Removes the compiled pages, the navigation tree, the search index and the rendered
component fragments. The next request rebuilds what it needs.

It also runs as part of `optimize:clear`, so a normal Laravel cache clear removes Vellum's
caches too.

## vellum:export

```bash
php artisan vellum:export [--out=path/to/dir]
```

Writes a complete static site: HTML for every page, the assets, the raw Markdown, the
search index, a changelog feed if a changelog is configured, and a `404.html` at the
root.

Gated pages are left out, and the command prints a warning for each one it drops. Value
tags are resolved when the export runs. See [Export](/docs/export).

## A deploy

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan vellum:build
```

`vellum:build` writes plain PHP files that OPcache can hold, so serving a page does not
read the Markdown again. Set `vellum.cache.path` to a writable directory that persists
between requests. The default is `storage/framework/vellum`.
