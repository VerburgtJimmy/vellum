<?php

declare(strict_types=1);

use Vellum\Markdown\Islands\MarkdownPipeline;
use Vellum\Markdown\MarkdownRenderer;
use Vellum\Support\Theme;

it('renders documented callout, tabs, steps, and cards samples', function (): void {
    $html = (new MarkdownRenderer)->render(<<<'MD'
:::note
Use callouts for notes, tips, warnings, danger, and info.
:::

:::tabs persist="pkg-manager"
::tab[npm]
```bash
npm install jimmyverburgt/vellum
```
::tab[composer]
```bash
composer require jimmyverburgt/vellum
```
:::

:::steps
## Install the package
Require Vellum with Composer.

## Publish starter docs
Run `php artisan vellum:install`.
:::

:::cards
::card[Installation](/docs/getting-started/installation){icon=book}
::card[Laravel](https://laravel.com){icon=link}
:::
MD);

    expect($html)
        ->toContain('data-vellum-callout="note"')
        ->toContain('data-vellum-tabs')
        ->toContain('data-persist="pkg-manager"')
        ->toContain('data-vellum-steps')
        ->toContain('data-vellum-cards')
        ->toContain('/docs/getting-started/installation');
});

it('renders documented value tags when allowlisted', function (): void {
    config()->set('app.name', 'Vellum Sample');
    config()->set('vellum.name', 'Vellum Sample');
    config()->set('vellum.components.allowlist', [
        'env' => ['APP_NAME'],
        'config' => ['vellum.name'],
        'route' => ['vellum.docs.index'],
    ]);

    $html = (new MarkdownPipeline)->render(<<<'MD'
<x-vellum::config key="vellum.name" />
<x-vellum::route key="vellum.docs.index" />
MD);

    expect($html)
        ->toContain('Vellum Sample')
        ->toContain('/docs')
        ->not->toContain('VELLUMISLAND');
});

it('renders a host component sample the same way Extending documents it', function (): void {
    $this->registerFixtureComponents();
    config()->set('vellum.components.namespaces', ['vellum', 'app']);

    $html = (new MarkdownPipeline)->render(<<<'MD'
<x-alert type="ok">
Hello **docs**
</x-alert>
MD);

    expect($html)
        ->toContain('data-test-alert')
        ->toContain('<strong>docs</strong>');
});

it('serves stub pages after a fresh install copy', function (): void {
    $stubs = dirname(__DIR__, 2).'/resources/stubs';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($stubs, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (! $file->isFile()) {
            continue;
        }

        $relative = substr($file->getPathname(), strlen($stubs) + 1);
        $target = $this->docsPath().DIRECTORY_SEPARATOR.$relative;
        $dir = dirname($target);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        copy($file->getPathname(), $target);
    }

    $this->get('/docs')
        ->assertOk()
        ->assertSee('Introduction', false)
        ->assertSee('data-vellum-callout="note"', false)
        ->assertSee('data-vellum-tabs', false)
        ->assertSee('data-vellum-steps', false)
        ->assertSee('data-vellum-cards', false);

    $this->get('/docs/getting-started/installation')
        ->assertOk()
        ->assertSee('Installation', false);

    $this->get('/docs/components/callouts')
        ->assertOk()
        ->assertSee('Callouts', false);
});

it('lists every colour preset on the theming contract', function (): void {
    $theming = file_get_contents(dirname(__DIR__, 2).'/docs/theming.md');

    expect($theming)->not->toBeFalse();

    foreach (Theme::PRESETS as $preset) {
        expect($theming)->toContain('`'.$preset.'`');
    }
});
