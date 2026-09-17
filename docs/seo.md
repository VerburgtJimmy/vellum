---
title: Search engines
description: What Vellum emits for crawlers, the sitemap route, and what to put in robots.txt.
---

Vellum gives every docs page the metadata a crawler needs, and publishes a sitemap so it can find them all.

## Per page

Each page emits a unique `<title>`, a `<meta name="description">` from its frontmatter, and `<link rel="canonical">`, plus Open Graph and Twitter card tags. The 404 page is marked `noindex`.

The canonical is only written when `app.url` names a real origin. During local development it is omitted rather than pointing at something wrong.

:::note
Pages without a `description` in frontmatter get no meta description. It is the one frontmatter key worth filling in on every page.
:::

## Sitemap

```
/docs/sitemap.xml
```

Built from the same navigation that renders the sidebar, so it stays correct as pages are added. Gated pages are left out, including for a signed-in reader, since a sitemap is one public file. Every version is listed when versions are enabled.

It sits under the docs prefix rather than at the site root, which the package does not own. A sitemap may list any URL at or below its own path, so this one covers the whole docs tree.

Like the canonical, it needs `app.url` to be an origin. Without one it returns a 404 rather than publishing relative URLs, which are not valid in a sitemap.

## robots.txt

Laravel does not ship a `robots.txt` with a sitemap reference, so add one:

```
User-agent: *
Allow: /

# Raw Markdown is the same content as the HTML pages, and the page
# actions link to it. Crawl the pages, not both copies.
Disallow: /docs/_vellum/

Sitemap: https://example.com/docs/sitemap.xml
```

The `Disallow` matters more than it looks. [Page actions](/docs/page-actions) link to a raw `.md` copy of every page, so without it each page is crawled twice and indexed as a near-duplicate.

## Static export

`vellum:export` writes `sitemap.xml` at the export root, since a static host cannot generate one. It uses `export.base_url` when that names an origin, falling back to `app.url`. See [Export](/docs/export).
