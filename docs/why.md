---
title: What is Vellum
description: A Laravel package that serves a folder of Markdown as a documentation site from your app, or exports it as a static site.
---

Vellum is a Laravel package that turns a folder of Markdown into a documentation site. The Markdown lives in your application's repository, `php artisan vellum:build` compiles it during deploy, and your app serves it at `/docs`. The same files can also be exported as a static site for a host that does not run PHP.

## What you get

- A sidebar built from your folders, ordered with `meta.json` or frontmatter
- Search in the browser, filtered for each reader, with Laravel Scout as an option
- Light and dark mode, three colour presets and an accent colour
- Callouts, tabs, steps and cards as `:::` directives, and your own Blade components
- Code highlighted on the server, with titles, line numbers and highlighted lines
- Version folders and a changelog page with an Atom feed
- Access per page or folder: signed-in readers, or a Laravel gate
- Value tags that print allowlisted `env`, `config` and route values
- Raw Markdown for every page, a copy button, and links that open the page in ChatGPT or Claude
- A sitemap, canonical links and Open Graph tags
- Link, image and heading checks at build time

## How it works

`vellum:build` compiles each page to a PHP file, along with the navigation and the search index. Laravel serves them from a route like any other page, so a request does not parse Markdown. The CSS and JavaScript ship compiled with the package: installing needs Composer and nothing else.

`vellum:export` writes the same site as static HTML, with assets and a search index. Two things differ from the served version. Value tags hold the values from the moment of export, and gated pages are left out, since a static host has no session to check.

## What it does not do

- **Edit in the browser.** Pages are Markdown files, changed through pull requests.
- **Analytics or AI chat.** There is no hosted service behind Vellum.
- **Build a custom front end.** The layout is fixed. You can override any of its Blade views, but it is a docs theme, not a site builder.
- **Run outside Laravel.** It needs a Laravel app to build in, even when you only use the static export.

## Stability

The current release is 0.6. Config keys, frontmatter and the authoring syntax have been frozen since 0.5: later releases add to them and do not rename anything. Breaking changes wait for 1.0. [Upgrade](/docs/getting-started/upgrade) lists what changed between releases.

[Installation](/docs/getting-started/installation) takes about five minutes. [Comparisons](/docs/comparisons) covers how Vellum differs from LaRecipe.
