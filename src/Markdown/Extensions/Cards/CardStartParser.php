<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Cards;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;
use Vellum\Markdown\Extensions\Directive\AttributeParser;

/**
 * Parses a single-line ::card[Title](/url){attrs} as a leaf block.
 */
final class CardStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented()) {
            return BlockStart::none();
        }

        $cursor->advanceToNextNonSpaceOrTab();
        $line = trim($cursor->getRemainder());

        if (preg_match('/^::card\[([^\]]+)\]\(([^)]+)\)(?:\{([^}]*)\})?$/', $line, $match) !== 1) {
            return BlockStart::none();
        }

        $attributes = [];
        if (($match[3] ?? '') !== '') {
            $attributes = AttributeParser::parse($match[3]);
        }

        $cursor->advanceToEnd();

        return BlockStart::of(new CardContinueParser(new CardBlock($match[1], $match[2], $attributes)))->at($cursor);
    }
}
