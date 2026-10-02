---
title: Cards
description: Link cards in a two-up grid.
---

Cards link to other pages, for example the next steps at the end of an overview or the
entry points on a section index. They are not meant as a general layout tool.

```md
:::cards
::card[Installation](/docs/getting-started/installation) Requirements, the package and the build step.
::card[Laravel](https://laravel.com)
:::
```

:::cards
::card[Installation](/docs/getting-started/installation) Requirements, the package and the build step.
::card[Laravel](https://laravel.com)
:::

Cards in a row are as tall as the tallest of them, so a row stays even when only some cards
have a description.

## Syntax

Each card is a single line:

```
::card[Title](href) Description
```

The title and the description are plain text, and the description is optional. The href
can be a docs path, an app path or an external URL. Unlike an external link in prose, a card
with an external URL opens in the same tab and has no outward arrow.

A card needs both a title and an href. `::card[Title]` without an href is not turned into
a card. It appears on the page as the literal text you typed, which shows that the line
needs fixing.

Cards are laid out two per row on wide screens and in a single column on narrow ones, so
an even number of cards fills the grid best.

## Descriptions from the pages

`describe="pages"` gives each card without a description of its own the description of the
page it links to, from that page's frontmatter:

```md
:::cards describe="pages"
::card[Callouts](/docs/components/callouts)
::card[Tabs](/docs/components/tabs)
:::
```

:::cards describe="pages"
::card[Callouts](/docs/components/callouts)
::card[Tabs](/docs/components/tabs)
:::

The description is filled in when the page is shown, from the navigation, so a card follows
its page after the next `vellum:build` without you editing the card. A card for a page the
reader cannot open shows no description, so a gated page's summary is never shown to
someone it is hidden from. A card linking outside the docs shows the site's domain, and a
card with its own description keeps it.

## Only cards belong inside

`:::cards` should contain only `::card` lines. A paragraph between them becomes a grid
cell of its own, so put any introductory text above the directive:

```md
Pick up where you left off:

:::cards
::card[Configuration](/docs/getting-started/configuration)
::card[Navigation](/docs/writing/navigation)
:::
```

## A section index

The typical use is an overview page that ends with links to the pages in its section,
described by the pages themselves:

```md
:::cards describe="pages"
::card[Callouts](/docs/components/callouts)
::card[Tabs](/docs/components/tabs)
::card[Steps](/docs/components/steps)
::card[Cards](/docs/components/cards)
:::
```
