---
title: Page actions
description: Copy Markdown, raw source URLs, llms.txt, Edit on GitHub, and opening a page in ChatGPT or Claude.
---

Every page has a row of actions under its title. They let a reader copy the page's
source, edit it on GitHub, or open it in an AI chat tool.

## Copy Markdown

Copies the page's Markdown source to the clipboard, including the frontmatter. You can
paste it straight into a chat with a model, which then has the page without fetching
anything.

## Raw Markdown

Every page is also served as plain Markdown:

```
/docs/writing/markdown        the page
/docs/_vellum/raw/writing/markdown.md   its source
```

The raw route returns `text/markdown` and follows [gating](/docs/gating) with no extra
configuration: a page the reader cannot see returns a 404 here as well.

`vellum:export` writes the same files to `_vellum/raw/`, so the raw links also work on a
static site.

Each raw response names its HTML page in a `Link: <...>; rel="canonical"` header, so a
search engine credits the page rather than indexing the source as a copy of it. When
versions are enabled, raw responses also carry an `X-Vellum-Docs-Version` header naming
the version. A page with a last-updated date, from its `updated` frontmatter or its last
git commit, gets a `Last-Modified` header; a page without one gets none. The body stays
the file as written.

The changelog has a raw copy too, at `/docs/_vellum/raw/changelog.md`, served from
`changelog.path`. It leaves out `[Unreleased]` whenever the HTML page does. See
[Release notes](/docs/releases).

## For agents

The raw Markdown is also useful to tools that read docs on their own, such as coding
agents. Vellum points them to it in four ways and offers a search endpoint as well.

**llms.txt.** `/docs/llms.txt` is an index of the docs in the
[llms.txt](https://llmstxt.org) format: the site name, the index page's description, then
the sidebar as `##` sections in sidebar order, with a line per page linking to its raw
Markdown.

```
# Acme

> Everything about Acme.

## Docs

- [Home](https://example.com/docs/_vellum/raw/index.md): Everything about Acme.

## Getting started

- [Installation](https://example.com/docs/_vellum/raw/getting-started/installation.md)
```

Each top-level folder is a section, with anything nested in it flattened in. A run of
top-level pages is a section too, headed by the titled separator above it, or by the root
`meta.json` title (`Docs` without one) before any separator. When a run picks up again
after a folder, its heading repeats with "(continued)", so no two sections share a name.
The page description follows the link when the page has one.

The changelog is listed too, linking to its raw Markdown. It sits where the sidebar puts
it, or, when the sidebar does not list it, last under `## Optional`, the heading llms.txt
reserves for links an agent can skip when it is short on room.

**llms-full.txt.** `/docs/llms-full.txt` is every page's Markdown source in one file, in
sidebar order. Each page starts with a header block between two lines of 80 `=`
characters:

```
================================================================================
Title: Installation
URL: https://example.com/docs/getting-started/installation
Version: v2
Updated: 2026-09-17
================================================================================
```

`Version` only appears when versions are enabled, and `Updated` only when the page has a
last-updated date. The changelog belongs to no one version, so its block has no `Version`,
and its source leaves out `[Unreleased]` whenever the HTML page does.

Both files are built from the same navigation as the sidebar and the
[sitemap](/docs/seo#sitemap), with the same rules: gated pages are left out even for a
signed-in reader, since each is one public file. They cover the latest version only.
Links are absolute when `app.url` is an origin and root-relative otherwise.
`vellum:export` writes both at the export root. Set `agents.llms_txt` to `false` to turn
them off.

Agents look for `llms.txt` at the site root, so both files are also served at `/llms.txt`
and `/llms-full.txt`, on `route.domain` when that is set. The root is your app's, so
Vellum only adds those routes when the app does not already have one for the path, and a
route the app defines for it wins. Set `agents.llms_txt_root` to `false` to keep them
under the docs prefix only.

The links in `llms.txt` point under `/docs/_vellum/raw/`, so keep that path open in
`robots.txt`. [Search engines](/docs/seo#robotstxt) has the rules to use.

**An alternate link.** Every page's head has
`<link rel="alternate" type="text/markdown">` pointing at its raw Markdown, and the
response sends the same URL in a `Link` header.

**Content negotiation.** Request a page with `Accept: text/markdown` and the response is
the raw Markdown instead of HTML, the same body the raw route returns. It counts when
`Accept` names `text/markdown` and ranks it above `text/html`, so browsers keep getting
HTML. Gating applies as it does on the raw route. Page responses send `Vary: Accept` so a
cache keeps the two apart. Set `agents.content_negotiation` to `false` to turn it off.

**A search endpoint.** `/docs/_vellum/search?q=` runs the same search as the browser and
returns the top sections as JSON, for clients that cannot use the browser's index. See
[Search](/docs/search#the-search-endpoint).

## Open in ChatGPT / Open in Claude

Both open the chat tool with a prompt that points to the page's raw Markdown URL.

The model has to fetch that URL, so this only works for docs on the public internet. For
docs on localhost or behind a login, use Copy Markdown and paste the source instead.

:::note[Why the raw Markdown URL]
The raw file contains only the page content. A model reading the rendered page also
has to process the navigation, sidebar and other layout, which uses more tokens and gives
less accurate answers.
:::

## Edit on GitHub

Set `repo` to the base URL of your docs directory in source control:

```php
'repo' => 'https://github.com/acme/app/edit/main/resources/docs',
```

Vellum appends the page's path relative to `vellum.path`, so
`resources/docs/billing/invoices.md` becomes
`https://github.com/acme/app/edit/main/resources/docs/billing/invoices.md`.

The link only appears when `repo` is set. Use an `/edit/` URL to open GitHub's editor,
or a `/blob/` URL to open the file view.

No link is shown for a file whose real path is outside `vellum.path`, so a symlinked page
cannot expose a path from elsewhere on disk.

## Changing them

The row has no config option. To change it, override the view. Vellum registers its
views under the `vellum` namespace, so a file at the matching path in your app takes
precedence:

```
resources/views/vendor/vellum/components/docs/page-actions.blade.php
```

Creating that file replaces the shipped view, and an empty file removes the row. You do
not need to publish anything first. The same works for every other Vellum view.

:::warning
Views are not frozen the way config keys and frontmatter are. An override is a copy and
does not receive fixes or markup changes from later releases, so keep it as small as you
can.
:::
