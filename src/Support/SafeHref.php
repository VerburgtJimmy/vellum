<?php

declare(strict_types=1);

namespace Vellum\Support;

use League\CommonMark\Util\RegexHelper;

/**
 * The rule Markdown links already follow, for hrefs that come from somewhere
 * else: a card or a meta.json entry. A javascript:, vbscript:, file: or
 * non-image data: URL becomes "#" rather than something a click runs.
 */
final class SafeHref
{
    public static function of(string $href): string
    {
        return RegexHelper::isLinkPotentiallyUnsafe($href) ? '#' : $href;
    }
}
