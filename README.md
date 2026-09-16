# Vellum

Markdown documentation sites for Laravel.

[![CI](https://github.com/VerburgtJimmy/vellum/actions/workflows/ci.yml/badge.svg)](https://github.com/VerburgtJimmy/vellum/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/jimmyverburgt/vellum.svg)](https://packagist.org/packages/jimmyverburgt/vellum)

0.5 freezes `config/vellum.php` keys and page frontmatter. Breaking changes wait for 1.0.

## Requirements

- PHP 8.4+
- Laravel 11, 12, or 13

## Quick start

```bash
composer require jimmyverburgt/vellum
php artisan vellum:install
```

Open `/docs`. Edit Markdown under `resources/docs/`.

Compile the cache during deploy:

```bash
php artisan vellum:build
```

## What it includes

- Markdown extensions: callouts, tabs, steps, cards, code blocks, heading anchors
- Markdown plus components (`<x-...>` islands, allowlisted env/config/route tags)
- Changelog page and Atom feed
- Client-side search (MiniSearch by default; Laravel Scout optional)
- Optional versioned docs and page gating
- Static HTML export (`vellum:export`)
- Light / dark / system theme, three contrast-tested presets, and a brand accent

## Screenshots

Light:

![Vellum docs light theme](docs/screenshots/docs-light.png)

Dark:

![Vellum docs dark theme](docs/screenshots/docs-dark.png)

## Configuration

`config/vellum.php` (published by `vellum:install`).

Theme:

```php
'theme' => [
    'preset' => 'neutral', // neutral | ocean | laravel
    'primary' => null,     // oklch hue, used by Neutral
    'accent' => null,      // '#7c3aed', or ['light' => '#...', 'dark' => '#...']
    'radius' => '0.5rem',
    'default' => 'system', // light | dark | system
],
```

`accent` recolours links, buttons and the focus ring on any preset. Give it one colour or
one per mode; the button label colour is derived from it.

Every shipped preset clears **WCAG AA (4.5:1)** in both light and dark for body text,
secondary text, links and button labels. `tests/Support/PresetContrastTest.php` reads the
stylesheets and fails the build if a palette edit drops below that.

Optional `'fonts'` injects HTML into the layout head (for example a `<link>` tag).

## Commands

| Command | Description |
| --- | --- |
| `php artisan vellum:install` | Publish config, starter Markdown stubs, and public assets |
| `php artisan vellum:build` | Compile Markdown into the Vellum cache |
| `php artisan vellum:index` | Rebuild the search index (MiniSearch, and Scout when that driver is on) |
| `php artisan vellum:clear` | Clear the compiled cache |
| `php artisan vellum:export` | Export a static HTML site (`--out=` optional) |

## Documentation

Full docs live in [`docs/`](docs) and are what the package renders at `/docs` once
installed. Start with [`docs/index.md`](docs/index.md).

## Credits

Vellum leans on [Fumadocs](https://fumadocs.dev) for its shape, [Phosphor
Icons](https://phosphoricons.com) for every icon, and a short list of PHP and JavaScript
packages named in [`docs/credits.md`](docs/credits.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT. See [LICENSE.md](LICENSE.md).
