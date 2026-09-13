<?php

declare(strict_types=1);

namespace Vellum\Changelog;

/**
 * A parsed Keep a Changelog file ready for the docs page and Atom feed.
 *
 * @phpstan-import-type HeadingData from \Vellum\Content\HeadingExtractor
 */
final readonly class Changelog
{
    /**
     * @param  list<ChangelogRelease>  $releases
     * @param  list<HeadingData>  $headings
     */
    public function __construct(
        public string $title,
        public string $introHtml,
        public string $markdown,
        public array $releases,
        public array $headings,
        public string $path,
        public int $mtime,
    ) {}

    public static function load(?string $path = null): ?self
    {
        $configured = self::configuredPath($path);

        if ($configured === null) {
            return null;
        }

        if (! is_file($configured)) {
            return null;
        }

        $markdown = file_get_contents($configured);

        if ($markdown === false) {
            return null;
        }

        return (new ChangelogParser)->parse(
            $markdown,
            $configured,
            (int) filemtime($configured),
        );
    }

    /**
     * Keep a Changelog [Unreleased] stays in the source file. The Atom feed
     * never includes it. The HTML page does only when changelog.unreleased is true.
     */
    public static function includeUnreleased(): bool
    {
        $configured = config('vellum.changelog');

        if (is_array($configured)) {
            return (bool) ($configured['unreleased'] ?? false);
        }

        return false;
    }

    /**
     * @return list<ChangelogRelease>
     */
    public function published(): array
    {
        return array_values(array_filter(
            $this->releases,
            static fn (ChangelogRelease $release): bool => ! $release->unreleased,
        ));
    }

    /**
     * Releases shown on the HTML page.
     *
     * @return list<ChangelogRelease>
     */
    public function visible(): array
    {
        return self::includeUnreleased() ? $this->releases : $this->published();
    }

    /**
     * @return list<HeadingData>
     */
    public function visibleHeadings(): array
    {
        if (self::includeUnreleased()) {
            return $this->headings;
        }

        return array_values(array_filter(
            $this->headings,
            static fn (array $heading): bool => $heading['id'] !== 'unreleased',
        ));
    }

    public static function configuredPath(?string $path = null): ?string
    {
        if (is_string($path) && $path !== '') {
            return $path;
        }

        $configured = config('vellum.changelog');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if (is_array($configured)) {
            $file = $configured['path'] ?? null;

            if (is_string($file) && $file !== '') {
                return $file;
            }
        }

        return null;
    }
}
