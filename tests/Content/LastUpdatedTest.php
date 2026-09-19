<?php

declare(strict_types=1);

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Vellum\Content\ContentRepository;
use Vellum\Content\LastUpdated;

/**
 * Run git in a fixture repository with fixed dates and no inherited GIT_DIR.
 *
 * @param  list<string>  $arguments
 */
function fixtureGit(string $cwd, array $arguments, string $date = '2020-01-02T03:04:05+00:00'): void
{
    $process = new Process(['git', ...$arguments], $cwd, [
        'GIT_DIR' => false,
        'GIT_WORK_TREE' => false,
        'GIT_INDEX_FILE' => false,
        'GIT_AUTHOR_NAME' => 'Docs',
        'GIT_AUTHOR_EMAIL' => 'docs@example.com',
        'GIT_COMMITTER_NAME' => 'Docs',
        'GIT_COMMITTER_EMAIL' => 'docs@example.com',
        'GIT_AUTHOR_DATE' => $date,
        'GIT_COMMITTER_DATE' => $date,
    ]);
    $process->mustRun();
}

function commitFixture(string $cwd, string $message, string $date): void
{
    fixtureGit($cwd, ['add', '-A']);
    fixtureGit($cwd, ['-c', 'commit.gpgsign=false', 'commit', '-q', '-m', $message], $date);
}

function skipWithoutGit(): void
{
    if ((new ExecutableFinder)->find('git') === null) {
        test()->markTestSkipped('git is not installed.');
    }
}

it('reads a frontmatter date', function (mixed $value, ?string $expected): void {
    expect(LastUpdated::fromMatter($value))->toBe($expected);
})->with([
    'date' => ['2026-09-17', '2026-09-17'],
    'utc date and time' => ['2026-09-17T10:15:00Z', '2026-09-17T10:15:00+00:00'],
    'with an offset' => ['2026-09-17T10:15:00+02:00', '2026-09-17T10:15:00+02:00'],
    'fractional seconds' => ['2026-09-17T10:15:00.250+02:00', '2026-09-17T10:15:00+02:00'],
    'no offset is utc' => ['2026-09-17T10:15', '2026-09-17T10:15:00+00:00'],
    'unquoted yaml date' => [1789603200, '2026-09-17'],
    'unquoted yaml date and time' => [1789640100, '2026-09-17T10:15:00+00:00'],
    'a date object' => [new DateTimeImmutable('2026-09-17T10:15:00+02:00'), '2026-09-17T10:15:00+02:00'],
    'not a date' => ['yesterday', null],
    'impossible day' => ['2026-02-30', null],
    'day first' => ['17-09-2026', null],
    'a boolean' => [true, null],
    'a list' => [['2026-09-17'], null],
    'empty' => ['', null],
]);

it('takes updated from frontmatter, quoted or not', function (): void {
    $this->writeDoc('plain.md', "---\ntitle: Plain\nupdated: 2026-09-17\n---\nBody");
    $this->writeDoc('stamped.md', "---\ntitle: Stamped\nupdated: '2026-09-17T10:15:00+02:00'\n---\nBody");

    $repository = ContentRepository::fromConfig();

    expect($repository->find('plain')?->updated)->toBe('2026-09-17')
        ->and($repository->find('stamped')?->updated)->toBe('2026-09-17T10:15:00+02:00');
});

it('keeps the date in the compile cache', function (): void {
    $this->writeDoc('page.md', "---\ntitle: Page\nupdated: 2026-09-17\n---\nBody");

    ContentRepository::fromConfig()->buildAll();

    expect(ContentRepository::fromConfig()->store()->get('page')?->updated)->toBe('2026-09-17');
});

it('has no date outside git and never falls back to the file mtime', function (): void {
    $path = $this->writeDoc('page.md', "---\ntitle: Page\n---\nBody");
    touch($path, 1_700_000_000);

    expect(ContentRepository::fromConfig()->find('page')?->updated)->toBeNull();
});

it('ignores an invalid updated and warns at build', function (): void {
    $this->writeDoc('page.md', "---\ntitle: Page\nupdated: last tuesday\n---\nBody");

    expect(ContentRepository::fromConfig()->find('page')?->updated)->toBeNull();

    $this->artisan('vellum:build')
        ->expectsOutputToContain('Invalid updated date in')
        ->assertSuccessful();

    $this->artisan('vellum:build')
        ->expectsOutputToContain('page.md: last tuesday')
        ->assertSuccessful();
});

it('uses the last git commit date when there is no frontmatter date', function (): void {
    skipWithoutGit();

    $docs = $this->docsPath();
    $this->writeDoc('old.md', "---\ntitle: Old\n---\nBody");
    $this->writeDoc('new.md', "---\ntitle: New\n---\nBody");
    $this->writeDoc('pinned.md', "---\ntitle: Pinned\nupdated: 2026-09-17\n---\nBody");
    fixtureGit($docs, ['init', '-q']);
    commitFixture($docs, 'First.', '2020-01-02T03:04:05+00:00');
    $this->writeDoc('new.md', "---\ntitle: New\n---\nChanged");
    commitFixture($docs, 'Second.', '2021-06-07T08:09:10+02:00');
    $this->writeDoc('uncommitted.md', "---\ntitle: Uncommitted\n---\nBody");

    // One page at a time, the way a request compiles.
    $single = ContentRepository::fromConfig();

    expect($single->find('old')?->updated)->toBe('2020-01-02T03:04:05+00:00')
        ->and($single->find('new')?->updated)->toBe('2021-06-07T08:09:10+02:00')
        ->and($single->find('pinned')?->updated)->toBe('2026-09-17')
        ->and($single->find('uncommitted')?->updated)->toBeNull();

    // Every page at once, the way a build compiles.
    $built = [];

    foreach (ContentRepository::fromConfig()->buildAll() as $document) {
        $built[$document->slug] = $document->updated;
    }

    expect($built)->toMatchArray([
        'old' => '2020-01-02T03:04:05+00:00',
        'new' => '2021-06-07T08:09:10+02:00',
        'pinned' => '2026-09-17',
        'uncommitted' => null,
    ]);
});

it('treats a shallow clone as no git', function (): void {
    skipWithoutGit();

    $origin = $this->docsPath().'-origin';
    mkdir($origin, 0755, true);
    file_put_contents($origin.'/page.md', "---\ntitle: Page\n---\nBody");
    fixtureGit($origin, ['init', '-q']);
    commitFixture($origin, 'First.', '2020-01-02T03:04:05+00:00');
    file_put_contents($origin.'/other.md', "---\ntitle: Other\n---\nBody");
    commitFixture($origin, 'Second.', '2021-01-02T03:04:05+00:00');

    $clone = $this->docsPath().'-shallow';
    fixtureGit(dirname($clone), ['clone', '-q', '--depth', '1', 'file://'.$origin, $clone]);
    config()->set('vellum.path', $clone);

    // Every file in a shallow clone would carry the one fetched commit's date.
    expect(ContentRepository::fromConfig()->find('page')?->updated)->toBeNull();

    $this->deleteDirectory($origin);
    $this->deleteDirectory($clone);
});
