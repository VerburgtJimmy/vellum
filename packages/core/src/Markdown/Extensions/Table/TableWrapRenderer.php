<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Table;

use InvalidArgumentException;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableRenderer;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerHelper;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Wraps GFM tables so overflow and border-radius clip to a rounded container.
 *
 * Each body cell also carries its column's heading in data-label, and the
 * table its roles, so a stylesheet can lay rows out as blocks on a narrow
 * screen, each value under its heading, without the table losing its meaning
 * to a screen reader.
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

        $this->label($node);

        return new HtmlElement('div', [
            'class' => 'vellum-table',
        ], $this->inner->render($node, $childRenderer));
    }

    private function label(Table $table): void
    {
        $headings = [];
        $table->data->set('attributes/role', 'table');

        foreach ($table->children() as $section) {
            if (! $section instanceof TableSection) {
                continue;
            }

            $section->data->set('attributes/role', 'rowgroup');

            foreach ($section->children() as $row) {
                if (! $row instanceof TableRow) {
                    continue;
                }

                $row->data->set('attributes/role', 'row');
                $column = 0;

                foreach ($row->children() as $cell) {
                    if (! $cell instanceof TableCell) {
                        continue;
                    }

                    if ($section->isHead()) {
                        $headings[$column] = trim(StringContainerHelper::getChildText($cell));
                        $cell->data->set('attributes/role', 'columnheader');
                    } else {
                        $cell->data->set('attributes/role', 'cell');
                        $cell->data->set('attributes/data-label', $headings[$column] ?? '');
                    }

                    $column++;
                }
            }
        }
    }
}
