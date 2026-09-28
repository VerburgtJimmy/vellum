<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Steps;

use Illuminate\Support\HtmlString;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;
use Vellum\Support\MarkdownView;

/**
 * Renders :::steps via the steps/step Blade views. Each step's number links
 * to the heading the step starts with.
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
                'target' => $this->headingId($child),
                'slot' => new HtmlString((string) $childRenderer->renderNodes($child->children())),
            ]);
        }

        return MarkdownView::render('steps', [
            'slot' => new HtmlString($items),
        ]);
    }

    /**
     * The id of the heading a step starts with, so its number can link there.
     * The link is for pointers: it is out of the tab order and hidden from
     * screen readers, which reach the heading itself right after it.
     */
    private function headingId(StepBlock $step): ?string
    {
        $heading = $step->firstChild();

        if (! $heading instanceof Heading) {
            return null;
        }

        foreach ($heading->children() as $child) {
            if ($child instanceof HeadingPermalink) {
                return $child->getSlug();
            }
        }

        return null;
    }
}
