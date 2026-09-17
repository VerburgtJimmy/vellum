---
title: Live previews
description: Render a Blade view in the docs, beside its own source.
---

`:::preview` renders one of your Blade views inside the page, next to the code that produced it. It is for showing a component rather than describing it.

```md
:::preview[button-variants]
:::
```

That renders `resources/views/vellum-previews/button-variants.blade.php`. Here is one, rendered by this page:

:::preview[button]
:::

A preview is an ordinary Blade view, so loops and conditionals work:

:::preview[badge]{padding="sm"}
:::

Those two come with the package, which is how this page can show them in any installation. `vellum:install` copies them into your previews directory as a starting point, and a view of your own with the same name takes precedence.

## Where views live

Views resolve from `previews.path` and nowhere else, so a docs page cannot name an arbitrary file in your application.

```php
'previews' => [
    'path' => resource_path('views/vellum-previews'),
    'stylesheets' => [],
],
```

Use a dot for a subfolder: `:::preview[forms.text-input]` renders `forms/text-input.blade.php`.

If the name is not there, Vellum falls back to the examples it ships, which is how the two previews above render here. Anything else fails the build.

## Styling

Each preview renders in its own frame. That is what lets it carry your application's compiled CSS without restyling the documentation around it, and it stops the docs theme leaking into the component you are showing.

List the stylesheets your components need:

```php
'stylesheets' => ['/build/assets/app.css'],
```

Without them the preview renders unstyled. It is your application's CSS, not Vellum's, that a component needs.

The frame follows light and dark mode with the rest of the page, and reports its own height, so a preview is as tall as its contents.

:::note[Previews are compiled once]
A preview is rendered at build time and served as fixed HTML. A view that reads the database fails the build rather than freezing one row of real data into the page. Pass fixed values into the view instead.
:::

## Options

| Attribute | Purpose |
| --- | --- |
| `height="240"` | Fix the frame height instead of measuring it |
| `padding="none\|sm\|md\|lg"` | Space around the component inside the frame. `md` by default |
| `code="false"` | Hide the source tab and show the preview alone |

```md
:::preview[button-variants]{height="180" padding="sm"}
:::
```

## What gets indexed

The source tab is a code block like any other and is searchable. What the frame renders is not: it lives in an attribute rather than in the page text.

## Static export

`vellum:export` carries previews with no extra files. The frame document travels inside the page, so a static host needs no route for it. Any JavaScript a preview needs must be inline in the view; Vellum loads nothing on its behalf.
