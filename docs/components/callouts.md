---
title: Callouts
description: Notes, tips, warnings, and related asides.
---

A callout sets one point apart from the surrounding text. Use them sparingly, because
callouts lose their emphasis when a page has too many of them.

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

:::warning[Careful]
Optional titles go in brackets after the directive name.
:::

## The five types

| Directive | Use it for |
| --- | --- |
| `:::note` | Useful information that does not change what the reader does. |
| `:::tip` | A shortcut, or a better way to do what the reader is already doing. |
| `:::info` | Background that is neither a warning nor advice. |
| `:::warning` | Something likely to cause problems, such as a limitation, a common mistake or a migration step. |
| `:::danger` | Data loss, irreversible actions and security risks. |

:::tip
Tips map to the same style as `success`.
:::

:::info
Extra context that is not a warning.
:::

:::danger
Destructive or irreversible actions.
:::

`success` is an alias of `tip`, and `idea` is an alias of `note`. The name you wrote is
kept in the `data-vellum-callout` attribute, so you can select an alias separately in CSS
or JavaScript even though it looks the same as the type it maps to.

## Titles

Put the title in square brackets directly after the directive name:

```md
:::warning[Run this before deploying]
`vellum:build` has to run after the Markdown changes, not before.
:::
```

:::warning[Run this before deploying]
`vellum:build` has to run after the Markdown changes, not before.
:::

Without a title, the callout shows only its icon and body.

:::warning[title= is not the bracket form]
`:::note title="Custom"` parses without an error, but the title is ignored. Only the
bracket form sets a title. This is a known limitation.
:::

## Contents

The body is ordinary Markdown and can hold lists, links, code, tables and other
directives.

:::note[A callout with a block in it]
Surfaces are layered, so a code block inside a callout sits one level deeper than the
callout around it:

```bash
php artisan vellum:build
```
:::

## Island form

The same Blade view is available as a tag for use inside a component:

```html
<x-vellum::callout type="tip" title="Same view">
Body here.
</x-vellum::callout>
```

| Attribute | Purpose |
| --- | --- |
| `type` | One of the five types, or an alias. Anything unrecognised falls back to `note`. |
| `title` | Optional title. |
| `name` | Overrides the value in `data-vellum-callout`. Defaults to `type`. |

Use `:::` in docs pages. Use `<x-vellum::callout>` when a component of your own needs a
callout inside it.
