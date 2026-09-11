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

        $persist = $node->getAttribute('persist');
        $persistKey = is_string($persist) && $persist !== '' ? $persist : null;

        if ($tabs === []) {
            $attrs = [
                'class' => 'vellum-tabs',
                'data-vellum-tabs' => '',
            ];

            if ($persistKey !== null) {
                $attrs['data-persist'] = Xml::escape($persistKey);
            }

            return new HtmlElement('div', $attrs, $childRenderer->renderNodes($node->children()));
        }

        $defaultId = $tabs[0]->getId();
        $storageKey = $persistKey !== null ? 'vellum-tabs-'.$persistKey : null;

        if ($storageKey !== null) {
            $xData = '{ active: (typeof localStorage !== "undefined" && localStorage.getItem("'.$storageKey.'")) || "'.$defaultId.'", set(id) { this.active = id; localStorage.setItem("'.$storageKey.'", id) } }';
        } else {
            $xData = '{ active: "'.$defaultId.'", set(id) { this.active = id } }';
        }

        $listItems = [];
        $panels = [];

        foreach ($tabs as $index => $tab) {
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

            $panelAttrs = [
                'class' => 'vellum-tabs-panel',
                'role' => 'tabpanel',
                'x-show' => "active === '{$escapedId}'",
                'aria-labelledby' => 'vellum-tab-'.$escapedId,
                'x-cloak' => '',
            ];

            // Keep the default panel visible before Alpine boots (no localStorage yet).
            if ($index === 0 && $storageKey === null) {
                unset($panelAttrs['x-cloak']);
            }

            $panels[] = new HtmlElement('div', $panelAttrs, $childRenderer->renderNodes($tab->children()));
        }

        $attrs = [
            'class' => 'vellum-tabs',
            'data-vellum-tabs' => '',
            'x-data' => $xData,
        ];

        if ($persistKey !== null) {
            $attrs['data-persist'] = Xml::escape($persistKey);
        }

        return new HtmlElement('div', $attrs, [
            new HtmlElement('div', [
                'class' => 'vellum-tabs-list',
                'role' => 'tablist',
            ], $listItems),
            ...$panels,
        ]);
    }
}
