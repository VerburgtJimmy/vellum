<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\CodeBlock;

use InvalidArgumentException;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;
use Tempest\Highlight\Highlighter;

/**
 * Renders inline code, applying Tempest highlighting when a {:lang} suffix was merged.
 */
final class InlineCodeRenderer implements NodeRendererInterface
{
    public function __construct(
        private readonly Highlighter $highlighter = new Highlighter,
    ) {}

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        if (! $node instanceof Code) {
            throw new InvalidArgumentException('Inline must be instance of '.Code::class);
        }

        $language = $node->data->get('vellum_language');
        $literal = $node->getLiteral();

        if (is_string($language) && $language !== '') {
            return new HtmlElement('code', [
                'class' => 'language-'.$language,
                'data-vellum-inline-code' => '',
            ], $this->highlighter->parse($literal, $language));
        }

        return new HtmlElement('code', [], Xml::escape($literal));
    }
}
