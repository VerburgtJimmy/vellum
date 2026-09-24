---
title: Credits
description: The open source Vellum is built on, and the work it takes its cues from.
---

Vellum is built on other people's open source work. This page lists the projects it uses and the ones it borrows ideas from.

## Inspiration

Vellum's design follows [Fumadocs](https://fumadocs.dev). The sidebar and
table-of-contents layout, the `:::` directive syntax, code tabs with a shared chrome, and
the approach of treating a docs framework as a design system all come from Fumadocs.
Fumadocs is a React and Next.js project. Vellum provides the same kind of docs site for a
Laravel app, without adding a second codebase. If you are building on Next.js, use
Fumadocs.

The syntax highlighting palette is GitHub Light and GitHub Dark, the Shiki themes Fumadocs
uses by default, so code looks the way it does on GitHub and in most editors.

## Rendering

| Package | What it does |
| --- | --- |
| [league/commonmark](https://commonmark.thephpleague.com) | CommonMark and GitHub Flavored Markdown. Vellum's directives, callouts, tabs, steps and cards are extensions on top of it. |
| [tempest/highlight](https://github.com/tempestphp/highlight) | Server-side syntax highlighting, so no highlighter ships to the browser. |
| [symfony/yaml](https://symfony.com/doc/current/components/yaml.html) | Page frontmatter. |
| [tales-from-a-dev/tailwind-merge-php](https://github.com/tales-from-a-dev/tailwind-merge-php) | Resolves conflicting Tailwind classes when a component's classes are overridden. |

## Interface

| Package | What it does |
| --- | --- |
| [Phosphor Icons](https://phosphoricons.com) | Every icon in the chrome, the callout glyphs and the code-block language marks. Regular weight for chrome, fill for glyphs. MIT. |
| [Tailwind CSS](https://tailwindcss.com) | The stylesheet, built to a single file at release time. |
| [Alpine.js](https://alpinejs.dev) | The theme toggle, tabs, dialogs, sidebar and table-of-contents behaviour, with the anchor, collapse and focus plugins. |
| [MiniSearch](https://github.com/lucaong/minisearch) | Client-side search over a prebuilt index. |
| [Vite](https://vite.dev) | Builds the CSS and JS bundles. |

## Foundations

Vellum relies on [Laravel](https://laravel.com) for routing, Blade, caching, gates and
config, which is what lets the docs run inside the app you already deploy.

Testing and quality tooling: [Pest](https://pestphp.com),
[Orchestra Testbench](https://packages.tools/testbench),
[PHPStan](https://phpstan.org) with [Larastan](https://github.com/larastan/larastan), and
[Laravel Pint](https://laravel.com/docs/pint).

## Licences

Vellum is MIT licensed. Every package above is MIT or BSD licensed. Run
`composer licenses` in your app to see the exact terms for the versions you have installed.
