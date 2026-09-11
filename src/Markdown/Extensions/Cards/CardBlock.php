<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Cards;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A link card produced from ::card[Title](/url){icon=...}.
 */
final class CardBlock extends AbstractBlock
{
    /**
     * @param  array<string, string>  $attributes
     */
    public function __construct(
        private readonly string $title,
        private readonly string $href,
        private readonly array $attributes = [],
    ) {
        parent::__construct();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getHref(): string
    {
        return $this->href;
    }

    public function getIcon(): ?string
    {
        return $this->attributes['icon'] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
