<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Vellum\Support\Slug;

/**
 * Sorts a spec's operations into the pages they will be written to.
 *
 * Group order follows the spec's own tags array where there is one, because
 * that is the order the API's author chose. Anything not listed there keeps
 * the order it appears in the document, which at least matches reading it.
 */
final class Grouper
{
    private const UNGROUPED = 'Other';

    /**
     * @return list<Group>
     */
    public function group(Spec $spec, string $by = 'tag'): array
    {
        $buckets = [];

        foreach ($spec->operations as $operation) {
            $name = $by === 'path' ? $this->pathSegment($operation) : $this->tag($operation);
            $buckets[$name][] = $operation;
        }

        $buckets = $this->ordered($buckets, $by === 'path' ? [] : array_keys($spec->tags()));
        $tags = $spec->tags();
        $slugs = [];
        $groups = [];

        foreach ($buckets as $name => $operations) {
            $description = $tags[$name]['description'] ?? null;

            $groups[] = new Group(
                name: (string) $name,
                slug: $this->uniqueSlug((string) $name, $slugs),
                operations: $operations,
                description: is_string($description) && trim($description) !== '' ? $description : null,
            );
        }

        return $groups;
    }

    private function tag(Operation $operation): string
    {
        $tag = $operation->tags[0] ?? null;

        return is_string($tag) && trim($tag) !== '' ? $tag : self::UNGROUPED;
    }

    private function pathSegment(Operation $operation): string
    {
        foreach (explode('/', trim($operation->path, '/')) as $segment) {
            // A path that starts with a parameter has no useful name in it.
            if ($segment !== '' && ! str_starts_with($segment, '{')) {
                return $segment;
            }
        }

        return self::UNGROUPED;
    }

    /**
     * @param  array<string, list<Operation>>  $buckets
     * @param  list<string>  $preferred
     * @return array<string, list<Operation>>
     */
    private function ordered(array $buckets, array $preferred): array
    {
        $ordered = [];

        foreach ($preferred as $name) {
            if (isset($buckets[$name])) {
                $ordered[$name] = $buckets[$name];
                unset($buckets[$name]);
            }
        }

        // Ungrouped operations go last wherever they came from.
        $ungrouped = $buckets[self::UNGROUPED] ?? null;
        unset($buckets[self::UNGROUPED]);

        $ordered += $buckets;

        if ($ungrouped !== null) {
            $ordered[self::UNGROUPED] = $ungrouped;
        }

        return $ordered;
    }

    /**
     * @param  array<string, true>  $taken
     */
    private function uniqueSlug(string $name, array &$taken): string
    {
        $base = Slug::from($name);
        $base = $base === '' ? 'group' : $base;
        $slug = $base;
        $suffix = 2;

        while (isset($taken[$slug])) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        $taken[$slug] = true;

        return $slug;
    }
}
