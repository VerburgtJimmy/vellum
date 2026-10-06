<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

/**
 * An example value for a schema, preferring what the spec says over anything
 * invented. A spec's own example is the one that has been looked at by a
 * human; a generated one only has to be shaped right.
 */
final class ExampleGenerator
{
    private const MAX_DEPTH = 6;

    /**
     * @param  array<string, mixed>  $media  A media type object.
     */
    public static function forMedia(array $media): mixed
    {
        if (array_key_exists('example', $media)) {
            return $media['example'];
        }

        if (is_array($media['examples'] ?? null)) {
            foreach ($media['examples'] as $example) {
                if (is_array($example) && array_key_exists('value', $example)) {
                    return $example['value'];
                }
            }
        }

        return self::fromSchema(is_array($media['schema'] ?? null) ? $media['schema'] : []);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public static function fromSchema(array $schema, int $depth = 0): mixed
    {
        if ($depth > self::MAX_DEPTH || SchemaView::isRecursive($schema)) {
            return null;
        }

        if (array_key_exists('example', $schema)) {
            return $schema['example'];
        }

        if (array_key_exists('default', $schema)) {
            return $schema['default'];
        }

        $enum = SchemaView::enumValues($schema);

        if ($enum !== []) {
            return $enum[0];
        }

        $variants = SchemaView::variants($schema);

        if ($variants !== []) {
            return self::fromSchema($variants[0]['schema'], $depth + 1);
        }

        return self::byType($schema, $depth);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private static function byType(array $schema, int $depth): mixed
    {
        $type = $schema['type'] ?? null;
        $types = is_array($type) ? array_values(array_filter($type, is_string(...))) : [is_string($type) ? $type : null];
        $primary = null;

        foreach ($types as $candidate) {
            if ($candidate !== null && $candidate !== 'null') {
                $primary = $candidate;
                break;
            }
        }

        $primary ??= is_array($schema['properties'] ?? null) ? 'object' : ($schema['items'] ?? null ? 'array' : 'string');

        return match ($primary) {
            'integer' => 0,
            'number' => 0,
            'boolean' => true,
            'array' => self::arrayExample($schema, $depth),
            'object' => self::objectExample($schema, $depth),
            'null' => null,
            default => self::stringExample($schema),
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<mixed>
     */
    private static function arrayExample(array $schema, int $depth): array
    {
        $items = is_array($schema['items'] ?? null) ? $schema['items'] : [];

        if ($items === [] || SchemaView::isRecursive($items)) {
            return [];
        }

        return [self::fromSchema($items, $depth + 1)];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function objectExample(array $schema, int $depth): array
    {
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $out = [];

        foreach ($properties as $name => $property) {
            if (! is_string($name) || ! is_array($property)) {
                continue;
            }

            if (SchemaView::isRecursive($property)) {
                continue;
            }

            $out[$name] = self::fromSchema($property, $depth + 1);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private static function stringExample(array $schema): string
    {
        $format = is_string($schema['format'] ?? null) ? $schema['format'] : '';

        return match ($format) {
            'uuid' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
            'date' => '2026-01-31',
            'date-time' => '2026-01-31T09:00:00Z',
            'email' => 'user@example.com',
            'uri', 'url' => 'https://example.com',
            'password' => '••••••••',
            default => 'string',
        };
    }
}
