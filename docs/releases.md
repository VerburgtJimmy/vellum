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

`[Unreleased]` stays in the source file for authors. The feed never includes it. The HTML page shows it only when `unreleased` is `true`.

Headings may be `## [1.2.0] - 2026-09-13` or `## 1.2.0`.

The changelog is not versioned. It stays at `/docs/changelog` even when version folders are on.

The package's own history is that page on this demo: [Changelog](/docs/changelog).
