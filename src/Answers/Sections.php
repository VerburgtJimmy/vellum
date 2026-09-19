<?php

declare(strict_types=1);

namespace Vellum\Answers;

use Vellum\Content\Document;
use Vellum\Markdown\Islands\IslandRenderer;

/**
 * Splits compiled pages at h2 and h3 into search sections. The text before the
 * first heading, plus the description, is the page's own section (anchor '').
 * An h3 section records its h2 as parent.
 */
final class Sections
{
    /**
     * @param  list<Document>  $documents
     * @return list<array{page: string, version: string|null, access: string, anchor: string, parent: string, title: string, heading: string, own: string, text: string, html: string, questions: list<string>}>
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
                        'version' => $document->version,
                        'access' => $document->access(),
                        'anchor' => '',
                        'parent' => '',
                        'title' => $document->title,
                        'heading' => '',
                        'own' => '',
                        'text' => trim(($document->description ?? '').' '.self::text($part)),
                        'html' => $part,
                        'questions' => array_values(array_filter((array) ($document->frontmatter['questions'] ?? []), 'is_string')),
                    ];

                    continue;
                }

                if (preg_match('/^<h([23])\b[^>]*\bid="([^"]+)"[^>]*>(.*?)<\/h\1>/s', $part, $match) !== 1) {
                    // Heading markup this does not recognise: keep its text with the section above.
                    $previous = array_pop($sections);

                    if ($previous !== null) {
                        $previous['text'] = trim($previous['text'].' '.self::text($part));
                        $sections[] = $previous;
                    }

                    continue;
                }

                $level = (int) $match[1];
                $anchor = $match[2];
                $text = $headings[$anchor]['text'] ?? self::text($match[3]);

                if ($level === 2) {
                    $parent = $anchor;
                    $h2 = $text;
                }

                $sections[] = [
                    'page' => $document->slug,
                    'version' => $document->version,
                    'access' => $document->access(),
                    'anchor' => $anchor,
                    'parent' => $level === 2 ? $anchor : $parent,
                    'title' => $document->title,
                    'heading' => $level === 2 ? $text : trim($h2.' '.$text),
                    'own' => $text,
                    'text' => self::text(substr($part, strlen($match[0]))),
                    'html' => substr($part, strlen($match[0])),
                    'questions' => [],
                ];
            }
        }

        return $sections;
    }

    public static function text(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
