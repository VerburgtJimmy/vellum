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
- Light / dark / system theme with eleven colour presets

## Screenshots

Light:

![Vellum docs light theme](docs/screenshots/docs-light.png)

Dark:

![Vellum docs dark theme](docs/screenshots/docs-dark.png)

## Demo

The site in `docs/` deploys via Cloudflare Workers (`wrangler.toml` + committed `demo-dist`).

Cloudflare Git build settings:

- Build command: leave empty
- Deploy command: `npx wrangler deploy`

Regenerate the static export locally after docs changes: `bash bin/build-demo`

## Configuration

`config/vellum.php` (published by `vellum:install`).

Theme:

```php
'theme' => [
    'preset' => 'neutral', // black, vitepress, dusk, catppuccin, ocean, purple, solar, emerald, ruby, aspen
    'primary' => null,     // oklch hue, used by Neutral
    'radius' => '0.5rem',
    'default' => 'system', // light | dark | system
],
```

Optional `'fonts'` injects HTML into the layout head (for example a `<link>` tag).

## Commands

| Command | Description |
| --- | --- |
| `php artisan vellum:install` | Publish config, starter Markdown stubs, and public assets |
| `php artisan vellum:build` | Compile Markdown into the Vellum cache |
| `php artisan vellum:index` | Rebuild the search index (MiniSearch, and Scout when that driver is on) |
| `php artisan vellum:clear` | Clear the compiled cache |
| `php artisan vellum:export` | Export a static HTML site (`--out=` optional) |

## Publishing

```bash
git tag v0.5.0
git push origin v0.5.0
```

Submit the repo once at [https://packagist.org/packages/submit](https://packagist.org/packages/submit). Packagist reads the Git tag; do not add a `version` field to `composer.json`.

## License

MIT. See [LICENSE.md](LICENSE.md).
