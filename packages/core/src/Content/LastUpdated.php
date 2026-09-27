<?php

declare(strict_types=1);

namespace Vellum\Content;

use Symfony\Component\Process\Process;

/**
 * Resolves when a page was last updated: frontmatter first, then git.
 *
 * Never the file's mtime. A composer install, a deploy checkout or a copy
 * between build stages resets it, so it says when the file landed on this
 * disk, not when anyone changed it. A date nobody can vouch for is worse
 * than none, so when neither source has one the page has no date.
 *
 * A shallow clone counts as no git. Every file in it carries the date of the
 * one commit that was fetched, which is the mtime problem over again.
 */
final class LastUpdated
{
    private const GIT_TIMEOUT = 10;

    /** Whether git has been asked where the work tree is. */
    private bool $detected = false;

    /** Top of the work tree, or null when git is not usable here. */
    private ?string $toplevel = null;

    /**
     * Commit dates by absolute path, filled by prime() in one git call.
     *
     * @var array<string, string>|null
     */
    private ?array $primed = null;

    /** @var array<string, string|null> */
    private array $single = [];

    public function __construct(
        private readonly string $contentPath,
    ) {}

    /**
     * A date from frontmatter, normalised to YYYY-MM-DD or ISO 8601, or null
     * when the value is not one.
     *
     * Unquoted YAML dates reach here as Unix timestamps, since that is what
     * the YAML parser turns them into. Midnight UTC reads back as a plain date.
     */
    public static function fromMatter(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_int($value)) {
            $date = new \DateTimeImmutable('@'.$value);

            return $value % 86400 === 0 ? $date->format('Y-m-d') : $date->format(DATE_ATOM);
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?)?$/', $value, $match) !== 1) {
            return null;
        }

        if (! checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
            return null;
        }

        if (($match[4] ?? '') === '') {
            return $value;
        }

        try {
            // No offset given means UTC, rather than whatever the server is set to.
            $date = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }

        return $date->format(DATE_ATOM);
    }

    /**
     * @param  array<string, mixed>  $matter
     */
    public function resolve(string $path, array $matter): ?string
    {
        if (array_key_exists('updated', $matter)) {
            $date = self::fromMatter($matter['updated']);

            if ($date !== null) {
                return $date;
            }
        }

        return $this->git($path);
    }

    /**
     * Read the last commit date of every file under the content path in one
     * git call, rather than one call per page. For a build, which compiles
     * everything anyway.
     */
    public function prime(): void
    {
        if ($this->primed !== null || ! $this->detect()) {
            return;
        }

        $output = $this->run(['log', '--format=%x00%cI', '--name-only', '--no-renames', '--', '.'], $this->contentPath);

        if ($output === null) {
            return;
        }

        $dates = [];
        $current = null;

        foreach (explode("\n", $output) as $line) {
            if (str_starts_with($line, "\0")) {
                // Newer git writes UTC as Z; normalise to the frontmatter form.
                $current = self::fromMatter(substr($line, 1));

                continue;
            }

            if ($line === '' || $current === null || isset($dates[$this->toplevel.'/'.$line])) {
                continue;
            }

            // Newest commit first, so the first date seen for a file is its last.
            $dates[$this->toplevel.'/'.$line] = $current;
        }

        $this->primed = $dates;
    }

    private function git(string $path): ?string
    {
        if (! $this->detect()) {
            return null;
        }

        $real = realpath($path);

        if ($real === false) {
            return null;
        }

        $real = str_replace('\\', '/', $real);

        if ($this->primed !== null) {
            return $this->primed[$real] ?? null;
        }

        if (array_key_exists($real, $this->single)) {
            return $this->single[$real];
        }

        $output = $this->run(['log', '-1', '--format=%cI', '--', $real], dirname($real));

        return $this->single[$real] = $output === null ? null : self::fromMatter(trim($output));
    }

    /**
     * Once per instance: is the content path inside a full git work tree?
     */
    private function detect(): bool
    {
        if ($this->detected) {
            return $this->toplevel !== null;
        }

        $this->detected = true;

        if (! is_dir($this->contentPath)) {
            return false;
        }

        $output = $this->run(['rev-parse', '--is-shallow-repository', '--show-toplevel'], $this->contentPath);
        $lines = $output === null ? [] : explode("\n", trim($output));

        if (count($lines) !== 2 || $lines[0] !== 'false' || $lines[1] === '') {
            return false;
        }

        $toplevel = realpath($lines[1]);
        $this->toplevel = $toplevel === false ? null : str_replace('\\', '/', $toplevel);

        return $this->toplevel !== null;
    }

    /**
     * Run git with an argument list, so no shell ever parses a path. Any
     * failure, a missing binary or a timeout included, reads as no git, and
     * stops later calls from waiting on it again.
     *
     * @param  list<string>  $arguments
     */
    private function run(array $arguments, string $cwd): ?string
    {
        // A GIT_DIR inherited from a hook or CI step would point every call
        // at that repository instead of the one around the docs.
        $process = new Process(
            ['git', '-c', 'core.quotePath=false', ...$arguments],
            $cwd,
            ['GIT_DIR' => false, 'GIT_WORK_TREE' => false, 'GIT_INDEX_FILE' => false],
            null,
            self::GIT_TIMEOUT,
        );

        try {
            $process->run();
        } catch (\RuntimeException) {
            $this->toplevel = null;

            return null;
        }

        return $process->isSuccessful() ? $process->getOutput() : null;
    }
}
