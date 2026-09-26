---
title: Search
description: Built-in search in the browser by default, with Laravel Scout as an option.
---

```php
'search' => [
    'enabled' => true,
    'hotkey' => 'k',
    'driver' => env('VELLUM_SEARCH_DRIVER', 'builtin'),
    'scout' => [
        'index' => 'vellum',
    ],
],
```

## In the browser

The default driver is `builtin`. It needs no extra services and works on every host, including sites built with `vellum:export`. `minisearch` is still accepted as its old name.

The browser loads its index from `/docs/_vellum/answers.json`, which holds every section of every page the reader can open. It is filtered for the current user and cached per visibility set (guest, authenticated, and each combination of gates). Responses carry an ETag and are sent with `must-revalidate`, so a browser that already has the current index gets a 304.

`Ctrl+K` or `⌘K` opens search. The arrow keys move through the results and Escape closes the dialog. The dialog's accessible label is "Search documentation".

## Scout

Use Scout if you already run Meilisearch or Typesense and want heading-level relevance on a large docs site.

```bash
composer require laravel/scout
```

Set `VELLUM_SEARCH_DRIVER=scout`. The same visibility filter is applied at query time, and `vellum:build` and `vellum:index` sync the Scout index while that driver is active. Scout searches whole pages on the server, so its results are pages rather than sections.

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

`vellum:export` always writes the built-in index, whatever `driver` is set to. A static host has no session, so the exported index holds public pages only.

Use `layout.search` to place the search trigger in the sidebar (the default) or in the header.
