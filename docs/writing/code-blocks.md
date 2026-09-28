---
title: Code blocks
description: Titles, line numbers, highlighted lines, and inline code with a language.
---

Code is highlighted on the server by [Tempest
Highlight](https://github.com/tempestphp/highlight). No highlighter is sent to the browser, so
the number of code blocks on a page does not change how much JavaScript it loads.

The colours are GitHub Light and GitHub Dark. Every token meets WCAG AA contrast against
the code background in all three [presets](/docs/theming).

## A fence

````md
```php
Route::get('/invoices', InvoiceController::class);
```
````

```php
Route::get('/invoices', InvoiceController::class);
```

The first word of the info string is the language. Without one, the block is treated as
plain text.

A block without a title has no header. Its language and a copy button sit in the top
corner, and the label makes way for the button when the pointer is over the block. A block
with a [title](#title) gets a header naming the file, and a shell block without one is
shown as a [terminal](#terminal-commands).

The languages below are recognised for the label and the file glyph in a titled block's
header, and an alias is labelled with the main name. `php` and `blade` use the general
code-file glyph; the others have one of their own.

| Language | Also accepted as |
| --- | --- |
| `php` | `php8` |
| `blade` | `laravel` |
| `js` | `javascript`, `mjs`, `cjs` |
| `ts` | `typescript`, `mts` |
| `jsx`, `tsx`, `vue` | |
| `html` | `htm` |
| `css` | `scss` |
| `json` | `jsonc` |
| `yaml` | `yml` |
| `bash` | `sh`, `shell`, `zsh`, `console` |
| `python` | `py` |
| `rust` | `rs` |
| `cpp` | `c++`, `cxx`, `cc` |
| `sql`, `c`, `md` | `markdown` |

Any other language gets the general code-file glyph. Highlighting depends on what Tempest
supports, so a language it does not know (Rust, C and C++ among them) renders as plain
text with its glyph and label intact.

## Title

Name the file the snippet comes from:

````md
```php title="routes/web.php"
Route::get('/invoices', InvoiceController::class);
```
````

```php title="routes/web.php"
Route::get('/invoices', InvoiceController::class);
```

A titled block gets a header with the file's glyph and name, drawn as an editor tab. Single
or double quotes both work.

## Terminal commands

A shell block without a title is shown as a terminal, with a `$` before each command:

````md
```bash
# Install the package
composer require jimmyverburgt/vellum \
    --no-interaction
```
````

```bash
# Install the package
composer require jimmyverburgt/vellum \
    --no-interaction
```

A line gets a `$` unless it is blank, a `#` comment, or the continuation of a line ending in
`\`. The `$` cannot be selected and the copy button leaves it out, so what the reader copies
runs as it is. Do not write the `$` yourself.

A shell block with a title is shown as a file, a script to save rather than commands to
run, so it gets no `$`. With `showLineNumbers`, the line numbers take the place of the `$`.

## Line numbers

Add `showLineNumbers`:

````md
```php showLineNumbers
$invoice = Invoice::query()
    ->where('team_id', $team->id)
    ->latest()
    ->firstOrFail();
```
````

```php showLineNumbers
$invoice = Invoice::query()
    ->where('team_id', $team->id)
    ->latest()
    ->firstOrFail();
```

The numbers cannot be selected, so copying the code by hand leaves them out.

## Highlighted lines

Put the line numbers in braces, as single lines, ranges or a mix, separated by commas. They get a bar and a tint in the theme's primary colour:

````md
```php showLineNumbers {1,3-4}
$invoice = Invoice::query()
    ->where('team_id', $team->id)
    ->latest()
    ->firstOrFail();
```
````

```php showLineNumbers {1,3-4}
$invoice = Invoice::query()
    ->where('team_id', $team->id)
    ->latest()
    ->firstOrFail();
```

Lines are counted from one within the block, whatever their position in the original
file. The braces can go before or after `title` and `showLineNumbers`:

````md
```php title="app/Models/Invoice.php" showLineNumbers {3}
```
````

## Inline code with a language

Add `{:language}` immediately after the closing backtick:

```md
Call `Invoice::query()`{:php} inside the controller.
```

Call `Invoice::query()`{:php} inside the controller.

Without the suffix, inline code renders as plain monospace text. The suffix is useful for
class and method names in prose. File paths and commands read fine without it.

## Grouping blocks as tabs

When every panel of a `:::tabs` group is a single fenced block, Vellum renders the group
as code tabs, which share one frame and one copy button. Add `persist` and the reader's
choice carries over to other groups. See [Tabs](/docs/components/tabs).

## Showing directive syntax

To show `:::` or a fence as literal text, wrap it in a longer fence. A four-backtick
fence can contain a three-backtick one:

`````md
````md
```php
echo 'nested';
```
````
`````
