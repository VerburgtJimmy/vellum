<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Directive;

use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\StringContainerHelper;

/**
 * Reads plain text from a paragraph for directive marker matching.
 */
final class ParagraphText
{
    public static function of(Paragraph $paragraph): string
    {
        return trim(StringContainerHelper::getChildText($paragraph));
    }
}
