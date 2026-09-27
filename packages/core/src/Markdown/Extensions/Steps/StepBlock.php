<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Steps;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * One numbered step inside a :::steps container.
 */
final class StepBlock extends AbstractBlock
{
    public function __construct(
        private readonly int $number,
    ) {
        parent::__construct();
    }

    public function getNumber(): int
    {
        return $this->number;
    }
}
