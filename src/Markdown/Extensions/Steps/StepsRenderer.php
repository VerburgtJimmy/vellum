<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Steps;

use Illuminate\Support\HtmlString;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;
use Vellum\Support\MarkdownView;

/**
 * Renders :::steps via the steps/step Blade views.
 */
final class StepsRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?\Stringable
    {
        if (! $node instanceof DirectiveBlock || $node->getName() !== 'steps') {
            return null;
        }

        $items = '';

        foreach ($node->children() as $child) {
            if (! $child instanceof StepBlock) {
                $items .= (string) $childRenderer->renderNodes([$child]);

                continue;
            }

            $items .= MarkdownView::render('step', [
                'number' => (string) $child->getNumber(),
                'slot' => new HtmlString((string) $childRenderer->renderNodes($child->children())),
            ]);
        }

        return MarkdownView::render('steps', [
            'slot' => new HtmlString($items),
        ]);
    }
}
