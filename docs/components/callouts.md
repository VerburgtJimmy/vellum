---
title: Callouts
description: Notes, tips, warnings, and related asides.
---

A callout pulls one idea out of the flow of the page. Use them sparingly: a page where
every third paragraph is a callout has no emphasis left.

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
| `:::note` | Something worth knowing that does not change what the reader does. |
| `:::tip` | A shortcut or a better way to do the thing they are already doing. |
| `:::info` | Context that is neither a warning nor advice. |
| `:::warning` | Something that will bite them: a footgun, a limitation, a migration step. |
| `:::danger` | Data loss, irreversible actions, security. |

:::tip
Tips map to the same style as `success`.
:::

:::info
Extra context that is not a warning.
:::

:::danger
Destructive or irreversible actions.
:::

`success` is an alias of `tip`, and `idea` is an alias of `note`. The alias you wrote is
kept in the `data-vellum-callout` attribute, so you can style or find them separately
even though they share an appearance.

## Titles

The title goes in square brackets, straight after the directive name:

```md
:::warning[Run this before deploying]
`vellum:build` has to run after the Markdown changes, not before.
:::
```

:::warning[Run this before deploying]
`vellum:build` has to run after the Markdown changes, not before.
:::

Without a title the callout shows its icon and body only.

:::warning[title= is not the bracket form]
`:::note title="Custom"` parses without complaint and the title is ignored. Only the
bracket form sets a title. This is a known rough edge in 0.5.
:::

## Contents

The body is ordinary Markdown: lists, links, code, tables, even another directive.

:::note[A callout with a block in it]
The surfaces are layered, so a code block inside a callout sits one step deeper than the
callout itself:

```bash
php artisan vellum:build
```
:::

## Island form

The same Blade view, when you need it inside a component:

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

Prefer `:::` in docs. Reach for `<x-vellum::callout>` when you are writing a component of
your own and want a callout inside it.
