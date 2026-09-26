---
title: Release notes
description: Keep a Changelog page and Atom feed.
---

Vellum can render a Keep a Changelog file as `/docs/changelog` with an Atom feed at `/docs/changelog.atom`.

```php
'changelog' => [
    'path' => env('VELLUM_CHANGELOG', base_path('CHANGELOG.md')),
    'unreleased' => false,
],
```

Set `path` to `null` to disable the page and feed.

You can keep an `[Unreleased]` section in the file while you work. The feed never includes it, and the HTML page shows it only when `unreleased` is `true`.

The changelog also has a raw Markdown copy at `/docs/_vellum/raw/changelog.md`, like every page (see [Page actions](/docs/page-actions#raw-markdown)). It follows the same rule: with `unreleased` off, the `[Unreleased]` section is removed from it as well. With `unreleased` on, it is the file as written.

Release headings can be written as `## [1.2.0] - 2026-09-13` or `## 1.2.0`.

The changelog is not versioned. It stays at `/docs/changelog` when version folders are enabled.

Vellum's own [changelog](/docs/changelog) is rendered this way.
