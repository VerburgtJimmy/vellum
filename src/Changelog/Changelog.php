<?php

declare(strict_types=1);

namespace Vellum\Changelog;

use Illuminate\Support\Facades\Cache;
use Vellum\Cache\FragmentCache;
use Vellum\Content\LastUpdated;

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

        $mtime = (int) filemtime($configured);

        // Parsing and rendering the whole file took longer than serving a
        // page, on every request to the page and the feed. Keep the result
        // until the file, the settings that shape it, or vellum:clear change.
        $key = 'vellum:changelog:'.hash('xxh128', (string) json_encode([
            $configured,
            $mtime,
            filesize($configured),
            config('vellum.components'),
            config('vellum.route'),
            config('app.url'),
            (new FragmentCache)->generation(),
        ]));

        // Stored as plain arrays: an app may forbid objects in its cache
        // with cache.serializable_classes.
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return self::fromArray($cached);
        }

        $markdown = file_get_contents($configured);

        if ($markdown === false) {
            return null;
        }

        $changelog = (new ChangelogParser)->parse($markdown, $configured, $mtime);
        Cache::put($key, $changelog->toArray(), now()->addDay());

        return $changelog;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'introHtml' => $this->introHtml,
            'markdown' => $this->markdown,
            'releases' => array_map(static fn (ChangelogRelease $release): array => (array) $release, $this->releases),
            'headings' => $this->headings,
            'path' => $this->path,
            'mtime' => $this->mtime,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<array{version: string, unreleased: bool, date: string|null, id: string, html: string, url: string|null}> $releases */
        $releases = $data['releases'];
        /** @var list<HeadingData> $headings */
        $headings = $data['headings'];

        return new self(
            title: (string) $data['title'],
            introHtml: (string) $data['introHtml'],
            markdown: (string) $data['markdown'],
            releases: array_map(static fn (array $release): ChangelogRelease => new ChangelogRelease(...$release), $releases),
            headings: $headings,
            path: (string) $data['path'],
            mtime: (int) $data['mtime'],
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
     * The Markdown served for the changelog: the source file, minus the
     * [Unreleased] section when the HTML page hides it.
     *
     * Raw Markdown is otherwise the file as written, but hiding unreleased
     * notes on the page and publishing them one URL over would make the
     * setting decorative. Reference definitions in the dropped section are
     * kept, since a later release may use them.
     */
    public function rawMarkdown(): string
    {
        $source = is_file($this->path) ? file_get_contents($this->path) : false;
        $source = $source === false ? $this->markdown : $source;

        if (self::includeUnreleased()) {
            return $source;
        }

        $source = str_replace(["\r\n", "\r"], "\n", $source);

        if (preg_match('/^## \[?Unreleased\]?[ \t]*$/mi', $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return $source;
        }

        $start = $match[0][1];
        $end = preg_match('/^## /m', $source, $next, PREG_OFFSET_CAPTURE, $start + strlen($match[0][0])) === 1
            ? $next[0][1]
            : strlen($source);
        $dropped = substr($source, $start, $end - $start);

        preg_match_all('/^\[[^\]]+\]:[ \t]*\S.*$/m', $dropped, $references);
        $kept = $references[0] === [] ? '' : implode("\n", $references[0])."\n";

        return substr($source, 0, $start).$kept.substr($source, $end);
    }

    /**
     * The date of the file's last git commit, or null outside a full clone.
     * Never the mtime, for the same reason as docs pages.
     *
     * The changelog is not compiled, so the answer is cached against the
     * file's path and mtime instead: a request does not start git, and a
     * deploy that touches the file asks again.
     */
    public function updated(): ?string
    {
        $key = 'vellum.changelog.updated.'.hash('xxh128', $this->path.'|'.$this->mtime);
        $date = Cache::rememberForever(
            $key,
            fn (): string => (new LastUpdated(dirname($this->path)))->resolve($this->path, []) ?? '',
        );

        return $date !== '' ? $date : null;
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
