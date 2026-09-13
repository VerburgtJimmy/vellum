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
use Vellum\Support\Icons;

/**
 * Renders fenced code with Tempest highlighting, a language header, and a copy control.
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
        $highlighted = $this->highlighter->parse($node->getLiteral(), $info->language);
        $highlighted = $this->wrapHighlightedLines($highlighted, $info->highlightLines);
        $embedded = $node->data->get('vellum_embedded', false) === true;

        $attrs = [
            'class' => $embedded ? 'vellum-code vellum-code-embedded' : 'vellum-code',
            'data-vellum-code' => '',
        ];

        if ($info->showLineNumbers) {
            $attrs['data-vellum-line-numbers'] = '';
        }

        $pre = new HtmlElement('pre', [], new HtmlElement('code', [
            'class' => 'language-'.$info->language,
        ], $highlighted));

        if ($embedded) {
            return new HtmlElement('div', $attrs, [
                $this->copyButton(),
                $pre,
            ]);
        }

        $label = ($info->title !== null && $info->title !== '')
            ? $info->title
            : LanguageIcon::normalize($info->language);

        return new HtmlElement('div', $attrs, [
            new HtmlElement('div', ['class' => 'vellum-code-header'], [
                new HtmlElement('span', ['class' => 'vellum-code-header-meta'], [
                    LanguageIcon::svg($info->language),
                    new HtmlElement('span', ['class' => 'vellum-code-title'], Xml::escape($label)),
                ]),
                $this->copyButton(),
            ]),
            $pre,
        ]);
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

        if ($lines !== [] && $lines[array_key_last($lines)] === '') {
            array_pop($lines);
        }

        $highlightLookup = array_fill_keys($highlightLines, true);
        $wrapped = [];

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            $classes = ['vellum-code-line'];

            if (isset($highlightLookup[$lineNumber])) {
                $classes[] = 'vellum-code-line-highlighted';
            }

            $wrapped[] = '<span class="'.implode(' ', $classes).'">'.$line."\n".'</span>';
        }

        return implode('', $wrapped);
    }

    private function copyButton(): HtmlElement
    {
        return new HtmlElement('button', [
            'type' => 'button',
            'class' => 'vellum-code-copy',
            'x-data' => '{ copied: false }',
            '@click' => "navigator.clipboard.writeText(\$el.closest('[data-vellum-code]').querySelector('code').textContent); copied = true; setTimeout(() => copied = false, 1500)",
            ':aria-label' => "copied ? 'Copied' : 'Copy code'",
        ], [
            new HtmlElement('span', [
                'class' => 'vellum-code-copy-icon',
                'x-show' => '!copied',
                'aria-hidden' => 'true',
            ], Icons::copy()),
            new HtmlElement('span', [
                'class' => 'vellum-code-check-icon',
                'x-show' => 'copied',
                'x-cloak' => '',
                'aria-hidden' => 'true',
            ], Icons::check()),
        ]);
    }
}
