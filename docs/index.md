---
title: Vellum
description: Markdown documentation sites that ship with your Laravel app.
---

Vellum turns a folder of Markdown into docs inside the app you already deploy: sidebar, search, light and dark theme, and optional static export.

Authoring is **markdown plus components**. Built-in callouts, tabs, steps, and cards stay as `:::` directives. `<x-…>` is how you add your own Blade components, plus allowlisted `env`, `config`, and `route` tags. There is no `{{ }}`, no Blade directives, and no `@php` in Markdown.

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
