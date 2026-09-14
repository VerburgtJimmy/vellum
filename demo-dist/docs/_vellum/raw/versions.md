---
title: Versions
description: Version folders, unprefixed latest URLs, and the switcher.
---

Off by default. When enabled, Markdown lives in version folders (`docs/v2/`, `docs/v1/`).

```php
'versions' => [
    'enabled' => true,
    'latest' => 'v2',
    'list' => ['v2', 'v1'],
],
```

The `latest` folder is served at `/docs/...` with no version in the URL. Other folders are at `/docs/v1/...`. Visiting `/docs/v2/...` redirects to the unprefixed URL with HTTP 301. The switcher labels `latest` as **Latest**.

Changelog stays at `/docs/changelog` for every version. Do not detect versions from git tags: the `list` in config is the source of truth.

Each version is its own Markdown tree. A page that exists only in `v1` 404s on the latest tree.

The switcher is a keyboard menu: arrows, Home, End, Escape. Screen readers get `Select documentation version` on the trigger.
