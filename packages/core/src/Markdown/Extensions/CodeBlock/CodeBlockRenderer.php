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
 * Renders fenced code with Tempest highlighting and a copy control, as one of
 * three kinds:
 *
 * - file: a block with a title, under a header naming it;
 * - terminal: an untitled shell block, under a "Terminal" header, with each
 *   command line marked so a prompt can be shown before it;
 * - snippet: any other untitled block, with no header, and its language and
 *   copy control in a corner.
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
        $language = LanguageIcon::normalize($info->language);
        $titled = $info->title !== null && $info->title !== '';
        $kind = $titled ? 'file' : ($language === 'bash' ? 'terminal' : 'snippet');

        $highlighted = $this->highlighter->parse($node->getLiteral(), $info->language);
        $highlighted = $this->wrapLines($highlighted, $info->highlightLines, commands: $kind === 'terminal');
        $embedded = $node->data->get('vellum_embedded', false) === true;

        $attrs = [
            'class' => $embedded ? 'vellum-code vellum-code-embedded' : 'vellum-code',
            'data-vellum-code' => '',
            'data-vellum-code-kind' => $kind,
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

        if ($kind === 'snippet') {
            $actions = $language === 'txt'
                ? [$this->copyButton()]
                : [new HtmlElement('span', ['class' => 'vellum-code-lang'], Xml::escape($language)), $this->copyButton()];

            return new HtmlElement('div', $attrs, [
                new HtmlElement('div', ['class' => 'vellum-code-actions'], $actions),
                $pre,
            ]);
        }

        return new HtmlElement('div', $attrs, [
            new HtmlElement('div', ['class' => 'vellum-code-header'], [
                new HtmlElement('span', ['class' => 'vellum-code-header-meta'], [
                    LanguageIcon::svg($info->language),
                    new HtmlElement('span', ['class' => 'vellum-code-title'], Xml::escape($titled ? (string) $info->title : 'Terminal')),
                ]),
                $this->copyButton(),
            ]),
            $pre,
        ]);
    }

    /**
     * Wraps each line in a span, marking highlighted lines and, in a terminal
     * block, the lines that are commands: not blank, not a # comment, and not
     * the continuation of a line ending in a backslash.
     *
     * @param  list<int>  $highlightLines
     */
    private function wrapLines(string $highlighted, array $highlightLines, bool $commands): string
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
        $continues = false;

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            $classes = ['vellum-code-line'];

            if (isset($highlightLookup[$lineNumber])) {
                $classes[] = 'vellum-code-line-highlighted';
            }

            if ($commands) {
                $text = trim(html_entity_decode(strip_tags($line), ENT_QUOTES | ENT_HTML5));

                if (! $continues && $text !== '' && ! str_starts_with($text, '#')) {
                    $classes[] = 'vellum-code-command';
                }

                $continues = str_ends_with($text, '\\');
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
            'data-vellum-copy-code' => '',
            'aria-label' => 'Copy code',
        ], [
            new HtmlElement('span', [
                'class' => 'vellum-code-copy-icon',
                'aria-hidden' => 'true',
            ], Icons::copy()),
            new HtmlElement('span', [
                'class' => 'vellum-code-check-icon',
                'aria-hidden' => 'true',
            ], Icons::check()),
        ]);
    }
}
