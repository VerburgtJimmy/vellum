<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

/**
 * A schema as one side of the exchange sees it.
 *
 * A property marked readOnly is the server's to set, an id or a timestamp:
 * it comes back in a response but is not sent in a request. writeOnly is the
 * other way round, a password set but never read back. One schema usually
 * serves both sides, so each side gets it without what is not its own, and
 * without requiring it.
 */
final class SchemaDirection
{
    private const MAX_DEPTH = 32;

    /**
     * @param  array<array-key, mixed>  $schema
     * @return array<array-key, mixed>
     */
    public static function request(array $schema): array
    {
        return self::without($schema, 'readOnly', 0);
    }

    /**
     * @param  array<array-key, mixed>  $schema
     * @return array<array-key, mixed>
     */
    public static function response(array $schema): array
    {
        return self::without($schema, 'writeOnly', 0);
    }

    /**
     * Every media type's schema in a content map, as one side sees it.
     *
     * @param  array<array-key, mixed>  $content
     * @return array<array-key, mixed>
     */
    public static function content(array $content, string $side): array
    {
        foreach ($content as $type => $media) {
            if (is_array($media) && is_array($media['schema'] ?? null)) {
                $content[$type]['schema'] = $side === 'request' ? self::request($media['schema']) : self::response($media['schema']);
            }
        }

        return $content;
    }

    /**
     * @param  array<array-key, mixed>  $schema
     * @return array<array-key, mixed>
     */
    private static function without(array $schema, string $flag, int $depth): array
    {
        if ($depth > self::MAX_DEPTH || isset($schema[RefResolver::RECURSIVE])) {
            return $schema;
        }

        if (is_array($schema['properties'] ?? null)) {
            $dropped = [];

            foreach ($schema['properties'] as $name => $property) {
                if (is_array($property) && ($property[$flag] ?? false) === true) {
                    unset($schema['properties'][$name]);
                    $dropped[] = $name;
                } elseif (is_array($property)) {
                    $schema['properties'][$name] = self::without($property, $flag, $depth + 1);
                }
            }

            if ($dropped !== [] && is_array($schema['required'] ?? null)) {
                $schema['required'] = array_values(array_diff($schema['required'], $dropped));
            }
        }

        foreach (['items', 'additionalProperties', 'not'] as $key) {
            if (is_array($schema[$key] ?? null)) {
                $schema[$key] = self::without($schema[$key], $flag, $depth + 1);
            }
        }

        foreach (['oneOf', 'anyOf', 'allOf'] as $key) {
            foreach (is_array($schema[$key] ?? null) ? $schema[$key] : [] as $index => $member) {
                if (is_array($member)) {
                    $schema[$key][$index] = self::without($member, $flag, $depth + 1);
                }
            }
        }

        return $schema;
    }
}
