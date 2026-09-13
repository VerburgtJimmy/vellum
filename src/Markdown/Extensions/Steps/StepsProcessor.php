<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Steps;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Node;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;

/**
 * Groups :::steps children into StepBlock items keyed by ## headings.
 */
final class StepsProcessor
{
    public function __invoke(DocumentParsedEvent $event): void
    {
        $stepsNodes = [];

        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof DirectiveBlock && $node->getName() === 'steps') {
                $stepsNodes[] = $node;
            }
        }

        foreach ($stepsNodes as $steps) {
            $this->process($steps);
        }
    }

    private function process(DirectiveBlock $steps): void
    {
        /** @var list<Node> $children */
        $children = [];
        foreach ($steps->children() as $child) {
            $children[] = $child;
        }

        $steps->detachChildren();

        $current = null;
        $index = 0;

        foreach ($children as $child) {
            if ($child instanceof Heading && $child->getLevel() === 2) {
                $index++;
                $child->setLevel(3);
                $current = new StepBlock($index);
                $steps->appendChild($current);
                $current->appendChild($child);

                continue;
            }

            if ($current instanceof StepBlock) {
                $current->appendChild($child);
            } else {
                $steps->appendChild($child);
            }
        }
    }
}
