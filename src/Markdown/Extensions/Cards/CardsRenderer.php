<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Cards;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;

/**
 * Renders :::cards as a grid of link cards.
 */
final class CardsRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?\Stringable
    {
        if ($node instanceof CardBlock) {
            return $this->renderCard($node);
        }

        if (! $node instanceof DirectiveBlock || $node->getName() !== 'cards') {
            return null;
        }

        $items = [];

        foreach ($node->children() as $child) {
            if ($child instanceof CardBlock) {
                $items[] = $this->renderCard($child);
            } else {
                $items[] = new HtmlElement('div', [], $childRenderer->renderNodes([$child]));
            }
        }

        return new HtmlElement('div', [
            'class' => 'vellum-cards',
            'data-vellum-cards' => '',
        ], $items);
    }

    private function renderCard(CardBlock $card): HtmlElement
    {
        $children = [];

        if ($card->getIcon() !== null && $card->getIcon() !== '') {
            $children[] = new HtmlElement('span', [
                'class' => 'vellum-card-icon',
                'data-icon' => $card->getIcon(),
                'aria-hidden' => 'true',
            ]);
        }

        $children[] = new HtmlElement('span', ['class' => 'vellum-card-title'], Xml::escape($card->getTitle()));

        return new HtmlElement('a', [
            'class' => 'vellum-card',
            'href' => $card->getHref(),
            'data-vellum-card' => '',
        ], $children);
    }
}
