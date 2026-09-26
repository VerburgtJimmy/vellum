---
title: Introduction
description: The home page of your documentation.
---

This is the home page of your documentation. Edit `resources/docs/index.md` to replace it with your own content. The examples below show the Markdown extensions Vellum supports.

## Markdown extensions

Highlight inline code by adding a language: `Route::get()`{:php}

:::note
Callouts come in five types: note, tip, info, warning and danger.
:::

:::warning[Careful]
Add an optional title in brackets after the callout type.
:::

:::tabs
::tab[Composer]
```bash title="install.sh"
composer require jimmyverburgt/vellum
```
::tab[composer.json]
```json showLineNumbers
{
    "require": {
        "jimmyverburgt/vellum": "^0.7"
    }
}
```
:::

:::steps
## Install the package
Require Vellum with Composer.

## Publish starter docs
Run `php artisan vellum:install`.
:::

:::cards
::card[Installation](/docs/getting-started/installation)
::card[Laravel](https://laravel.com)
:::

![Placeholder](assets/600x200.svg "Local images include width and height")

See the [Laravel docs](https://laravel.com) for framework details.

## Next steps

- Add pages under `resources/docs/`.
- Run `php artisan vellum:build` when you deploy to production.
