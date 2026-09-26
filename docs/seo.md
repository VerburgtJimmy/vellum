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

A page gets a `<lastmod>` when it has a last-updated date: its `updated` frontmatter, or its last git commit when the docs are in a full git clone. Pages without one get no `<lastmod>`. File modification times are not used, because a deploy resets them.

It lives under the docs prefix because the package does not own the site root. A sitemap can list any URL at or below its own path, so this one covers the whole docs tree.

Like the canonical link, the sitemap needs `app.url` to be an absolute URL. Without one the route returns a 404, because sitemap URLs must be absolute.

## robots.txt

Laravel does not ship a `robots.txt` that references a sitemap, so add one:

```
User-agent: *
Allow: /

# Package internals: the search index and embedded files.
Disallow: /docs/_vellum/
# Raw Markdown declares its HTML page canonical, and llms.txt links to it.
Allow: /docs/_vellum/raw/

Sitemap: https://example.com/docs/sitemap.xml
```

Every raw Markdown response sends `Link: <page URL>; rel="canonical"`, naming the HTML page it is a copy of. A crawler that fetches the `.md` file credits the page and does not index the copy as a duplicate. The raw files are also what [`llms.txt`](/docs/page-actions#for-agents) links to, so a `Disallow` for them would stop agents that follow `robots.txt` from reading them.

The rest of `/docs/_vellum/` holds the search index and files that pages embed, which do not need crawling on their own. The longer `Allow` rule takes precedence over the shorter `Disallow`, so only the raw files are crawled.

Like the canonical `<link>`, the header is absolute when `app.url` is an origin. Without one it is root-relative, and a client resolves it against the URL it requested.

## Static export

A static host cannot generate a sitemap, so `vellum:export` writes `sitemap.xml` to the export root. It uses `export.base_url` when that is an origin and falls back to `app.url` otherwise. See [Export](/docs/export).
