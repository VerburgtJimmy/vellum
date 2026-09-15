<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;
use Vellum\Markdown\Extensions\Directive\DirectiveStartParser;
use Vellum\Markdown\Extensions\Directive\UnknownDirectiveRenderer;

/**
 * Registers the shared ::: directive container block parser once, plus the
 * fallback renderer that turns an unclaimed directive into a named error.
 */
final class DirectiveExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addBlockStartParser(new DirectiveStartParser, 80);
        $environment->addRenderer(DirectiveBlock::class, new UnknownDirectiveRenderer, -100);
    }
}
