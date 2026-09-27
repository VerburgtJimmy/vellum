<?php

declare(strict_types=1);

namespace Vellum\Exceptions;

use Throwable;

/**
 * A mistake in the docs rather than in the app: frontmatter that does not
 * parse, or a directive or component that does not exist.
 *
 * The full package renders these as a page of its own. Without it, Laravel
 * handles them like any other exception.
 */
interface ContentError extends Throwable
{
    /**
     * The docs file the error came from, when known. Not Exception::$file,
     * which is the PHP source that threw.
     */
    public function contentFile(): ?string;
}
