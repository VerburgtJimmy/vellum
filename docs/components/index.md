---
title: Components
description: Built-in directives and value tags.
---

Callouts, tabs, steps and cards ship with Vellum. Write them with `:::` directives, which is the authoring syntax frozen since 0.5.

Each one is a Blade view under `resources/views/components/`, so `:::note` and `<x-vellum::callout type="note">` render exactly the same markup. Prefer `:::` for anything shipped; `<x-…>` is how you add **your** own components. See [Extending](/docs/extending).

:::cards
::card[Callouts](/docs/components/callouts)
::card[Tabs](/docs/components/tabs)
::card[Steps](/docs/components/steps)
::card[Cards](/docs/components/cards)
::card[Value tags](/docs/value-tags)
::card[Code blocks](/docs/writing/code-blocks)
::card[Troubleshooting](/docs/troubleshooting)
:::
