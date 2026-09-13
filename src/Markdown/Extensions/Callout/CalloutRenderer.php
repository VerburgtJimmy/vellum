<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Callout;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;
use Vellum\Support\Icons;

/**
 * Renders :::note|tip|warning|danger|info|success|idea containers as callouts.
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
        $body = [];

        if ($node->getTitle() !== null && $node->getTitle() !== '') {
            $body[] = new HtmlElement('p', ['class' => 'vellum-callout-title'], Xml::escape($node->getTitle()));
        }

        $body[] = new HtmlElement('div', ['class' => 'vellum-callout-content'], $childRenderer->renderNodes($node->children()));

        return new HtmlElement('div', [
            'class' => 'vellum-callout vellum-callout-'.$type,
            'data-vellum-callout' => $name,
        ], [
            new HtmlElement('span', [
                'class' => 'vellum-callout-rail',
                'aria-hidden' => 'true',
            ]),
            new HtmlElement('span', [
                'class' => 'vellum-callout-icon vellum-callout-icon-'.$type,
                'aria-hidden' => 'true',
            ], Icons::callout($type)),
            new HtmlElement('div', ['class' => 'vellum-callout-body'], $body),
        ]);
    }
}
