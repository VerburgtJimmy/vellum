<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\ExternalLink;

use InvalidArgumentException;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\RegexHelper;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;

/**
 * Marks absolute http(s) links to other hosts with target, rel, and an external icon.
 */
final class ExternalLinkRenderer implements ConfigurationAwareInterface, NodeRendererInterface
{
    private ConfigurationInterface $config;

    public function __construct(
        private readonly ?string $appUrl = null,
    ) {}

    public function setConfiguration(ConfigurationInterface $configuration): void
    {
        $this->config = $configuration;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        if (! $node instanceof Link) {
            throw new InvalidArgumentException('Inline must be instance of '.Link::class);
        }

        /** @var array<string, string|bool> $attrs */
        $attrs = $node->data->get('attributes');

        $forbidUnsafeLinks = ! $this->config->get('allow_unsafe_links');
        if (! ($forbidUnsafeLinks && RegexHelper::isLinkPotentiallyUnsafe($node->getUrl()))) {
            $attrs['href'] = $node->getUrl();
        }

        if (($title = $node->getTitle()) !== null) {
            $attrs['title'] = $title;
        }

        $contents = $childRenderer->renderNodes($node->children());

        if ($this->isExternal($node->getUrl())) {
            $attrs['target'] = '_blank';
            $attrs['rel'] = 'noopener';

            $existingClass = $attrs['class'] ?? '';
            $attrs['class'] = trim((is_string($existingClass) ? $existingClass : '').' vellum-external-link');

            $contents .= (string) new HtmlElement('span', [
                'class' => 'vellum-external-icon',
                'aria-hidden' => 'true',
            ]);
        }

        return new HtmlElement('a', $attrs, $contents);
    }

    private function isExternal(string $url): bool
    {
        if (preg_match('#^https?://#i', $url) !== 1) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $appHost = $this->appHost();

        if ($appHost === null) {
            return true;
        }

        return strcasecmp($host, $appHost) !== 0;
    }

    private function appHost(): ?string
    {
        if ($this->appUrl === null || $this->appUrl === '') {
            return null;
        }

        $host = parse_url($this->appUrl, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}
