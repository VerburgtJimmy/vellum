<?php

declare(strict_types=1);

namespace Vellum\Exceptions;

use RuntimeException;

/**
 * Thrown when a ::: directive has no renderer, which in practice means a typo.
 */
final class UnknownDirectiveException extends RuntimeException
{
    use RendersDocsError;

    public function __construct(
        string $message,
        public readonly string $directive,
        public readonly ?string $docsFile = null,
    ) {
        parent::__construct($message);
    }

    public static function missing(string $name): self
    {
        return new self(
            "Unknown docs directive [:::{$name}]. Check the spelling, or see the directives reference for the ones Vellum ships.",
            $name,
        );
    }

    /**
     * Re-throwable copy that names the file the directive was found in.
     */
    public function withFile(string $file): self
    {
        return new self(
            "Unknown docs directive [:::{$this->directive}] in [{$file}]. Check the spelling, or see the directives reference for the ones Vellum ships.",
            $this->directive,
            $file,
        );
    }
}
