<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Callout;

use Illuminate\Support\HtmlString;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;
use Vellum\Support\MarkdownView;

/**
 * Renders :::note|tip|warning|danger|info|success|idea via the callout Blade view.
 */
final class CalloutRenderer implements NodeRendererInterface
{
    private const TYPES = ['note', 'tip', 'warning', 'danger', 'info'];

    private const ALIASES = [
        'success' => 'tip',
        'idea' => 'note',
    ];

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?\Stringable
    {
        if (! $node instanceof DirectiveBlock) {
            return null;
        }

        $name = $node->getName();
        $type = self::ALIASES[$name] ?? $name;

        if (! in_array($type, self::TYPES, true)) {
            return null;
        }

        return MarkdownView::render('callout', [
            'type' => $type,
            'name' => $name,
            'title' => $node->getTitle(),
            'slot' => new HtmlString((string) $childRenderer->renderNodes($node->children())),
        ]);
    }
}
