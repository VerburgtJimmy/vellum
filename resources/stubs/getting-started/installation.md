---
title: Installation
description: Install Vellum in a Laravel application.
---

Require the package with Composer and run the installer:

```bash title="terminal" {1} showLineNumbers
composer require jimmyverburgt/vellum
php artisan vellum:install
```

The installer publishes the config file and copies these starter docs. You can also run it on its own:

```bash
php artisan vellum:install
```

:::tip
Edit or delete these starter pages in `resources/docs` as you write your own.
:::

:::info
Code blocks support titles, highlighted lines, line numbers and a copy button.
:::
