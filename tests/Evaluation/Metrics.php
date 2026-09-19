<?php

declare(strict_types=1);

namespace Vellum\Tests\Evaluation;

/**
 * Recall for golden question sets. A section hits when it is the target, sits
 * under the target heading, or belongs to a page-level target's page.
 */
final class Metrics
{
    /**
     * @param  array{page: string, section?: string, also?: list<array{page: string, section?: string}>}  $entry
     * @param  array{page: string, anchor: string, parent: string}  $section
     */
    public static function hits(array $entry, array $section): bool
    {
        foreach ([$entry, ...($entry['also'] ?? [])] as $target) {
            if ($section['page'] !== $target['page']) {
                continue;
            }

            $anchor = $target['section'] ?? null;

            if ($anchor === null || $section['anchor'] === $anchor || $section['parent'] === $anchor) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, list<int>>  $ranking  question index => section ids, best first
     * @param  list<array{kind: string}>  $golden
     * @param  list<array{page: string, anchor: string, parent: string}>  $sections
     * @return array<string, array{1: float, 5: float, n: int}>
     */
    public static function recall(array $ranking, array $golden, array $sections): array
    {
        $counts = [];

        foreach ($golden as $index => $entry) {
            $top = array_slice($ranking[$index] ?? [], 0, 5);
            $first = isset($top[0]) && self::hits($entry, $sections[$top[0]]);
            $five = array_filter($top, static fn (int $id): bool => self::hits($entry, $sections[$id])) !== [];

            foreach (['all', $entry['kind']] as $bucket) {
                $counts[$bucket] ??= [0, 0, 0];
                $counts[$bucket][0]++;
                $counts[$bucket][1] += (int) $first;
                $counts[$bucket][2] += (int) $five;
            }
        }

        return array_map(static fn (array $c): array => [1 => $c[1] / $c[0], 5 => $c[2] / $c[0], 'n' => $c[0]], $counts);
    }
}
