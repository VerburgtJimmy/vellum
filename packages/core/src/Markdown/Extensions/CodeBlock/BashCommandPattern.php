<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\CodeBlock;

use Tempest\Highlight\IsPattern;
use Tempest\Highlight\Pattern;
use Tempest\Highlight\Tokens\TokenTypeEnum;

/**
 * First token on a shell line, matching Shiki's shell function colour.
 */
final readonly class BashCommandPattern implements Pattern
{
    use IsPattern;

    public function getPattern(): string
    {
        return '/^(?<match>[A-Za-z][\w.-]*)/m';
    }

    public function getTokenType(): TokenTypeEnum
    {
        return TokenTypeEnum::PROPERTY;
    }
}
