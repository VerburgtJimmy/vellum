<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Tabs;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;
use Vellum\Markdown\Extensions\Directive\ParagraphText;

/**
 * Rewrites :::tabs children so ::tab[Label] markers become TabBlock panels.
 */
final class TabsProcessor
{
    public function __invoke(DocumentParsedEvent $event): void
    {
        $tabsNodes = [];

        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof DirectiveBlock && $node->getName() === 'tabs') {
                $tabsNodes[] = $node;
            }
        }

        foreach ($tabsNodes as $tabs) {
            $this->process($tabs);
        }
    }

    private function process(DirectiveBlock $tabs): void
    {
        /** @var list<Node> $children */
        $children = [];
        foreach ($tabs->children() as $child) {
            $children[] = $child;
        }

        $tabs->detachChildren();

        $currentTab = null;

        foreach ($children as $child) {
            if ($child instanceof Paragraph && preg_match('/^::tab\[([^\]]+)\]$/', ParagraphText::of($child), $match) === 1) {
                $currentTab = new TabBlock($match[1]);
                $tabs->appendChild($currentTab);
                $child->detach();

                continue;
            }

            if ($currentTab instanceof TabBlock) {
                $currentTab->appendChild($child);
            } else {
                $tabs->appendChild($child);
            }
        }
    }
}
