---
title: Cards
description: Link cards in a two-up grid.
---

Cards link to other pages, for example the next steps at the end of an overview or the
entry points on a section index. They are not meant as a general layout tool.

```md
:::cards
::card[Installation](/docs/getting-started/installation)
::card[Laravel](https://laravel.com)
:::
```

:::cards
::card[Installation](/docs/getting-started/installation)
::card[Laravel](https://laravel.com)
:::

## Syntax

Each card is a single line, and the whole line must match this form:

```
::card[Title](href)
```

The title is plain text. The href can be a docs path, an app path or an external URL.
Unlike an external link in prose, a card with an external URL opens in the same tab and
has no outward arrow.

A card needs both a title and an href. `::card[Title]` without an href is not turned into
a card. It appears on the page as the literal text you typed, which shows that the line
needs fixing.

Cards are laid out two per row on wide screens and in a single column on narrow ones, so
an even number of cards fills the grid best.

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

The typical use is an overview page that ends with links to the pages in its section.

```md
:::cards
::card[Callouts](/docs/components/callouts)
::card[Tabs](/docs/components/tabs)
::card[Steps](/docs/components/steps)
::card[Cards](/docs/components/cards)
:::
```
