<?php

declare(strict_types=1);

use Vellum\Changelog\Changelog;
use Vellum\Changelog\ChangelogParser;

it('parses keep a changelog headings with and without brackets', function (): void {
    $markdown = <<<'MD'
# Changelog

Intro paragraph.

## [Unreleased]

- Pending work

## [1.2.0] - 2026-09-13

### Added

- Bracketed release

## 1.1.0 - 2026-01-02

- Unbracketed dated release

## 1.0.0

- Bare version
MD;

    $changelog = (new ChangelogParser)->parse($markdown, '/tmp/CHANGELOG.md', 1_700_000_000);

    expect($changelog->title)->toBe('Changelog')
        ->and($changelog->introHtml)->toContain('Intro paragraph')
        ->and($changelog->releases)->toHaveCount(4)
        ->and($changelog->releases[0]->unreleased)->toBeTrue()
        ->and($changelog->releases[0]->id)->toBe('unreleased')
        ->and($changelog->releases[1]->version)->toBe('1.2.0')
        ->and($changelog->releases[1]->date)->toBe('2026-09-13')
        ->and($changelog->releases[1]->id)->toBe('1.2.0')
        ->and($changelog->releases[1]->html)->toContain('Bracketed release')
        ->and($changelog->releases[2]->version)->toBe('1.1.0')
        ->and($changelog->releases[2]->date)->toBe('2026-01-02')
        ->and($changelog->releases[3]->version)->toBe('1.0.0')
        ->and($changelog->releases[3]->date)->toBeNull()
        ->and($changelog->published())->toHaveCount(3)
        ->and($changelog->headings[0]['id'])->toBe('unreleased')
        ->and($changelog->headings[1]['text'])->toBe('1.2.0');
});

it('returns null when the changelog path is missing or disabled', function (): void {
    expect(Changelog::load(null))->toBeNull()
        ->and(Changelog::load('/tmp/vellum-missing-changelog.md'))->toBeNull();
});

it('hides unreleased from visible releases unless changelog.unreleased is true', function (): void {
    $markdown = <<<'MD'
# Changelog

## [Unreleased]

- Pending

## [1.0.0] - 2026-09-11

- Shipped
MD;

    $changelog = (new ChangelogParser)->parse($markdown, '/tmp/CHANGELOG.md', 1_700_000_000);

    config()->set('vellum.changelog', ['path' => '/tmp/CHANGELOG.md', 'unreleased' => false]);

    expect($changelog->visible())->toHaveCount(1)
        ->and($changelog->visible()[0]->version)->toBe('1.0.0')
        ->and($changelog->visibleHeadings())->toHaveCount(1)
        ->and($changelog->visibleHeadings()[0]['id'])->toBe('1.0.0');

    config()->set('vellum.changelog.unreleased', true);

    expect($changelog->visible())->toHaveCount(2)
        ->and($changelog->visible()[0]->unreleased)->toBeTrue()
        ->and($changelog->visibleHeadings()[0]['id'])->toBe('unreleased');
});
