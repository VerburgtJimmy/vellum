---
title: Versions
description: Version folders, unprefixed URLs for the latest version, and the version switcher.
---

Versioning is off by default. When you enable it, your Markdown lives in one folder per version (`docs/v2/`, `docs/v1/`).

```php
'versions' => [
    'enabled' => true,
    'latest' => 'v1',
    'list' => ['next', 'v1'],
    'labels' => [
        'v1' => '1.x (LTS)',
        'next' => 'Next',
    ],
],
```

The `latest` folder is served at `/docs/...` with no version in the URL. Other versions are served under their slug, such as `/docs/v1/...` or `/docs/next/...`. A request for `/docs/{latest}/...` gets a 301 redirect to the unprefixed URL.

`list` runs from newest to oldest, and its order is the order of the switcher. `labels` only changes the switcher text: folders and URLs always use the slug from `list`. A version without a label is shown by its slug.

Where a version sits in `list` also tells Vellum what it is. The one named in `latest` is tagged "Latest" in the switcher. Any version listed before it is newer than the latest, so it is tagged "Unreleased". Any listed after it is an older version.

The changelog stays at `/docs/changelog` for every version. Versions come from `list` in the config, and Vellum does not read git tags.

Each version is a separate Markdown tree, so a page that exists in one version returns a 404 in any version whose folder does not contain it.

## The switcher

With `layout.search` set to `sidebar`, the switcher sits above the search field, at the same size. With `header`, it is a compact button beside the site name, without the tag. Either way its menu lists every version with its tag.

When the page being read does not exist in a version, that version's entry says so, and choosing it opens that version's start page instead.

The switcher is keyboard accessible with the arrow keys, Home, End and Escape. Its trigger's accessible label names the version being read, as in `Documentation version: v2`.

## Reading a version that is not the latest

A page of any version but the latest starts with a notice, so a reader who arrives from a search engine knows which docs they are in:

- On an older version: "You are reading the docs for 1.x (LTS), an older version."
- On an unreleased one: "These are the docs for Next, which is not released yet."

The notice links to the same page in the latest version, or to the latest version's start page when that page does not exist there. Pages of the latest version have no notice.
