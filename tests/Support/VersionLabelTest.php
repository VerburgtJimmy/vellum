<?php

declare(strict_types=1);

use Vellum\Support\VersionLabel;

it('labels a version by its slug when no override is set, the latest included', function (): void {
    config()->set('vellum.versions.latest', 'v2');
    config()->set('vellum.versions.labels', []);

    expect(VersionLabel::for('v2'))->toBe('v2')
        ->and(VersionLabel::for('v1'))->toBe('v1');
});

it('tells the latest version from the ones before and after it in the list', function (): void {
    config()->set('vellum.versions.latest', 'v2');
    config()->set('vellum.versions.list', ['next', 'v2', 'v1']);

    expect(VersionLabel::kind('next'))->toBe('unreleased')
        ->and(VersionLabel::kind('v2'))->toBe('latest')
        ->and(VersionLabel::kind('v1'))->toBe('older')
        ->and(VersionLabel::kind('gone'))->toBe('older');
});

it('uses config labels for the switcher without changing the slug', function (): void {
    config()->set('vellum.versions.latest', 'v1');
    config()->set('vellum.versions.labels', [
        'v1' => '1.x (LTS)',
        'next' => 'Next',
    ]);

    expect(VersionLabel::map(['next', 'v1']))->toBe([
        'next' => 'Next',
        'v1' => '1.x (LTS)',
    ]);
});

it('ignores blank label overrides', function (): void {
    config()->set('vellum.versions.latest', 'v2');
    config()->set('vellum.versions.labels', [
        'v2' => '',
        'v1' => '   ',
    ]);

    expect(VersionLabel::for('v2'))->toBe('v2')
        ->and(VersionLabel::for('v1'))->toBe('v1');
});
