---
title: Tabs
description: Switchable panels, including code tabs.
---

`persist` stores the active tab in `localStorage` under `vellum-tabs-{key}`.

When every panel is a single fenced block, Vellum treats the group as code tabs (shared chrome, embedded copy control).

```md
:::tabs persist="pkg-manager"
::tab[npm]
npm install jimmyverburgt/vellum
::tab[composer]
composer require jimmyverburgt/vellum
:::
```

:::tabs persist="pkg-manager"
::tab[npm]
```bash
npm install jimmyverburgt/vellum
```
::tab[composer]
```bash
composer require jimmyverburgt/vellum
```
:::

Panels can contain other directives:

:::tabs
::tab[Note]
:::note
A callout inside a tab.
:::
::tab[Prose]
Ordinary Markdown in the second panel.
:::

Island form, if you need it:

```html
<x-vellum::tabs persist="pkg">
<x-vellum::tab label="npm">
npm body
</x-vellum::tab>
<x-vellum::tab label="pnpm">
pnpm body
</x-vellum::tab>
</x-vellum::tabs>
```
