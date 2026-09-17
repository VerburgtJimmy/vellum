<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

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
}
