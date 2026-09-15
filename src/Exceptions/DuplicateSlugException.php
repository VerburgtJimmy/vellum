<?php

declare(strict_types=1);

namespace Vellum\Exceptions;

use RuntimeException;

/**
 * Thrown by a build when two source files resolve to the same URL.
 *
 * One of them would win and the other would be unreachable, with nothing to
 * say so, which is worse than refusing to build.
 */
final class DuplicateSlugException extends RuntimeException
{
    public static function forPaths(string $slug, string $first, string $second): self
    {
        $url = $slug === '' ? '/' : '/'.$slug;

        return new self(
            "Two docs files resolve to [{$url}]:\n  {$first}\n  {$second}\n"
            .'Rename one, or set a different `slug:` in its frontmatter.'
        );
    }
}
