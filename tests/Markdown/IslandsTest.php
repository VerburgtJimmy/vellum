<?php

declare(strict_types=1);

use Vellum\Exceptions\UnknownComponentException;
use Vellum\Markdown\Islands\IslandRenderer;
use Vellum\Markdown\Islands\MarkdownPipeline;

beforeEach(function (): void {
    $this->registerFixtureComponents();
    config()->set('vellum.components.namespaces', ['vellum', '']);
});

it('renders nested components and their markdown slots', function (): void {
    $html = (new MarkdownPipeline)->render(<<<'MD'
<x-card>
Hello **world**

<x-alert type="ok">Inside</x-alert>
</x-card>
MD);

    expect($html)->toContain('data-test-card')
        ->and($html)->toContain('<strong>world</strong>')
        ->and($html)->toContain('data-test-alert')
        ->and($html)->toContain('data-type="ok"')
        ->and($html)->toContain('Inside')
        ->and($html)->not->toContain('VELLUMISLAND');
});

it('leaves component tags and blade echoes inside fenced and inline code', function (): void {
    $html = (new MarkdownPipeline)->render(<<<'MD'
```html
<x-alert type="ok">{{ config('app.name') }}</x-alert>
```

Use `<x-alert>` and `{{ $slot }}` in code.
MD);

    expect($html)->not->toContain('data-test-alert')
        ->and($html)->toContain('{{')
        ->and($html)->toContain('x-alert');
});

it('does not evaluate blade echoes in prose', function (): void {
    $html = (new MarkdownPipeline)->render("The name is {{ config('app.name') }}.\n");

    expect($html)->toContain("{{ config('app.name') }}")
        ->and($html)->not->toContain(config('app.name'));
});

it('throws when a component is missing', function (): void {
    (new MarkdownPipeline)->render('<x-vellum::definitely-missing />');
})->throws(UnknownComponentException::class);

it('treats app as the unprefixed host namespace', function (): void {
    config()->set('vellum.components.namespaces', ['app']);

    $html = (new MarkdownPipeline)->render('<x-alert>Host</x-alert>');

    expect($html)->toContain('data-test-alert')
        ->and($html)->toContain('Host');
});

it('throws when the namespace is not allowed', function (): void {
    config()->set('vellum.components.namespaces', ['vellum']);

    (new MarkdownPipeline)->render('<x-alert>Nope</x-alert>');
})->throws(UnknownComponentException::class);

it('throws when a value tag is not allowlisted', function (): void {
    (new MarkdownPipeline)->render('<x-vellum::env key="APP_NAME" />');
})->throws(UnknownComponentException::class);

it('throws on bound attributes', function (): void {
    (new MarkdownPipeline)->render('<x-alert :type="$foo">x</x-alert>');
})->throws(UnknownComponentException::class);

it('renders x-vellum::callout the same way as a note directive', function (): void {
    $directive = (new MarkdownPipeline)->render(":::note\nHello body.\n:::");
    $island = (new MarkdownPipeline)->render('<x-vellum::callout type="note">Hello body.</x-vellum::callout>');

    expect($island)->toContain('data-vellum-callout="note"')
        ->and($island)->toContain('Hello body.')
        ->and($island)->toContain('vellum-callout-note')
        ->and($directive)->toContain('data-vellum-callout="note"')
        ->and($directive)->toContain('Hello body.');
});

it('renders allowlisted env, config, and route value tags', function (): void {
    config()->set('vellum.components.allowlist', [
        'env' => ['VELLUM_TEST_ENV'],
        'config' => ['vellum.name'],
        'route' => ['vellum.docs.index'],
    ]);
    config()->set('vellum.name', 'ConfigDocs');
    putenv('VELLUM_TEST_ENV=EnvDocs');
    $_ENV['VELLUM_TEST_ENV'] = 'EnvDocs';

    $env = (new MarkdownPipeline)->render('<x-vellum::env key="VELLUM_TEST_ENV" />');
    $config = (new MarkdownPipeline)->render('<x-vellum::config key="vellum.name" />');
    $route = (new MarkdownPipeline)->render('<x-vellum::route key="vellum.docs.index" />');

    expect($env)->toContain('EnvDocs')
        ->and($config)->toContain('ConfigDocs')
        ->and($route)->toContain('/docs');
});

