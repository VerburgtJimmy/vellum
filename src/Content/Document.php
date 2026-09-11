<?php

declare(strict_types=1);

namespace Vellum\Content;

/**
 * Immutable compiled documentation page.
 *
 * @phpstan-type Heading array{id: string, text: string, level: int}
 * @phpstan-type FrontMatter array<string, mixed>
 */
final readonly class Document
{
    /**
     * @param  list<Heading>  $headings
     * @param  FrontMatter  $frontmatter
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $html,
        public array $headings,
        public array $frontmatter,
        public string $path,
        public int $mtime,
        public ?string $description = null,
        public ?string $version = null,
        public bool $full = false,
        public ?string $icon = null,
    ) {}

    /**
     * @return array{
     *     slug: string,
     *     title: string,
     *     html: string,
     *     headings: list<Heading>,
     *     frontmatter: FrontMatter,
     *     path: string,
     *     mtime: int,
     *     description: string|null,
     *     version: string|null,
     *     full: bool,
     *     icon: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'html' => $this->html,
            'headings' => $this->headings,
            'frontmatter' => $this->frontmatter,
            'path' => $this->path,
            'mtime' => $this->mtime,
            'description' => $this->description,
            'version' => $this->version,
            'full' => $this->full,
            'icon' => $this->icon,
        ];
    }

    /**
     * @param  array{
     *     slug: string,
     *     title: string,
     *     html: string,
     *     headings?: list<Heading>,
     *     frontmatter?: FrontMatter,
     *     path: string,
     *     mtime: int,
     *     description?: string|null,
     *     version?: string|null,
     *     full?: bool,
     *     icon?: string|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            slug: $data['slug'],
            title: $data['title'],
            html: $data['html'],
            headings: $data['headings'] ?? [],
            frontmatter: $data['frontmatter'] ?? [],
            path: $data['path'],
            mtime: $data['mtime'],
            description: $data['description'] ?? null,
            version: $data['version'] ?? null,
            full: $data['full'] ?? false,
            icon: $data['icon'] ?? null,
        );
    }
}
