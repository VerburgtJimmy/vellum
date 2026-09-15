<?php

declare(strict_types=1);

namespace Vellum\Changelog;

use Vellum\Markdown\Islands\MarkdownPipeline;

/**
 * Splits a Keep a Changelog file into version headings and Markdown bodies.
 */
final class ChangelogParser
{
    public function __construct(
        private readonly MarkdownPipeline $pipeline = new MarkdownPipeline,
    ) {}

    public function parse(string $markdown, string $path, int $mtime): Changelog
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $refs = $this->referenceUrls($markdown);
        $refBlock = $this->referenceBlock($markdown);

        $title = 'Changelog';

        if (preg_match('/^#\s+(.+)$/m', $markdown, $titleMatch) === 1) {
            $title = trim($titleMatch[1]);
        }

        if (preg_match_all('/^## (.+)$/m', $markdown, $matches, PREG_OFFSET_CAPTURE) < 1) {
            $intro = $this->stripTitle($markdown);

            return new Changelog(
                title: $title,
                introHtml: $this->render($intro, $refBlock),
                markdown: $markdown,
                releases: [],
                headings: [],
                path: $path,
                mtime: $mtime,
            );
        }

        $firstOffset = $matches[0][0][1];
        $intro = $this->stripTitle(substr($markdown, 0, $firstOffset));
        $releases = [];
        $headings = [];
        $count = count($matches[0]);

        for ($index = 0; $index < $count; $index++) {
            $heading = trim($matches[1][$index][0]);
            $headingLine = $matches[0][$index][0];
            $start = $matches[0][$index][1] + strlen($headingLine);
            $end = $index + 1 < $count ? $matches[0][$index + 1][1] : strlen($markdown);
            $body = substr($markdown, $start, $end - $start);
            $parsed = $this->parseHeading($heading);
            $html = $this->render($this->stripReferences($body), $refBlock);

            $release = new ChangelogRelease(
                version: $parsed['version'],
                unreleased: $parsed['unreleased'],
                date: $parsed['date'],
                id: $parsed['id'],
                html: $html,
                url: $refs[$parsed['version']] ?? null,
            );

            $releases[] = $release;
            $headings[] = [
                'id' => $release->id,
                'text' => $release->version,
                'level' => 2,
            ];
        }

        return new Changelog(
            title: $title,
            introHtml: $this->render($intro, $refBlock),
            markdown: $markdown,
            releases: $releases,
            headings: $headings,
            path: $path,
            mtime: $mtime,
        );
    }

    /**
     * @return array{version: string, unreleased: bool, date: string|null, id: string}
     */
    private function parseHeading(string $heading): array
    {
        $heading = trim($heading);

        if (preg_match('/^\[?Unreleased\]?$/i', $heading) === 1) {
            return [
                'version' => 'Unreleased',
                'unreleased' => true,
                'date' => null,
                'id' => 'unreleased',
            ];
        }

        $version = $heading;
        $date = null;

        if (preg_match('/^\[([^\]]+)\](?:\s+-\s+(\d{4}-\d{2}-\d{2}))?$/', $heading, $match) === 1) {
            $version = $match[1];
            $date = $match[2] ?? null;
        } elseif (preg_match('/^(.+?)\s+-\s+(\d{4}-\d{2}-\d{2})$/', $heading, $match) === 1) {
            $version = trim($match[1], '[]');
            $date = $match[2];
        }

        if ($date !== null && ! self::isRealDate($date)) {
            $date = null;
        }

        $unreleased = strcasecmp($version, 'Unreleased') === 0;

        return [
            'version' => $version,
            'unreleased' => $unreleased,
            'date' => $date,
            'id' => $unreleased ? 'unreleased' : $version,
        ];
    }

    /**
     * The heading pattern matches any yyyy-mm-dd shape, so 2026-13-45 gets
     * through it. An impossible date would go straight into the Atom feed,
     * which readers reject, so treat it as no date at all.
     */
    private static function isRealDate(string $date): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) !== 1) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    /**
     * @return array<string, string>
     */
    private function referenceUrls(string $markdown): array
    {
        $urls = [];

        if (preg_match_all('/^\[([^\]]+)\]:\s+(\S+)/m', $markdown, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $urls[$match[1]] = $match[2];
        }

        return $urls;
    }

    private function referenceBlock(string $markdown): string
    {
        if (preg_match_all('/^\[[^\]]+\]:\s+\S+.*$/m', $markdown, $matches) < 1) {
            return '';
        }

        return implode("\n", $matches[0]);
    }

    private function stripTitle(string $markdown): string
    {
        return trim(preg_replace('/^#\s+.+\n*/', '', $markdown, 1) ?? $markdown);
    }

    private function stripReferences(string $markdown): string
    {
        $stripped = preg_replace('/^\[[^\]]+\]:\s+\S+.*$/m', '', $markdown) ?? $markdown;

        return trim($stripped);
    }

    private function render(string $markdown, string $refBlock): string
    {
        $markdown = trim($markdown);

        if ($markdown === '' && $refBlock === '') {
            return '';
        }

        if ($refBlock !== '') {
            $markdown = $markdown === '' ? $refBlock : $markdown."\n\n".$refBlock;
        }

        return $this->pipeline->render($markdown);
    }
}
