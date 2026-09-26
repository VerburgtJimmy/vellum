---
title: Markdown
description: The Markdown flavour Vellum renders and where it departs from GitHub.
---

Vellum renders **CommonMark plus GitHub Flavored Markdown**, with a few additions. Anything
that works in a GitHub README works here.

## What you get

| Feature | Notes |
| --- | --- |
| Headings | `##` to `######` get anchors. See below. |
| Emphasis, lists, blockquotes | Standard CommonMark. |
| Tables | GFM pipe tables. On narrow screens a table scrolls sideways inside its own wrapper and the page keeps its width. |
| Task lists | `- [x] done` renders a styled checkbox. |
| Strikethrough | `~~gone~~`. |
| Autolinks | Bare URLs become links. |
| Footnotes | `Text[^1]` with `[^1]: The note` renders a numbered footnote with a back-reference. |
| Code | Fenced blocks are highlighted, and so is inline code tagged with a language. See [Code blocks](/docs/writing/code-blocks). |
| Images | See [Images](/docs/writing/images). |

Vellum also adds `:::` directives for [callouts, tabs, steps and
cards](/docs/components), `<x-…>` tags for [your own
components](/docs/extending), and allowlisted [value tags](/docs/value-tags).

## Headings and anchors

Every heading from `##` down gets an `id` and a copy-link button that appears on hover.
The id is generated from the heading text. If two headings on a page produce the same id,
the later ones get a suffix so every link still resolves.

`#` is reserved for the page title. Set `title` in frontmatter and the layout renders the
`h1` for you. If a page has no frontmatter title and opens with a `# Heading`, that
heading becomes the title and is removed from the body, so the page does not end up with
two `h1`s. If the page has both a frontmatter title and a `# Heading`, the heading stays
and `vellum:build` warns about it.

The table of contents is built from these headings. A page without any keeps the empty
column, so the text width stays the same from page to page.

Write headings answer-shaped: one thing a reader would ask about per heading. Search
indexes a page by section, and both the ranking and the answer card on top of the results
work on one section at a time, so a heading that covers three unrelated settings can only
ever half-answer each of them. When a section starts collecting leftovers, split it.

## Links

Internal links are ordinary Markdown: `[Gating](/docs/gating)`.

A link to a host other than the one in `app.url` gets `target="_blank"`, `rel="noopener"`
and a small outward arrow, so readers can tell it leaves the site before they click.

Links with `javascript:` and other unsafe schemes are stripped.

## Raw HTML is removed

Markdown is parsed with `html_input: strip`, so a `<details>`, `<div>` or `<img>` written
straight into a page is removed without a warning. Inside a paragraph only the tags go and
the text between them stays: `Before <details>x</details> after` renders as
`Before x after`. HTML that starts on its own line forms an HTML block, and the whole block
is dropped, text included.

This keeps docs files from injecting arbitrary markup into the layout. When Markdown cannot
express what you need, write a Blade component. See [Extending](/docs/extending).

## No Blade

`{{ }}`, `@if` and `@php` are never compiled in a docs file. They render as the literal
characters you typed, in prose, in code and inside component attributes.

To show a real application value, use an allowlisted [value tag](/docs/value-tags).

## Frontmatter

YAML between `---` fences at the very top of the file:

```md
---
title: Billing
description: How invoices are generated.
---
```

[Configuration](/docs/getting-started/configuration#frontmatter) lists every key. Unknown
keys are kept and ignored.

:::note[Save the file as UTF-8 without a BOM]
A byte order mark sits in front of the opening `---`. Vellum strips a leading BOM so the
frontmatter still parses, but other tools that read your Markdown may not.
:::
