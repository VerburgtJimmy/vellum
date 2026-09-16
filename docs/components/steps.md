---
title: Steps
description: Numbered procedures from headings.
---

Steps are for a sequence the reader performs in order. If the order does not matter, use
a list.

Every `##` heading inside `:::steps` opens a numbered step. Everything after it, up to the
next `##`, belongs to that step.

```md
:::steps
## Install the package
Require Vellum with Composer.

## Publish starter docs
Run `php artisan vellum:install`.
:::
```

:::steps
## Install the package
Require Vellum with Composer.

## Publish starter docs
Run `php artisan vellum:install`.
:::

## Blocks inside a step

A step holds anything: code, callouts, tables, nested lists. The connecting line runs the
full height, so a long step still reads as one unit.

:::steps
## Require the package

```bash
composer require jimmyverburgt/vellum
```

## Publish config, stubs and assets

```bash
php artisan vellum:install
```

:::note
`vellum:install` will not overwrite files you have already edited.
:::

## Open the site

Visit `/docs`.
:::

## Rules worth knowing

**Only `##` opens a step.** `###` and below are ordinary headings inside the step they
fall in. Use them to break a long step into parts.

**Content before the first `##` is rendered as-is**, outside any step. That is a
reasonable place for one sentence of setup, though usually the sentence belongs above the
directive.

**Numbers are generated.** They count the `##` headings in this block, starting at one.
Do not write them yourself; `## 1. Install` produces a step numbered 1 with the title
"1. Install".

**Step headings join the table of contents** like any other `h2`, which for a long
procedure is usually what you want.
