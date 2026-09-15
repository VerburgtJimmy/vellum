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

Every preset defines the same token set, including its own sidebar and code-block
surface, and each one is tested for WCAG AA contrast in both light and dark. See
[Contrast](#contrast).

:::note
0.5 removed nine presets: `black`, `vitepress`, `dusk`, `catppuccin`, `purple`, `solar`,
`emerald`, `ruby` and `aspen`. They fall back to `neutral`, and `vellum:build` says so
once. Most of them existed to change one accent colour, which `accent` now does on any
preset.
:::

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
