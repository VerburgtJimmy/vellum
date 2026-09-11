<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;

/**
 * Registers heading IDs and permalink anchors via CommonMark's HeadingPermalinkExtension.
 */
final class HeadingAnchorExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addExtension(new HeadingPermalinkExtension);
    }
}
