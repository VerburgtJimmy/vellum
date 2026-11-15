<?php

declare(strict_types=1);

namespace Vellum\View;

/**
 * The lines the scripts read, in the app's locale. The layout writes them
 * into the page as one JSON block, so a static export carries the language
 * it was exported in.
 */
final class ScriptStrings
{
    /**
     * Each language file a script reads from, by the name it reads it under.
     */
    public const FILES = [
        'search' => 'vellum::search',
        'sidebar' => 'vellum::sidebar',
        'toc' => 'vellum::toc',
        'page' => 'vellum::page',
        'code' => 'vellum-core::code',
        'playground' => 'vellum-openapi::playground',
    ];

    /**
     * The lines the scripts on every page read, each a line or a group of
     * them. Only these go in the block, so a page does not carry the lines
     * the server already wrote into it.
     */
    public const KEYS = [
        'search.recent',
        'search.status',
        'sidebar.changed',
        'toc.title',
        'page.updated',
        'page.zoom',
        'code.copied',
    ];

    /**
     * Read whole by the playground's script, which only loads when it is on.
     */
    public const PLAYGROUND = 'playground';

    /**
     * @return array<string, mixed>
     */
    public static function all(bool $playground = false): array
    {
        $strings = [];

        foreach ($playground ? [...self::KEYS, self::PLAYGROUND] : self::KEYS as $key) {
            $file = strstr($key, '.', true) ?: $key;
            $line = app('translator')->get(self::FILES[$file].substr($key, strlen($file)));

            data_set($strings, $key, $line);
        }

        return $strings;
    }

    /**
     * The block's contents: JSON that cannot close the script element.
     */
    public static function json(bool $playground = false): string
    {
        return json_encode(
            self::all($playground),
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
