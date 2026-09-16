---
title: Cards
description: Link cards in a two-up grid.
---

Cards are a way out of a page: four good next steps at the end of an overview, or the
entry points on a section index. They are not a layout tool for arbitrary content.

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

Each card is a single line, and the whole line has to match:

```
::card[Title](href)
```

The title is plain text. The href can be a docs path, an app path, or an external URL.
External hrefs open in a new tab with `rel="noopener"`, the same as any other external
link.

A card needs both parts. `::card[Title]` with no href does not become a card; it is left
in the page as the literal text you typed, which is the clearest signal available that
the line is wrong.

Cards sit in a two-up grid on wide screens and stack on narrow ones. Two, four or six
read better than three or five.

## Only cards belong inside

`:::cards` expects `::card` lines. A paragraph written between them is rendered as a
grid cell of its own, which is almost never what anyone wants. Put the prose above the
directive:

```md
Pick up where you left off:

:::cards
::card[Configuration](/docs/getting-started/configuration)
::card[Navigation](/docs/writing/navigation)
:::
```

## A section index

The pattern this exists for: an overview page that ends by handing the reader onward.

```md
:::cards
::card[Callouts](/docs/components/callouts)
::card[Tabs](/docs/components/tabs)
::card[Steps](/docs/components/steps)
::card[Cards](/docs/components/cards)
:::
```
