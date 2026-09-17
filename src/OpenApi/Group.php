<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Vellum\Support\Slug;

/**
 * A page's worth of operations: one tag, or one first path segment.
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
    ) {}

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
     */
    public function slugFor(Operation $operation): string
    {
        $id = $operation->operationId;
        $slug = is_string($id) && trim($id) !== '' ? Slug::from($this->kebab($id)) : '';

        return $slug === '' ? $operation->headingId() : $slug;
    }

    private function kebab(string $value): string
    {
        return (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '-', $value);
    }
}