it('keeps value tags as islands when they sit inside :::tabs', function (): void {
    config()->set('vellum.components.allowlist.config', ['vellum.name']);
    config()->set('vellum.name', 'CompileTimeName');

    $converted = (new MarkdownPipeline)->convert(<<<'MD'
Top <x-vellum::config key="vellum.name" />

:::tabs
::tab[Live]
Nested <x-vellum::config key="vellum.name" />
:::
MD);

    $names = array_map(static fn ($island): string => $island->name, $converted['islands']);

    expect($converted['html'])->toContain('VELLUMISLAND')
        ->and($converted['html'])->not->toContain('CompileTimeName')
        ->and($names)->toBe(['vellum::config', 'vellum::config']);

    config()->set('vellum.name', 'RequestTimeName');

    $html = (new IslandRenderer)->render($converted['html'], $converted['islands']);

    expect($html)->toContain('RequestTimeName')
        ->and($html)->not->toContain('CompileTimeName')
        ->and($html)->not->toContain('VELLUMISLAND')
        ->and($html)->toContain('data-vellum-tabs');
});

it('keeps value tags as children of x-vellum::tab islands', function (): void {
    config()->set('vellum.components.allowlist.config', ['vellum.name']);
    config()->set('vellum.name', 'CompileTimeName');

    $converted = (new MarkdownPipeline)->convert(<<<'MD'
<x-vellum::tabs>
<x-vellum::tab label="Live">
<x-vellum::config key="vellum.name" />
</x-vellum::tab>
</x-vellum::tabs>
MD);

    expect($converted['islands'])->toHaveCount(1)
        ->and($converted['islands'][0]->name)->toBe('vellum::tabs')
        ->and($converted['islands'][0]->children[0]->name)->toBe('vellum::tab')
        ->and($converted['islands'][0]->children[0]->children[0]->name)->toBe('vellum::config')
        ->and($converted['html'])->not->toContain('CompileTimeName');

    config()->set('vellum.name', 'RequestTimeName');

    $html = (new IslandRenderer)->render($converted['html'], $converted['islands']);

    expect($html)->toContain('RequestTimeName')
        ->and($html)->not->toContain('CompileTimeName');
});

it('assembles x-vellum::tabs from nested tab islands', function (): void {
    $html = (new MarkdownPipeline)->render(<<<'MD'
<x-vellum::tabs persist="pkg">
<x-vellum::tab label="npm">
npm body
</x-vellum::tab>
<x-vellum::tab label="pnpm">
pnpm body
</x-vellum::tab>
</x-vellum::tabs>
MD);

    expect($html)->toContain('data-vellum-tabs')
        ->and($html)->toContain('data-persist="pkg"')
        ->and($html)->toContain('role="tablist"')
        ->and($html)->toContain('npm body')
        ->and($html)->toContain('pnpm body')
        ->and($html)->toContain('data-value="npm"')
        ->and($html)->toContain('data-value="pnpm"')
        ->and($html)->toContain('vellumTabs(')
        ->and($html)->not->toContain('VELLUMISLAND');
});

it('keeps alpine click handlers when a component sits next to a code fence', function (): void {
    $html = (new MarkdownPipeline)->render(<<<'MD'
<x-alert>Hi</x-alert>

```php
echo 1;
```
MD);

    expect($html)->toContain('@click')
        ->and($html)->toContain('data-test-alert');
});

it('never compiles blade inside a component attribute value', function (string $value): void {
    $html = (new MarkdownPipeline)->render('<x-alert type="'.$value.'">Body</x-alert>');

    expect($html)->toContain('data-test-alert')
        ->and($html)->toContain('Body')
        ->and($html)->not->toContain(gethostname() ?: 'unreachable-hostname')
        ->and(html_entity_decode($html, ENT_QUOTES | ENT_HTML5))->toContain($value);
})->with([
    '{{ php_uname() }}',
    '{!! php_uname() !!}',
    '@php echo php_uname(); @endphp',
    '@if (true) yes @endif',
    '{{-- comment --}}',
]);

it('passes an attribute value through as data, escaped exactly once', function (): void {
    $html = (new MarkdownPipeline)->render('<x-alert type="Tom & Jerry">Body</x-alert>');

    expect($html)->toContain('data-type="Tom &amp; Jerry"')
        ->and($html)->not->toContain('&amp;amp;');
});

it('keeps blade literal in a self-closing component attribute', function (): void {
    $html = (new MarkdownPipeline)->render('<x-alert type="{{ php_uname() }}" />');

    expect($html)->toContain('data-test-alert')
        ->and($html)->not->toContain(gethostname() ?: 'unreachable-hostname');
});
