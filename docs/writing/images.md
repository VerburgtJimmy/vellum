---
title: Images
description: Where image files live, how they are served, and what Vellum adds to them.
---

Put image files in the docs directory next to your Markdown, and link them with ordinary
Markdown syntax.

```
resources/docs/
├── index.md
├── assets/
│   └── architecture.svg
└── billing/
    └── invoices.md
```

```md
![The request lifecycle](assets/architecture.svg)
```

## Paths resolve from the docs root

A relative path is resolved from the top of the docs directory, not from the page it is
written on. `assets/architecture.svg` means the same thing in `index.md` and in
`billing/invoices.md`.

When [versions](/docs/versions) are on, the root is the version folder, so
`assets/architecture.svg` in `v2/billing/invoices.md` resolves to `v2/assets/architecture.svg`.
Each version keeps its own copies.

Absolute URLs, `data:` URIs and `#` fragments are left exactly as written.

## How they are served

A file Vellum finds on disk is rewritten to a route under the docs prefix:

```
assets/architecture.svg  →  /docs/_vellum/files/assets/architecture.svg
```

A path that does not match a file on disk is left alone, on the assumption you meant
something the app serves itself.

`vellum:export` copies the same files into `_vellum/files/` in the export, so a static
snapshot keeps working.

## What Vellum adds

**Dimensions.** Vellum reads the real width and height off the file, sets them on the
`img`, and adds a matching `aspect-ratio`. The browser reserves the right box before the
image arrives, so the text does not jump. SVGs are read from their `width` and `height`
attributes.

For a file Vellum cannot open, such as one on a CDN, name the dimensions as their own
path segment and they are used instead:

```md
![Chart](https://cdn.example.com/900x300.png)
```

The segment has to be exactly `{width}x{height}`. `assets/800x600.png` works;
`assets/chart-800x600.png` does not.

**Lazy loading.** Every image gets `loading="lazy"`.

**Alt text.** The Markdown alt text is used as written. Write it.

## Captions

Give the image a title and it becomes a `<figure>` with a `<figcaption>`:

```md
![Request lifecycle](assets/architecture.svg "How a docs request is resolved")
```

The alt text describes the image for someone who cannot see it. The caption is for
everyone. They should usually say different things.

## Formats the route will serve

The asset route answers for images, fonts, video, audio, `pdf`, `txt`, `csv` and `zip`.
It refuses Markdown, `meta.json`, `_meta.md` and any dot-prefixed file, so page sources
and folder metadata cannot be read through it.

:::warning[Assets are not gated]
[Gating](/docs/gating) applies to pages. The asset route answers anyone who has the URL,
including for files sitting next to a gated page. Keep anything genuinely secret out of
the docs directory.
:::
