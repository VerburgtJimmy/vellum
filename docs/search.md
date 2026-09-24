---
title: Search
description: MiniSearch by default, Laravel Scout optional.
---

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

## MiniSearch

The default. No extra services. Works on every host, including `vellum:export`.

The live index is `/docs/_vellum/search.json`, filtered for the current user and cached per visibility set (guest, auth, and per-gate combinations). Responses send an ETag (index hash plus visibility) so `must-revalidate` can 304. It is not a public immutable file.

`Ctrl+K` / `⌘K` opens search. Arrow keys move through results. Escape closes. The dialog is labelled **Search documentation**.

## Scout

Opt-in for people who already run Meilisearch or Typesense and want heading-level relevance at scale.

```bash
composer require laravel/scout
```

Set `VELLUM_SEARCH_DRIVER=scout`. The same visibility filter runs at query time. `vellum:build` and `vellum:index` sync Scout when that driver is on.

Each sync replaces the whole index, every version included, so a page you delete, rename or gate does not keep its old record. Keep the index to Vellum alone: `search.scout.index` names it, `vellum` by default.

With versions on, a search is filtered by `version`, which the engine has to allow. For Meilisearch, add it to the index settings in `config/scout.php` and run `php artisan scout:sync-index-settings`:

```php
'meilisearch' => [
    'index-settings' => [
        'vellum' => ['filterableAttributes' => ['version']],
    ],
],
```

Typesense needs a collection schema for `Vellum\Search\SearchableDocument` in `model-settings`, with `id`, `title`, `content`, `url`, `description`, `access` and `version` as string fields, `version` optional and faceted.

## Export

`vellum:export` always writes MiniSearch JSON, regardless of `driver`. Gated pages are dropped from the exported index.

Place the search trigger in the sidebar (default) or the header with `layout.search`.
