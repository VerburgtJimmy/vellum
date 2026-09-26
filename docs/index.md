---
title: Vellum
description: Markdown documentation sites that ship with your Laravel app.
---

Vellum serves a folder of Markdown as a documentation site from the Laravel app you already deploy. It includes a sidebar, search, light and dark themes, and an optional static export.

You write pages in Markdown. The built-in callouts, tabs, steps and cards use `:::` directives, and `<x-…>` tags add your own Blade components and the allowlisted `env`, `config` and `route` value tags. Blade syntax such as `{{ }}`, `@if` and `@php` is not compiled in Markdown files.

:::note
`config/vellum.php` keys and page frontmatter have been frozen since 0.5. Breaking changes wait for 1.0.
:::

:::steps
## Require the package
`composer require jimmyverburgt/vellum`

## Publish stubs
`php artisan vellum:install`

## Open the site
Visit `/docs` and edit `resources/docs/`.
:::

:::cards
::card[What is Vellum](/docs/why)
::card[Installation](/docs/getting-started/installation)
::card[Writing Markdown](/docs/writing/markdown)
::card[Components](/docs/components)
::card[Navigation](/docs/writing/navigation)
::card[Extending](/docs/extending)
:::
