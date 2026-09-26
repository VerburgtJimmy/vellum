---
title: Installation
description: Requirements, installing the package, and the build step for production.
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

`vellum:install` publishes `config/vellum.php`, copies starter Markdown into `resources/docs` and copies the compiled CSS and JavaScript to `public/vendor/vellum`.

## Open the site

Visit `/docs`, then edit the files in `resources/docs/`.
:::

## Production

Compile the Markdown once per deploy:

```bash
php artisan vellum:build
```

In the `local` environment pages recompile on request as you edit them, so you only need this
command in your deploy. See [Commands](/docs/commands).

## Next

- [Markdown](/docs/writing/markdown) for the Markdown syntax Vellum renders
- [Navigation](/docs/writing/navigation) to arrange the sidebar
- [Configuration](/docs/getting-started/configuration) for every config key and frontmatter field
- [Commands](/docs/commands) for what to run in a deploy
- [Upgrade](/docs/getting-started/upgrade) if you are upgrading from an earlier version
- [Troubleshooting](/docs/troubleshooting) when a page does not render
