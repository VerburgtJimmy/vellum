<?php

declare(strict_types=1);

namespace Vellum\Content;

/**
 * Resolves page access: guest, auth, or a gate name.
 *
 * Folder access in meta.json or _meta.md inherits downward. Page frontmatter wins.
 */
final class Access
{
    public function __construct(
        private readonly FrontMatterParser $frontMatterParser = new FrontMatterParser,
    ) {}

    /**
     * The two values Vellum resolves itself. Anything else is a gate name.
     */
    private const RESERVED = ['guest', 'auth'];

    public static function normalize(mixed $value): string
    {
        if (! is_string($value)) {
            return 'guest';
        }

        $value = trim($value);

        if ($value === '') {
            return 'guest';
        }

        // Fold case for the reserved words only: "Auth" is a typo for "auth",
        // but a gate is registered under an exact name and may well be capitalised.
        $lower = strtolower($value);

        return in_array($lower, self::RESERVED, true) ? $lower : $value;
    }

    /**
     * @param  array<string, mixed>  $matter
     */
    public function forPage(array $matter, string $absolutePath, string $contentRoot): string
    {
        $page = $this->optional($matter['access'] ?? null);

        if ($page !== null) {
            return $page;
        }

        return $this->inherited($absolutePath, $contentRoot);
    }

    public function inherited(string $absolutePath, string $contentRoot): string
    {
        $access = 'guest';

        foreach ($this->ancestorFolders($absolutePath, $contentRoot) as $folder) {
            $folderAccess = $this->folderAccess($folder);

            if ($folderAccess !== null) {
                $access = $folderAccess;
            }
        }

        return $access;
    }

    public function folderMtime(string $absolutePath, string $contentRoot): int
    {
        $mtime = 0;

        foreach ($this->ancestorFolders($absolutePath, $contentRoot) as $folder) {
            foreach (['_meta.md', 'meta.json'] as $name) {
                $path = $folder.DIRECTORY_SEPARATOR.$name;

                if (is_file($path)) {
                    $mtime = max($mtime, (int) filemtime($path));
                }
            }
        }

        return $mtime;
    }

    private function optional(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function folderAccess(string $folder): ?string
    {
        $metaMarkdown = $folder.DIRECTORY_SEPARATOR.'_meta.md';

        if (is_file($metaMarkdown)) {
            $access = $this->optional($this->frontMatterParser->parseFile($metaMarkdown)['matter']['access'] ?? null);

            if ($access !== null) {
                return $access;
            }
        }

        $metaJson = $folder.DIRECTORY_SEPARATOR.'meta.json';

        if (! is_file($metaJson)) {
            return null;
        }

        $json = file_get_contents($metaJson);

        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return null;
        }

        return $this->optional($decoded['access'] ?? null);
    }

    /**
     * @return list<string>
     */
    private function ancestorFolders(string $absolutePath, string $contentRoot): array
    {
        $file = str_replace('\\', '/', $absolutePath);
        $root = rtrim(str_replace('\\', '/', $contentRoot), '/');
        $dir = dirname($file);
        $folders = [];

        while (str_starts_with($dir.'/', $root.'/') || $dir === $root) {
            array_unshift($folders, str_replace('/', DIRECTORY_SEPARATOR, $dir));

            if ($dir === $root) {
                break;
            }

            $parent = dirname($dir);

            if ($parent === $dir) {
                break;
            }

            $dir = $parent;
        }

        return $folders;
    }
}
