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

it('drops the heading of a section whose pages are all hidden, keeping the one after it', function (): void {
    $page = static fn (string $slug, string $access = 'guest'): array => ['type' => 'page', 'slug' => $slug, 'title' => $slug, 'access' => $access];
    $separator = static fn (string $title): array => ['type' => 'separator', 'title' => $title];

    $tree = (new SearchVisibility)->filterNavigation([
        $page('index'),
        $separator('Acquisition Plans'),
        $page('admin', 'auth'),
        $separator('Reference'),
        $page('webhooks'),
        $separator('Internal'),
        $page('ops', 'auth'),
    ]);

    expect(array_column($tree, 'title'))->toBe(['index', 'Reference', 'webhooks']);
});
