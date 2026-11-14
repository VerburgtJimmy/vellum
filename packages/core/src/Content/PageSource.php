<?php

declare(strict_types=1);

namespace Vellum\Content;

use Closure;

/**
 * Something that adds pages to the docs that nobody wrote as Markdown, such
 * as an API reference built from a spec.
 *
 * The repository asks each source for its pages wherever it builds the
 * sidebar, stores them beside the written ones, and searches, links and
 * prunes them the same way. A source's pages belong to the latest version
 * only: what they are built from is not kept per version.
 *
 * A source works once per instance. A build, a sidebar and a page all ask
 * for the same pages, and a source that is costly to read should not be read
 * three times in one request.
 */
interface PageSource
{
    /**
     * A short name, unique among the sources: the key its signature is kept
     * under, and its sidebar's.
     */
    public function name(): string;

    /**
     * What the pages would be built from, worked out without building them,
     * or null when there is nothing to build. A change means the stored pages
     * are stale. Local development compares it with the one the last build
     * recorded.
     */
    public function signature(): ?string;

    /**
     * The pages, each with a slug only this source writes.
     *
     * @param  Closure(string): string  $href  The URL of a slug.
     * @return list<Document>
     */
    public function documents(?string $version, Closure $href): array;

    /**
     * Whether a slug is one of this source's pages, even one it no longer
     * builds.
     */
    public function owns(string $slug): bool;

    /**
     * Whether a stored page is still the one the source would build now.
     */
    public function isCurrent(Document $document): bool;

    /**
     * The pages as one sidebar folder, or null to leave them out of the
     * sidebar.
     *
     * @param  Closure(string): string  $href
     * @return array<string, mixed>|null
     */
    public function navigation(?string $version, Closure $href): ?array;

    /**
     * Whether the folder gets a sidebar of its own, with a switch to it at the
     * top of the docs' sidebar. Otherwise it is one more folder at the end of
     * the docs' own.
     */
    public function separate(): bool;

    /**
     * What the source had to work around while reading, for the build to
     * print.
     *
     * @return list<string>
     */
    public function warnings(): array;
}
