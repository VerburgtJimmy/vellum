<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Cards;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;
use Vellum\Markdown\Extensions\Directive\AttributeParser;

/**
 * Parses a single-line ::card[Title](/url){attrs} Description as a leaf block.
 * The description, everything after the link, is optional.
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

        if (preg_match('/^::card\[([^\]]+)\]\(([^)\s]+)\)(?:\{([^}]*)\})?(?:\s+(\S.*))?$/', $line, $match) !== 1) {
            return BlockStart::none();
        }

        $attributes = [];
        if (($match[3] ?? '') !== '') {
            $attributes = AttributeParser::parse($match[3]);
        }

        $cursor->advanceToEnd();

        $description = trim($match[4] ?? '');

        return BlockStart::of(new CardContinueParser(new CardBlock($match[1], $match[2], $attributes, $description === '' ? null : $description)))->at($cursor);
    }
}
