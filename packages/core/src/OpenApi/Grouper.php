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
        $buckets = [];

        foreach ($spec->operations as $operation) {
            $name = $by === 'path' ? $this->pathSegment($operation, $ungrouped) : $this->tag($operation, $ungrouped);
            $buckets[$name][] = $operation;
        }

        $buckets = $this->ordered($buckets, $by === 'path' ? [] : array_keys($spec->tags()), $ungrouped);
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

    private function ungroupedLabel(): string
    {
        $label = config('vellum.openapi.untagged_label', 'Other');

        return is_string($label) && trim($label) !== '' ? $label : 'Other';
    }

    private function tag(Operation $operation, string $ungrouped): string
    {
        $tag = $operation->tags[0] ?? null;

        return is_string($tag) && trim($tag) !== '' ? $tag : $ungrouped;
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
     * @param  array<string, list<Operation>>  $buckets
     * @param  list<string>  $preferred
     * @return array<string, list<Operation>>
     */
    private function ordered(array $buckets, array $preferred, string $ungrouped): array
    {
        $ordered = [];

        foreach ($preferred as $name) {
            if (isset($buckets[$name])) {
                $ordered[$name] = $buckets[$name];
                unset($buckets[$name]);
            }
        }

        // Ungrouped operations go last wherever they came from.
        $trailing = $buckets[$ungrouped] ?? null;
        unset($buckets[$ungrouped]);

        $ordered += $buckets;

        if ($trailing !== null) {
            $ordered[$ungrouped] = $trailing;
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
