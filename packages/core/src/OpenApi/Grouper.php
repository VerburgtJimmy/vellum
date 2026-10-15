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
    /**
     * @return list<Group>
     */
    public function group(Spec $spec, string $by = 'tag'): array
    {
        $ungrouped = $this->ungroupedLabel();
        $tags = $by === 'path' ? [] : $this->definitions($spec);
        $buckets = [];

        foreach ($spec->operations as $operation) {
            $name = $by === 'path' ? $this->pathSegment($operation, $ungrouped) : $this->tag($operation, $tags, $ungrouped);
            $buckets[$name][] = $operation;
        }

        $parents = $this->parents($tags);
        // A tag called "2024" is an integer key once it is in an array.
        $names = array_values(array_unique(array_map(strval(...), [...array_keys($tags), ...array_keys($buckets)])));
        $order = $this->ordered($names, $parents, $buckets, $ungrouped);
        $slugs = [];
        $groups = [];

        foreach ($order as [$name, $depth]) {
            $tag = $tags[$name] ?? [];
            $description = $tag['description'] ?? null;
            $title = $tag['summary'] ?? $tag['x-displayName'] ?? null;

            $groups[] = new Group(
                name: $name,
                slug: $this->uniqueSlug($name, $slugs),
                operations: $buckets[$name] ?? [],
                description: is_string($description) && trim($description) !== '' ? $description : null,
                title: is_string($title) && trim($title) !== '' ? $title : null,
                parent: $parents[$name] ?? null,
                depth: $depth,
            );
        }

        // Webhooks are requests the API sends, so they are read apart from
        // the ones it answers, after them.
        if ($spec->webhooks !== []) {
            $groups[] = new Group(name: 'Webhooks', slug: $this->uniqueSlug('webhooks', $slugs), operations: $spec->webhooks);
        }

        return $groups;
    }

    /**
     * The spec's tags by name, with a tag for each Redoc tag group that is
     * not one already, since such a group is a parent with nothing of its own.
     *
     * @return array<string, array<string, mixed>>
     */
    private function definitions(Spec $spec): array
    {
        $tags = $spec->tags();
        $groups = $spec->document['x-tagGroups'] ?? [];

        foreach (is_array($groups) ? $groups : [] as $group) {
            if (! is_array($group) || ! is_string($group['name'] ?? null) || ! is_array($group['tags'] ?? null)) {
                continue;
            }

            $tags[$group['name']] ??= ['name' => $group['name']];

            foreach ($group['tags'] as $child) {
                if (is_string($child) && $child !== $group['name']) {
                    $tags[$child] ??= ['name' => $child];
                    $tags[$child]['parent'] ??= $group['name'];
                }
            }
        }

        return $tags;
    }

    /**
     * Each tag's parent, where it names one that exists. A chain that comes
     * back round to itself is cut where it closes, so every tag has a root.
     *
     * @param  array<string, array<string, mixed>>  $tags
     * @return array<string, string>
     */
    private function parents(array $tags): array
    {
        $parents = [];

        foreach ($tags as $name => $tag) {
            $name = (string) $name;
            $parent = $tag['parent'] ?? null;

            if (is_string($parent) && $parent !== $name && isset($tags[$parent])) {
                $parents[$name] = $parent;
            }
        }

        foreach (array_keys($parents) as $name) {
            $seen = [$name => true];

            for ($at = $parents[$name]; $at !== null; $at = $parents[$at] ?? null) {
                if (isset($seen[$at])) {
                    unset($parents[$name]);

                    break;
                }

                $seen[$at] = true;
            }
        }

        return $parents;
    }

    /**
     * Groups in the order they are read: each parent followed by the tags
     * inside it, in the order the spec lists them. A tag with no operations
     * anywhere under it is left out, and ungrouped operations come last.
     *
     * @param  list<string>  $names
     * @param  array<string, string>  $parents
     * @param  array<string, list<Operation>>  $buckets
     * @return list<array{0: string, 1: int}>
     */
    private function ordered(array $names, array $parents, array $buckets, string $ungrouped): array
    {
        $children = [];

        foreach ($names as $name) {
            $children[$parents[$name] ?? ''][] = $name;
        }

        $order = [];

        $walk = function (string $name, int $depth) use (&$walk, &$order, $children, $buckets): bool {
            $at = count($order);
            $order[] = [$name, $depth];
            $kept = isset($buckets[$name]);

            foreach ($children[$name] ?? [] as $child) {
                $kept = $walk($child, $depth + 1) || $kept;
            }

            if (! $kept) {
                array_splice($order, $at);
            }

            return $kept;
        };

        foreach ($children[''] ?? [] as $name) {
            if ($name !== $ungrouped) {
                $walk($name, 0);
            }
        }

        if (isset($buckets[$ungrouped])) {
            $order[] = [$ungrouped, 0];
        }

        return $order;
    }

    private function ungroupedLabel(): string
    {
        $label = config('vellum.openapi.untagged_label', 'Other');

        return is_string($label) && trim($label) !== '' ? $label : 'Other';
    }

    /**
     * The tag an operation is listed under: its first one that is for
     * navigation. A tag of another kind, such as a badge or an audience,
     * says something about the operation, not where it lives.
     *
     * @param  array<string, array<string, mixed>>  $tags
     */
    private function tag(Operation $operation, array $tags, string $ungrouped): string
    {
        foreach ($operation->tags as $tag) {
            $kind = $tags[$tag]['kind'] ?? 'nav';

            if (trim($tag) !== '' && ($kind === 'nav' || ! is_string($kind))) {
                return $tag;
            }
        }

        return $ungrouped;
    }

    private function pathSegment(Operation $operation, string $ungrouped): string
    {
        foreach (explode('/', trim($operation->path, '/')) as $segment) {
            // A path that starts with a parameter has no useful name in it.
            if ($segment !== '' && ! str_starts_with($segment, '{')) {
                return $segment;
            }
        }

        return $ungrouped;
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
