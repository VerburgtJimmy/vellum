---
title: Gating
description: Hide pages from guests, require auth, or require a Laravel gate.
---

```yaml
---
title: Billing
access: auth
---
```

Set `access` to `guest` (the default), `auth`, or the name of a Laravel gate. Each page's access is resolved once, into the same visibility set that search uses, and applied to the sidebar, the page route, raw Markdown and search.

`guest` and `auth` are case-insensitive, so `access: Auth` works. Any other value is passed to `Gate::allows()` exactly as written, so it must match the gate's registered name, including case.

Access set on a folder applies to everything below it. Set it in the folder's `meta.json` or `_meta.md`; a page's own `access` overrides it. Links listed in `meta.json` inherit the folder's access too, and a link to a gated page is only shown to readers who can open that page.

```json
{
  "title": "Billing",
  "access": "auth"
}
```

```yaml
---
access: auth
---
```

Guests and users who fail the gate get a 404, so a gated page looks the same as one that does not exist.

`vellum:export` runs as a guest. It leaves out gated pages and prints a warning for each one: `Dropped gated page: {slug} (access: ...)`.

:::warning[Images and other assets are not gated]
Gating applies to pages: the page route, the sidebar, raw Markdown and search. Files under `/{prefix}/_vellum/files/` are assets, and that route serves them to anyone who has the URL. It never serves Markdown, `meta.json`, `_meta.md` or dotfiles, so a gated page's source cannot be read through it, but a diagram stored next to that page can. Keep anything secret out of the docs directory.
:::
