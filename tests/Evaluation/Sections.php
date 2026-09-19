<?php

declare(strict_types=1);

namespace Vellum\Tests\Evaluation;

use Vellum\Content\Document;
use Vellum\Markdown\Islands\IslandRenderer;

/**
 * Splits compiled pages at h2 and h3 into search sections. The text before the
 * first heading, plus the description, is the page's own section (anchor '').
 */
final class Sections
{
    /**
     * @param  list<Document>  $documents
     * @return list<array{page: string, anchor: string, parent: string, title: string, heading: string, text: string}>
     */
    public static function from(array $documents): array
    {
        $sections = [];

        foreach ($documents as $document) {
            $html = (new IslandRenderer)->inlineSlots($document->html, $document->islands);
            $html = (string) preg_replace('/<button\b.*?<\/button>/s', '', $html);
            $parts = preg_split('/(?=<h[23]\b[^>]*\bid=")/', $html) ?: [];
            $headings = [];

            foreach ($document->headings as $heading) {
                $headings[$heading['id']] = $heading;
            }

            $parent = '';
            $h2 = '';

            foreach ($parts as $index => $part) {
                if ($index === 0) {
                    $sections[] = [
                        'page' => $document->slug,
                        'anchor' => '',
                        'parent' => '',
                        'title' => $document->title,
                        'heading' => '',
                        'text' => trim(($document->description ?? '').' '.self::text($part)),
                    ];

                    continue;
                }

                preg_match('/^<h([23])\b[^>]*\bid="([^"]+)"[^>]*>(.*?)<\/h\1>/s', $part, $match);
                $level = (int) $match[1];
                $anchor = $match[2];
                $text = $headings[$anchor]['text'] ?? self::text($match[3]);

                if ($level === 2) {
                    $parent = $anchor;
                    $h2 = $text;
                }

                $sections[] = [
                    'page' => $document->slug,
                    'anchor' => $anchor,
                    'parent' => $level === 2 ? $anchor : $parent,
                    'title' => $document->title,
                    'heading' => $level === 2 ? $text : trim($h2.' '.$text),
                    'text' => self::text(substr($part, strlen($match[0]))),
                ];
            }
        }

        return $sections;
    }

    private static function text(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
