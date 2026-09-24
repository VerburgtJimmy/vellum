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

Each block has a header with a small file glyph, the language label and a copy button. The
languages below are recognised for the glyph and label, and an alias is labelled with the
main name. `php` and `blade` use the general code-file glyph; the others have one of their
own.

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
| `bash` | `sh`, `shell`, `zsh` |
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

The title replaces the language label in the header. Single or double quotes both work.

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

Put the line numbers in braces, as single lines, ranges or a mix, separated by commas:

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
