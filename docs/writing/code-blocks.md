---
title: Code blocks
description: Titles, line numbers, highlighted lines, and inline code with a language.
---

Code is highlighted on the server by [Tempest
Highlight](https://github.com/tempestphp/highlight). No highlighter is sent to the
browser, so a page with fifty code blocks costs the reader nothing extra.

The palette is GitHub Light and GitHub Dark. Every token clears WCAG AA against the code
surface in all three [presets](/docs/theming).

## A fence

````md
```php
Route::get('/invoices', InvoiceController::class);
```
````

```php
Route::get('/invoices', InvoiceController::class);
```

The first word of the info string is the language. Leave it off and the block is treated
as plain text.

Every block carries a small file glyph next to its label, and these languages get a
distinctive one:

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

Anything else still highlights and still gets the generic file glyph.

Every block gets a copy control in its top right.

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

The gutter is not selectable, so copying the block by hand does not drag the numbers
along with it.

## Highlighted lines

Put line numbers in braces. Single lines, ranges, or both, separated by commas:

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

Line numbers count from one, and count the lines of the block, not of the original file.
They combine with `title` and `showLineNumbers` in any order:

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

Without the suffix, inline code renders as plain monospace text. This is worth using for
class and method names in prose, and worth skipping for file paths and commands.

## Grouping blocks as tabs

When every panel of a `:::tabs` group is a single fenced block, Vellum renders the group
as code tabs: one shared frame, one copy control, and the reader's choice remembered.
See [Tabs](/docs/components/tabs).

## Showing directive syntax

To show `:::` or a fence as literal text rather than rendering it, wrap it in a longer
fence. Four backticks hold three:

`````md
````md
```php
echo 'nested';
```
````
`````
