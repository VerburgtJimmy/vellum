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

it('drops an impossible date rather than emit it', function (string $heading): void {
    $changelog = (new ChangelogParser)->parse(
        "# Changelog\n\n## {$heading}\n\n- Something\n",
        '/tmp/CHANGELOG.md',
        1_700_000_000,
    );

    expect($changelog->releases[0]->date)->toBeNull()
        ->and($changelog->releases[0]->version)->toBe('1.0.0');
})->with([
    'month 13' => '[1.0.0] - 2026-13-45',
    'day 45' => '[1.0.0] - 2026-09-45',
    'day 31 in a 30 day month' => '[1.0.0] - 2026-09-31',
    'february 30' => '[1.0.0] - 2026-02-30',
    'month zero' => '[1.0.0] - 2026-00-10',
]);

it('keeps a real date, including a leap day', function (string $heading, string $expected): void {
    $changelog = (new ChangelogParser)->parse(
        "# Changelog\n\n## {$heading}\n\n- Something\n",
        '/tmp/CHANGELOG.md',
        1_700_000_000,
    );

    expect($changelog->releases[0]->date)->toBe($expected);
})->with([
    ['[1.0.0] - 2026-09-13', '2026-09-13'],
    ['[1.0.0] - 2024-02-29', '2024-02-29'],
    ['1.0.0 - 2026-12-31', '2026-12-31'],
]);

it('emits a valid atom timestamp for every release', function (): void {
    $changelog = sys_get_temp_dir().'/vellum-tests/CHANGELOG-'.$this->fixtureId().'.md';
    config()->set('vellum.changelog.path', $changelog);
    file_put_contents(
        $changelog,
        "# Changelog\n\n## [1.1.0] - 2026-13-45\n\n- Bad date\n\n## [1.0.0] - 2026-01-02\n\n- Good date\n",
    );

    $feed = $this->get('/docs/changelog.atom')->assertOk()->getContent();

    expect($feed)->not->toContain('2026-13-45');

    $xml = simplexml_load_string((string) $feed);

    expect($xml)->not->toBeFalse();

    foreach ($xml->entry as $entry) {
        expect(strtotime((string) $entry->updated))->not->toBeFalse();
    }

    unlink($changelog);
});

it('gives each release its own heading ids, so a link lands on that release', function (): void {
    $changelog = (new ChangelogParser)->parse(<<<'MD'
# Changelog

## [1.1.0] - 2026-02-01

### Fixed

- Second

## [1.0.0] - 2026-01-01

### Fixed

- First
MD, '/tmp/CHANGELOG.md', 1_700_000_000);

    [$newer, $older] = $changelog->releases;

    expect($newer->html)->toContain('id="1.1.0-fixed"')
        ->and($newer->html)->not->toContain('id="fixed"')
        ->and($older->html)->toContain('id="1.0.0-fixed"');
});
