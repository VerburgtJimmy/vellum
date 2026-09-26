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

The order of `list` is the order of the switcher, so put `next` first to show "Next" above "1.x (LTS)". `labels` only changes the switcher text: folders and URLs always use the slug from `list`. If the latest version has no label, the switcher shows "Latest".

The changelog stays at `/docs/changelog` for every version. Versions come from `list` in the config, and Vellum does not read git tags.

Each version is a separate Markdown tree, so a page that exists in one version returns a 404 in any version whose folder does not contain it.

The switcher is keyboard accessible with the arrow keys, Home, End and Escape. Its trigger has the accessible label `Select documentation version`.
