<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\ExtensionInterface;
use Vellum\Markdown\Extensions\ExternalLink\ExternalLinkRenderer;

/**
 * Registers external link rendering with target, rel, and indicator icon.
 */
final class ExternalLinkExtension implements ExtensionInterface
{
    public function __construct(
        private readonly ?string $appUrl = null,
    ) {}

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addRenderer(Link::class, new ExternalLinkRenderer($this->appUrl), 10);
    }
}
