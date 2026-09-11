<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Directive;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

/**
 * Continues a ::: container until a closing ::: fence.
 */
final class DirectiveContinueParser extends AbstractBlockContinueParser
{
    private DirectiveBlock $block;

    public function __construct(DirectiveInfo $info)
    {
        $this->block = new DirectiveBlock($info->name, $info->title, $info->attributes);
    }

    public function getBlock(): DirectiveBlock
    {
        return $this->block;
    }

    public function isContainer(): bool
    {
        return true;
    }

    public function canContain(AbstractBlock $childBlock): bool
    {
        return true;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): BlockContinue
    {
        if ($cursor->isIndented()) {
            return BlockContinue::at($cursor);
        }

        $cursor->advanceToNextNonSpaceOrTab();
        $remainder = trim($cursor->getRemainder());

        if ($remainder === ':::') {
            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }
}
