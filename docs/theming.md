---
title: Theming
description: Three contrast-tested presets, a brand accent, corner radius, and a light, dark or system default.
---

```php
'theme' => [
    'preset' => 'neutral',
    'primary' => null,
    'accent' => null,
    'radius' => '0.5rem',
    'default' => 'system',
],
```

## Presets

| Preset | Notes |
| --- | --- |
| `neutral` | The default grey. `primary` (an oklch hue) only applies to this preset. |
| `ocean` | Cool blue page and a deep navy accent, with a near-black navy dark mode. |
| `laravel` | The laravel.com colours: warm sand neutrals and the Laravel red. |

An unknown preset name falls back to `neutral`.

Every preset defines the same set of tokens and is contrast-tested in light and dark mode.
See [Surfaces](#surfaces) and [Contrast](#contrast).

:::note
0.5 removed nine presets: `black`, `vitepress`, `dusk`, `catppuccin`, `purple`, `solar`,
`emerald`, `ruby` and `aspen`. A config that still names one falls back to `neutral`, and
`vellum:build` prints a single warning about it. Most of these presets only changed the
accent colour, which you can now set on any preset with `accent`.
:::

## Surfaces

Every preset uses the same three surface layers in both modes:

| Token | Used for | Position |
| --- | --- | --- |
| `--background` | prose | the canvas, at the light or dark extreme |
| `--card` | sidebar, callouts, popovers | one step toward mid-grey |
| `--muted` | code blocks, table headers, tab strips, step markers | a second step |

Each layer steps away from the canvas in one direction: darker in light mode and lighter
in dark mode. The reading surface stays the cleanest part of the page, and nested elements
go one step deeper, so a code block inside a callout sits below the callout. The contrast
test checks both the direction and the order.

## Accent

`accent` recolours links, buttons and the focus ring on any preset. Set one colour for
both modes, or one per mode:

```php
'accent' => '#7c3aed',
```

```php
'accent' => [
    'light' => '#1d4ed8',
    'dark' => '#bfdbfe',
],
```

Hex, `hsl()` and `oklch()` values are accepted. The button label colour is derived from
the accent: a light accent gets dark text and a dark accent gets white text. A value that
cannot be parsed is ignored and the page renders with the preset's own colours.

`primary` is the older option and still works, but it only sets an oklch hue on
`neutral`. Use `accent` instead.

## Contrast

Every shipped preset meets WCAG AA (4.5:1) in both modes for:

- body text on the page background
- secondary text (sidebar items, breadcrumbs, table of contents, page meta)
- link and accent text
- button labels on a filled button

Syntax highlighting uses one palette for light mode and one for dark, shared by every
preset. Each token colour clears 4.5:1 on every preset's code surface, so a preset changes
the code block background and leaves the token colours alone.

`tests/Support/PresetContrastTest.php` reads the shipped stylesheets and fails if a palette
change drops below these ratios. A custom `accent` is not covered by that test, so choose
one that clears 4.5:1 against your page background.

## Default mode

`default` sets the mode a first-time reader gets: `light`, `dark` or `system`, which
follows the operating system. Readers can change it from the theme menu. Their choice is
stored in `localStorage` and takes precedence over this setting from then on.

## Radius

`radius` sets the `--radius` CSS variable on the document. Every rounded corner in the
layout is derived from it, so cards, buttons, code blocks and the search dialog change
together. Any CSS length works.

```php
'radius' => '0.25rem',
```

## Fonts

`fonts` is a top-level config key, separate from `theme`. Its value is added to the layout
head as raw HTML, so you can load a web font without publishing the layout:

```php
'fonts' => '<link rel="stylesheet" href="https://example.com/inter.css">',
```

Loading the font does not apply it. Set the family in a stylesheet the layout already
loads, or add a `<style>` block to the same value:

```php
'fonts' => '<link rel="stylesheet" href="https://example.com/inter.css">'
    .'<style>body { font-family: Inter, sans-serif }</style>',
```

## Where search sits

`layout.search` is either `sidebar` (the default, which also removes the top header) or
`header`.
