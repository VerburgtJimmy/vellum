<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Vellum\Exceptions\InvalidSpecException;
use Vellum\OpenApi\SpecSource;

beforeEach(function (): void {
    config()->set('vellum.openapi', [
        'enabled' => true,
        'spec' => null,
        'scramble' => true,
        'prefix' => 'api',
    ]);
});

/**
 * Stand in for dedoc/scramble. Only the command name and its --path option
 * are contracted, which is exactly as much as SpecSource depends on.
 */
function fakeScramble(string $signature, ?callable $handler = null): void
{
    Artisan::command($signature, $handler ?? function () {
        $path = $this->option('path');
        file_put_contents($path, json_encode(['openapi' => '3.0.0', 'paths' => []]));

        return 0;
    });
}

it('uses the configured spec when there is one', function (): void {
    $path = $this->cachePath().'/custom.json';
    file_put_contents($path, '{}');
    config()->set('vellum.openapi.spec', $path);

    $source = new SpecSource;

    expect($source->resolve())->toBe($path)
        ->and($source->warnings())->toBe([]);
});

it('fails when the configured spec is not there', function (): void {
    config()->set('vellum.openapi.spec', '/no/such/spec.json');

    expect(fn () => (new SpecSource)->resolve())
        ->toThrow(InvalidSpecException::class, '/no/such/spec.json');
});

it('prefers the configured spec over Scramble', function (): void {
    $path = $this->cachePath().'/custom.json';
    file_put_contents($path, '{}');
    config()->set('vellum.openapi.spec', $path);
    fakeScramble('scramble:export {--path=}');

    expect((new SpecSource)->resolve())->toBe($path);
});

it('does nothing at all when openapi is off', function (): void {
    config()->set('vellum.openapi.enabled', false);
    fakeScramble('scramble:export {--path=}');

    $source = new SpecSource;

    expect($source->resolve())->toBeNull()
        ->and($source->warnings())->toBe([]);
});

it('exports from Scramble when it is installed', function (): void {
    fakeScramble('scramble:export {--path=}');

    $source = new SpecSource;
    $resolved = $source->resolve();

    expect($resolved)->toBe($this->cachePath().'/openapi.json')
        ->and(is_file((string) $resolved))->toBeTrue()
        ->and($source->warnings())->toBe([]);
});

it('leaves Scramble alone when the bridge is switched off', function (): void {
    config()->set('vellum.openapi.scramble', false);
    fakeScramble('scramble:export {--path=}');

    $source = new SpecSource;

    expect($source->resolve())->toBeNull()
        ->and($source->warnings()[0])->toContain('no spec was found');
});

it('says so rather than guessing when the export has no --path', function (): void {
    Artisan::command('scramble:export', fn (): int => 0);

    $source = new SpecSource;

    expect($source->resolve())->toBeNull()
        ->and($source->warnings()[0])->toContain('--path');
});

it('reports an export that produced nothing', function (): void {
    fakeScramble('scramble:export {--path=}', fn (): int => 0);

    $source = new SpecSource;

    expect($source->resolve())->toBeNull()
        ->and($source->warnings()[0])->toContain('did not produce a spec');
});

it('does not hand back a spec left over from a previous run', function (): void {
    // A stale file must not pass for a fresh export, or a failing generator
    // silently publishes yesterday's API.
    file_put_contents($this->cachePath().'/openapi.json', '{"openapi":"3.0.0","paths":{}}');
    // Reports success but writes nothing, which is the case a stale file hides.
    fakeScramble('scramble:export {--path=}', fn (): int => 0);

    expect((new SpecSource)->resolve())->toBeNull();
});

it('warns once when there is nothing to render', function (): void {
    $source = new SpecSource;

    expect($source->resolve())->toBeNull()
        ->and($source->warnings())->toHaveCount(1)
        ->and($source->warnings()[0])->toContain('vellum.openapi.spec');
});

it('reports Scramble as absent when the command is not registered', function (): void {
    expect(SpecSource::scrambleAvailable())->toBeFalse();
});

it('reports Scramble as present once the command is registered', function (): void {
    // Registered before the first look, which is how a service provider does
    // it: Artisan memoises its command list on the first bootstrap.
    fakeScramble('scramble:export {--path=}');

    expect(SpecSource::scrambleAvailable())->toBeTrue();
});
