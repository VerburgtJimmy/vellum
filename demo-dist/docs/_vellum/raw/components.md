---
title: Components
description: Built-in directives and value tags.
---

Built-ins use `:::` as the stable authoring syntax. The Blade views under `resources/views/components/` are the single implementation: directives parse attributes and pass inner HTML into those views. `<x-vellum::callout>` and friends resolve to the same views.

`<x-…>` is how you add **your** components. See [Extending](/docs/extending).

:::cards
::card[Callouts](/docs/components/callouts){icon=book}
::card[Tabs](/docs/components/tabs){icon=book}
::card[Steps](/docs/components/steps){icon=book}
::card[Cards](/docs/components/cards){icon=book}
:::

Value tags (`env`, `config`, `route`) are also components. They only resolve when the key is in `vellum.components.allowlist`.
