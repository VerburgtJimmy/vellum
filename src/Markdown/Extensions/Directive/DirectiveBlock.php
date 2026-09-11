<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Directive;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * Container opened by a :::name fence and closed by :::.
 */
final class DirectiveBlock extends AbstractBlock
{
    /**
     * @param  array<string, string>  $attributes
     */
    public function __construct(
        private readonly string $name,
        private readonly ?string $title = null,
        private readonly array $attributes = [],
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @return array<string, string>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $key): ?string
    {
        return $this->attributes[$key] ?? null;
    }
}
