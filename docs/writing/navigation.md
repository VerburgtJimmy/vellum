---
title: Navigation
description: How the sidebar is built from folders, meta.json, and frontmatter.
---

The sidebar mirrors your folder structure, so there is no separate navigation file or
route list to maintain.

```
resources/docs/
├── index.md              → /docs
├── why.md                → /docs/why
└── billing/
    ├── meta.json
    ├── index.md          → /docs/billing
    └── invoices.md       → /docs/billing/invoices
```

Each folder becomes a collapsible group, and the `index.md` inside it is the group's own
page. A page's sidebar label is its `title`. Without one, Vellum uses the first `#`
heading, then the file name.

## Ordering

By default, pages in the same folder are sorted by their frontmatter `order`, and pages
without one follow alphabetically by file name:

```md
---
title: Invoices
order: 1
---
```

`order` gets hard to manage once a folder has more than a handful of pages. For larger
folders, list the pages in `meta.json` instead.

## meta.json

Add a `meta.json` to a folder to configure its group:

```json
{
    "title": "Billing",
    "defaultOpen": true,
    "pages": ["index", "invoices", "refunds"]
}
```

| Key | Purpose |
| --- | --- |
| `title` | Group label in the sidebar. Defaults to the folder name, title-cased. |
| `defaultOpen` | Open the group on first load. A group containing the current page always opens. |
| `pages` | Explicit order. Entries are file names without `.md`, relative to this folder. |
| `access` | Gate the whole folder. See [Gating](/docs/gating). |

Names in `pages` are relative to the folder that holds `meta.json`. In `billing/`, write
`"invoices"` and not `"billing/invoices"`.

`pages` sets the order only. Pages you leave out are still shown, in alphabetical order
after the listed ones. To remove a page from the sidebar, gate it with
[`access`](/docs/gating).

### Keep the rest

`"..."` stands for every page not listed by name and sets where those pages appear.
Without it they go at the end:

```json
{
    "pages": ["index", "...", "changelog"]
}
```

Here `index` comes first, `changelog` last, and the rest in alphabetical order between
them.

### Section headings

An entry wrapped in triple dashes becomes a label that is not a link. Use it to split a
long list into sections:

```json
{
    "pages": [
        "index",
        "---Billing---",
        "invoices",
        "refunds",
        "---Reference---",
        "webhooks"
    ]
}
```

Consecutive and trailing separators are removed, so a section whose pages are all
hidden by [gating](/docs/gating) does not leave an empty heading behind.

### External and custom entries

An object entry adds a link to something other than a file in this folder:

```json
{
    "pages": [
        "index",
        { "title": "Changelog", "slug": "changelog" },
        { "title": "API reference", "href": "https://api.example.com" }
    ]
}
```

`slug` is turned into a docs URL. `href` is used as written.

A link inherits its folder's `access` in the same way a page does, and an `access` key on
the link overrides it. A link to a gated page is only shown to readers who can open that
page.

## _meta.md

`_meta.md` sets a folder's `title` and `access` in frontmatter, for when that is all you
need and you would rather not add JSON:

```md
---
title: Billing
access: auth
---
```

`_meta.md` is never rendered as a page. When a folder has both files and they set the same
key, the value in `_meta.md` is used.

## Gating and the sidebar

Pages a reader cannot open are left out of the sidebar entirely, and a group with no
visible pages left is removed too. See [Gating](/docs/gating).

## Two files, one URL

`billing.md` and `billing/index.md` both resolve to `/docs/billing`, and so do two pages
with the same frontmatter `slug`. When that happens, `vellum:build` fails with an error
that names both files.
