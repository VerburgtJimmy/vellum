<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Tabs;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A single tab panel within a :::tabs container.
 */
final class TabBlock extends AbstractBlock
{
    public function __construct(
        private readonly string $label,
    ) {
        parent::__construct();
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getId(): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $this->label) ?? ''));

        return trim($slug, '-') ?: 'tab';
    }
}
