---
title: Search
description: Search in the browser by default, Laravel Scout optional.
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

The default, `builtin`. No extra services. (`minisearch` still works as its old name; the library it was named after is gone.) Works on every host, including `vellum:export`.

Search reads two files: `/docs/_vellum/answers.json`, every section with the questions it answers, and `/docs/_vellum/semantic.bin`, the vectors that match a question to a section phrased differently. Both are filtered for the current user and cached per visibility set (guest, auth, and per-gate combinations), and both send an ETag so `must-revalidate` can 304. Neither is a public immutable file.

The ranking, and when an answer card is shown, are described in [Answers](/docs/answers).

`Ctrl+K` / `⌘K` opens search. Arrow keys move through results. Escape closes. The dialog is labelled **Search documentation**.

## Scout

Opt-in for people who already run Meilisearch or Typesense and want heading-level relevance at scale.

```bash
composer require laravel/scout
```

Set `VELLUM_SEARCH_DRIVER=scout`. The same visibility filter runs at query time. `vellum:build` and `vellum:index` sync Scout when that driver is on. Scout searches whole pages on the server, so it has no answer cards.

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

`vellum:export` always writes the in-browser files, regardless of `driver`. A static host has no session, so the exported index holds public pages only.

Place the search trigger in the sidebar (default) or the header with `layout.search`.

See [Answers](/docs/answers) for how a question finds the section that answers it.
