<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use Vellum\Markdown\Extensions\Callout\CalloutRenderer;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;

/**
 * Registers :::note|tip|warning|danger|info callout containers.
 */
final class CalloutExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addRenderer(DirectiveBlock::class, new CalloutRenderer, 10);
    }
}
