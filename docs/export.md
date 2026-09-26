---
title: Export
description: Write a static HTML site for GitHub Pages, Cloudflare Workers, or any static host.
---

```bash
php artisan vellum:export
```

The command writes the HTML pages, assets, the search index and a `sitemap.xml` to `public/docs-static`. Change the directory with `export.out`, or pass `--out=` for a single run.

```php
'export' => [
    'out' => public_path('docs-static'),
    'base_url' => '/',
],
```

The export always uses the built-in search, even when `search.driver` is `scout`. Gated pages are left out, and the command logs each one it drops.

When you export into the same directory again, files from the previous export that this run did not write are removed, such as a page you have since deleted or gated. The list of written files is kept in `.vellum-export.json` at the export root. Anything else in the directory, like a `CNAME` file, is left alone.

Value tags are resolved at export time, so the exported pages contain the values the app had when you ran the command.

The sitemap is written to the export root. It uses `base_url` when that is an origin and falls back to `app.url`. If neither is an origin, the sitemap is skipped with a warning, because sitemap URLs have to be absolute. See [Search engines](/docs/seo).

Use the export when the docs need to live on a host that cannot run PHP. If the app is already deployed, it is simpler to serve the docs from it, and value tags, gating and search then resolve against the live application.
