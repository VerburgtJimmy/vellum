<?php

declare(strict_types=1);

it('keeps the built-in synonyms well formed', function (): void {
    $groups = require __DIR__.'/../../resources/synonyms.php';
    $seen = [];

    expect($groups)->toBeArray()->not->toBeEmpty();

    foreach ($groups as $group) {
        expect($group)->toBeArray()->and(count($group))->toBeGreaterThan(1);

        foreach ($group as $term) {
            expect($term)->toBe(mb_strtolower(trim($term)))
                ->and($seen)->not->toHaveKey($term);

            $seen[$term] = true;
        }
    }
});
