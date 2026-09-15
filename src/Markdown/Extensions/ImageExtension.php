<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExtensionInterface;
use Vellum\Markdown\Extensions\Image\ImageRenderer;

/**
 * Registers image rendering with lazy loading and local dimension detection.
 */
final class ImageExtension implements ExtensionInterface
{
    public function __construct(
        private readonly ?string $contentPath = null,
        private readonly ?string $assetPrefix = null,
    ) {}

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addRenderer(Image::class, new ImageRenderer($this->contentPath, $this->assetPrefix), 10);
    }
}
