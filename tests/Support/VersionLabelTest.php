<?php

declare(strict_types=1);

use Vellum\Support\VersionLabel;

it('labels the latest slug Latest when no override is set', function (): void {
    config()->set('vellum.versions.latest', 'v2');
    config()->set('vellum.versions.labels', []);

    expect(VersionLabel::for('v2'))->toBe('Latest')
        ->and(VersionLabel::for('v1'))->toBe('v1');
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

    expect(VersionLabel::for('v2'))->toBe('Latest')
        ->and(VersionLabel::for('v1'))->toBe('v1');
});
