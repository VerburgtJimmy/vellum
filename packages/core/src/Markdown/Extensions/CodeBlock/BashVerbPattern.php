<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\CodeBlock;

use Tempest\Highlight\IsPattern;
use Tempest\Highlight\Pattern;
use Tempest\Highlight\Tokens\TokenTypeEnum;

/**
 * Common package-manager verbs, mapped to GitHub constant blue.
 */
final readonly class BashVerbPattern implements Pattern
{
    use IsPattern;

    public function getPattern(): string
    {
        return '\b(?<match>require|install|add|remove|update|init|run|exec|npx)\b';
    }

    public function getTokenType(): TokenTypeEnum
    {
        return TokenTypeEnum::NUMBER;
    }
}
