---
title: Troubleshooting
description: The errors Vellum raises, what each one means, and the failures that raise no error.
---

## Errors you will see

### Invalid frontmatter in [file]: ...

The YAML between the opening and closing `---` did not parse, or the closing `---` is
missing. The message names the file and includes the YAML parser's reason.

The usual causes are an unquoted value that contains a colon (`title: Vellum: docs` needs
quotes) and inconsistent indentation.

### Unknown docs directive [:::name] in [file]

A `:::` block uses a name that is not one of the shipped directives, usually because of a
typo. The shipped directives are `note`, `tip`, `info`, `warning`, `danger`, `success`,
`idea`, `tabs`, `steps` and `cards`.

### Unknown docs component [x-name]

A `<x-…>` tag has no matching Blade view. Check that the view exists and that the tag name
matches its path: `<x-docs::figure>` needs `resources/views/…/docs/figure.blade.php` under
a registered `docs` namespace.

### Component [x-name] is not in vellum.components.namespaces

The view may exist, but its prefix is not allowed in Markdown. Add the prefix to
`components.namespaces`, using `''` or `'app'` for unprefixed host components. See
[Extending](/docs/extending).

### Value tag [x-vellum::env] key [APP_KEY] is not in vellum.components.allowlist

Value tags allow no keys by default, so a docs page cannot print arbitrary config values
or environment variables. Add the key to the matching list under `components.allowlist`.
See [Value tags](/docs/value-tags).

### Unclosed docs component [x-name]

An opening `<x-name>` has no `</x-name>`. A self-closing tag needs the slash:
`<x-vellum::env key="APP_NAME" />`.

### Docs component [x-name] attributes must be quoted strings

An attribute uses a Blade expression, such as `:type="$foo"`. Attributes in Markdown are
literal strings and have no expression syntax. Pass dynamic values through a
[value tag](/docs/value-tags), or compute them inside the component.

### Two docs files resolve to [/slug]

Two files map to the same URL. Either `billing.md` and `billing/index.md` both claim
`/docs/billing`, or two pages set the same frontmatter `slug`. The message names both
files. Rename one of them.

### vellum.search.driver is scout but laravel/scout is not installed

Run `composer require laravel/scout`, or set the driver back to `minisearch`. See
[Search](/docs/search).

## Where errors appear

`vellum:build` stops with the messages above.

On the site, a page that raises one of these errors returns a Vellum error page with a 500
status. When `APP_DEBUG` is on, as it usually is in `local`, the page shows the message and
the file. When it is off, the page says only that it could not be rendered, and the details
go to your log. Run `vellum:build` in your deploy before the app goes live so these errors
stop the deploy instead of reaching readers. See [Commands](/docs/commands).

## Failures that are quiet

These raise no error, so you have to spot them yourself.

| Symptom | Cause |
| --- | --- |
| A page shows its frontmatter as body text | The file does not start with `---` on the first line. A block that opens but never closes raises an error instead. |
| An image 404s | Image paths resolve from the docs root, not from the page's folder. See [Images](/docs/writing/images). |
| A `<details>` or `<div>` has disappeared | Raw HTML is stripped. Use a component instead. See [Markdown](/docs/writing/markdown). |
| `:::note title="x"` has no title | Only the bracket form sets a title: `:::note[x]`. |
| `::card[Title]` renders as literal text | A card needs a link: `::card[Title](/path)`. |
| A page is missing from the sidebar but loads by URL | The page or one of its parent folders is gated. See [Gating](/docs/gating). |
| A page you left out of `meta.json` still appears in the sidebar | `pages` sets the order of the sidebar but does not remove anything from it. See [Navigation](/docs/writing/navigation). |
| A table of contents entry does not scroll anywhere | The heading is inside a `:::tabs` panel that is not showing. See [Tabs](/docs/components/tabs). |

## Editing changes nothing

In `local`, a page recompiles when its file changes. In every other environment Vellum
serves the compiled cache until you rebuild:

```bash
php artisan vellum:build
```

If a page is still out of date after that, clear the cache and rebuild:

```bash
php artisan vellum:clear
php artisan vellum:build
```

The same applies to adding, deleting and renaming pages. Outside `local`, a new page is
served once `vellum:build` has compiled it, and a deleted or renamed page stops being served
after the next `vellum:build`.

## The site has no styling

Vellum registers routes under `/vendor/vellum/`, including `/vendor/vellum/vellum.css`
and `/vendor/vellum/vellum.js`, that serve the compiled assets directly from the package.
Styling therefore works on a server where `vellum:install` has never run, and you do not
need to publish or commit the assets.

`vellum:install` also copies the files into `public/vendor/vellum`. When they are there,
the web server serves them as static files and the routes are never called, which saves a
PHP request per asset. Either setup works.

If pages are unstyled, the routes cannot be reached. Check that the package is discovered
(`php artisan route:list | grep vellum.assets`) and that nothing in your app intercepts
`/vendor/*`.

## Search returns nothing

Check that `search.enabled` is `true`, then rebuild the index with `vellum:build` or
`vellum:index`. Gated pages are left out of the results for readers who cannot open them;
that is intended.

## Still stuck

Open an issue with the page's Markdown, the relevant part of `config/vellum.php`, and the
output of `php artisan vellum:build`.
