<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;
use Vellum\Markdown\Extensions\Steps\StepsProcessor;
use Vellum\Markdown\Extensions\Steps\StepsRenderer;

/**
 * Registers :::steps containers that number ## headings.
 */
final class StepsExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, new StepsProcessor);
        $environment->addRenderer(DirectiveBlock::class, new StepsRenderer, 20);
    }
}
