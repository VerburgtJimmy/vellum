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
     * @return list<string|Paragraph>
     */
    private function splitTabParagraph(Paragraph $paragraph): array
    {
        $text = ParagraphText::of($paragraph);
        $parts = preg_split('/(?=::tab\[)/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            return [$paragraph];
        }

        $result = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (preg_match('/^::tab\[([^\]]+)\](?:\s*\n([\s\S]*))?$/', $part, $match) !== 1) {
                $result[] = $this->paragraphFromText($part);

                continue;
            }

            $result[] = $match[1];

            $body = trim($match[2] ?? '');

            if ($body !== '') {
                $result[] = $this->paragraphFromText($body);
            }
        }

        return $result;
    }

    private function paragraphFromText(string $text): Paragraph
    {
        $paragraph = new Paragraph;
        $lines = preg_split("/\n/", $text) ?: [$text];

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $paragraph->appendChild(new Newline);
            }

            $paragraph->appendChild(new Text($line));
        }

        return $paragraph;
    }
}
