---
title: What is Vellum
description: A Laravel package that serves a folder of Markdown as a documentation site from your app, or exports it as a static site.
---

Vellum is a Laravel package that turns a folder of Markdown into a documentation site. The Markdown lives in your application's repository, `php artisan vellum:build` compiles it during deploy, and your app serves it at `/docs`. The same files can also be exported as a static site for a host that does not run PHP.

## What you get

- A sidebar built from your folders, ordered with `meta.json` or frontmatter
- In-browser search filtered for each reader, or Laravel Scout
- Light and dark mode, three colour presets and an accent colour
- Callouts, tabs, steps and cards as `:::` directives, and your own Blade components
- Code highlighted on the server, with titles, line numbers and highlighted lines
- Version folders and a changelog page with an Atom feed
- Access control per page or folder, for signed-in readers or through a Laravel gate
- Value tags that print allowlisted `env`, `config` and route values
- Raw Markdown for every page, a copy button, and links that open the page in ChatGPT or Claude
- A sitemap, canonical links and Open Graph tags
- Link, image and heading checks at build time

## How it works

`vellum:build` compiles each page to a PHP file and writes the navigation and search index. Laravel serves the pages from a route like any other, so requests do not parse Markdown. The CSS and JavaScript ship precompiled with the package, so you install Vellum with Composer alone.

`vellum:export` writes the same site as static HTML, with its assets and a search index. The export differs from the served site in two ways: value tags keep the values they had at export time, and gated pages are left out because a static host has no session to check access against.

## What it does not do

- **Edit in the browser.** Pages are Markdown files in your repository, changed through pull requests.
- **Analytics or AI chat.** There is no hosted service behind Vellum.
- **Build a custom front end.** The layout is a fixed docs theme, though you can override any of its Blade views.
- **Run outside Laravel.** It needs a Laravel app to build in, even when you only use the static export.

## Stability

The current release is 0.6. Config keys, frontmatter and the authoring syntax have been frozen since 0.5. Later releases add to them without renaming anything, and breaking changes wait for 1.0. [Upgrade](/docs/getting-started/upgrade) lists what changed between releases.

[Installation](/docs/getting-started/installation) takes about five minutes. [Comparisons](/docs/comparisons) covers how Vellum differs from LaRecipe.
