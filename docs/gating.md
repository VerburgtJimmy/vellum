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

`access` is `guest` (default), `auth`, or a Laravel gate name. It is resolved once per page into the same visibility set search uses, then applied to the sidebar, the page route, raw Markdown, and search.

Folder access inherits downward. Set it on `meta.json` or `_meta.md`. A page's own `access` wins.

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

Guests and users who fail the gate get a **404**, not a 403. That keeps private slugs off the public surface.

`vellum:export` runs as a guest: gated pages are omitted, and each one is logged as `Dropped gated page: {slug} (access: ...)`.
