---
title: Page actions
description: Copy Markdown, raw source URLs, Edit on GitHub, and opening a page in ChatGPT or Claude.
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
