---
title: Navigation
description: How the sidebar is built from folders, meta.json, and frontmatter.
---

The sidebar is your folder structure. There is no separate navigation file to keep in
sync, and no route list to register.

```
resources/docs/
├── index.md              → /docs
├── why.md                → /docs/why
└── billing/
    ├── meta.json
    ├── index.md          → /docs/billing
    └── invoices.md       → /docs/billing/invoices
```

A folder becomes a collapsible group. `index.md` inside it becomes that group's own page.
A page's sidebar label is its `title`, falling back to the first heading, then to the
file name.

## Ordering

Without any configuration, siblings are sorted by frontmatter `order` first, then
alphabetically by file name:

```md
---
title: Invoices
order: 1
---
```

`order` is a blunt tool once a folder has more than a handful of pages. For anything
larger, list the pages explicitly.

## meta.json

Drop a `meta.json` in a folder to control it:

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

Names in `pages` are relative to the folder the file sits in, so `"invoices"`, not
`"billing/invoices"`.

`pages` orders the sidebar; it does not filter it. Anything you leave out is still shown,
appended after the listed pages in alphabetical order. To keep a page out of the sidebar
entirely, gate it with [`access`](/docs/gating).

### Keep the rest

`"..."` stands for every page not named explicitly, and controls **where** those pages
land. Without it they go to the end, so use it to put them somewhere else:

```json
{
    "pages": ["index", "...", "changelog"]
}
```

Here `index` is first, `changelog` is last, and everything else falls in between
alphabetically.

### Section headings

An entry wrapped in triple dashes becomes a non-clickable label, for breaking a long list
into sections:

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

Consecutive and trailing separators are trimmed automatically, so a section whose pages
are all hidden by [gating](/docs/gating) does not leave a heading behind.

### External and custom entries

An object adds a link that is not a file in this folder:

```json
{
    "pages": [
        "index",
        { "title": "Changelog", "slug": "changelog" },
        { "title": "API reference", "href": "https://api.example.com" }
    ]
}
```

`slug` builds a docs URL; `href` is used as written.

A link takes its folder's `access` the way a page does, and an `access` key on the link
overrides it. A link to a gated page is only shown to readers who can open that page.

## _meta.md

`_meta.md` is the same idea in frontmatter, for when you only need `access` or a title
and would rather not add JSON:

```md
---
title: Billing
access: auth
---
```

`_meta.md` is never rendered as a page. When both files are present, `_meta.md` wins for
`access`.

## Gating and the sidebar

Pages a reader cannot see are removed from the sidebar, not greyed out, and a group with
nothing visible left in it disappears entirely. See [Gating](/docs/gating).

## Two files, one URL

`billing.md` and `billing/index.md` both resolve to `/docs/billing`, as do two pages
sharing a frontmatter `slug`. `vellum:build` refuses to build and names both files
rather than letting one silently win.
