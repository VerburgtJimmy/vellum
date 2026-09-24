---
title: Components
description: The built-in directives and value tags.
---

Vellum ships with callouts, tabs, steps and cards. You write them as `:::` directives, a syntax that has been frozen since 0.5.

Each one is a Blade view in `resources/views/components/`, so `:::note` and `<x-vellum::callout type="note">` produce identical markup. Use `:::` for the built-in components and `<x-…>` tags for components you add yourself. See [Extending](/docs/extending).

:::cards
::card[Callouts](/docs/components/callouts)
::card[Tabs](/docs/components/tabs)
::card[Steps](/docs/components/steps)
::card[Cards](/docs/components/cards)
::card[Value tags](/docs/value-tags)
::card[Code blocks](/docs/writing/code-blocks)
::card[Troubleshooting](/docs/troubleshooting)
:::
