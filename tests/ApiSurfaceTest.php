<?php

declare(strict_types=1);

use Vellum\Tests\ApiSurface;

/*
 * An app can call any public class and method of Vellum, and 0.10.0 changed
 * several without a word in the upgrade guide. The site built on Vellum went
 * down on the update.
 *
 * tests/fixtures/api-surface.txt lists what an app could call at the last
 * release. When a line of it stops being true, this test fails until the
 * upgrade guide names what changed, in the section for the release being
 * worked on. `composer api:baseline` writes the file again, and is run only
 * in the commit that prepares a release.
 */

$root = dirname(__DIR__);
$baseline = $root.'/tests/fixtures/api-surface.txt';

it('names in the upgrade guide every public class and method that changed since the last release', function () use ($root, $baseline): void {
    $current = ApiSurface::lines($root);

    if (getenv('VELLUM_API_BASELINE') === '1') {
        $changelog = (string) file_get_contents($root.'/CHANGELOG.md');
        preg_match('/^## \[(\d+\.\d+)\.\d+[^\]]*\]/m', $changelog, $release);

        file_put_contents($baseline, '# '.($release[1] ?? '')."\n".implode("\n", $current)."\n");
    }

    $recorded = array_values(array_filter(explode("\n", (string) file_get_contents($baseline))));
    $minor = substr((string) array_shift($recorded), 2);

    $gone = ApiSurface::broken($recorded, $current);

    if ($gone === []) {
        expect($gone)->toBe([]);

        return;
    }

    // The section that takes an app from the last release to the next.
    $guide = (string) file_get_contents($root.'/docs/getting-started/upgrade.md');
    preg_match('/^## '.preg_quote($minor, '/').' to .*?(?=^## |\z)/ms', $guide, $section);
    $section = $section[0] ?? '';

    $unnamed = [];

    foreach ($gone as $line) {
        $subject = ApiSurface::subject($line);

        // A class that is gone is named once, for everything it had. In a
        // class that is still there, each member that changed is named.
        $name = in_array('class '.$subject['class'], $current, true)
            ? ($subject['member'] ?? $subject['short'])
            : $subject['short'];

        if (! str_contains($section, $name)) {
            $unnamed[] = $line;
        }
    }

    expect($unnamed)->toBe([], implode("\n", [
        'These could be called in '.$minor.' and can no longer be called the same way.',
        'Keep the old call working, or say what replaces it under "## '.$minor.' to …" in docs/getting-started/upgrade.md.',
        'A class nobody outside Vellum should call can be marked @internal instead, which also needs the note once.',
    ]));
});

it('records the release the baseline was taken at', function () use ($baseline): void {
    expect(strtok((string) file_get_contents($baseline), "\n"))->toMatch('/^# \d+\.\d+$/');
});
