---
title: Extending with your own components
description: Register Blade components for use in Markdown.
---

Docs pages are written in Markdown and can include Blade components. Vellum does not compile `{{ }}`, `@if` or `@php` in docs files, and fenced or inline code containing them is shown as text. Component attributes are quoted strings that reach the component as data, so a `{{ … }}` inside an attribute is rendered as literal text and never executed.

:::note[Raw HTML is stripped]
Vellum parses Markdown with `html_input: strip`, so a `<details>`, `<img>` or `<div>` written straight into a page is removed without a warning. For anything Markdown cannot express, write a Blade component.
:::

## Namespaces

`vellum.components.namespaces` defaults to `['vellum']`, which allows `<x-vellum::…>` tags and rejects unprefixed ones such as `<x-alert>`.

Add `''` or `'app'` to allow unprefixed host components:

```php
'components' => [
    'namespaces' => ['vellum', 'app'],
],
```

A view at `resources/views/components/alert.blade.php` can then be used as `<x-alert type="ok">…</x-alert>` in Markdown.

To use a named prefix, register a view namespace in your service provider and add the prefix to the list. For example, `<x-docs::figure>` needs `'docs'` in `namespaces`.

Vellum caches the rendered HTML of pages that use only its own components. A page that uses one of yours is rendered on every request, so your component can read the signed-in user, the session or the request and show each reader their own output.

## Slots

The body of a paired tag is Markdown and can contain nested components. Vellum extracts each balanced `<x-…>` tag as an island, converts the rest of the document to HTML, converts each slot, and then renders the islands with Blade, innermost first.

```html
<x-alert type="ok">
Hello **docs**
</x-alert>
```

A self-closing tag such as `<x-vellum::env key="APP_NAME" />` has no slot.

Attributes must be quoted strings. Bound attributes such as `:type="$foo"` are rejected.

## Built-ins share Blade views

`:::note` and `<x-vellum::callout type="note">` both render `resources/views/components/callout.blade.php`. Use `:::` for the built-in callouts, tabs, steps and cards, and `<x-…>` for components you add.

<x-vellum::callout type="tip" title="Same view">
This callout is written as an `<x-vellum::callout>` tag and renders the same markup as `:::tip`.
</x-vellum::callout>

## Missing components

An unknown tag, a namespace that is not allowed, or a value-tag key missing from the allowlist throws an exception in the local environment and during `vellum:build`. In production that page fails and the exception is logged, so the component is never silently left out.
