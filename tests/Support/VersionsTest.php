<?php

declare(strict_types=1);

use Vellum\Search\SearchDriver;
use Vellum\Site\Versions;
use Vellum\Support\VersionLabel;

it('labels a version by its slug when no override is set, the latest included', function (): void {
    $versions = Versions::fromArray(['latest' => 'v2', 'labels' => []]);

    expect($versions->label('v2'))->toBe('v2')
        ->and($versions->label('v1'))->toBe('v1');
});

it('tells the latest version from the ones before and after it in the list', function (): void {
    $versions = Versions::fromArray(['latest' => 'v2', 'list' => ['next', 'v2', 'v1']]);

    expect($versions->kind('next'))->toBe('unreleased')
        ->and($versions->kind('v2'))->toBe('latest')
        ->and($versions->kind('v1'))->toBe('older')
        ->and($versions->kind('gone'))->toBe('older');
});

it('uses config labels for the switcher without changing the slug', function (): void {
    $versions = Versions::fromArray([
        'latest' => 'v1',
        'labels' => [
            'v1' => '1.x (LTS)',
            'next' => 'Next',
        ],
    ]);

    expect($versions->labelsFor(['next', 'v1']))->toBe([
        'next' => 'Next',
        'v1' => '1.x (LTS)',
    ]);
});

it('ignores blank label overrides', function (): void {
    $versions = Versions::fromArray([
        'latest' => 'v2',
        'labels' => [
            'v2' => '',
            'v1' => '   ',
        ],
    ]);

    expect($versions->label('v2'))->toBe('v2')
        ->and($versions->label('v1'))->toBe('v1');
});

it('builds no version segment for URLs when versioning is off', function (): void {
    expect(Versions::fromArray(['enabled' => false, 'latest' => 'v2'])->current())->toBeNull()
        ->and(Versions::fromArray(['enabled' => true, 'latest' => 'v2'])->current())->toBe('v2');
});

it('still answers through the old VersionLabel and SearchDriver helpers, for views published before 0.10', function (): void {
    config()->set('vellum.versions', ['enabled' => true, 'latest' => 'v2', 'list' => ['v3', 'v2', 'v1'], 'labels' => ['v1' => '1.x']]);
    config()->set('vellum.search.driver', 'minisearch');

    expect(VersionLabel::for('v1'))->toBe('1.x')
        ->and(VersionLabel::kind('v3'))->toBe('unreleased')
        ->and(VersionLabel::map(['v2', 'v1']))->toBe(['v2' => 'v2', 'v1' => '1.x'])
        ->and(SearchDriver::name())->toBe('builtin')
        ->and(SearchDriver::isScout())->toBeFalse();
});
