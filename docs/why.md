---
title: Why Vellum
description: Laravel documentation that lives in your repo and ships either way, served from the app or exported static. How Vellum compares with Fumadocs, Mintlify and VitePress.
---

Vellum's premise is a small one: your documentation is Markdown in the Laravel repo, next to the code it describes. It is reviewed in the same pull request, versioned in the same history, and shipped in the same release.

Where it goes from there is your choice. Serve it from the app at `/docs`, or export a static site and host it anywhere. Both paths are first-class and both read the same files.

## One source, two ways to ship it

**Served from your app.** `php artisan vellum:build` compiles the Markdown during deploy and Laravel serves `/docs` like any other route. The docs can then do things only an in-app page can: sit behind a gate, resolve a named route, print a live config value.

**Exported to a static site.** `php artisan vellum:export` writes HTML, assets and a MiniSearch index to a directory you can drop on GitHub Pages, Cloudflare Workers, S3 or any host that cannot run PHP. The theme, search, sidebar and components all come along.

The static snapshot has honest limits, and they follow from what it is. Value tags are baked in at export time, so the page shows the config you exported with rather than the config running now. Gated pages are omitted entirely, and each one is logged as it drops, because there is no session on a static host to gate against.

Plenty of projects use both: the app serves internal docs behind a gate, and an export publishes the public subset. Neither is a fallback for the other, and neither needs a second copy of the Markdown.

## What living in the app adds

If you do serve the docs from Laravel, you get things a separate docs build cannot reach:

**Your gates, your session.** A page can sit behind `auth` or a named Laravel gate, set per page or inherited by a whole folder. Guests get a 404 rather than a login redirect, so a private page does not announce that it exists. There is no second auth system to bridge, because it is the same session.

**Values that are actually true.** Allowlisted value tags read `env`, `config` and named routes at request time, so a page shows the billing URL that is really registered rather than a string someone pasted last quarter. Your own Blade components work in Markdown too, so a pricing table can be the component the app already renders.

**One deploy.** Markdown lives in `resources/docs` and compiles in the same release step as your migrations. There is no second pipeline and no window where the app and its docs disagree.

**No Node in your build.** Vellum ships its compiled CSS and JavaScript. `composer require` then `php artisan vellum:install` is the whole install. There is no bundler to configure and no `node_modules` on your production host.

## Compared with the usual choices

**Fumadocs** is excellent, and Vellum's layout owes it a direct debt. The sidebar, the page structure and the general restraint are cues taken from it, which the [credits](/docs/credits) say plainly. If your product is already a Next.js app, Fumadocs is very likely the right answer. The difference is the runtime, not the philosophy: Fumadocs documents a React app from inside React, and Vellum documents a Laravel app from inside Laravel.

**Mintlify** and other hosted platforms give you a polished site with almost no setup, plus a web editor, analytics and AI search that Vellum does not have. In exchange the content lives on their infrastructure and cannot see your application. For purely public marketing documentation that is often a good trade.

**VitePress and Docusaurus** are mature static site generators with large plugin ecosystems. If the docs are the product, or the site needs a bespoke front end, they give you far more room than Vellum's theme does. Vellum's static export deliberately covers less ground: it is the same docs, on a static host, not a site builder.

**A wiki or Notion** wins on contributors who will never open an editor, and loses on review. Documentation that does not go through a pull request tends not to get read before it is published.

## When another tool is the better fit

Vellum is the wrong shape for some projects, and it is worth saying which:

- **Writers who should never touch the Laravel repo.** This package assumes documentation arrives as Markdown in a pull request. If that is a barrier for the people writing, a hosted editor will serve you better.
- **Docs with their own team and release cadence.** If the documentation site is a product in its own right, give it its own codebase.
- **You want a platform.** Vellum gives you Markdown, search, a theme and two ways to ship. It does not give you a CMS, analytics or AI chat over your content.

## What 0.5 commits to

Config keys and frontmatter names are frozen. The authoring syntax is frozen too: `:::` directives, `<x-…>` components and allowlisted value tags. Layout chrome can still get bug fixes and visual changes. Breaking changes wait for 1.0. See [Upgrade](/docs/getting-started/upgrade) for the 0.2 to 0.5 path.

Ready to try it? [Installation](/docs/getting-started/installation) takes about five minutes, and [Export](/docs/export) covers the static path.
