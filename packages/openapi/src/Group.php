<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Vellum\Support\Slug;

/**
 * A page's worth of operations: one tag, or one first path segment.
 *
 * A tag may sit inside another (OpenAPI 3.2's parent, or a Redoc tag group),
 * and a parent may have no operations of its own, only the tags inside it.
 */
final readonly class Group
{
    /**
     * @param  list<Operation>  $operations
     */
    public function __construct(
        public string $name,
        public string $slug,
        public array $operations,
        public ?string $description = null,
        public ?string $title = null,
        public ?string $parent = null,
        public int $depth = 0,
    ) {}

    /**
     * What the group is called where a reader sees it: the tag's summary when
     * it has one, its name otherwise. The name stays what operations refer to.
     */
    public function label(): string
    {
        return $this->title ?? $this->name;
    }

    public function methodCount(): int
    {
        return count($this->operations);
    }

    /**
     * Slug for an operation inside this group.
     *
     * operationId when the spec has one, because /api/pets/update-pet reads
     * far better than /api/pets/put-pets-petid. Specs that omit it fall back
     * to the request line, which is the only other stable thing available.
     *
     * Slugs stay lowercase, as everywhere else in Vellum: "updatePet" and
     * "updatepet" are one file on a Mac and two on the deploy target, and
     * this project has already been bitten by that once. camelCase is broken
     * on its humps first, so lowercasing does not run the words together.
     * Whatever else separates the words becomes a hyphen for the same reason:
     * Scramble names an operation "note.index", and a slash in an id would
     * otherwise read as a folder.
     */
    public function slugFor(Operation $operation): string
    {
        $id = $operation->operationId;
        $slug = is_string($id) && trim($id) !== '' ? Slug::from($this->kebab($id)) : '';

        return $slug === '' ? $operation->headingId() : $slug;
    }

    private function kebab(string $value): string
    {
        $value = (string) preg_replace('/[^A-Za-z0-9]+/', '-', $value);

        return (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '-', $value);
    }
}
