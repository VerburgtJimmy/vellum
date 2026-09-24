---
title: Search engines
description: The metadata Vellum outputs for crawlers, the sitemap route, and what to put in robots.txt.
---

Every docs page includes the metadata a crawler needs, and a sitemap lists all of them.

## Per page

Each page outputs its own `<title>`, a `<meta name="description">` taken from its frontmatter, a `<link rel="canonical">`, and Open Graph and Twitter card tags. The 404 page is marked `noindex`.

The canonical link is only written when `app.url` is an absolute `http://` or `https://` URL. Otherwise the tag is left out.

:::note
A page without a `description` in its frontmatter gets no meta description. Of all the frontmatter keys, this is the one to fill in on every page.
:::

## Sitemap

```
/docs/sitemap.xml
```

The sitemap is built from the same navigation as the sidebar, so it stays up to date as you add pages. It lists every version when versions are enabled. Gated pages are always left out, even for a signed-in reader, because every visitor gets the same sitemap file.

It lives under the docs prefix because the package does not own the site root. A sitemap can list any URL at or below its own path, so this one covers the whole docs tree.

Like the canonical link, the sitemap needs `app.url` to be an absolute URL. Without one the route returns a 404, because sitemap URLs must be absolute.

## robots.txt

Laravel does not ship a `robots.txt` that references a sitemap, so add one:

```
User-agent: *
Allow: /

# Raw Markdown is the same content as the HTML pages, and the page
# actions link to it. Crawl the pages, not both copies.
Disallow: /docs/_vellum/

Sitemap: https://example.com/docs/sitemap.xml
```

Keep the `Disallow` line. [Page actions](/docs/page-actions) link to a raw `.md` copy of every page, and without it crawlers fetch each page twice and index the copy as a near-duplicate.

## Static export

A static host cannot generate a sitemap, so `vellum:export` writes `sitemap.xml` to the export root. It uses `export.base_url` when that is an origin and falls back to `app.url` otherwise. See [Export](/docs/export).
