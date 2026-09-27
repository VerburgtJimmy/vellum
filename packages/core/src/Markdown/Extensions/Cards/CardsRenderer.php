<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Cards;

use Illuminate\Support\HtmlString;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;
use Vellum\Support\MarkdownView;

/**
 * Renders :::cards via the cards/card Blade views.
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

        $items = '';

        foreach ($node->children() as $child) {
            if ($child instanceof CardBlock) {
                $items .= (string) $this->renderCard($child);
            } else {
                $items .= (string) $childRenderer->renderNodes([$child]);
            }
        }

        return MarkdownView::render('cards', [
            'slot' => new HtmlString($items),
        ]);
    }

    private function renderCard(CardBlock $card): \Stringable
    {
        return MarkdownView::render('card', [
            'href' => $card->getHref(),
            'title' => $card->getTitle(),
            'icon' => $card->getIcon(),
        ]);
    }
}
