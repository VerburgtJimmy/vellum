<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\HeadingPermalink;

use InvalidArgumentException;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Vellum\Support\Icons;

/**
 * Heading permalink as a hover/focus copy-link control (no visible #).
 */
final class HeadingPermalinkRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        if (! $node instanceof HeadingPermalink) {
            throw new InvalidArgumentException('Inline must be instance of '.HeadingPermalink::class);
        }

        return new HtmlElement('button', [
            'type' => 'button',
            'class' => 'vellum-heading-anchor',
            'data-vellum-heading-copy' => '',
            'aria-label' => 'Copy link to heading',
            'x-data' => 'vellumHeadingCopy',
            '@click' => 'copy()',
        ], [
            new HtmlElement('span', [
                'class' => 'vellum-heading-anchor-icon',
                'x-show' => '!copied',
                'aria-hidden' => 'true',
            ], Icons::link()),
            new HtmlElement('span', [
                'class' => 'vellum-heading-anchor-copied',
                'x-show' => 'copied',
                'x-cloak' => '',
            ], 'Copied'),
        ]);
    }
}
