<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\CodeBlock;

use InvalidArgumentException;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;
use Tempest\Highlight\Highlighter;

/**
 * Renders fenced code with Tempest highlighting, titles, gutters, and a copy control.
 */
final class CodeBlockRenderer implements NodeRendererInterface
{
    public function __construct(
        private readonly Highlighter $highlighter = new Highlighter,
    ) {}

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        if (! $node instanceof FencedCode) {
            throw new InvalidArgumentException('Block must be instance of '.FencedCode::class);
        }

        $info = CodeBlockInfo::parse($node->getInfo());
        $highlighter = $this->highlighter;

        if ($info->showLineNumbers) {
            $highlighter = $highlighter->withGutter(1);
        }

        $highlighted = $highlighter->parse($node->getLiteral(), $info->language);
        $highlighted = $this->wrapHighlightedLines($highlighted, $info->highlightLines);

        $codeAttrs = [
            'class' => 'language-'.$info->language,
        ];

        $inner = [];

        if ($info->title !== null && $info->title !== '') {
            $inner[] = new HtmlElement('div', ['class' => 'vellum-code-header'], [
                new HtmlElement('span', ['class' => 'vellum-code-title'], Xml::escape($info->title)),
            ]);
        }

        $inner[] = $this->copyButton();
        $inner[] = new HtmlElement('pre', [], new HtmlElement('code', $codeAttrs, $highlighted));

        return new HtmlElement('div', [
            'class' => 'vellum-code',
            'data-vellum-code' => '',
        ], $inner);
    }

    /**
     * @param  list<int>  $highlightLines
     */
    private function wrapHighlightedLines(string $highlighted, array $highlightLines): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $highlighted);

        if ($lines === false) {
            $lines = [$highlighted];
        }

        $highlightLookup = array_fill_keys($highlightLines, true);
        $wrapped = [];

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            $classes = ['vellum-code-line'];

            if (isset($highlightLookup[$lineNumber])) {
                $classes[] = 'vellum-code-line-highlighted';
            }

            $wrapped[] = '<span class="'.implode(' ', $classes).'">'.$line.'</span>';
        }

        return implode("\n", $wrapped);
    }

    private function copyButton(): HtmlElement
    {
        return new HtmlElement('button', [
            'type' => 'button',
            'class' => 'vellum-code-copy',
            'x-data' => '{ copied: false }',
            '@click' => "navigator.clipboard.writeText(\$el.closest('[data-vellum-code]').querySelector('code').innerText); copied = true; setTimeout(() => copied = false, 1500)",
            ':aria-label' => "copied ? 'Copied' : 'Copy code'",
        ], [
            new HtmlElement('span', [
                'class' => 'vellum-code-copy-icon',
                'x-show' => '!copied',
                'aria-hidden' => 'true',
            ], 'Copy'),
            new HtmlElement('span', [
                'class' => 'vellum-code-check-icon',
                'x-show' => 'copied',
                'x-cloak' => '',
                'aria-hidden' => 'true',
            ], 'Copied'),
        ]);
    }
}
