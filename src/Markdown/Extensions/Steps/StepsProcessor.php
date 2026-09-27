<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Steps;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Node;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;

/**
 * Groups :::steps children into StepBlock items keyed by ## headings.
 *
 * Authors always start a step with ##, but a steps block belongs to the
 * section it sits in. Its step titles are rendered one level below that
 * section's heading, and every heading inside the block moves with them, so
 * the outline and the table of contents nest the steps under their section.
 */
final class StepsProcessor
{
    public function __invoke(DocumentParsedEvent $event): void
    {
        $stepsNodes = [];
        $sectionLevel = 1;

        foreach ($event->getDocument()->iterator() as $node) {
            // A section heading is a top-level one, outside any directive.
            if ($node instanceof Heading && $node->parent() === $event->getDocument()) {
                $sectionLevel = $node->getLevel();
            }

            if ($node instanceof DirectiveBlock && $node->getName() === 'steps') {
                $stepsNodes[] = [$node, $sectionLevel];
            }
        }

        foreach ($stepsNodes as [$steps, $level]) {
            $this->process($steps);
            $this->nest($steps, $level);
        }
    }

    /**
     * Move the block's headings so its step titles sit one level below the
     * section heading above it. A block before any section heading, or
     * under an h1, keeps its step titles at h2.
     */
    private function nest(DirectiveBlock $steps, int $sectionLevel): void
    {
        $shift = max(0, $sectionLevel - 1);

        if ($shift === 0) {
            return;
        }

        foreach ($steps->iterator() as $node) {
            if ($node instanceof Heading) {
                $node->setLevel(min(6, $node->getLevel() + $shift));
            }
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
