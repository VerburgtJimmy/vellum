---
title: Comparisons
description: How Vellum differs from LaRecipe, the other package that serves Markdown documentation from inside a Laravel app.
---

## LaRecipe

[LaRecipe](https://github.com/saleem-hadad/larecipe) is the tool closest to Vellum: a Laravel package that serves Markdown from `resources/docs` at `/docs`. It has been around since 2018 and is still maintained. The table reflects both packages as of September 2026.

| | Vellum | LaRecipe |
| --- | --- | --- |
| Rendering | Compiled once by `vellum:build` | Parsed on each request, with an optional cache |
| Markdown | CommonMark and GitHub Flavored Markdown | Parsedown Extra |
| Sidebar | Your folder structure, ordered by `meta.json` or frontmatter | A hand-written `index.md` per version |
| Access control | Per page or folder: signed-in readers or a named gate, hidden pages return 404 | The whole site, behind a guard or your own middleware |
| Search | Built in, in the browser, filtered per reader. Laravel Scout optional | Algolia, or a built-in index of `h2` and `h3` headings |
| Static export | Yes | No |
| Front end | Alpine.js, compiled assets included | Vue |
| Dark mode | Built in | A separate package |
| Components | `:::` directives and Blade components | Blade, and Vue components through asset packages |
| Requirements | PHP 8.4, Laravel 11 to 13 | PHP 7.1 or later, Laravel 5.4 to 13 |

**LaRecipe fits better** if you are on an older PHP or Laravel version, or want one of its add-on packages: Swagger, right-to-left layouts, reader feedback, Disqus comments.

**Vellum fits better** if some pages are for some readers only, if you also want a static copy of the docs, or if you want broken links caught before a deploy.

## Outside Laravel

If the product is not a Laravel app, a documentation framework for its own stack will suit it better: [Fumadocs](https://fumadocs.dev) for Next.js, or [VitePress](https://vitepress.dev) as a standalone static site. Vellum's layout takes its cues from Fumadocs; the [credits](/docs/credits) say which.
