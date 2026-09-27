<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Table;

use InvalidArgumentException;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Wraps GFM tables so overflow and border-radius clip to a rounded container.
 */
final class TableWrapRenderer implements NodeRendererInterface
{
    public function __construct(
        private readonly TableRenderer $inner = new TableRenderer,
    ) {}

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        if (! $node instanceof Table) {
            throw new InvalidArgumentException('Block must be instance of '.Table::class);
        }

        return new HtmlElement('div', [
            'class' => 'vellum-table',
        ], $this->inner->render($node, $childRenderer));
    }
}
