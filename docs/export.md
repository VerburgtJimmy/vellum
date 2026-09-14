---
title: Export
description: Write a static HTML site for GitHub Pages, Cloudflare Workers, or any static host.
---

```bash
php artisan vellum:export
```

Writes HTML, assets, and a MiniSearch index to `public/docs-static` (configurable via `export.out`). `--out=` overrides the directory.

```php
'export' => [
    'out' => public_path('docs-static'),
    'base_url' => '/',
],
```

The export always uses MiniSearch, even when `search.driver` is `scout`. Gated pages are omitted; each drop is logged.

Value tags are baked in at export time, so the snapshot matches the app config you exported with.

This package's own docs are not exported: the public site is a Laravel app that serves `docs/` live, which is the point.
