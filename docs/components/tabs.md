---
title: Tabs
description: Switchable panels, including code tabs.
---

Tabs are for genuine alternatives: two ways to install the same thing, the same call in
two languages. They hide content, so anything a reader needs to read in sequence belongs
on the page instead.

```md
:::tabs
::tab[Composer]
Run the command.
::tab[composer.json]
Edit the file.
:::
```

:::tabs
::tab[Composer]
Run the command.
::tab[composer.json]
Edit the file.
:::

Each `::tab[Label]` opens a panel that runs to the next `::tab` or the closing `:::`.
Labels are plain text; Markdown inside the brackets is not rendered.

## Code tabs

When **every** panel holds exactly one fenced code block and nothing else, Vellum renders
the group as code tabs: one frame, one copy control, labels as an underlined row.

:::tabs persist="install"
::tab[Composer]
```bash
composer require jimmyverburgt/vellum
```
::tab[composer.json]
```json
{
    "require": {
        "jimmyverburgt/vellum": "^0.5"
    }
}
```
:::

Add one sentence of prose to any panel and the group falls back to ordinary tabs. That is
the switch: one fence per panel, nothing else.

## Remembering the choice

`persist="key"` stores the active tab in `localStorage` under `vellum-tabs-{key}`.

Use the same key on every group that asks the same question. A reader who picks
**Composer** on the installation page sees Composer selected everywhere else, which is
the whole point. Use a different key, or leave `persist` off, when the groups are
unrelated.

## Other directives inside a panel

:::tabs
::tab[Note]
:::note
A callout inside a tab.
:::
::tab[Prose]
Ordinary Markdown in the second panel.
:::

## Rules worth knowing

**Headings inside a panel still join the table of contents.** A reader who clicks one
while a different panel is showing scrolls nowhere. Avoid `##` inside tabs.

**Panel ids come from the label**, lowercased and dashed, so `::tab[composer.json]`
becomes `composer-json`.

**Every panel is rendered into the page**, hidden with CSS rather than loaded on demand.
Search indexes all of it, and so do crawlers.

## Island form

```html
<x-vellum::tabs persist="install">
<x-vellum::tab label="Composer">
Panel body.
</x-vellum::tab>
<x-vellum::tab label="composer.json">
Second panel.
</x-vellum::tab>
</x-vellum::tabs>
```

| Attribute | On | Purpose |
| --- | --- | --- |
| `persist` | `tabs` | localStorage key, as above. |
| `code` | `tabs` | Set `"true"` to force the code-tab presentation. |
| `label` | `tab` | Panel label. `title` is accepted as an alias. |
| `id` | `tab` | Override the generated panel id. |
