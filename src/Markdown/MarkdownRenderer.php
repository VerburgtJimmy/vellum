<?php

declare(strict_types=1);

namespace Vellum\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use Vellum\Content\HeadingExtractor;
use Vellum\Markdown\Extensions\CalloutExtension;
use Vellum\Markdown\Extensions\CardsExtension;
use Vellum\Markdown\Extensions\CodeBlockExtension;
use Vellum\Markdown\Extensions\DirectiveExtension;
use Vellum\Markdown\Extensions\ExternalLinkExtension;
use Vellum\Markdown\Extensions\HeadingAnchorExtension;
use Vellum\Markdown\Extensions\ImageExtension;
use Vellum\Markdown\Extensions\StepsExtension;
use Vellum\Markdown\Extensions\TableExtension;
use Vellum\Markdown\Extensions\TabsExtension;

/**
 * Compiles Markdown to HTML using CommonMark with GFM and Vellum extensions.
 *
 * @phpstan-type Heading array{id: string, text: string, level: int}
 * @phpstan-type RenderedMarkdown array{html: string, headings: list<Heading>}
 */
final class MarkdownRenderer
{
    private Environment $environment;

    private MarkdownParser $parser;

    private HtmlRenderer $htmlRenderer;

    private HeadingExtractor $headingExtractor;

    public function __construct(
        ?HeadingExtractor $headingExtractor = null,
        ?string $contentPath = null,
        ?string $appUrl = null,
    ) {
        $this->environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'html_class' => 'vellum-heading-anchor',
                'id_prefix' => '',
                'fragment_prefix' => '',
                'insert' => 'after',
                'title' => 'Copy link',
                'symbol' => '',
                'aria_hidden' => false,
                'apply_id_to_heading' => true,
                'heading_class' => 'vellum-heading',
                'min_heading_level' => 2,
                'max_heading_level' => 6,
            ],
            'footnote' => [
                'backref_class' => 'footnote-backref',
                'container_add_hr' => false,
                'container_class' => 'footnotes',
                'ref_class' => 'footnote-ref',
                'footnote_class' => 'footnote',
            ],
        ]);

        $this->environment->addExtension(new CommonMarkCoreExtension);
        $this->environment->addExtension(new GithubFlavoredMarkdownExtension);
        $this->environment->addExtension(new TableExtension);
        $this->environment->addExtension(new FootnoteExtension);
        $this->environment->addExtension(new HeadingAnchorExtension);
        $this->environment->addExtension(new DirectiveExtension);
        $this->environment->addExtension(new CodeBlockExtension);
        $this->environment->addExtension(new CalloutExtension);
        $this->environment->addExtension(new TabsExtension);
        $this->environment->addExtension(new StepsExtension);
        $this->environment->addExtension(new CardsExtension);
        $this->environment->addExtension(new ImageExtension($contentPath));
        $this->environment->addExtension(new ExternalLinkExtension($appUrl));

        $this->parser = new MarkdownParser($this->environment);
        $this->htmlRenderer = new HtmlRenderer($this->environment);
        $this->headingExtractor = $headingExtractor ?? new HeadingExtractor;
    }

    /**
     * Parse once: apply heading IDs in the AST, then render HTML and collect TOC entries.
     *
     * @return RenderedMarkdown
     */
    public function convert(string $markdown): array
    {
        $document = $this->parser->parse($markdown);
        $headings = $this->headingExtractor->extractFromDocument($document);
        $html = trim($this->htmlRenderer->renderDocument($document)->getContent());

        return [
            'html' => $html,
            'headings' => $headings,
        ];
    }

    /**
     * Convert a Markdown body string into an HTML fragment.
     */
    public function render(string $markdown): string
    {
        return $this->convert($markdown)['html'];
    }

    public function parseDocument(string $markdown): Document
    {
        return $this->parser->parse($markdown);
    }
}
