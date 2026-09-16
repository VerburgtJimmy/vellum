---
title: Page actions
description: Copy Markdown, raw source URLs, Edit on GitHub, and handing a page to an AI tool.
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
