<?php

declare(strict_types=1);

namespace Vellum\Search;

use Illuminate\Support\Facades\Gate;
use Vellum\Content\Access;

/**
 * Page access for search, nav, and routes. Values: guest, auth, or a gate name.
 */
final class SearchVisibility
{
    /**
     * Cache key for the current user's visibility set.
     *
     * @param  list<array<string, mixed>>  $documents
     */
    public function key(array $documents): string
    {
        $allowed = [];

        foreach ($documents as $document) {
            if ($this->allows($document)) {
                $access = $this->access($document);
                $allowed[$access] = true;
            }
        }

        $parts = array_keys($allowed);
        sort($parts);

        return $parts === [] ? 'none' : implode('+', $parts);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function allows(array $document): bool
    {
        return $this->allowsAccess($this->access($document));
    }

    public function allowsAccess(string $access): bool
    {
        $access = Access::normalize($access);

        if ($access === 'guest') {
            return true;
        }

        if ($access === 'auth') {
            return auth()->check();
        }

        return Gate::allows($access);
    }

    /**
     * Drop pages (and empty folders) the current user cannot see.
     *
     * @param  list<array<string, mixed>>  $tree
     * @return list<array<string, mixed>>
     */
    public function filterNavigation(array $tree): array
    {
        $filtered = [];

        foreach ($tree as $node) {
            $type = $node['type'] ?? null;

            if ($type === 'page') {
                if ($this->allows($node)) {
                    $filtered[] = $node;
                }

                continue;
            }

            if ($type === 'folder' && isset($node['children']) && is_array($node['children'])) {
                /** @var list<array<string, mixed>> $children */
                $children = array_values($node['children']);
                $node['children'] = $this->filterNavigation($children);

                if ($this->containsPage($node['children'])) {
                    $filtered[] = $node;
                }

                continue;
            }

            $filtered[] = $node;
        }

        return $this->trimSeparators($filtered);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function access(array $document): string
    {
        return Access::normalize($document['access'] ?? 'guest');
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     */
    private function containsPage(array $tree): bool
    {
        foreach ($tree as $node) {
            $type = $node['type'] ?? null;

            if ($type === 'page') {
                return true;
            }

            if ($type === 'folder' && isset($node['children']) && is_array($node['children'])) {
                /** @var list<array<string, mixed>> $children */
                $children = array_values($node['children']);

                if ($this->containsPage($children)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     * @return list<array<string, mixed>>
     */
    private function trimSeparators(array $tree): array
    {
        $trimmed = [];

        foreach ($tree as $node) {
            if (($node['type'] ?? null) === 'separator') {
                if ($trimmed === [] || ($trimmed[array_key_last($trimmed)]['type'] ?? null) === 'separator') {
                    continue;
                }

                $trimmed[] = $node;

                continue;
            }

            $trimmed[] = $node;
        }

        $last = array_key_last($trimmed);

        if ($last !== null && ($trimmed[$last]['type'] ?? null) === 'separator') {
            array_pop($trimmed);
        }

        return $trimmed;
    }
}
