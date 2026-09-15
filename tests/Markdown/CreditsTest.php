<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

it('credits every runtime dependency the package requires', function () use ($root): void {
    $credits = file_get_contents($root.'/docs/credits.md');
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true);

    expect($credits)->not->toBeFalse();

    $missing = [];

    foreach (array_keys($composer['require']) as $package) {
        // php and the illuminate/* split packages are Laravel itself, credited as one.
        if ($package === 'php' || str_starts_with($package, 'illuminate/')) {
            continue;
        }

        // The full vendor/package string, not the bare name: "highlight" alone
        // matches the word "highlighting" in the prose and passes for free.
        if (! str_contains((string) $credits, $package)) {
            $missing[] = $package;
        }
    }

    expect($missing)->toBe([], 'Uncredited in docs/credits.md: '.implode(', ', $missing));
});

it('credits every shipped front-end dependency', function () use ($root): void {
    $credits = strtolower((string) file_get_contents($root.'/docs/credits.md'));
    $package = json_decode((string) file_get_contents($root.'/package.json'), true);

    $names = [
        'alpinejs' => 'alpine',
        'minisearch' => 'minisearch',
        'tailwindcss' => 'tailwind',
        'vite' => 'vite',
    ];

    $missing = [];

    foreach (array_keys($package['devDependencies'] ?? []) as $dependency) {
        // Alpine plugins are credited with Alpine itself.
        $key = str_starts_with($dependency, '@alpinejs/') ? 'alpinejs' : $dependency;
        $needle = $names[$key] ?? null;

        // Word boundary, so "vite" does not match "invite" and so on.
        if ($needle !== null && preg_match('/\b'.preg_quote($needle, '/').'\b/', $credits) !== 1) {
            $missing[] = $dependency;
        }
    }

    expect($missing)->toBe([], 'Uncredited in docs/credits.md: '.implode(', ', $missing));
});

it('credits the icon set and the project it takes its cues from', function () use ($root): void {
    $credits = strtolower((string) file_get_contents($root.'/docs/credits.md'));

    expect($credits)->toContain('phosphor')
        ->and($credits)->toContain('fumadocs')
        ->and($credits)->toContain('laravel');
});

it('lists credits in the docs navigation', function () use ($root): void {
    $meta = json_decode((string) file_get_contents($root.'/docs/meta.json'), true);

    expect($meta['pages'])->toContain('credits');
});
