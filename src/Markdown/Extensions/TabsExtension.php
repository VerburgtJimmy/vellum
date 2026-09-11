<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;
use Vellum\Markdown\Extensions\Tabs\TabsProcessor;
use Vellum\Markdown\Extensions\Tabs\TabsRenderer;

/**
 * Registers :::tabs containers with ::tab[Label] panels.
 */
final class TabsExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, new TabsProcessor);
        $environment->addRenderer(DirectiveBlock::class, new TabsRenderer, 20);
    }
}
