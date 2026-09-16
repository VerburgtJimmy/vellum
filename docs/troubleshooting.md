---
title: Troubleshooting
description: The errors Vellum raises, what each one means, and the failures that are silent.
---

## Errors you will see

### Invalid frontmatter in [file]: ...

The YAML between the opening and closing `---` did not parse, or the closing `---` is
missing. The message names the file and repeats the YAML parser's reason.

The usual causes are an unquoted value containing a colon (`title: Vellum: docs` needs
quotes) and inconsistent indentation.

### Unknown docs directive [:::name] in [file]

A `:::` block whose name is not one Vellum ships. Almost always a typo. The shipped set is
`note`, `tip`, `info`, `warning`, `danger`, `success`, `idea`, `tabs`, `steps` and
`cards`.

### Unknown docs component [x-name]

A `<x-…>` tag with no matching Blade view. Check the view exists and the tag name matches
its path: `<x-docs::figure>` needs `resources/views/…/docs/figure.blade.php` under a
registered `docs` namespace.

### Component [x-name] is not in vellum.components.namespaces

The view may well exist, but its prefix is not allowed in Markdown. Add the prefix to
`components.namespaces`. Use `''` or `'app'` for unprefixed host components. See
[Extending](/docs/extending).

### Value tag [x-vellum::env] key [APP_KEY] is not in vellum.components.allowlist

Value tags refuse everything by default. Add the key you want to the matching list. This
is deliberate: without it a docs page could print any config value or environment
variable. See [Value tags](/docs/value-tags).

### Unclosed docs component [x-name]

An opening `<x-name>` with no `</x-name>`. Self-closing tags need the slash:
`<x-vellum::env key="APP_NAME" />`.

### Docs component [x-name] attributes must be quoted strings

Something like `:type="$foo"` in Markdown. Docs attributes are literal strings; there is
no expression syntax. Pass dynamic values through a [value tag](/docs/value-tags), or
compute them inside the component.

### Two docs files resolve to [/slug]

`billing.md` and `billing/index.md` both claim `/docs/billing`, or two pages share a
frontmatter `slug`. The message names both files. Rename one.

### vellum.search.driver is scout but laravel/scout is not installed

Either run `composer require laravel/scout` or set the driver back to `minisearch`. See
[Search](/docs/search).

## Where errors appear

In `local`, and in `vellum:build`, these throw with the message above.

In production a reader gets a Vellum error page that says the page could not be rendered
and nothing else; the reason and the file are in your log. `vellum:build` is the place to
catch them, which is why it belongs in your deploy before the app goes live. See
[Commands](/docs/commands).

## Failures that are quiet

These do not raise. Check them by eye.

| Symptom | Cause |
| --- | --- |
| A page shows its frontmatter as body text | The file does not open with `---` on line one. A block that opens and never closes raises instead. |
| An image 404s | The path is resolved from the docs root, not from the page. See [Images](/docs/writing/images). |
| A `<details>` or `<div>` vanished | Raw HTML is stripped. Use a component. See [Markdown](/docs/writing/markdown). |
| `:::note title="x"` has no title | Only the bracket form sets a title: `:::note[x]`. |
| `::card[Title]` renders as literal text | A card needs an href: `::card[Title](/path)`. |
| A page is missing from the sidebar but loads by URL | It is gated, or a parent folder is. See [Gating](/docs/gating). |
| A page is in the sidebar that you left out of `meta.json` | `pages` orders the sidebar, it does not filter it. See [Navigation](/docs/writing/navigation). |
| A table of contents entry scrolls nowhere | The heading is inside a `:::tabs` panel that is not showing. See [Tabs](/docs/components/tabs). |

## Editing changes nothing

In `local`, a page recompiles when its file changes. Everywhere else the compiled cache is
authoritative until you rebuild.

```bash
php artisan vellum:build
```

If a page is still stale after that, clear and rebuild:

```bash
php artisan vellum:clear
php artisan vellum:build
```

Deleting a Markdown file does not remove its compiled page on its own. `vellum:clear`
does.

## The site has no styling

`vellum:install` copies the CSS and JS to `public/vendor/vellum`. Those files are a build
artifact of the package, so a server that has never had `vellum:install` run on it has no
stylesheet to serve. Either commit `public/vendor/vellum` or run `vellum:install` as a
deploy step; running it again is safe, since it only ever overwrites the assets.

## Search returns nothing

Check `search.enabled` is `true`, then rebuild the index with `vellum:build` or
`vellum:index`. Gated pages are absent for readers who cannot see them, which is working
as intended.

## Still stuck

Open an issue with the page's Markdown, the relevant part of `config/vellum.php`, and
what `php artisan vellum:build` prints.
