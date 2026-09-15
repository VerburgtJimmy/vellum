---
title: Theming
description: Three contrast-tested presets, a brand accent, radius, and light / dark / system.
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
| `neutral` | Default gray. `primary` (oklch hue) only applies here. |
| `ocean` | Cool blue page, deep navy accent, near-black navy in dark. |
| `laravel` | laravel.com colours: warm sand neutrals and the Laravel red. |

Unknown names fall back to `neutral`.

Every preset defines the same token set and is tested in both modes. See
[Surfaces](#surfaces) and [Contrast](#contrast).

:::note
0.5 removed nine presets: `black`, `vitepress`, `dusk`, `catppuccin`, `purple`, `solar`,
`emerald`, `ruby` and `aspen`. They fall back to `neutral`, and `vellum:build` says so
once. Most of them existed to change one accent colour, which `accent` now does on any
preset.
:::

## Surfaces

Three layers, the same in every preset and both modes:

| Token | Carries | Position |
| --- | --- | --- |
| `--background` | prose | the canvas, at the light or dark extreme |
| `--card` | sidebar, callouts, popovers | one step toward mid-grey |
| `--muted` | code blocks, table headers, tab strips, step markers | a second step |

Surfaces only move one way from the canvas: darker in light mode, lighter in dark. That
keeps the reading surface the cleanest area of the page, makes nesting read correctly (a
code block inside a callout sits one step deeper), and means a surface can never collide
with the canvas. The contrast test enforces the direction and the order.

## Accent

`accent` recolours links, buttons, and the focus ring on whichever preset you picked.
Give it one colour, or one per mode:

```php
'accent' => '#7c3aed',
```

```php
'accent' => [
    'light' => '#1d4ed8',
    'dark' => '#bfdbfe',
],
```

Hex, `hsl()` and `oklch()` are accepted. The button label colour is derived from the
accent, so a light accent gets dark text and a dark accent gets white. A value Vellum
cannot parse is ignored rather than breaking the page.

`primary` is the older knob and still works, but it only sets an oklch hue on `neutral`.
Prefer `accent`.

## Contrast

Vellum targets **WCAG AA (4.5:1)** in every shipped preset, in both modes, for:

- body text on the page background
- secondary text (sidebar items, breadcrumbs, table of contents, page meta)
- link and accent text
- button labels on a filled button

`tests/Support/PresetContrastTest.php` reads the real stylesheets and fails the build if
a palette edit drops below that. A custom `accent` is your own responsibility: pick one
that clears 4.5:1 against your page background.

## The rest

`default` is `light`, `dark`, or `system`. Readers can still override in the theme menu;
the choice is stored in `localStorage`.

`radius` sets `--radius` on the document.

Optional `'fonts'` injects HTML into the layout head (for example a `<link>` tag).

`layout.search` is `sidebar` (default, no top header) or `header`.
