<?php

declare(strict_types=1);

namespace Vellum\Markdown\Islands;

/**
 * Extracted island before Markdown runs on its slot.
 *
 * @phpstan-type AttrMap array<string, string>
 */
final readonly class RawIsland
{
    /**
     * @param  AttrMap  $attributes
     * @param  list<self>  $children
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $attributes,
        public string $slotMarkdown,
        public array $children,
        public bool $selfClosing,
    ) {}
}
