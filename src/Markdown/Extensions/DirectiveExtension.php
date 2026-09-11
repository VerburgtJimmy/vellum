<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use Vellum\Markdown\Extensions\Directive\DirectiveStartParser;

/**
 * Registers the shared ::: directive container block parser once.
 */
final class DirectiveExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addBlockStartParser(new DirectiveStartParser, 80);
    }
}
