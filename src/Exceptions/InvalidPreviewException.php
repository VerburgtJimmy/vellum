<?php

declare(strict_types=1);

namespace Vellum\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when a :::preview cannot be rendered.
 *
 * Every message names both the view and the docs page that asked for it. A
 * preview fails at build time, far from the Markdown that caused it, and
 * "view not found" without the page is a search rather than a fix.
 */
final class InvalidPreviewException extends RuntimeException
{
    use RendersDocsError;

    public static function missingName(string $docsFile): self
    {
        return new self(
            "A :::preview in [{$docsFile}] does not name a view. Write :::preview[name-of-view]."
        );
    }

    public static function unsafeName(string $name, string $docsFile): self
    {
        return new self(
            "The :::preview name [{$name}] in [{$docsFile}] is not a plain view name. ".
            'Use letters, numbers and dashes, with dots for folders.'
        );
    }

    public static function notFound(string $name, string $path, string $docsFile): self
    {
        return new self(
            "The :::preview [{$name}] in [{$docsFile}] has no view at [{$path}]."
        );
    }

    public static function failed(string $name, string $docsFile, Throwable $previous): self
    {
        return new self(
            "The :::preview [{$name}] in [{$docsFile}] failed to render: {$previous->getMessage()}",
            0,
            $previous,
        );
    }

    public static function impure(string $name, string $docsFile, string $query): self
    {
        return new self(
            "The :::preview [{$name}] in [{$docsFile}] queried the database while rendering: {$query} ".
            'Previews are compiled once at build time, so they must not depend on data. '.
            'Pass fixed values into the view instead.'
        );
    }
}
