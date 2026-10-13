<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Directive;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
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

        // Looked at, not consumed: the spaces a line opens with belong to what
        // is inside, and a code block indented by two would lose them.
        if (trim($cursor->getRemainder()) === ':::') {
            // Inside a fenced block it is a line of code like any other.
            if ($activeBlockParser->getBlock() instanceof FencedCode) {
                return BlockContinue::at($cursor);
            }

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
