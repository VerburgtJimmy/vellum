<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Tabs;

use Illuminate\Support\HtmlString;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Vellum\Markdown\Extensions\Directive\DirectiveBlock;
use Vellum\Support\MarkdownView;

/**
 * Renders :::tabs via the tabs Blade view.
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
            return MarkdownView::render('tabs', [
                'persist' => $persistKey,
                'code' => false,
                'tabs' => [],
                'slot' => new HtmlString((string) $childRenderer->renderNodes($node->children())),
            ]);
        }

        $codeTabs = $this->isCodeTabs($tabs);

        foreach ($tabs as $tab) {
            if (! $codeTabs) {
                break;
            }

            foreach ($tab->children() as $child) {
                if ($child instanceof FencedCode) {
                    $child->data->set('vellum_embedded', true);
                }
            }
        }

        $items = [];

        foreach ($tabs as $tab) {
            $items[] = [
                'id' => $tab->getId(),
                'label' => $tab->getLabel(),
                'html' => (string) $childRenderer->renderNodes($tab->children()),
            ];
        }

        return MarkdownView::render('tabs', [
            'persist' => $persistKey,
            'code' => $codeTabs,
            'tabs' => $items,
            'slot' => new HtmlString(''),
        ]);
    }

    /**
     * @param  list<TabBlock>  $tabs
     */
    private function isCodeTabs(array $tabs): bool
    {
        if ($tabs === []) {
            return false;
        }

        foreach ($tabs as $tab) {
            $fences = 0;

            foreach ($tab->children() as $child) {
                if ($child instanceof FencedCode) {
                    $fences++;

                    continue;
                }

                return false;
            }

            if ($fences !== 1) {
                return false;
            }
        }

        return true;
    }
}
