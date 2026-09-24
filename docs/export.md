---
title: Export
description: Write a static HTML site for GitHub Pages, Cloudflare Workers, or any static host.
---

```bash
php artisan vellum:export
```

Writes HTML, assets, a MiniSearch index, and a `sitemap.xml` to `public/docs-static` (configurable via `export.out`). `--out=` overrides the directory.

```php
'export' => [
    'out' => public_path('docs-static'),
    'base_url' => '/',
],
```

The export always uses MiniSearch, even when `search.driver` is `scout`. Gated pages are omitted; each drop is logged.

Exporting again into the same directory removes what the last export wrote and this one did not, such as a page you deleted or gated since. The list of what was written is kept in `.vellum-export.json` at the export root; anything else in the directory, like a `CNAME`, is left alone.

Value tags are baked in at export time, so the snapshot matches the app config you exported with.

The sitemap lands at the export root and uses `base_url` when it names an origin, falling back to `app.url`. With neither it is skipped and the command says so, because sitemap URLs have to be absolute. See [Search engines](/docs/seo).

Export when the docs have to sit on a host that cannot run PHP. If the app is already deployed, serving the docs from it is simpler and keeps value tags, gating and search resolving against the live application.
