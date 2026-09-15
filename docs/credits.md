---
title: Credits
description: The open source Vellum is built on, and the work it takes its cues from.
---

Vellum is a thin layer over other people's work. This page names it.

## Inspiration

[**Fumadocs**](https://fumadocs.dev) is the reference Vellum is measured against. The
sidebar and table-of-contents layout, the `:::` directive syntax, code tabs with a shared
chrome, and the idea that a docs framework should feel like a design system rather than a
theme all come from spending time in Fumadocs. It is a React and Next.js project; Vellum
is the same shape of thing for a Laravel app that would rather not add a second codebase.
If you are building on Next, use Fumadocs.

The syntax highlighting palette is **GitHub Light** and **GitHub Dark**, which is also the
Shiki theme Fumadocs ships by default. Code in these docs should look the way it looks in
your editor and on GitHub.

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

[**Laravel**](https://laravel.com) is the whole premise: Vellum is docs that ship inside
the app you already deploy, which only works because routing, Blade, caching, gates and
config are already there.

Testing and quality tooling: [Pest](https://pestphp.com),
[Orchestra Testbench](https://packages.tools/testbench),
[PHPStan](https://phpstan.org) with [Larastan](https://github.com/larastan/larastan), and
[Laravel Pint](https://laravel.com/docs/pint).

## Licences

Vellum is MIT licensed. Every package above is MIT or BSD licensed; running
`composer licenses` in your app lists the exact terms for the versions you have installed.
