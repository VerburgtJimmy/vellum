---
title: Installation
description: Install Vellum in a Laravel application.
---

## Requirements

- PHP 8.4+
- Laravel 11, 12, or 13

## Install

```bash title="terminal"
composer require jimmyverburgt/vellum
php artisan vellum:install
```

`vellum:install` publishes `config/vellum.php`, starter Markdown under `resources/docs`, and the compiled CSS/JS to `public/vendor/vellum`.

Open `/docs`. Edit the files in `resources/docs/`.

## Compile cache

In production, compile Markdown once per deploy:

```bash
php artisan vellum:build
```

`vellum:clear` drops that cache. It also runs from `optimize:clear`.

`vellum:index` rebuilds the MiniSearch compile cache. When `vellum.search.driver` is `scout`, it also syncs Laravel Scout.

## Static export

```bash
php artisan vellum:export
```

Writes HTML, assets, and the search index to `public/docs-static` (configurable). Suitable for GitHub Pages, Cloudflare Workers, or any static host.
