---
title: Search
description: Built-in MiniSearch by default, with Laravel Scout as an option.
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

MiniSearch is the default driver. It needs no extra services and works on every host, including sites built with `vellum:export`.

The live index is served by a package route at `/docs/_vellum/search.json`. It is filtered for the current user and cached per visibility set (guest, authenticated, and each combination of gates). Responses carry an ETag built from the index hash and the visibility set and are sent with `must-revalidate`, so a browser that already has the current index gets a 304.

`Ctrl+K` or `⌘K` opens search. The arrow keys move through the results and Escape closes the dialog. The dialog's accessible label is "Search documentation".

## Scout

Use Scout if you already run Meilisearch or Typesense and want heading-level relevance on a large docs site.

```bash
composer require laravel/scout
```

Set `VELLUM_SEARCH_DRIVER=scout`. The same visibility filter is applied at query time, and `vellum:build` and `vellum:index` sync the Scout index while that driver is active.

Each sync replaces the whole index, including every version, so pages you delete, rename or gate do not leave old records behind. Because of this, the index should hold Vellum's records only. `search.scout.index` sets its name and defaults to `vellum`.

When versions are enabled, searches filter on `version`, and the engine has to allow filtering on that attribute. For Meilisearch, add it to the index settings in `config/scout.php` and run `php artisan scout:sync-index-settings`:

```php
'meilisearch' => [
    'index-settings' => [
        'vellum' => ['filterableAttributes' => ['version']],
    ],
],
```

For Typesense, define a collection schema for `Vellum\Search\SearchableDocument` in `model-settings`. It needs `id`, `title`, `content`, `url`, `description`, `access` and `version` as string fields, with `version` marked optional and faceted.

## Export

`vellum:export` always writes a MiniSearch JSON index, whatever `driver` is set to. Gated pages are left out of the exported index.

Use `layout.search` to place the search trigger in the sidebar (the default) or in the header.
