<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Vellum\Search\SearchVisibility;

it('allows guest pages for everyone and auth pages only when signed in', function (): void {
    $visibility = new SearchVisibility;
    $guest = ['access' => 'guest'];
    $auth = ['access' => 'auth'];

    expect($visibility->allows($guest))->toBeTrue()
        ->and($visibility->allows($auth))->toBeFalse()
        ->and($visibility->key([$guest, $auth]))->toBe('guest');

    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Ada']));

    expect($visibility->allows($auth))->toBeTrue()
        ->and($visibility->key([$guest, $auth]))->toBe('auth+guest');
});

it('denies named gates for guests even when the ability would pass for a user', function (): void {
    Gate::define('billing', fn (): bool => true);

    expect((new SearchVisibility)->allows(['access' => 'billing']))->toBeFalse();
});

it('denies a named gate when it fails', function (): void {
    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Ada']));

    $visibility = new SearchVisibility;
    $billing = ['access' => 'billing'];

    Gate::define('billing', fn (): bool => false);

    expect($visibility->allows($billing))->toBeFalse()
        ->and($visibility->key([$billing]))->toBe('none');
});

it('allows a named gate when it passes for the current user', function (): void {
    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Ada']));

    $visibility = new SearchVisibility;
    $billing = ['access' => 'billing'];

    Gate::define('billing', fn (): bool => true);

    expect($visibility->allows($billing))->toBeTrue()
        ->and($visibility->key([$billing]))->toBe('billing');
});
