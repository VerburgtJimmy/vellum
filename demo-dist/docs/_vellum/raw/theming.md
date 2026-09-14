---
title: Theming
description: Eleven colour presets, radius, and light / dark / system.
---

```php
'theme' => [
    'preset' => 'neutral',
    'primary' => null,
    'radius' => '0.5rem',
    'default' => 'system',
],
```

`preset` is one of:

| Preset | Notes |
| --- | --- |
| `neutral` | Default gray. `primary` (oklch hue) only applies here. |
| `black` | Near-true black dark surface. |
| `vitepress` | VitePress-like slate. |
| `dusk` | Warm muted chrome. |
| `catppuccin` | Pastel Catppuccin-style tokens. |
| `ocean` | Blue. |
| `purple` | Violet. |
| `solar` | Amber. |
| `emerald` | Green. |
| `ruby` | Red. |
| `aspen` | Warm gold. |

Unknown names fall back to `neutral`.

`default` is `light`, `dark`, or `system`. Readers can still override in the theme menu; the choice is stored in `localStorage`.

`radius` sets `--radius` on the document.

Optional `'fonts'` injects HTML into the layout head (for example a `<link>` tag).

`layout.search` is `sidebar` (default, no top header) or `header`.
