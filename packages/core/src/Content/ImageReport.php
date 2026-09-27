<?php

declare(strict_types=1);

namespace Vellum\Content;

/**
 * Where the image renderer says what it could not find.
 *
 * The renderer is the only thing that knows whether a src resolved, because
 * resolving is what it does. Inferring it afterwards from the shape of the
 * emitted URL worked, but it was a guess about a fallback, and it would have
 * gone quiet the day that fallback changed. This is the renderer saying so.
 *
 * @phpstan-type MissingImage array{page: string, version: string|null, url: string, path: string}
 */
final class ImageReport
{
    private string $page = '';

    private ?string $version = null;

    /** @var list<MissingImage> */
    private array $missing = [];

    /**
     * Whose page the next renders belong to. Set by the repository as it
     * compiles, since a renderer has no idea which document it is inside.
     */
    public function forPage(string $slug, ?string $version = null): void
    {
        $this->page = $slug;
        $this->version = $version;
    }

    public function missing(string $url, string $path): void
    {
        $this->missing[] = [
            'page' => $this->page,
            'version' => $this->version,
            'url' => $url,
            'path' => $path,
        ];
    }

    /**
     * @return list<MissingImage>
     */
    public function all(): array
    {
        return $this->missing;
    }

    public function reset(): void
    {
        $this->missing = [];
    }
}
