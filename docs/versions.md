---
title: Versions
description: Version folders, unprefixed latest URLs, and the switcher.
---

Off by default. When enabled, Markdown lives in version folders (`docs/v2/`, `docs/v1/`).

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

The `latest` folder is served at `/docs/...` with no version in the URL. Other folders are at `/docs/v1/...` or `/docs/next/...`. Visiting `/docs/{latest}/...` redirects to the unprefixed URL with HTTP 301.

`list` order is switcher order. Put `next` first so **Next** sits above **1.x (LTS)**. `labels` is switcher text only. Folders and URLs stay the list slug. An unlabelled latest slug still shows **Latest**.

Changelog stays at `/docs/changelog` for every version. Do not detect versions from git tags: the `list` in config is the source of truth.

Each version is its own Markdown tree. A page that exists only in `v1` 404s on the latest tree.

The switcher is a keyboard menu: arrows, Home, End, Escape. Screen readers get `Select documentation version` on the trigger.
