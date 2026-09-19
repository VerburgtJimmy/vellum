---
title: Page actions
description: Copy Markdown, raw source URLs, llms.txt, Edit on GitHub, and handing a page to an AI tool.
---

Every page carries a small row of actions under its title. They exist so a reader can
take the page somewhere else: into an editor, into a pull request, or into a chat window.

## Copy Markdown

Copies the page's Markdown source, frontmatter and all, to the clipboard. Not the
rendered text and not the HTML.

This is the fastest way to give a model the page you are looking at without it having to
fetch anything.

## Raw Markdown

Every page is also served as plain Markdown:

```
/docs/writing/markdown        the page
/docs/_vellum/raw/writing/markdown.md   its source
```

The raw route returns `text/markdown` and honours [gating](/docs/gating): a page a reader
cannot see returns 404 here too, with no separate configuration.

`vellum:export` writes the same files into `_vellum/raw/`, so the static build keeps
working.

Each raw response names its HTML page in a `Link: <...>; rel="canonical"` header, so a
search engine credits the page rather than indexing the source as a copy of it. When
versions are enabled, raw responses also carry an `X-Vellum-Docs-Version` header naming
the version. The body stays the file as written.

## For agents

The raw files are there for readers, but also for tools that fetch docs on their own.
Vellum points them at the Markdown in four ways.

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

**llms-full.txt.** `/docs/llms-full.txt` is every page's Markdown source in one file, in
sidebar order. Each page starts with a header block between two lines of 80 `=`
characters:

```
================================================================================
Title: Installation
URL: https://example.com/docs/getting-started/installation
Version: v2
================================================================================
```

`Version` only appears when versions are enabled.

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

## Open in ChatGPT / Open in Claude

Both open the tool with a prompt already filled in, pointing at this page's raw Markdown
URL.

This only works for docs that are reachable from the public internet. On localhost, or
behind a login, the model cannot fetch the URL. Use **Copy Markdown** and paste instead.

:::note[Why raw Markdown rather than the page]
A model reading the rendered page spends its budget on navigation, sidebar and chrome.
The raw file is the content and nothing else, which is both cheaper and more accurate.
:::

## Edit on GitHub

Set `repo` to the base URL of your docs directory in source control:

```php
'repo' => 'https://github.com/acme/app/edit/main/resources/docs',
```

Vellum appends the page's path relative to `vellum.path`, so
`resources/docs/billing/invoices.md` becomes
`https://github.com/acme/app/edit/main/resources/docs/billing/invoices.md`.

The link only appears when `repo` is set. Point it at `/edit/` to open GitHub's editor
directly, or `/blob/` to land on the file.

Vellum refuses to build the link for a file resolving outside `vellum.path`, so a
symlinked page cannot leak a path from elsewhere on disk.

## Changing them

There is no config flag for the row in 0.5. Override the view instead: Vellum registers
its views under the `vellum` namespace, so a file at the matching path in your app wins.

```
resources/views/vendor/vellum/components/docs/page-actions.blade.php
```

Create that file and it replaces the shipped one. An empty file removes the row. Nothing
needs publishing first, and the same applies to any other Vellum view.

:::warning
Views are not frozen the way config keys and frontmatter are. An override is a copy, and
it will not pick up fixes or markup changes from a later release. Keep the override as
small as you can.
:::
