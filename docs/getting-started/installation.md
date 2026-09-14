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

`vellum:clear` drops that cache. It also runs from `optimize:clear`.

`vellum:index` rebuilds the MiniSearch compile cache. When `vellum.search.driver` is `scout`, it also syncs Laravel Scout.

## Next

- [Configuration](/docs/getting-started/configuration) for the frozen config schema
- [Upgrade](/docs/getting-started/upgrade) if you are coming from 0.2
- [Export](/docs/export) for a static host
