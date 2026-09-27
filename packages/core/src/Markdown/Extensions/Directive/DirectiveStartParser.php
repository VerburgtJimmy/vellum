<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Directive;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Starts a DirectiveBlock when a line begins with :::name.
 */
final class DirectiveStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented()) {
            return BlockStart::none();
        }

        $cursor->advanceToNextNonSpaceOrTab();

        if ($cursor->peek() !== ':' || $cursor->peek(1) !== ':' || $cursor->peek(2) !== ':') {
            return BlockStart::none();
        }

        $line = $cursor->getRemainder();

        if (! preg_match('/^:::(.+)$/', $line, $match)) {
            return BlockStart::none();
        }

        $info = DirectiveInfo::parse(trim($match[1]));

        if ($info === null) {
            return BlockStart::none();
        }

        $cursor->advanceToEnd();

        return BlockStart::of(new DirectiveContinueParser($info))->at($cursor);
    }
}
