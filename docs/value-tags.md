---
title: Value tags
description: Allowlisted env, config, and route lookups that resolve against the live app.
---

Value tags are components that print a value from the running app. Docs files do not compile Blade echoes, so use these tags instead. A tag only resolves when its key is listed in `vellum.components.allowlist`, and an empty list, the default, refuses every key.

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

Attributes are plain strings. Bound attributes such as `:key` are not supported.

`env` reads the process environment, as `getenv()` does. After `php artisan config:cache`, Laravel no longer loads `.env`, so a value defined only there renders empty in production. When a config key already holds the value, as `app.name` holds `APP_NAME`, use the `config` tag.

On a live docs site, top-level value tags resolve at request time and are cached with the page fragment. `vellum:export` writes the resolved values into the static HTML, so the snapshot shows the values as they were at export time.

A key missing from the allowlist throws an exception locally and during `vellum:build`. In production the page fails and the exception is logged.

Use value tags when the docs should show a real app value, such as the site name, a named route or an allowlisted environment flag. An allowlisted value appears in the page HTML and in the static export, so allowlist a secret only if you accept that.
