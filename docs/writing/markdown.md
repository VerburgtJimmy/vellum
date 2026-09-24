---
title: Markdown
description: The Markdown flavour Vellum renders, and the few places it differs.
---

Vellum renders **CommonMark plus GitHub Flavored Markdown**, with a small number of
additions. If it works in a GitHub README, it works here.

## What you get

| Feature | Notes |
| --- | --- |
| Headings | `##` to `######` get anchors. See below. |
| Emphasis, lists, blockquotes | Standard CommonMark. |
| Tables | GFM pipe tables. They scroll sideways inside their own wrapper on narrow screens rather than stretching the page. |
| Task lists | `- [x] done` renders a styled checkbox. |
| Strikethrough | `~~gone~~`. |
| Autolinks | Bare URLs become links. |
| Footnotes | `Text[^1]` with `[^1]: The note` renders a numbered footnote with a back-reference. |
| Code | Fenced and inline, both highlighted. See [Code blocks](/docs/writing/code-blocks). |
| Images | See [Images](/docs/writing/images). |

On top of that: `:::` directives for [callouts, tabs, steps and
cards](/docs/components), `<x-…>` tags for [your own
components](/docs/extending), and allowlisted [value tags](/docs/value-tags).

## Headings and anchors

Every heading from `##` down gets an `id` and a copy-link control that appears on hover.
Ids come from the heading text; when two headings in a page produce the same id, Vellum
de-duplicates so each link still lands somewhere.

`#` is left to the page title. Set `title` in frontmatter; the layout renders the `h1`
for you. If you open a page with a `# Heading` and no frontmatter title, that heading
becomes the title and is removed from the body, so the page does not ship two `h1`s.
With both a frontmatter title and a `# Heading`, the heading stays, and `vellum:build`
says so.

The table of contents is built from these headings. A page with none still keeps its
column, so the text width does not jump between pages.

## Links

Internal links are ordinary Markdown: `[Gating](/docs/gating)`.

Links that point outside your app get `target="_blank"`, `rel="noopener"` and a small
outward arrow, so a reader knows before clicking. Vellum decides this by comparing
against `app.url`.

`javascript:` and other unsafe schemes are stripped.

## Raw HTML is removed

Markdown is parsed with `html_input: strip`. A `<details>`, `<div>` or `<img>` written
straight into a page has its tags removed without a warning. Text between the tags
survives, so `Before <details>x</details> after` renders as `Before x after`.

This is deliberate: docs files should not be able to inject arbitrary markup into the
layout. When Markdown cannot express what you need, use a Blade component, which is the
supported escape hatch and one you control. See [Extending](/docs/extending).

## No Blade

`{{ }}`, `@if` and `@php` are never compiled in a docs file. They render as the literal
characters you typed, in prose and in code alike, and the same is true inside component
attributes.

To show a real application value, use an allowlisted [value tag](/docs/value-tags).

## Frontmatter

YAML between `---` fences at the very top of the file:

```md
---
title: Billing
description: How invoices are generated.
---
```

Every key is listed in [Configuration](/docs/getting-started/configuration#frontmatter).
Unknown keys are kept and ignored.

:::note[Save the file as UTF-8 without a BOM]
A byte order mark sits before the opening `---`. Vellum strips a leading BOM so the
frontmatter still parses, but other tools reading your Markdown may not.
:::
