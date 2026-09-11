<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\ExtensionInterface;
use Tempest\Highlight\Highlighter;
use Vellum\Markdown\Extensions\CodeBlock\CodeBlockRenderer;
use Vellum\Markdown\Extensions\CodeBlock\InlineCodeHighlightListener;
use Vellum\Markdown\Extensions\CodeBlock\InlineCodeRenderer;

/**
 * Fenced and inline code highlighting with title, line marks, numbers, and copy control.
 */
final class CodeBlockExtension implements ExtensionInterface
{
    public function __construct(
        private readonly Highlighter $highlighter = new Highlighter,
    ) {}

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addRenderer(FencedCode::class, new CodeBlockRenderer($this->highlighter), 10);
        $environment->addRenderer(Code::class, new InlineCodeRenderer($this->highlighter), 10);
        $environment->addEventListener(DocumentParsedEvent::class, new InlineCodeHighlightListener);
    }
}
