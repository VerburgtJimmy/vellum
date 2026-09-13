---
title: Steps
description: Numbered procedures from headings.
---

Each `##` heading inside `:::steps` becomes a numbered step. Nested content (including code) stays in that step.

:::steps
## Install the package
Require Vellum with Composer.

## Publish starter docs
Run `php artisan vellum:install`.
:::

:::steps
## Install the package
Require Vellum with Composer.

```bash
composer require jimmyverburgt/vellum
```

## Publish starter docs
Run `php artisan vellum:install`.
:::
