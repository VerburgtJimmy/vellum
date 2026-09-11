<?php

declare(strict_types=1);

use Vellum\Markdown\MarkdownRenderer;

it('renders code block title, highlights, line numbers, and copy button', function (): void {
    $html = (new MarkdownRenderer)->render(<<<'MD'
```php title="routes/web.php" {2,4-6} showLineNumbers
line1
line2
line3
line4
line5
line6
```
MD);

    expect($html)->toContain('class="vellum-code"')
        ->and($html)->toContain('vellum-code-title')
        ->and($html)->toContain('routes/web.php')
        ->and($html)->toContain('vellum-code-copy')
        ->and($html)->toContain('vellum-code-line-highlighted')
        ->and($html)->toContain('hl-gutter')
        ->and($html)->toContain('x-data')
        ->and($html)->toMatchSnapshot();
});

it('html-escapes code block titles', function (): void {
    $html = (new MarkdownRenderer)->render(<<<'MD'
```php title="<script>alert(1)</script>"
echo 1;
```
MD);

    expect($html)->toContain('vellum-code-title')
        ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and($html)->not->toContain('<span class="vellum-code-title"><script>')
        ->and($html)->toMatchSnapshot();
});

it('highlights inline code with trailing language suffix', function (): void {
    $html = (new MarkdownRenderer)->render('Use `Route::get()`{:php} for routes.');

    expect($html)->toContain('data-vellum-inline-code')
        ->and($html)->toContain('language-php')
        ->and($html)->not->toContain('{:php}')
        ->and($html)->toMatchSnapshot();
});

it('renders callouts with and without custom titles', function (): void {
    $html = (new MarkdownRenderer)->render(<<<'MD'
:::note
A note body.
:::

:::warning[Careful]
Watch this.
:::
MD);

    expect($html)->toContain('data-vellum-callout="note"')
        ->and($html)->toContain('data-vellum-callout="warning"')
        ->and($html)->toContain('vellum-callout-icon')
        ->and($html)->toContain('Careful')
        ->and($html)->toContain('A note body.')
        ->and($html)->toMatchSnapshot();
});

it('renders tabs with persist key in markup', function (): void {
    $html = (new MarkdownRenderer)->render(<<<'MD'
:::tabs persist="pkg-manager"
::tab[npm]
```bash
npm install vellum
```
::tab[pnpm]
```bash
pnpm add vellum
```
:::
MD);

    expect($html)->toContain('data-vellum-tabs')
        ->and($html)->toContain('data-persist="pkg-manager"')
        ->and($html)->toContain('vellum-tabs-pkg-manager')
        ->and($html)->toContain('role="tablist"')
        ->and($html)->toContain('npm')
        ->and($html)->toContain('pnpm')
        ->and($html)->toMatchSnapshot();
});

it('renders numbered steps from three headings', function (): void {
    $html = (new MarkdownRenderer)->render(<<<'MD'
:::steps
## Install
Run composer require.

## Configure
Publish the config.

## Build
Compile the docs.
:::
MD);

    expect($html)->toContain('data-vellum-steps')
        ->and($html)->toContain('data-vellum-step="1"')
        ->and($html)->toContain('data-vellum-step="2"')
        ->and($html)->toContain('data-vellum-step="3"')
        ->and($html)->toContain('vellum-step-indicator')
        ->and($html)->toMatchSnapshot();
});

it('renders cards with and without icons', function (): void {
    $html = (new MarkdownRenderer)->render(<<<'MD'
:::cards
::card[Getting started](/docs/installation){icon=book}
::card[API](/docs/api)
:::
MD);

    expect($html)->toContain('data-vellum-cards')
        ->and($html)->toContain('class="vellum-card"')
        ->and($html)->toContain('href="/docs/installation"')
        ->and($html)->toContain('data-icon="book"')
        ->and($html)->toContain('Getting started')
        ->and($html)->toContain('href="/docs/api"')
        ->and($html)->not->toMatch('/href="\/docs\/api"[^>]*data-icon=/')
        ->and($html)->toMatchSnapshot();
});

it('renders local images with dimensions from file', function (): void {
    $docs = sys_get_temp_dir().'/vellum-image-test-'.uniqid('', true);
    mkdir($docs, 0755, true);

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
    expect($png)->not->toBeFalse();
    file_put_contents($docs.'/dot.png', $png);

    $html = (new MarkdownRenderer(contentPath: $docs))->render('![Dot](dot.png "A tiny pixel")');

    expect($html)->toContain('loading="lazy"')
        ->and($html)->toContain('width="1"')
        ->and($html)->toContain('height="1"')
        ->and($html)->toContain('<figure')
        ->and($html)->toContain('A tiny pixel')
        ->and($html)->toMatchSnapshot();

    unlink($docs.'/dot.png');
    rmdir($docs);
});

it('marks external links with target rel and icon', function (): void {
    $html = (new MarkdownRenderer(appUrl: 'https://docs.example.test'))->render(
        'See [Laravel](https://laravel.com) and [local](/docs/intro).'
    );

    expect($html)->toContain('href="https://laravel.com"')
        ->and($html)->toContain('target="_blank"')
        ->and($html)->toContain('rel="noopener"')
        ->and($html)->toContain('vellum-external-icon')
        ->and($html)->toContain('href="/docs/intro"')
        ->and($html)->not->toMatch('/href="\/docs\/intro"[^>]*target="_blank"/')
        ->and($html)->toMatchSnapshot();
});

it('nests a callout inside a tab', function (): void {
    $html = (new MarkdownRenderer)->render(<<<'MD'
:::tabs
::tab[One]
:::note
Inside tab
:::
::tab[Two]
Second panel
:::
MD);

    expect($html)->toContain('data-vellum-tabs')
        ->and($html)->toContain('data-vellum-callout="note"')
        ->and($html)->toContain('Inside tab')
        ->and($html)->toContain('Second panel')
        ->and($html)->toContain('vellum-tab-one')
        ->and($html)->toContain('vellum-tab-two')
        ->and($html)->not->toContain('::tab[Two]')
        ->and($html)->toMatchSnapshot();
});

it('nests a code block inside a step', function (): void {
    $html = (new MarkdownRenderer)->render(<<<'MD'
:::steps
## Install
```php title="install.php"
echo 1;
```
## Next
Done
:::
MD);

    expect($html)->toContain('data-vellum-step="1"')
        ->and($html)->toContain('data-vellum-step="2"')
        ->and($html)->toContain('class="vellum-code"')
        ->and($html)->toContain('install.php')
        ->and($html)->toMatchSnapshot();
});

it('nests tabs inside a callout', function (): void {
    $html = (new MarkdownRenderer)->render(<<<'MD'
:::tip
:::tabs
::tab[A]
Alpha
::tab[B]
Beta
:::
:::
MD);

    expect($html)->toContain('data-vellum-callout="tip"')
        ->and($html)->toContain('data-vellum-tabs')
        ->and($html)->toContain('Alpha')
        ->and($html)->toContain('Beta')
        ->and($html)->toContain('vellum-tab-a')
        ->and($html)->toContain('vellum-tab-b')
        ->and($html)->not->toContain('::tab[A]')
        ->and($html)->toMatchSnapshot();
});
