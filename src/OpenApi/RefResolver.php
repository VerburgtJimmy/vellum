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
        $resolved = $this->walk($document, $document, []);

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
    private function walk(mixed $node, array $root, array $stack): mixed
    {
        if (! is_array($node)) {
            return $node;
        }

        if (isset($node['$ref']) && is_string($node['$ref'])) {
            return $this->expand($node, $root, $stack);
        }

        $out = [];

        foreach ($node as $key => $value) {
            $out[$key] = $this->walk($value, $root, $stack);
        }

        return isset($out['allOf']) ? $this->mergeAllOf($out) : $out;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $root
     * @param  list<string>  $stack
     * @return array<string, mixed>
     */
    private function expand(array $node, array $root, array $stack): array
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
        $resolved = $this->walk($target, $root, [...$stack, $ref]);

        /** @var array<string, mixed> $walkedSiblings */
        $walkedSiblings = $this->walk($siblings, $root, $stack);

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
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function mergeAllOf(array $schema): array
    {
        $members = $schema['allOf'];
        unset($schema['allOf']);

        if (! is_array($members)) {
            return $schema;
        }

        $merged = $schema;

        foreach ($members as $member) {
            if (! is_array($member)) {
                continue;
            }

            $memberProperties = is_array($member['properties'] ?? null) ? $member['properties'] : [];
            $mergedProperties = is_array($merged['properties'] ?? null) ? $merged['properties'] : [];
            $memberRequired = is_array($member['required'] ?? null) ? $member['required'] : [];
            $mergedRequired = is_array($merged['required'] ?? null) ? $merged['required'] : [];

            unset($member['properties'], $member['required']);

            // The declaring schema wins on scalars; properties and required accumulate.
            $merged = $merged + $member;

            if ($memberProperties !== [] || $mergedProperties !== []) {
                $merged['properties'] = $mergedProperties + $memberProperties;
            }

            if ($memberRequired !== [] || $mergedRequired !== []) {
                $merged['required'] = array_values(array_unique([...$mergedRequired, ...$memberRequired]));
            }
        }

        return $merged;
    }
}
