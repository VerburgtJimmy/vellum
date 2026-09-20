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

## Export

`vellum:export` always writes MiniSearch JSON, regardless of `driver`. Gated pages are dropped from the exported index.

Place the search trigger in the sidebar (default) or the header with `layout.search`.

See [Answers](/docs/answers) for how a question finds the section that answers it.
