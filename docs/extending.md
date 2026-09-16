---
title: Extending with your own components
description: Register Blade components for use in Markdown.
---

Authoring is markdown plus components. Vellum does not compile `{{ }}`, `@if`, or `@php` in docs files. Fence and inline code that contain those strings stay as text. Component attributes are quoted strings and reach the component as data, so a `{{ … }}` written inside one renders as literal text rather than running.

:::note[Raw HTML is stripped]
Vellum parses Markdown with `html_input: strip`, so a `<details>`, `<img>` or `<div>` written straight into a page is removed without a warning. Use a component for anything Markdown cannot express: a Blade component is the supported escape hatch, and it is one you control.
:::

## Namespaces

`vellum.components.namespaces` defaults to `['vellum']`, so `<x-vellum::…>` is allowed and `<x-alert>` is not.

Add `''` or `'app'` to allow unprefixed host components:

```php
'components' => [
    'namespaces' => ['vellum', 'app'],
],
```

Then a view at `resources/views/components/alert.blade.php` is available as `<x-alert type="ok">…</x-alert>` in Markdown.

For a named prefix, register a view namespace in your service provider and add that prefix to the list. `<x-docs::figure>` requires `'docs'` in `namespaces`.

## Slots

The body of a paired tag is Markdown (and may contain nested components). Vellum extracts balanced `<x-…>` islands, runs Markdown on the outer document, then Markdown on each slot, then Blade-renders islands from the inside out.

```html
<x-alert type="ok">
Hello **docs**
</x-alert>
```

Self-closing tags have no slot: `<x-vellum::env key="APP_NAME" />`.

Attributes must be quoted strings. `:type="$foo"` is rejected.

## Built-ins share Blade views

`:::note` and `<x-vellum::callout type="note">` render `resources/views/components/callout.blade.php`. Prefer `:::` for the shipped callouts, tabs, steps, and cards; use `<x-…>` for everything you add.

<x-vellum::callout type="tip" title="Same view">
This callout is an island, not a `:::` directive. The markup is the same.
</x-vellum::callout>

## Missing components

Unknown tags, disallowed namespaces, and keys missing from the value-tag allowlist throw in local and during `vellum:build`. In production the page fails and the exception is logged. Vellum never leaves an empty hole.
