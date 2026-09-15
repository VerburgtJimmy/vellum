# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

0.5 stability freeze. Config keys and frontmatter names do not change until 1.0.

### Added

- Package docs for why Vellum, versions, gating, search drivers, changelog, export, theming, and the 0.2 to 0.5 upgrade path
- `laravel` colour preset, matching laravel.com/docs warm sand neutrals and the Laravel red
- `theme.accent`: a brand colour applied to links, buttons and the focus ring on any preset, as one value or `['light' => ..., 'dark' => ...]`. Hex, `hsl()` and `oklch()` are accepted, the button label colour is derived from it, and an unparseable value is ignored
- `Vellum\Support\Color`: colour parsing, relative luminance and WCAG contrast
- Contrast test over the real stylesheets: every shipped preset clears WCAG AA (4.5:1) for body text, secondary text, links and button labels, in both modes
- `CONTRIBUTING.md`, `SECURITY.md`, GitHub issue and pull request templates
- `versions.labels`: switcher display names (`1.x (LTS)`, `Next`) while folders and URLs stay the list slug

### Changed

- Colour presets retuned to clear WCAG AA in light mode. Neutral's secondary text was 4.21:1, Laravel's link and button colours 3.96:1
- Laravel preset uses `#e32c03` for text and fills and keeps `#f53003` for the active wash and the focus ring, the way laravel.com does
- Code blocks take the shared `--muted` surface instead of a hard-coded white and `#191919`, so they match the page in every preset
- The sidebar takes the shared `--card` surface, instead of a tint on some presets and the page background on others
- Surfaces follow one rule in every preset and both modes: `--background` is the canvas at the light or dark extreme, `--card` is one step toward mid-grey for the sidebar, callouts and popovers, and `--muted` is a second step for code blocks, table headers, tab strips and step markers. Surfaces only ever move away from the canvas, darker in light and lighter in dark, so prose sits on the cleanest area and nesting reads correctly
- Light mode canvases are near-white and dark canvases near-black, replacing a mid-grey page with lighter elements sitting on top of it
- Light-mode surfaces are lighter: the code block sat at 1.19:1 from the canvas, roughly twice the step GitHub, VitePress and Fumadocs use, and is now 1.08:1
- Code tabs are tighter: the active underline sits closer to the label, the header is 39px instead of 49px, and the code is inset by 0.25rem instead of 0.5rem
- Code tabs take the same two surfaces as everything else, `--card` for the frame and `--muted` for the code, instead of a hard-coded grey frame around a pure white block
- Syntax highlighting is more colourful. Strings are green, numbers teal, types blue, variables orange, keywords red, and every token clears 4.5:1 on every preset's code surface in both modes
- Neutral's `--muted` was 1.003:1 against the page in light mode, so table headers, tab strips and step markers had no visible surface
- Markdown tabs and UI tabs share `vellumTabs()` for arrow keys, Home/End, and `aria-controls`
- Search dialog labelled for assistive tech (`combobox` + `listbox`); version switcher menu has Home/End and `aria-controls`

### Removed

- Colour presets `black`, `vitepress`, `dusk`, `catppuccin`, `purple`, `solar`, `emerald`, `ruby` and `aspen`. Most changed one accent colour, which `theme.accent` now does on any preset; several could not reach WCAG AA in light mode without losing the character that named them. A removed name falls back to `neutral` and `vellum:build` warns once
- Cloudflare Workers demo (`bin/build-demo`, `wrangler.toml`, committed `demo-dist`). The public site is now a Laravel app that serves `docs/` through the package

## [0.4.0] - 2026-09-13

Versioned docs URLs and search drivers.

### Added

- `php artisan vellum:index` rebuilds the MiniSearch compile cache and, when `search.driver` is `scout`, syncs Laravel Scout
- Page `access` frontmatter (`guest`, `auth`, or a gate name). Folder `meta.json` or `_meta.md` inherits downward; a page overrides. Export logs each dropped gated page

### Changed

- When `vellum.versions.enabled` is true, the latest version is served at `/docs/...`. Older versions stay at `/docs/{version}/...`. `/docs/{latest}/...` redirects to the unprefixed URL with HTTP 301. The version switcher labels the latest folder "Latest".
- Search is a driver (`vellum.search.driver`): MiniSearch by default, Laravel Scout optional. The live MiniSearch index is a package route that filters pages the current user cannot access, cached per visibility set, with an ETag so `must-revalidate` can 304. Static export always writes MiniSearch JSON and drops gated pages.

## [0.3.0] - 2026-09-13

Markdown plus components. Pre-1.0: the API may change.

### Added

- `<x-…>` component islands in Markdown. Slots are Markdown and may nest. Attributes are quoted strings only: no `{{ }}`, Blade directives, or `@php` in docs. Tags inside fenced or inline code stay as text
- Host Blade components via `vellum.components.namespaces` (default `['vellum']` allows `<x-vellum::…>` only; add `''` or `'app'` for `<x-alert>`). Unknown or disallowed tags fail locally and at build
- Built-in `:::callout`, `:::tabs`, `:::steps`, and `:::cards` render the same views as `<x-vellum::callout>`, tabs, steps, and cards. Prefer `:::` for those
- `<x-vellum::env />`, `config`, and `route` value tags, gated by `vellum.components.allowlist` (empty lists refuse every key). They resolve when the page is rendered, including inside `:::tabs`, not when Markdown is compiled
- Request-time fragment cache for those islands, cleared by `vellum:clear` and `optimize:clear`
- Changelog page at `/docs/changelog` and Atom feed at `/docs/changelog.atom` (`vellum.changelog.path`, default `CHANGELOG.md`). Set `path` to `null` to disable. `[Unreleased]` stays in the source file; the page hides it unless `vellum.changelog.unreleased` is true; the feed never includes it
- Package docs in `docs/` (the Cloudflare demo exports that tree). `vellum:install` stubs stay a short getting-started plus one page per built-in

