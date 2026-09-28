---
title: Core on its own
description: Use Vellum's content engine without its routes and layout.
---

Vellum is two Composer packages, released together under one version:

- `jimmyverburgt/vellum` is the docs site: routes, layout, theme, search dialog, and the `vellum:install` and `vellum:export` commands.
- `jimmyverburgt/vellum-core` is the engine under it. It reads the docs folder and handles frontmatter, navigation, versions, gating, Markdown, the compiled cache, search and the changelog.

Installing `jimmyverburgt/vellum` includes core, and that is what most apps want. Require core on its own when you render the docs yourself, for example in an Inertia or Livewire front end, or when a package of yours reads the docs:

```bash
composer require jimmyverburgt/vellum-core
```

## What core provides

- `config/vellum.php`, published with `php artisan vendor:publish --tag=vellum-config`. Core reads `path`, `versions`, `route`, `components`, `search`, `checks`, `changelog` and `cache`, the last for where compiled pages go. `route.prefix` is the start of every link core builds. The other keys, such as `layout`, `theme` and `export`, belong to the site and do nothing without it.
- `vellum:build`, `vellum:index` and `vellum:clear`, which work as described in [Commands](/docs/commands).
- No routes, controllers, layout, styles or scripts.

## Pages

`ContentRepository` is the entry point. It finds pages by slug, compiles them when they changed, and checks them against the reader's access:

```php
use Vellum\Content\ContentRepository;

$docs = ContentRepository::fromConfig();
$page = $docs->find('getting-started/installation');

if ($page === null || ! $docs->allows($page)) {
    abort(404);
}

$page->title;               // frontmatter, the first heading, or the file name
$page->description;
$page->headings;            // for a table of contents
$docs->render($page);       // the page's HTML, components included
$docs->navigation();        // the sidebar tree this reader may see
$docs->adjacent($page->slug); // previous and next pages
```

Use `render()` rather than `$page->html`. The stored HTML holds a placeholder for each Blade component in the page, and `render()` puts the rendered components in their place.

## Markup

The HTML uses `vellum-*` classes and `data-vellum-*` attributes and carries no styles. Style it with your own CSS, or start from the site's `resources/css/vellum.css`.

A few parts need a script to work:

- Code blocks have a copy button marked `data-vellum-copy-code`, inside the block's `data-vellum-code` element. Its `data-vellum-code-kind` is `file`, `terminal` or `snippet`, and in a terminal each command line has the class `vellum-code-command`, for a prompt drawn with CSS.
- Headings have a copy-link button marked `data-vellum-heading-copy`.
- Tabs follow the WAI-ARIA tabs pattern, with the first panel open and the others `hidden`.

To change the markup of a component, see [Change a built-in's markup](/docs/extending#change-a-built-ins-markup).

## Search

`vellum:build` writes a search index for each version. To query it for the current reader:

```php
use Vellum\Answers\AnswersQuery;
use Vellum\Answers\Ranker;

$query = new AnswersQuery;
$index = $query->index($docs, version: null);
$visible = $index->forAccess($query->allowed($index));

$results = (new Ranker($visible))->search('deploy', 10);
```

Each result's `record` has the page, heading, URL and a passage of text. This is the same ranking the site's search endpoint uses. See [Search](/docs/search).
