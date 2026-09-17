<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

/**
 * Display facts about a schema node, worked out once at build time.
 *
 * All of this could be done in the template, but none of it should be: the
 * rules for what "nullable" means differ between 3.0 and 3.1, and a Blade
 * file is a bad place to keep that straight. Nothing here runs in the browser.
 *
 * @phpstan-type Property array{
 *     name: string,
 *     schema: array<string, mixed>,
 *     required: bool,
 *     description: string|null,
 *     type: string,
 *     expandable: bool
 * }
 */
final class SchemaView
{
    /**
     * Properties of an object schema, required ones first.
     *
     * @param  array<string, mixed>  $schema
     * @return list<Property>
     */
    public static function properties(array $schema): array
    {
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];
        $out = [];

        foreach ($properties as $name => $property) {
            if (! is_string($name) || ! is_array($property)) {
                continue;
            }

            $out[] = [
                'name' => $name,
                'schema' => $property,
                'required' => in_array($name, $required, true),
                'description' => is_string($property['description'] ?? null) ? $property['description'] : null,
                'type' => self::type($property),
                'expandable' => self::expandable($property),
            ];
        }

        // Required fields first, then the order the spec gave. A reader
        // scanning a long object is almost always looking for those.
        usort($out, static fn (array $a, array $b): int => $b['required'] <=> $a['required']);

        return $out;
    }

    /**
     * A human type: "string", "array of Pet", "object (Address)", "integer · int64".
     *
     * @param  array<string, mixed>  $schema
     */
    public static function type(array $schema): string
    {
        if (isset($schema[RefResolver::RECURSIVE])) {
            return self::name($schema) ?? 'object';
        }

        $variants = self::variants($schema);

        if ($variants !== [] && self::types($schema) === []) {
            return implode(' or ', array_column($variants, 'label'));
        }

        $types = self::types($schema);
        $nullable = in_array('null', $types, true) || ($schema['nullable'] ?? false) === true;
        $types = array_values(array_diff($types, ['null']));
        $base = $types === [] ? self::implied($schema) : implode(' or ', $types);

        if ($base === 'array') {
            $items = is_array($schema['items'] ?? null) ? $schema['items'] : [];
            $base = 'array of '.($items === [] ? 'any' : self::type($items));
        } elseif ($base === 'object' && ($name = self::name($schema)) !== null) {
            $base = $name;
        }

        if (is_string($schema['format'] ?? null) && $schema['format'] !== '') {
            $base .= ' · '.$schema['format'];
        }

        if (isset($schema['enum']) && is_array($schema['enum'])) {
            $base .= ' · enum';
        }

        return $nullable ? $base.' | null' : $base;
    }

    /**
     * A spec value as a reader should see it.
     *
     * Booleans and null have to go through json_encode: casting false to a
     * string gives "", which renders as "Default" followed by nothing.
     */
    public static function literal(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return (string) json_encode($value);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    public static function enumValues(array $schema): array
    {
        $enum = $schema['enum'] ?? null;

        if (! is_array($enum)) {
            return [];
        }

        return array_values(array_map(
            self::literal(...),
            $enum,
        ));
    }

    /**
     * oneOf / anyOf members, as a tab strip of alternatives.
     *
     * @param  array<string, mixed>  $schema
     * @return list<array{label: string, schema: array<string, mixed>}>
     */
    public static function variants(array $schema): array
    {
        foreach (['oneOf', 'anyOf'] as $key) {
            if (! is_array($schema[$key] ?? null)) {
                continue;
            }

            $variants = [];

            foreach (array_values($schema[$key]) as $index => $member) {
                if (! is_array($member)) {
                    continue;
                }

                $variants[] = [
                    'label' => self::name($member) ?? (self::type($member) ?: 'Option '.($index + 1)),
                    'schema' => $member,
                ];
            }

            if ($variants !== []) {
                return $variants;
            }
        }

        return [];
    }

    /**
     * Whether this node has anything to show when opened.
     *
     * @param  array<string, mixed>  $schema
     */
    public static function expandable(array $schema): bool
    {
        if (self::isRecursive($schema)) {
            return false;
        }

        if (self::properties($schema) !== [] || self::variants($schema) !== []) {
            return true;
        }

        $items = is_array($schema['items'] ?? null) ? $schema['items'] : null;

        return $items !== null && (self::properties($items) !== [] || self::variants($items) !== []);
    }

    /**
     * The node whose properties should be listed: an array shows its items.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function body(array $schema): array
    {
        if (self::properties($schema) === [] && is_array($schema['items'] ?? null)) {
            return $schema['items'];
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public static function isRecursive(array $schema): bool
    {
        if (isset($schema[RefResolver::RECURSIVE])) {
            return true;
        }

        // "array of Pet" where Pet is the enclosing schema repeats just as
        // much as a bare Pet does, and the reader needs telling either way.
        $items = $schema['items'] ?? null;

        return is_array($items) && isset($items[RefResolver::RECURSIVE]);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public static function name(array $schema): ?string
    {
        $name = $schema[RefResolver::NAME] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    private static function types(array $schema): array
    {
        $type = $schema['type'] ?? null;

        if (is_string($type)) {
            return [$type];
        }

        if (is_array($type)) {
            return array_values(array_filter($type, is_string(...)));
        }

        return [];
    }

    /**
     * A schema with no type but with properties is an object, and saying
     * "any" there would be wrong as well as unhelpful.
     *
     * @param  array<string, mixed>  $schema
     */
    private static function implied(array $schema): string
    {
        if (is_array($schema['properties'] ?? null)) {
            return 'object';
        }

        return is_array($schema['items'] ?? null) ? 'array' : 'any';
    }
}
