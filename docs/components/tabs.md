---
title: Tabs
description: Switchable panels, including code tabs.
---

Use tabs for alternatives, such as two ways to install the same package or one call
written in two languages. Tabs hide all but one panel, so keep content that readers should
go through in sequence on the page itself.

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

Each `::tab[Label]` starts a panel that continues until the next `::tab` or the closing
`:::`. Labels are plain text, and Markdown inside the brackets is not rendered.

## Code tabs

When every panel holds exactly one fenced code block and nothing else, Vellum renders the
group as code tabs. They share one frame and one copy button, and the labels sit in an
underlined row.

:::tabs persist="install"
::tab[Composer]
```bash
composer require jimmyverburgt/vellum
```
::tab[composer.json]
```json
{
    "require": {
        "jimmyverburgt/vellum": "^0.7"
    }
}
```
:::

If any panel contains prose as well, even a single sentence, the group renders as
ordinary tabs.

## Remembering the choice

`persist="key"` stores the active tab in `localStorage` under `vellum-tabs-{key}`.

Give groups that offer the same choice the same key. A reader who picks Composer on the
installation page then sees Composer selected in every other group with that key. For
unrelated groups, use a different key or leave `persist` off.

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

Headings inside a panel still appear in the table of contents. Clicking one while a
different panel is showing does not scroll anywhere, so avoid `##` inside tabs.

Panel ids are generated from the label: it is lowercased and each run of characters other
than letters and digits becomes a dash, so `::tab[composer.json]` becomes `composer-json`.

Every panel is included in the page HTML, and the inactive ones are hidden in the browser.
Search and crawlers index the content of all panels.

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
| `persist` | `tabs` | localStorage key, as described above. |
| `code` | `tabs` | Set to `"true"` to render the group as code tabs. |
| `label` | `tab` | Panel label. `title` works as an alias. |
| `id` | `tab` | Replaces the generated panel id. |
