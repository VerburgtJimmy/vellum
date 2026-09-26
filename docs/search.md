---
title: Search
description: Built-in search in the browser by default, with Laravel Scout as an option.
---

```php
'search' => [
    'enabled' => true,
    'hotkey' => 'k',
    'driver' => env('VELLUM_SEARCH_DRIVER', 'builtin'),
    'scout' => [
        'index' => 'vellum',
    ],
],
```

## In the browser

The default driver is `builtin`. It needs no extra services and works on every host, including sites built with `vellum:export`. `minisearch` is still accepted as its old name.

The browser loads its index from `/docs/_vellum/answers.json`, which holds every section of every page the reader can open. It is filtered for the current user and cached per visibility set (guest, authenticated, and each combination of gates). Responses carry an ETag and are sent with `must-revalidate`, so a browser that already has the current index gets a 304.

`Ctrl+K` or `⌘K` opens search. The arrow keys move through the results and Escape closes the dialog. The dialog's accessible label is "Search documentation".

## How results are ranked

Search works on sections: each heading and the text under it is one result, shown with its page and heading, so a match takes the reader to the right part of a long page. At most two sections of one page are listed before sections from other pages.

Sections are ranked with BM25 over their title, heading and text, with the title weighted highest. A word also matches the start of longer words, at a lower weight, so results appear while the reader is still typing. A section whose heading, page title or alias the query names in full is ranked above one that only mentions those words.

Each section is also indexed with questions derived from its content when the docs are built:

| From | Example |
| --- | --- |
| Its heading | "what is meta.json" |
| A shell command in it | "how do i build", "what does vellum:build do" |
| A config key it documents | "what does checks.strict do" |
| A warning or danger callout | "why does assets are not gated fail" |
| A `questions:` list in the page's frontmatter | The questions you write |

A reader's words are expanded with a short built-in list of synonyms (for example "night mode" also searches for "dark mode") and with the `aliases:` a page declares in its frontmatter:

```yaml
---
title: Billing
aliases:
  - invoices
  - payments
---
```

## Checking search in CI

A renamed heading or a rewritten page can move an answer out of reach without breaking a link. To catch that, add a `questions.yml` next to your Markdown with the questions readers actually ask and where each is answered:

```yaml
- q: What does vellum:install publish?
  page: commands
  section: velluminstall
- q: What should a deploy run?
  page: commands
  section: a-deploy
  also:
    - page: getting-started/installation
      section: production
```

`q` is the question as a reader would type it, `page` is the page slug and `section` is the heading id that answers it. Without `section`, any section of the page counts. `also` lists other places that answer the question equally well.

`vellum:build` asks every question against the index it just wrote and reports how many find their answer in the top five results:

```
search check: 40 of 62 questions answered in the top 5 (65%)
  missed: which php and laravel do i need (wanted getting-started/installation#requirements)
```

Set `checks.search_min` (or `VELLUM_SEARCH_MIN`) to the share that must be answered, and the build fails below it. A good starting point is a little under the rate you get today, so a rewrite that makes search worse is caught.

A question whose target no longer exists is reported as a broken question and left out of the count. `--strict` and `checks.strict` fail the build on one. The check uses the index a guest gets, runs whenever the docs have a `questions.yml`, and can be turned off with `checks.search`; `vellum:build --check-search` runs it for one build even when it is off.

```yaml
- run: php artisan vellum:build --check-search --strict
  env:
    VELLUM_SEARCH_MIN: '0.6'
```

## The search endpoint

`{prefix}/_vellum/search?q=` runs the same search on the server and returns JSON, for clients such as agents and scripts that cannot use the browser's index:

```
GET /docs/_vellum/search?q=what+does+vellum:clear+remove
```

```json
{
  "query": "what does vellum:clear remove",
  "results": [
    {
      "url": "/docs/commands#vellumclear",
      "title": "Commands",
      "heading": "vellum:clear",
      "passage": "Removes the compiled pages, the navigation tree, the search index and the rendered component fragments.",
      "updated": "2026-09-20T23:01:13+02:00",
      "score": 2
    }
  ]
}
```

It returns up to five sections, each with its URL, page title, heading, passage and last-updated date. Add `version=` when versions are enabled. An empty `q` returns a 400 response. Results only include pages the caller can open. Set `agents.search` to `false` to turn the endpoint off. A static export has no endpoint, since it has no server.

## Scout

Use Scout if you already run Meilisearch or Typesense and want heading-level relevance on a large docs site.

```bash
composer require laravel/scout
```

Set `VELLUM_SEARCH_DRIVER=scout`. The same visibility filter is applied at query time, and `vellum:build` and `vellum:index` sync the Scout index while that driver is active. Scout searches whole pages on the server, so its results are pages rather than sections.

Each sync replaces the whole index, including every version, so pages you delete, rename or gate do not leave old records behind. Because of this, the index should hold Vellum's records only. `search.scout.index` sets its name and defaults to `vellum`.

When versions are enabled, searches filter on `version`, and the engine has to allow filtering on that attribute. For Meilisearch, add it to the index settings in `config/scout.php` and run `php artisan scout:sync-index-settings`:

```php
'meilisearch' => [
    'index-settings' => [
        'vellum' => ['filterableAttributes' => ['version']],
    ],
],
```

For Typesense, define a collection schema for `Vellum\Search\SearchableDocument` in `model-settings`. It needs `id`, `title`, `content`, `url`, `description`, `access` and `version` as string fields, with `version` marked optional and faceted.

## Export

`vellum:export` always writes the built-in index, whatever `driver` is set to. A static host has no session, so the exported index holds public pages only.

Use `layout.search` to place the search trigger in the sidebar (the default) or in the header.
