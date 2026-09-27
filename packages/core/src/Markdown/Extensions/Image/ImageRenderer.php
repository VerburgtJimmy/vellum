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
use Vellum\Content\ImageReport;

/**
 * Adds lazy loading, local dimensions, and figure/caption wrapping for images.
 */
final class ImageRenderer implements ConfigurationAwareInterface, NodeRendererInterface
{
    private ConfigurationInterface $config;

    /**
     * @param  string|null  $contentPath  Root that relative image URLs resolve against.
     * @param  string|null  $assetPrefix  Extra URL segment, used for the version folder.
     */
    public function __construct(
        private readonly ?string $contentPath = null,
        private readonly ?string $assetPrefix = null,
        private readonly ?ImageReport $report = null,
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

        $url = $node->getUrl();
        $forbidUnsafeLinks = ! $this->config->get('allow_unsafe_links');
        if ($forbidUnsafeLinks && RegexHelper::isLinkPotentiallyUnsafe($url)) {
            $attrs['src'] = '';
        } else {
            $attrs['src'] = $this->resolveSrc($url);
        }

        $attrs['alt'] = $this->getAltText($node);

        $title = $node->getTitle();
        if ($title !== null && $title !== '') {
            $attrs['title'] = $title;
        }

        $this->applyDimensions($attrs, $url);

        $img = new HtmlElement('img', $attrs, '', true);

        if ($title === null || $title === '') {
            return $img;
        }

        return new HtmlElement('figure', ['class' => 'vellum-figure'], [
            $img,
            new HtmlElement('figcaption', ['class' => 'vellum-figure-caption'], htmlspecialchars($title, ENT_QUOTES | ENT_HTML5)),
        ]);
    }

    private function resolveSrc(string $url): string
    {
        if ($this->contentPath === null || $this->contentPath === '') {
            return $url;
        }

        if (preg_match('#^(?:[a-z]+:)?//#i', $url) === 1 || str_starts_with($url, 'data:') || str_starts_with($url, '#')) {
            return $url;
        }

        $relative = ltrim(str_replace('\\', '/', $url), '/');
        $path = rtrim($this->contentPath, '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (! is_file($path)) {
            // Say so rather than leaving the caller to notice that the URL
            // came back unchanged. See Vellum\Content\ImageReport.
            $this->report?->missing($url, $path);

            return $url;
        }

        $prefix = trim((string) config('vellum.route.prefix', 'docs'), '/');
        $asset = $this->assetPrefix === null || $this->assetPrefix === ''
            ? $relative
            : trim($this->assetPrefix, '/').'/'.$relative;

        return '/'.$prefix.'/_vellum/files/'.$asset;
    }

    /**
     * @param  array<string, string|bool>  $attrs
     */
    private function applyDimensions(array &$attrs, string $url): void
    {
        if (isset($attrs['width'], $attrs['height'])) {
            return;
        }

        if (preg_match('~(?:^|/)(\d{2,5})x(\d{2,5})(?:[./?#]|$)~', $url, $matches) === 1) {
            $attrs['width'] = $matches[1];
            $attrs['height'] = $matches[2];
            $this->applyAspectRatio($attrs);

            return;
        }

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
            $size = $this->svgSize($path);
        }

        if ($size === null) {
            return;
        }

        $attrs['width'] = (string) $size[0];
        $attrs['height'] = (string) $size[1];
        $this->applyAspectRatio($attrs);
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function svgSize(string $path): ?array
    {
        if (! str_ends_with(strtolower($path), '.svg')) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        if (preg_match('/\bwidth=["\']?(\d+)/i', $contents, $width) !== 1) {
            return null;
        }

        if (preg_match('/\bheight=["\']?(\d+)/i', $contents, $height) !== 1) {
            return null;
        }

        return [(int) $width[1], (int) $height[1]];
    }

    /**
     * @param  array<string, string|bool>  $attrs
     */
    private function applyAspectRatio(array &$attrs): void
    {
        if (! isset($attrs['width'], $attrs['height'])) {
            return;
        }

        $ratio = 'aspect-ratio: '.$attrs['width'].' / '.$attrs['height'];
        $existing = $attrs['style'] ?? null;

        if (is_string($existing) && $existing !== '') {
            $attrs['style'] = rtrim($existing, ';').'; '.$ratio;

            return;
        }

        $attrs['style'] = $ratio;
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
