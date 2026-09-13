<?php

declare(strict_types=1);

namespace Vellum\Exceptions;

use RuntimeException;

/**
 * Thrown when a docs component tag cannot be rendered.
 */
final class UnknownComponentException extends RuntimeException
{
    public static function notAllowed(string $name): self
    {
        return new self("Component [{$name}] is not in vellum.components.namespaces.");
    }

    public static function missing(string $name, ?\Throwable $previous = null): self
    {
        return new self("Unknown docs component [{$name}].", 0, $previous);
    }

    public static function valueTagRefused(string $name, string $key): self
    {
        return new self("Value tag [{$name}] key [{$key}] is not in vellum.components.allowlist.");
    }

    public static function unclosed(string $name): self
    {
        return new self("Unclosed docs component [{$name}].");
    }

    public static function invalidAttributes(string $name): self
    {
        return new self("Docs component [{$name}] attributes must be quoted strings (no :bound expressions).");
    }
}
