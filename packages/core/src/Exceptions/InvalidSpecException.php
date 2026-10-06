<?php

declare(strict_types=1);

namespace Vellum\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when an OpenAPI spec cannot be read, parsed, or trusted.
 *
 * Every message names the file, because the spec is usually generated and the
 * author's first question is which one Vellum actually loaded.
 */
final class InvalidSpecException extends RuntimeException implements ContentError
{
    private ?string $spec = null;

    public static function unreadable(string $path, ?Throwable $previous = null): self
    {
        return self::at($path, "Unable to read OpenAPI spec at [{$path}].", $previous);
    }

    public static function unparsable(string $path, string $reason, ?Throwable $previous = null): self
    {
        return self::at($path, "Unable to parse OpenAPI spec at [{$path}]: {$reason}", $previous);
    }

    public static function missing(string $path, string $what): self
    {
        return self::at($path, "OpenAPI spec at [{$path}] is missing {$what}.");
    }

    public static function unsupportedVersion(string $path, string $version): self
    {
        return self::at($path, "OpenAPI spec at [{$path}] declares version [{$version}]. Vellum supports 3.0 and 3.1.");
    }

    public static function unresolvableRef(string $path, string $ref): self
    {
        return self::at($path, "OpenAPI spec at [{$path}] has a \$ref that points nowhere: [{$ref}].");
    }

    public function contentFile(): ?string
    {
        return $this->spec;
    }

    private static function at(string $path, string $message, ?Throwable $previous = null): self
    {
        $error = new self($message, 0, $previous);
        $error->spec = $path;

        return $error;
    }
}
