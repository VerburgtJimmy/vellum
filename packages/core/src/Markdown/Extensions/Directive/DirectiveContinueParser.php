<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Directive;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

/**
 * Continues a ::: container until its matching closing ::: fence.
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
            // CommonMark asks outer containers first. Do not close while a nested
            // directive is still open between this block and the tip.
            if ($this->hasOpenNestedDirective($activeBlockParser->getBlock())) {
                return BlockContinue::at($cursor);
            }

            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }

    private function hasOpenNestedDirective(AbstractBlock $tip): bool
    {
        $node = $tip;

        while ($node instanceof Node && $node !== $this->block) {
            if ($node instanceof DirectiveBlock) {
                return true;
            }

            $node = $node->parent();
        }

        return false;
    }
}
