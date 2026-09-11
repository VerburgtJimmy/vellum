<?php

declare(strict_types=1);

namespace Vellum\Content;

use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\NodeIterator;
use League\CommonMark\Node\StringContainerHelper;

/**
 * Reads heading outline data from the CommonMark AST after IDs are applied.
 *
 * @phpstan-type HeadingData array{id: string, text: string, level: int}
 */
final class HeadingExtractor
{
    public const MIN_LEVEL = 2;

    public const MAX_LEVEL = 4;

    /**
     * Collect h2-h4 headings that already have an id from HeadingAnchorExtension.
     *
     * @return list<HeadingData>
     */
    public function extractFromDocument(Document $document): array
    {
        $headings = [];

        foreach ($document->iterator(NodeIterator::FLAG_BLOCKS_ONLY) as $node) {
            if (! $node instanceof Heading) {
                continue;
            }

            $level = $node->getLevel();

            if ($level < self::MIN_LEVEL || $level > self::MAX_LEVEL) {
                continue;
            }

            $id = $node->data->get('attributes/id');

            if (! is_string($id) || $id === '') {
                continue;
            }

            $text = $this->visibleText($node);

            if ($text === '') {
                continue;
            }

            $headings[] = [
                'id' => $id,
                'text' => $text,
                'level' => $level,
            ];
        }

        return $headings;
    }

    /**
     * Nest a flat heading list into a tree for TOC rendering.
     *
     * @param  list<HeadingData>  $headings
     * @return list<array{id: string, text: string, level: int, children: list<mixed>}>
     */
    public function nest(array $headings): array
    {
        if ($headings === []) {
            return [];
        }

        /** @var list<array{id: string, text: string, level: int}> $nodes */
        $nodes = [];
        /** @var list<list<int>> $childIndexes */
        $childIndexes = [];

        foreach ($headings as $heading) {
            $nodes[] = [
                'id' => $heading['id'],
                'text' => $heading['text'],
                'level' => $heading['level'],
            ];
            $childIndexes[] = [];
        }

        /** @var list<int> $roots */
        $roots = [];
        /** @var array<int, int> $lastAtLevel */
        $lastAtLevel = [];

        foreach ($nodes as $index => $node) {
            $parent = null;

            for ($level = $node['level'] - 1; $level >= self::MIN_LEVEL; $level--) {
                if (isset($lastAtLevel[$level])) {
                    $parent = $lastAtLevel[$level];
                    break;
                }
            }

            if ($parent === null) {
                $roots[] = $index;
            } else {
                $childIndexes[$parent][] = $index;
            }

            foreach (array_keys($lastAtLevel) as $level) {
                if ($level >= $node['level']) {
                    unset($lastAtLevel[$level]);
                }
            }

            $lastAtLevel[$node['level']] = $index;
        }

        $build = function (int $index) use (&$build, $nodes, $childIndexes): array {
            $children = [];

            foreach ($childIndexes[$index] as $childIndex) {
                $children[] = $build($childIndex);
            }

            return [
                'id' => $nodes[$index]['id'],
                'text' => $nodes[$index]['text'],
                'level' => $nodes[$index]['level'],
                'children' => $children,
            ];
        };

        $tree = [];

        foreach ($roots as $rootIndex) {
            $tree[] = $build($rootIndex);
        }

        return $tree;
    }

    private function visibleText(Heading $heading): string
    {
        $text = StringContainerHelper::getChildText($heading, [HeadingPermalink::class]);

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }
}
