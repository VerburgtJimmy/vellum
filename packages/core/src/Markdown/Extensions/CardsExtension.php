<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use Vellum\Markdown\Extensions\Cards\CardBlock;
use Vellum\Markdown\Extensions\Cards\CardsRenderer;
use Vellum\Markdown\Extensions\Cards\CardStartParser;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;

/**
 * Registers :::cards containers with ::card[Title](/link) items.
 */
final class CardsExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addBlockStartParser(new CardStartParser, 90);
        $environment->addRenderer(DirectiveBlock::class, new CardsRenderer, 20);
        $environment->addRenderer(CardBlock::class, new CardsRenderer, 10);
    }
}
