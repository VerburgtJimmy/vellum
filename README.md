# Vellum

Markdown documentation sites for Laravel.

## Requirements

- PHP 8.4+
- Laravel 11 or 12

## Quick start

```bash
composer require jimmyverburgt/vellum
php artisan vellum:install
```

Open `/docs` in your app. Edit Markdown under `resources/docs/`.

For production, compile the cache once during deploy:

```bash
php artisan vellum:build
```

## Features

- Markdown extensions: callouts, tabs, steps, cards, code blocks, heading anchors
- Client-side search
- Optional versioned docs
- Static HTML export
- Light / dark / system theme

## Screenshots

Light theme:

![Vellum docs in light theme](docs/screenshots/docs-light.png)

Dark theme:

![Vellum docs in dark theme](docs/screenshots/docs-dark.png)

## Demo

Live stub docs: [https://verburgtjimmy.github.io/vellum/](https://verburgtjimmy.github.io/vellum/)

## Configuration

Publish and edit `config/vellum.php` (also published by `vellum:install`).

## Commands

| Command | Description |
| --- | --- |
| `php artisan vellum:install` | Publish config, starter Markdown stubs, and public assets |
| `php artisan vellum:build` | Compile Markdown into the Vellum cache |
| `php artisan vellum:clear` | Clear the compiled cache |
| `php artisan vellum:export` | Export a static HTML site (`--out=` optional) |

## Publishing

Packagist does not use a `version` field in `composer.json`. Release by tagging:

```bash
git tag v0.1.0
git push origin v0.1.0
```

Then submit the GitHub repo at [https://packagist.org/packages/submit](https://packagist.org/packages/submit) (once). Enable GitHub Service Hook / auto-update so later tags sync.

## License

MIT. See [LICENSE.md](LICENSE.md).
