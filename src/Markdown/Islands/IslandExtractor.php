<?php

declare(strict_types=1);

namespace Vellum\Markdown\Islands;

use Vellum\Exceptions\UnknownComponentException;

/**
 * Pulls balanced &lt;x-…&gt; tags out of Markdown, leaving placeholders. Skips protected code tokens.
 *
 * @phpstan-type Extracted array{markdown: string, islands: list<RawIsland>}
 */
final class IslandExtractor
{
    private const OPEN = '/<x-([a-zA-Z][\w.-]*(?:::[a-zA-Z][\w.-]*)?)(\s[^>]*?)?(\s*\/\s*)?>/';

    /**
     * @return Extracted
     */
    public static function extract(string $markdown): array
    {
        $nextId = 0;

        return self::extractLevel($markdown, $nextId);
    }

    /**
     * @return Extracted
     */
    private static function extractLevel(string $markdown, int &$nextId): array
    {
        $islands = [];
        $out = '';
        $offset = 0;
        $length = strlen($markdown);

        while ($offset < $length && preg_match(self::OPEN, $markdown, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = $match[0][1];
            $out .= substr($markdown, $offset, $start - $offset);

            $name = $match[1][0];
            $attrSource = $match[2][0] ?? '';
            $selfClosing = ($match[3][0] ?? '') !== '';
            $openEnd = $start + strlen($match[0][0]);

            $attributes = self::parseAttributes($name, $attrSource);

            if ($selfClosing) {
                $id = (string) $nextId++;
                $islands[] = new RawIsland($id, $name, $attributes, '', [], true);
                $out .= Island::token($id);
                $offset = $openEnd;

                continue;
            }

            $close = self::findClose($markdown, $name, $openEnd);

            if ($close === null) {
                throw UnknownComponentException::unclosed($name);
            }

            $inner = substr($markdown, $openEnd, $close['start'] - $openEnd);
            $nested = self::extractLevel($inner, $nextId);
            $id = (string) $nextId++;
            $islands[] = new RawIsland($id, $name, $attributes, $nested['markdown'], $nested['islands'], false);
            $out .= Island::token($id);
            $offset = $close['end'];
        }

        $out .= substr($markdown, $offset);

        return [
            'markdown' => $out,
            'islands' => $islands,
        ];
    }

    /**
     * @return array{start: int, end: int}|null
     */
    private static function findClose(string $markdown, string $name, int $from): ?array
    {
        $quoted = preg_quote($name, '/');
        $pattern = '/<x-'.$quoted.'(?:\s[^>]*)?\s*\/\s*>|<\/x-'.$quoted.'\s*>|<x-'.$quoted.'(?:\s[^>]*)?>/';
        $depth = 1;
        $offset = $from;
        $length = strlen($markdown);

        while ($offset < $length && preg_match($pattern, $markdown, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $token = $match[0][0];
            $start = $match[0][1];
            $end = $start + strlen($token);

            if (str_starts_with($token, '</')) {
                $depth--;

                if ($depth === 0) {
                    return ['start' => $start, 'end' => $end];
                }
            } elseif (! str_ends_with(rtrim($token, '>'), '/')) {
                $depth++;
            }

            $offset = $end;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function parseAttributes(string $name, string $source): array
    {
        $source = trim($source);

        if ($source === '') {
            return [];
        }

        if (preg_match('/(?:^|\s):[A-Za-z]/', $source) === 1) {
            throw UnknownComponentException::invalidAttributes($name);
        }

        preg_match_all(
            '/([a-zA-Z_][\w:-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/',
            $source,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        $attributes = [];
        $cursor = 0;

        foreach ($matches as $match) {
            $start = $match[0][1];

            if (trim(substr($source, $cursor, $start - $cursor)) !== '') {
                throw UnknownComponentException::invalidAttributes($name);
            }

            $attributes[$match[1][0]] = ($match[2][1] ?? -1) >= 0
                ? $match[2][0]
                : ($match[3][0] ?? '');
            $cursor = $start + strlen($match[0][0]);
        }

        if (trim(substr($source, $cursor)) !== '') {
            throw UnknownComponentException::invalidAttributes($name);
        }

        return $attributes;
    }
}
