<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;
use Vellum\Markdown\Extensions\Preview\PreviewRenderer;

/**
 * Registers the :::preview[view-name] container.
 */
final class PreviewExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addRenderer(DirectiveBlock::class, new PreviewRenderer, 30);
    }
}
