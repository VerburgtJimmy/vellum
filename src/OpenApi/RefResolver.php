<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Vellum\Exceptions\InvalidSpecException;

/**
 * Inlines local $ref pointers and merges allOf, so nothing downstream has to
 * know the document still had indirection in it.
 *
 * Two things are deliberately not errors. A remote $ref degrades to an empty
 * object with a warning, because a spec that points at another file is still
 * worth rendering. A $ref that reappears inside its own resolution stops with
 * a marker rather than recursing forever: a tree of comments each containing
 * comments is a legitimate schema, and the renderer shows the repeat instead
 * of trying to draw it.
 */
final class RefResolver
{
    public const RECURSIVE = 'x-vellum-recursive';

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(private readonly string $path) {}

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function resolve(array $document): array
    {
        $this->warnings = [];

        /** @var array<string, mixed> $resolved */
        $resolved = $this->walk($document, $document, [], '');

        return $resolved;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return array_values(array_unique($this->warnings));
    }

    /**
     * @param  array<string, mixed>  $root
     * @param  list<string>  $stack
     */
    private function walk(mixed $node, array $root, array $stack, string $pointer): mixed
    {
        if (! is_array($node)) {
            return $node;
        }

        if (isset($node['$ref']) && is_string($node['$ref'])) {
            return $this->expand($node, $root, $stack, $pointer);
        }

        $out = [];

        foreach ($node as $key => $value) {
            $out[$key] = $this->walk($value, $root, $stack, $pointer === '' ? (string) $key : $pointer.'.'.$key);
        }

        return isset($out['allOf']) ? $this->mergeAllOf($out, $pointer) : $out;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $root
     * @param  list<string>  $stack
     * @return array<string, mixed>
     */
    private function expand(array $node, array $root, array $stack, string $pointer): array
    {
        /** @var string $ref */
        $ref = $node['$ref'];

        // 3.1 allows keys beside a $ref, and they win over the target.
        $siblings = $node;
        unset($siblings['$ref']);

        if (! str_starts_with($ref, '#/')) {
            $this->warnings[] = "Remote \$ref [{$ref}] is not supported and was rendered as an empty object.";

            return $siblings + ['type' => 'object'];
        }

        if (in_array($ref, $stack, true)) {
            return $siblings + [self::RECURSIVE => $ref, 'type' => 'object'];
        }

        $target = $this->pointer($ref, $root);

        if (! is_array($target)) {
            throw InvalidSpecException::unresolvableRef($this->path, $ref);
        }

        /** @var array<string, mixed> $resolved */
        $resolved = $this->walk($target, $root, [...$stack, $ref], $pointer);

        /** @var array<string, mixed> $walkedSiblings */
        $walkedSiblings = $this->walk($siblings, $root, $stack, $pointer);

        return $walkedSiblings + $resolved;
    }

    /**
     * @param  array<string, mixed>  $root
     */
    private function pointer(string $ref, array $root): mixed
    {
        $node = $root;

        foreach (explode('/', substr($ref, 2)) as $segment) {
            // RFC 6901 escaping: ~1 is "/", ~0 is "~", and in that order.
            $segment = str_replace(['~1', '~0'], ['/', '~'], rawurldecode($segment));

            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return null;
            }

            $node = $node[$segment];
        }

        return $node;
    }

    /**
     * Fold allOf members into the schema that declared them.
     *
     * The rules, in the absence of anything decisive in the specification:
     * properties accumulate, required is a union, and description and example
     * take the last value given, so the most specific wrapper has the final
     * word. Everything else keeps the first value, since the declaring schema
     * is the one the author was looking at. A genuine type disagreement is not
     * resolvable and warns rather than picking a winner quietly.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function mergeAllOf(array $schema, string $pointer): array
    {
        $members = $schema['allOf'];
        unset($schema['allOf']);

        if (! is_array($members)) {
            return $schema;
        }

        // The declaring schema is simply the first source to be merged.
        $sources = [$schema, ...array_values(array_filter($members, is_array(...)))];

        $properties = [];
        $required = [];
        $types = [];

        foreach ($sources as $source) {
            if (is_array($source['properties'] ?? null)) {
                // Accumulate, and let the earliest definition of a name stand.
                $properties += $source['properties'];
            }

            if (is_array($source['required'] ?? null)) {
                $required = [...$required, ...array_values($source['required'])];
            }

            if (isset($source['type'])) {
                $types[] = $source['type'];
            }
        }

        $merged = [];

        foreach ($sources as $source) {
            unset($source['properties'], $source['required']);

            foreach (['description', 'example'] as $lastWins) {
                if (array_key_exists($lastWins, $source)) {
                    $merged[$lastWins] = $source[$lastWins];
                    unset($source[$lastWins]);
                }
            }

            // Everything else keeps the first value seen.
            $merged += $source;
        }

        $this->warnConflictingTypes($types, $pointer);

        if ($properties !== []) {
            $merged['properties'] = $properties;
        }

        if ($required !== []) {
            $merged['required'] = array_values(array_unique($required));
        }

        return $merged;
    }

    /**
     * @param  list<mixed>  $types
     */
    private function warnConflictingTypes(array $types, string $pointer): void
    {
        $seen = [];

        foreach ($types as $type) {
            // 3.1 allows a list of types; order within it is not meaningful.
            $normalised = is_array($type)
                ? implode('|', array_map(strval(...), $this->sorted($type)))
                : (string) $type;

            $seen[$normalised] = true;
        }

        if (count($seen) < 2) {
            return;
        }

        $names = implode(', ', array_map(static fn (string $t): string => "[{$t}]", array_keys($seen)));
        $where = $pointer === '' ? 'the root schema' : "[{$pointer}]";
        $kept = array_key_first($seen);

        $this->warnings[] = "allOf at {$where} merges conflicting types: {$names}. Kept [{$kept}].";
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<mixed>
     */
    private function sorted(array $values): array
    {
        $values = array_values($values);
        sort($values);

        return $values;
    }
}
