<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Tabs;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
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
            if ($child instanceof Paragraph && str_contains(ParagraphText::of($child), '::tab[')) {
                foreach ($this->splitTabParagraph($child) as $piece) {
                    if (is_string($piece)) {
                        $currentTab = new TabBlock($piece);
                        $tabs->appendChild($currentTab);

                        continue;
                    }

                    if ($currentTab instanceof TabBlock) {
                        $currentTab->appendChild($piece);
                    } else {
                        $tabs->appendChild($piece);
                    }
                }

                continue;
            }

            if ($currentTab instanceof TabBlock) {
                $currentTab->appendChild($child);
            } else {
                $tabs->appendChild($child);
            }
        }
    }

    /**
     * Splits a paragraph at its ::tab[Label] lines. The other lines keep their
     * parsed inline nodes, so code, emphasis and links in a panel survive;
     * consecutive ones stay one paragraph.
     *
     * @return list<string|Paragraph>
     */
    private function splitTabParagraph(Paragraph $paragraph): array
    {
        /** @var list<list<Node>> $lines */
        $lines = [[]];

        foreach ($paragraph->children() as $node) {
            if ($node instanceof Newline) {
                $lines[] = [];

                continue;
            }

            $lines[array_key_last($lines)][] = $node;
        }

        $result = [];
        $current = null;

        foreach ($lines as $nodes) {
            $label = $this->tabLabel($nodes);

            if ($label !== null) {
                $result[] = $label;
                $current = null;

                continue;
            }

            if ($nodes === []) {
                continue;
            }

            if ($current === null) {
                $current = new Paragraph;
                $result[] = $current;
            } else {
                $current->appendChild(new Newline(Newline::SOFTBREAK));
            }

            foreach ($nodes as $node) {
                $node->detach();
                $current->appendChild($node);
            }
        }

        return $result;
    }

    /**
     * The label when a line is only "::tab[Label]". Its text may be split
     * across several Text nodes, since brackets start a possible link.
     *
     * @param  list<Node>  $nodes
     */
    private function tabLabel(array $nodes): ?string
    {
        $text = '';

        foreach ($nodes as $node) {
            if (! $node instanceof Text) {
                return null;
            }

            $text .= $node->getLiteral();
        }

        return preg_match('/^\s*::tab\[([^\]]+)\]\s*$/', $text, $match) === 1 ? $match[1] : null;
    }
}
