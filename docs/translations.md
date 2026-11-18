---
title: Translations
description: Show the words around your pages, from "On this page" to the playground's messages, in your app's language.
---

Every word Vellum writes around your pages comes from a language file: the sidebar's buttons, "On this page", the search box and its messages, the page actions, the 404 page, the callout labels, the API reference's headings and the playground. Vellum ships them in English and shows them in your app's locale, `app.locale` in `config/app.php`.

To show them in another language, set the locale and add the lines for it.

## Publish the strings

```bash
php artisan vendor:publish --tag=vellum-lang
```

This copies the English files of each package into `lang/vendor/`:

| Folder | Package | Files |
| --- | --- | --- |
| `lang/vendor/vellum/en` | `jimmyverburgt/vellum` | `layout`, `sidebar`, `versions`, `toc`, `search`, `page`, `pagination`, `changelog`, `errors` |
| `lang/vendor/vellum-core/en` | `jimmyverburgt/vellum-core` | `code`, `callouts`, `components`, `navigation`, `changelog` |
| `lang/vendor/vellum-openapi/en` | `jimmyverburgt/vellum-openapi` | `reference`, `playground` |

Each file holds the words of one part of the page, and a comment at its top says which. Each package has its own folder because Laravel reads a namespace's lines from one directory.

## Add a language

Copy a package's `en` folder to a folder named for your locale, such as `lang/vendor/vellum/nl`, and translate the values:

```php
// lang/vendor/vellum/nl/toc.php
return [
    'title' => 'Op deze pagina',
    'label' => 'Inhoudsopgave',
    'progress' => 'Leesvoortgang',
];
```

Keep the keys as they are, and keep each placeholder. Vellum fills in `:version`, `:count`, `:date` and the others; a translation can move one within its sentence, but not rename it. A line with a `|` has a form for one and a form for more, as in `':count result|:count results'`.

A file can hold only some of the lines. Laravel takes each missing line from `app.fallback_locale`, English unless you changed it, so a site can be translated a file at a time. A published `en` file works the same way, for changing a few English words.

## What follows the locale

- The page around your docs: header, sidebar, table of contents, search, page actions, pagination, the changelog page, and the 404 and error pages.
- The scripts. The layout writes the lines they use into the page as one block of JSON, so a [static export](/docs/export) carries the language it was exported in. A layout you published before 0.10 writes no block, and its scripts stay in English. The [upgrade guide](/docs/getting-started/upgrade#views-you-overrode) has the line to add.
- Dates. The "Updated" date under a page's title is written in the locale, and a recent one reads "3 days ago" in the page's language.
- The `lang` attribute of every page, and of the window that finishes signing in to an API from the playground.
- The API reference and its playground.

## When a change shows

Most lines show on the next request. Two places keep what they rendered:

- **API reference pages** are written when they are built, in the locale the build runs in. Run `php artisan vellum:build` after you change the locale or a reference line. In local development a change of locale rebuilds them by itself.
- **Pages that use components**, such as a callout, are kept rendered in the cache, one copy per locale. After you edit a line in a published file, run `php artisan vellum:clear`.

## Words you write yourself

Names that come from your config or your docs are shown as you wrote them: the site's `name`, the `links` labels, `openapi.title`, page and folder titles, and a callout's own title. Two have a default that follows the locale when you leave them unset: `openapi.untagged_label`, the folder for operations without a tag, and the title of the docs' own sidebar, which a `title` in the docs folder's `meta.json` replaces.

## What is not translated

- Your pages. Vellum shows one set of docs; docs written in more than one language, with a switch between them, are not supported yet.
- Search's tuning. Matching by stem and the built-in synonyms are made for English. In another language search still finds the words a reader types, but not their other forms.
- Right-to-left layout.
- The names of keys (`Ctrl`, `Esc`), HTTP status texts and methods, and the type names in the API reference (`string`, `integer`), which read the same in any language.
