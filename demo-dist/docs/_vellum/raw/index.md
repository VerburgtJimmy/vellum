---
title: Introduction
description: Welcome to your Vellum documentation.
---

This is your documentation home page. Edit `resources/docs/index.md` to get started.

## Markdown extensions

Inline highlight: `Route::get()`{:php}

:::note
Use callouts for notes, tips, warnings, danger, and info.
:::

:::warning[Careful]
Optional titles go in brackets after the directive name.
:::

:::tabs
::tab[npm]
```bash title="install.sh"
npm install jimmyverburgt/vellum
```
::tab[composer]
```bash showLineNumbers
composer require jimmyverburgt/vellum
```
:::

:::steps
## Install the package
Require Vellum with Composer.

## Publish starter docs
Run `php artisan vellum:install`.
:::

:::cards
::card[Installation](/docs/getting-started/installation){icon=book}
::card[Laravel](https://laravel.com){icon=link}
:::

![Placeholder](assets/600x200.svg "Local images include width and height")

See the [Laravel docs](https://laravel.com) for framework details.

## Next steps

- Add pages under `resources/docs/`
- Run `php artisan vellum:build` in production
