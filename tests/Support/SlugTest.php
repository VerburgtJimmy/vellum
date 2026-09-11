<?php

declare(strict_types=1);

use Vellum\Support\Slug;
use Vellum\Support\Str;

it('creates lowercase dashed slugs', function (): void {
    expect(Slug::from('Hello World'))->toBe('hello-world')
        ->and(Slug::from('Auth_Guide'))->toBe('auth-guide');
});

it('builds slugs from relative markdown paths', function (): void {
    expect(Slug::fromRelativePath('guides/authentication.md'))->toBe('guides/authentication')
        ->and(Slug::fromRelativePath('guides/index.md'))->toBe('guides')
        ->and(Slug::fromRelativePath('index.md'))->toBe('');
});

it('title-cases filenames', function (): void {
    expect(Str::titleCase('getting-started'))->toBe('Getting Started');
});

it('extracts the first markdown heading', function (): void {
    expect(Str::firstHeading("Intro\n\n# Real Title\n"))->toBe('Real Title')
        ->and(Str::firstHeading('No heading here'))->toBeNull();
});
