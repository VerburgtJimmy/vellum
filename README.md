# Vellum

Documentation that ships with your Laravel app.

[![CI](https://github.com/VerburgtJimmy/vellum/actions/workflows/ci.yml/badge.svg)](https://github.com/VerburgtJimmy/vellum/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/jimmyverburgt/vellum.svg)](https://packagist.org/packages/jimmyverburgt/vellum)
[![License](https://img.shields.io/packagist/l/jimmyverburgt/vellum.svg)](LICENSE.md)

Vellum turns a folder of Markdown into a documentation site inside the app you already
deploy: sidebar, search, light and dark themes, and an optional static export. No second
codebase, no separate host, no copy of your config to keep in sync.

**[Read the docs](https://vellum.jimmyverburgt.com)** · 0.5 freezes `config/vellum.php`
keys and page frontmatter. Breaking changes wait for 1.0.

## Requirements

- PHP 8.4+
- Laravel 11, 12, or 13

## Quick start

```bash
composer require jimmyverburgt/vellum
php artisan vellum:install
```

Open `/docs` and edit Markdown under `resources/docs/`. Compile once per deploy:

```bash
php artisan vellum:build
```

## What it includes

- Markdown plus components: `:::` callouts, tabs, steps and cards, your own `<x-…>` Blade
  components, and allowlisted `env`, `config` and `route` tags that read the live app
- Code blocks with titles, line numbers and line highlighting, highlighted on the server
- Client-side search over a prebuilt index, or Laravel Scout
- Page gating through `auth` and your existing Laravel gates
- Optional versioned docs with an unprefixed latest
- Changelog page and Atom feed from a Keep a Changelog file
- Three contrast-tested colour presets and a brand accent, all holding WCAG AA
- Static HTML export for hosts that cannot run PHP

## Documentation

**[vellum.jimmyverburgt.com](https://vellum.jimmyverburgt.com)** — built with Vellum, so
it is also the thing it documents.

- [Installation](https://vellum.jimmyverburgt.com/docs/getting-started/installation) and [configuration](https://vellum.jimmyverburgt.com/docs/getting-started/configuration)
- [Writing](https://vellum.jimmyverburgt.com/docs/writing/markdown): Markdown, code blocks, images, navigation
- [Components](https://vellum.jimmyverburgt.com/docs/components) and [extending with your own](https://vellum.jimmyverburgt.com/docs/extending)
- [Commands](https://vellum.jimmyverburgt.com/docs/commands) and [troubleshooting](https://vellum.jimmyverburgt.com/docs/troubleshooting)
- [Upgrading from 0.2](https://vellum.jimmyverburgt.com/docs/getting-started/upgrade)

The same pages ship as Markdown in [`docs/`](docs), which is what the package renders at
`/docs` once installed.

## Commands

| Command | Description |
| --- | --- |
| `vellum:install` | Publish config, starter Markdown, and assets |
| `vellum:build` | Compile Markdown and rebuild the search index |
| `vellum:index` | Rebuild the search index only |
| `vellum:clear` | Clear the compiled cache |
| `vellum:export` | Export a static HTML site |

## Credits

Vellum takes its shape from [Fumadocs](https://fumadocs.dev), and its icons from
[Phosphor](https://phosphoricons.com). The full list is in [`docs/credits.md`](docs/credits.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Security reports go through
[SECURITY.md](SECURITY.md).

Release notes are in [CHANGELOG.md](CHANGELOG.md).

## License

MIT. See [LICENSE.md](LICENSE.md).