## [0.2.0] - 2026-09-13

Docs chrome and Markdown polish. Pre-1.0: the API may change.

### Added

- Footnotes via CommonMark, with styled list and backlinks
- `vellum.layout.search`: `sidebar` (default) or `header`
- Collapsible desktop sidebar, persisted in `localStorage`, with a floating expand/search pill, width animation, and a rounded left-edge hover peek
- Mobile "On this page" popover: sticky bar, reading-progress circle, overlay rail dropdown (`max-h-[50vh]`)
- Page actions: copy Markdown, Open menu (ChatGPT, Claude, raw Markdown, Edit on GitHub)
- Raw Markdown route `/docs/_vellum/raw/{slug}.md`, included in `vellum:export`
- `success` and `idea` callout aliases (map to tip and note)
- `vellum.theme.preset`: Fumadocs palettes (`neutral`, `black`, `vitepress`, `dusk`, `catppuccin`, `ocean`, `purple`, `solar`, `emerald`, `ruby`, `aspen`)

### Fixed

- Collapsed sidebar peek only opens from the left-edge hotzone after a short cooldown, and hides when the pointer leaves
- Theme menu in the sidebar footer opens upward so it stays on screen
- `bin/build-demo` registers the package through `testbench.yaml` so `vellum:export` is available

### Changed

- Plain inline code uses the same chip as tagged inline code
- Line numbers are CSS counters (`user-select: none`) and are omitted from copied text
- Sidebar item text aligns with the brand; nested items indent from that edge
- Default chrome has no top header; search and theme live in the sidebar
- Copyright footer removed; footer is an empty optional slot
- Previous/next cards use arrows, a bold title, and the page description; they sit after footnotes, half width when both exist, full width for a single direction, stacked on small screens
- Content stays left when there is no table of contents; the TOC column is still reserved
- Last-updated date sits above the H1 next to breadcrumbs
- Tighter content-column padding
- Hotkey chip defaults to `Ctrl` and switches to `⌘` on Apple platforms after hydration
- TOC uses a continuous SVG rail with heading-level jogs, a 420ms active clip, and a path-following thumb
- Heading permalinks are a hover/focus copy-link control (no visible `#`)
- Code copy control is icon-only; title bar uses Phosphor language file marks
- Chrome icons use Phosphor (search, theme, sidebar, carets, GitHub, heading permalinks)
- TOC active and hovered section titles are semibold
- Fenced code uses GitHub Light/Dark token colours on a Fumadocs-like card surface
- Code tabs sit in a lighter grey chrome; the inner fence uses the same surface as standalone code blocks
- Author `---` rules are lighter; theme no longer inserts rules between headings
- `:::cards` render two per row (one on mobile)
- Tempest token classes are styled in the built CSS
- Callouts: rounded card, inset rounded-pill accent rail, Phosphor fill icons aligned to the title/first line, bold title, muted body
- Mobile navigation is a Fumadocs-style right sheet: 85% width, sun/moon header, a heavily blurred page overlay, and the same 280ms sidebar slide
- Mobile "On this page" popover shares one frosted surface with the title bar so the blur matches
- Neutral palette matches Fumadocs (soft gray light, charcoal dark instead of OLED black)
- Copy on embedded/tab code sits on the first line
- Docs images align to the content edge
- Task-list checkboxes use muted-foreground grey so the check stays visible

### Removed

- Default copyright / "Built with Vellum" footer content

## [0.1.0] - 2026-09-11

Initial public release. Pre-1.0: the API may change.

### Added

- Laravel package that serves documentation from Markdown (`resources/docs` by default); supports Laravel 11, 12, and 13
- `vellum:install` to publish config, starter stubs, and public dist assets
- `vellum:build` / `vellum:clear` for an OPcache-friendly compile cache
- Markdown extensions: callouts, tabs, steps, cards, fenced code blocks, inline code highlighting, images, external links, heading anchors
- Docs UI: sidebar navigation, table of contents, breadcrumbs, search (MiniSearch), light/dark/system theme
- Optional version folders with latest redirect and version switcher
- `vellum:export` for static HTML output suitable for GitHub Pages or any static host
- Config surface in `config/vellum.php` (path, routes, versions, theme, search, cache, export)
- GitHub Actions CI (Pest on Laravel 11/12/13, Pint, PHPStan, frontend build / size gate)
- Demo site workflow that exports package stubs for Cloudflare Workers (`bin/build-demo`, `wrangler.toml`)

### Publishing note

Tag `v0.1.0` and push the tag, then submit `https://github.com/VerburgtJimmy/vellum` on Packagist if the package is not listed yet. Do not hardcode `"version"` in `composer.json`.

[Unreleased]: https://github.com/VerburgtJimmy/vellum/compare/v0.4.0...HEAD
[0.4.0]: https://github.com/VerburgtJimmy/vellum/releases/tag/v0.4.0
[0.3.0]: https://github.com/VerburgtJimmy/vellum/releases/tag/v0.3.0
[0.2.0]: https://github.com/VerburgtJimmy/vellum/releases/tag/v0.2.0
[0.1.0]: https://github.com/VerburgtJimmy/vellum/releases/tag/v0.1.0
