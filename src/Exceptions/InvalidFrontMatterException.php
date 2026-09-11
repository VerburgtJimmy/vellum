<?php

declare(strict_types=1);

namespace Vellum\Exceptions;

use RuntimeException;

/**
 * Thrown when a Markdown file's frontmatter cannot be parsed.
 */
final class InvalidFrontMatterException extends RuntimeException
{
    public static function forFile(string $path, string $reason, ?\Throwable $previous = null): self
    {
        return new self("Invalid frontmatter in [{$path}]: {$reason}", 0, $previous);
    }
}
