<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\CodeBlock;

/**
 * Parses fenced code info strings for language, title, line highlights, and gutter.
 */
final class CodeBlockInfo
{
    /**
     * @param  list<int>  $highlightLines
     */
    public function __construct(
        public readonly string $language,
        public readonly ?string $title,
        public readonly array $highlightLines,
        public readonly bool $showLineNumbers,
    ) {}

    public static function parse(?string $info): self
    {
        $info ??= '';
        $language = 'txt';
        $title = null;
        $highlightLines = [];
        $showLineNumbers = false;

        if (preg_match('/^([\w+-]+)/', trim($info), $match) === 1) {
            $language = strtolower($match[1]);
        }

        if (preg_match('/\btitle="([^"]*)"/', $info, $match) === 1) {
            $title = $match[1];
        } elseif (preg_match("/\btitle='([^']*)'/", $info, $match) === 1) {
            $title = $match[1];
        }

        if (preg_match('/\{([0-9,\-\s]+)\}/', $info, $match) === 1) {
            $highlightLines = self::parseRanges($match[1]);
        }

        if (preg_match('/\bshowLineNumbers\b/', $info) === 1) {
            $showLineNumbers = true;
        }

        return new self($language, $title, $highlightLines, $showLineNumbers);
    }

    /**
     * @return list<int>
     */
    private static function parseRanges(string $ranges): array
    {
        $lines = [];

        foreach (explode(',', $ranges) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (preg_match('/^(\d+)-(\d+)$/', $part, $match) === 1) {
                $start = (int) $match[1];
                $end = (int) $match[2];

                if ($end < $start) {
                    [$start, $end] = [$end, $start];
                }

                for ($i = $start; $i <= $end; $i++) {
                    $lines[] = $i;
                }

                continue;
            }

            if (preg_match('/^\d+$/', $part) === 1) {
                $lines[] = (int) $part;
            }
        }

        return array_values(array_unique($lines));
    }
}
