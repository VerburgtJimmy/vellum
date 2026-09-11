<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Steps;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;

/**
 * Renders :::steps as a numbered vertical list.
 */
final class StepsRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?\Stringable
    {
        if (! $node instanceof DirectiveBlock || $node->getName() !== 'steps') {
            return null;
        }

        $items = [];

        foreach ($node->children() as $child) {
            if (! $child instanceof StepBlock) {
                $items[] = new HtmlElement('div', [], $childRenderer->renderNodes([$child]));

                continue;
            }

            $items[] = new HtmlElement('div', [
                'class' => 'vellum-step',
                'data-vellum-step' => (string) $child->getNumber(),
            ], [
                new HtmlElement('div', [
                    'class' => 'vellum-step-indicator',
                    'aria-hidden' => 'true',
                ], (string) $child->getNumber()),
                new HtmlElement('div', [
                    'class' => 'vellum-step-content',
                ], $childRenderer->renderNodes($child->children())),
            ]);
        }

        return new HtmlElement('div', [
            'class' => 'vellum-steps',
            'data-vellum-steps' => '',
        ], $items);
    }
}
