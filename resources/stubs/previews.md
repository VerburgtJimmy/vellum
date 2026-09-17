---
title: Live previews
description: Rendering a Blade view in the page, beside its own source.
---

`:::preview` renders one of your own Blade views in the documentation. Both examples below were published by `vellum:install` into `resources/views/vellum-previews`.

:::preview[button]
:::

The source tab shows the view exactly as it is on disk, so there is no second copy to keep in step.

A preview is an ordinary Blade view. Loops and conditionals work:

:::preview[badge]{padding="sm"}
:::

## Making them look like your application

These two are styled inline so they render correctly the moment they are installed. Yours should use your own classes instead. Point Vellum at your compiled stylesheet and it will be loaded inside each preview:

```php
'previews' => [
    'path' => resource_path('views/vellum-previews'),
    'stylesheets' => ['/build/assets/app.css'],
],
```

Previews render in a frame, so your stylesheet dresses the component without restyling the documentation around it.

:::note[Compiled once]
Previews are rendered at build time. A view that reads the database fails the build rather than freezing one row of data into the page.
:::
