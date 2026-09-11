<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Image;

use InvalidArgumentException;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\NodeIterator;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\RegexHelper;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;

/**
 * Adds lazy loading, local dimensions, and figure/caption wrapping for images.
 */
final class ImageRenderer implements ConfigurationAwareInterface, NodeRendererInterface
{
    private ConfigurationInterface $config;

    public function __construct(
        private readonly ?string $contentPath = null,
    ) {}

    public function setConfiguration(ConfigurationInterface $configuration): void
    {
        $this->config = $configuration;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        if (! $node instanceof Image) {
            throw new InvalidArgumentException('Inline must be instance of '.Image::class);
        }

        /** @var array<string, string|bool> $attrs */
        $attrs = $node->data->get('attributes');
        $attrs['loading'] = 'lazy';

        $forbidUnsafeLinks = ! $this->config->get('allow_unsafe_links');
        if ($forbidUnsafeLinks && RegexHelper::isLinkPotentiallyUnsafe($node->getUrl())) {
            $attrs['src'] = '';
        } else {
            $attrs['src'] = $node->getUrl();
        }

        $attrs['alt'] = $this->getAltText($node);

        $title = $node->getTitle();
        if ($title !== null && $title !== '') {
            $attrs['title'] = $title;
        }

        $this->applyLocalDimensions($attrs, $node->getUrl());

        $img = new HtmlElement('img', $attrs, '', true);

        if ($title === null || $title === '') {
            return $img;
        }

        return new HtmlElement('figure', ['class' => 'vellum-figure'], [
            $img,
            new HtmlElement('figcaption', ['class' => 'vellum-figure-caption'], htmlspecialchars($title, ENT_QUOTES | ENT_HTML5)),
        ]);
    }

    /**
     * @param  array<string, string|bool>  $attrs
     */
    private function applyLocalDimensions(array &$attrs, string $url): void
    {
        if ($this->contentPath === null || $this->contentPath === '') {
            return;
        }

        if (preg_match('#^(?:[a-z]+:)?//#i', $url) === 1 || str_starts_with($url, 'data:')) {
            return;
        }

        $relative = ltrim(str_replace('\\', '/', $url), '/');
        $path = rtrim($this->contentPath, '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (! is_file($path)) {
            return;
        }

        $size = @getimagesize($path);

        if ($size === false) {
            return;
        }

        $attrs['width'] = (string) $size[0];
        $attrs['height'] = (string) $size[1];
    }

    private function getAltText(Image $node): string
    {
        $altText = '';

        foreach ((new NodeIterator($node)) as $n) {
            if ($n instanceof StringContainerInterface) {
                $altText .= $n->getLiteral();
            } elseif ($n instanceof Newline) {
                $altText .= "\n";
            }
        }

        return $altText;
    }
}
