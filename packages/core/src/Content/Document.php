<?php

declare(strict_types=1);

namespace Vellum\Content;

use Vellum\Markdown\Islands\Island;

/**
 * Immutable compiled documentation page.
 *
 * @phpstan-type Heading array{id: string, text: string, level: int, step?: int}
 * @phpstan-type FrontMatter array<string, mixed>
 */
final readonly class Document
{
    /**
     * @param  list<Heading>  $headings
     * @param  FrontMatter  $frontmatter
     * @param  list<Island>  $islands
     * @param  string|null  $updated  YYYY-MM-DD or ISO 8601, from frontmatter or git. Never the file mtime.
     * @param  string|null  $markdown  The page as Markdown, for a page generated from something
     *                                 other than a Markdown file. Null means: read $path.
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
        public array $islands = [],
        public ?string $updated = null,
        public ?string $markdown = null,
    ) {}

    /**
     * Whether this page was generated, from an OpenAPI spec for one, rather
     * than written. $path is then the file it came from, not its Markdown.
     */
    public function generated(): bool
    {
        return $this->markdown !== null;
    }

    /**
     * The page's Markdown: the file as written, or the generated rendition.
     */
    public function source(): string
    {
        if ($this->markdown !== null) {
            return $this->markdown;
        }

        $contents = is_file($this->path) ? file_get_contents($this->path) : false;

        return $contents === false ? '' : $contents;
    }

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
     *     icon: string|null,
     *     islands: list<array<string, mixed>>,
     *     updated: string|null,
     *     markdown: string|null
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
            'islands' => array_map(static fn (Island $island): array => $island->toArray(), $this->islands),
            'updated' => $this->updated,
            'markdown' => $this->markdown,
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
     *     icon?: string|null,
     *     islands?: list<array<string, mixed>>,
     *     updated?: string|null,
     *     markdown?: string|null
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
            islands: Island::listFromArray($data['islands'] ?? []),
            updated: $data['updated'] ?? null,
            markdown: $data['markdown'] ?? null,
        );
    }

    public function access(): string
    {
        return Access::normalize($this->frontmatter['access'] ?? 'guest');
    }
}
