<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Tabs;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;

/**
 * Renders :::tabs as an Alpine-powered tab group with optional localStorage persistence.
 */
final class TabsRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?\Stringable
    {
        if (! $node instanceof DirectiveBlock || $node->getName() !== 'tabs') {
            return null;
        }

        /** @var list<TabBlock> $tabs */
        $tabs = [];
        foreach ($node->children() as $child) {
            if ($child instanceof TabBlock) {
                $tabs[] = $child;
            }
        }

        if ($tabs === []) {
            return new HtmlElement('div', [
                'class' => 'vellum-tabs',
                'data-vellum-tabs' => '',
            ], $childRenderer->renderNodes($node->children()));
        }

        $persist = $node->getAttribute('persist');
        $defaultId = $tabs[0]->getId();
        $storageKey = is_string($persist) && $persist !== '' ? 'vellum-tabs-'.$persist : null;

        if ($storageKey !== null) {
            $xData = '{ active: (typeof localStorage !== "undefined" && localStorage.getItem("'.$storageKey.'")) || "'.$defaultId.'", set(id) { this.active = id; localStorage.setItem("'.$storageKey.'", id) } }';
        } else {
            $xData = '{ active: "'.$defaultId.'", set(id) { this.active = id } }';
        }

        $listItems = [];
        $panels = [];

        foreach ($tabs as $tab) {
            $id = $tab->getId();
            $escapedId = Xml::escape($id);

            $listItems[] = new HtmlElement('button', [
                'type' => 'button',
                'class' => 'vellum-tabs-trigger',
                'role' => 'tab',
                ':aria-selected' => "active === '{$escapedId}'",
                '@click' => "set('{$escapedId}')",
                'id' => 'vellum-tab-'.$escapedId,
            ], Xml::escape($tab->getLabel()));

            $panels[] = new HtmlElement('div', [
                'class' => 'vellum-tabs-panel',
                'role' => 'tabpanel',
                'x-show' => "active === '{$escapedId}'",
                'aria-labelledby' => 'vellum-tab-'.$escapedId,
            ], $childRenderer->renderNodes($tab->children()));
        }

        return new HtmlElement('div', [
            'class' => 'vellum-tabs',
            'data-vellum-tabs' => '',
            'x-data' => $xData,
        ], [
            new HtmlElement('div', [
                'class' => 'vellum-tabs-list',
                'role' => 'tablist',
            ], $listItems),
            ...$panels,
        ]);
    }
}
