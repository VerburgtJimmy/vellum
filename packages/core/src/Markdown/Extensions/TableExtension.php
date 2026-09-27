<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\Table\Table;
use Vellum\Markdown\Extensions\Table\TableWrapRenderer;

/**
 * Wraps GFM tables in a rounded, overflow-clipped container.
 */
final class TableExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addRenderer(Table::class, new TableWrapRenderer, 10);
    }
}
