---
title: Callouts
description: Notes, tips, warnings, and related asides.
---

```md
:::note
Use callouts for notes, tips, warnings, danger, and info.
:::

:::warning[Careful]
Optional titles go in brackets after the directive name.
:::
```

:::note
Use callouts for notes, tips, warnings, danger, and info.
:::

:::tip
Tips map to the same style as `success`.
:::

:::warning[Careful]
Optional titles go in brackets after the directive name.
:::

:::danger
Destructive or irreversible actions.
:::

:::info
Extra context that is not a warning.
:::

`success` is an alias of `tip`. `idea` is an alias of `note`. The `data-vellum-callout` attribute keeps the original name.

The same view is available as `<x-vellum::callout type="note">…</x-vellum::callout>`. Prefer `:::` in docs.
