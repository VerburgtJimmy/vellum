---
title: Installation
description: Install Vellum in a Laravel application in under five minutes.
---

## Requirements

- PHP 8.4+
- Laravel 11, 12, or 13

## Install

:::steps
## Require the package

```bash
composer require jimmyverburgt/vellum
```

## Publish config, stubs, and assets

```bash
php artisan vellum:install
```

`vellum:install` publishes `config/vellum.php`, starter Markdown under `resources/docs`, and the compiled CSS/JS to `public/vendor/vellum`.

## Open the site

Visit `/docs`. Edit the files in `resources/docs/`.
:::

## Production

Compile Markdown once per deploy:

```bash
php artisan vellum:build
```

In `local`, pages recompile on request as you edit, so this is a deploy step rather than
something to run while writing. See [Commands](/docs/commands).

## Next

- [Markdown](/docs/writing/markdown) for the flavour Vellum renders
- [Navigation](/docs/writing/navigation) to shape the sidebar
- [Configuration](/docs/getting-started/configuration) for the frozen config schema
- [Commands](/docs/commands) for what to run in a deploy
- [Upgrade](/docs/getting-started/upgrade) if you are coming from 0.2
