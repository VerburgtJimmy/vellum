# Vellum

Documentation that ships with your Laravel app.

[![CI](https://github.com/VerburgtJimmy/vellum/actions/workflows/ci.yml/badge.svg)](https://github.com/VerburgtJimmy/vellum/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/jimmyverburgt/vellum.svg)](https://packagist.org/packages/jimmyverburgt/vellum)
[![License](https://img.shields.io/packagist/l/jimmyverburgt/vellum.svg)](LICENSE.md)

Vellum serves a folder of Markdown as a documentation site from the Laravel app you already
deploy, with a sidebar, search, light and dark themes, and an optional static export. The
docs live in your app's repository and ship with each deploy, so there is no second
codebase or host to maintain.

Read the full documentation at [vellum.jimmyverburgt.com](https://vellum.jimmyverburgt.com).
Config keys and page frontmatter have been frozen since 0.5, and breaking changes are held
for 1.0.

## Requirements

- PHP 8.4+
- Laravel 11, 12, or 13

## Quick start

```bash
composer require jimmyverburgt/vellum
php artisan vellum:install
```

Open `/docs` in your browser and edit the Markdown files in `resources/docs/`. In production,
compile the docs as part of each deploy:

```bash
php artisan vellum:build
```

## What it includes

- Markdown with `:::` callouts, tabs, steps and cards, and your own `<x-…>` Blade components
- Allowlisted `env`, `config` and `route` tags that print values from the running app
- Server-side code highlighting with titles, line numbers and highlighted lines
- Client-side search over a prebuilt index, or Laravel Scout
- Page access control through `auth` or your existing Laravel gates
- Optional versioned docs, with the latest version served without a prefix
- A changelog page and Atom feed built from a Keep a Changelog file
- Three colour presets tested for WCAG AA contrast, plus a brand accent colour
- Static HTML export for hosts that cannot run PHP

## Documentation

The documentation site at [vellum.jimmyverburgt.com](https://vellum.jimmyverburgt.com) is
itself built with Vellum.

- [Installation](https://vellum.jimmyverburgt.com/docs/getting-started/installation) and [configuration](https://vellum.jimmyverburgt.com/docs/getting-started/configuration)
- [Writing](https://vellum.jimmyverburgt.com/docs/writing/markdown): Markdown, code blocks, images, navigation
- [Components](https://vellum.jimmyverburgt.com/docs/components) and [extending with your own](https://vellum.jimmyverburgt.com/docs/extending)
- [Commands](https://vellum.jimmyverburgt.com/docs/commands) and [troubleshooting](https://vellum.jimmyverburgt.com/docs/troubleshooting)
- [Upgrading](https://vellum.jimmyverburgt.com/docs/getting-started/upgrade)

The Markdown source for those pages is in [`docs/`](docs).

## Commands

| Command | Description |
| --- | --- |
| `vellum:install` | Publish config, starter Markdown, and assets |
| `vellum:build` | Compile Markdown and rebuild the search index |
| `vellum:index` | Rebuild the search index only |
| `vellum:clear` | Clear the compiled cache |
| `vellum:export` | Export a static HTML site |

## Credits

Vellum's layout is modelled on [Fumadocs](https://fumadocs.dev), and its icons come from
[Phosphor](https://phosphoricons.com). The full list is in [`docs/credits.md`](docs/credits.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Report security issues as described in
[SECURITY.md](SECURITY.md). Release notes are in [CHANGELOG.md](CHANGELOG.md).

## License

MIT. See [LICENSE.md](LICENSE.md).
