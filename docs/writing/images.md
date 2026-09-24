---
title: Images
description: Where image files live, how they are served, and the attributes Vellum adds.
---

Put image files in the docs directory alongside your Markdown and reference them with
ordinary Markdown image syntax.

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

A relative path is resolved from the top of the docs directory. The location of the page
makes no difference: `assets/architecture.svg` points to the same file from `index.md`
and from `billing/invoices.md`.

With [versions](/docs/versions) enabled, the root is the version folder, so
`assets/architecture.svg` in `v2/billing/invoices.md` resolves to `v2/assets/architecture.svg`.
Each version has its own copy of its assets.

Absolute URLs, `data:` URIs and `#` fragments are left as written.

## How they are served

When the file exists on disk, its path is rewritten to a route under the docs prefix:

```
assets/architecture.svg  →  /docs/_vellum/files/assets/architecture.svg
```

A path that matches no file on disk is left unchanged, in case it points at something
the app serves itself.

`vellum:export` copies the same files into `_vellum/files/` in the export, so images also
work in the static snapshot.

## What Vellum adds

**Dimensions.** Vellum reads the width and height from the file, sets them on the `img`
and adds a matching `aspect-ratio`. The browser reserves the space before the image loads,
so the text does not shift. For an SVG, the values come from its `width` and `height`
attributes.

For a file Vellum cannot read, such as one on a CDN, put the dimensions in the URL as a
path segment of their own:

```md
![Chart](https://cdn.example.com/900x300.png)
```

The segment must be exactly `{width}x{height}`, plus the file extension if it is the file
name. `assets/800x600.png` works and `assets/chart-800x600.png` does not.

**Lazy loading.** Every image gets `loading="lazy"`.

**Alt text.** The Markdown alt text is used as written, so always provide it.

## Captions

An image with a title is wrapped in a `<figure>`, and the title becomes its
`<figcaption>`:

```md
![Request lifecycle](assets/architecture.svg "How a docs request is resolved")
```

The alt text describes the image for readers who cannot see it, while the caption is
read by everyone, so the two usually say different things.

## Formats the route will serve

The asset route serves images, fonts, video, audio, `vtt` captions, `pdf`, `txt`, `csv`
and `zip` files. It does not serve Markdown, `meta.json`, `_meta.md`, or any file or folder
whose name starts with a dot, so page sources and folder metadata cannot be downloaded
through it.

:::warning[Assets are not gated]
[Gating](/docs/gating) applies to pages only. The asset route serves a file to anyone who
has the URL, even when it sits next to a gated page. Keep anything secret out of the docs
directory.
:::
