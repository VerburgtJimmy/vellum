---
title: Value tags
description: Allowlisted env, config, and route lookups.
---

These are components, not Blade echoes. They never run unless the key is listed in `vellum.components.allowlist`. An empty list (the default) refuses every key.

```php
'components' => [
    'allowlist' => [
        'env' => ['APP_NAME'],
        'config' => ['vellum.name'],
        'route' => ['vellum.docs.index'],
    ],
],
```

```html
<x-vellum::env key="APP_NAME" />
<x-vellum::config key="vellum.name" />
<x-vellum::route key="vellum.docs.index" />
```

Attributes are strings only. There is no `:bound` syntax.

On a live docs site, top-level value tags resolve at request time (cached with the page fragment). `vellum:export` renders them into the static HTML so the snapshot matches the values at export time.
