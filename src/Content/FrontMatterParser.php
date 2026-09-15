<?php

declare(strict_types=1);

namespace Vellum\Content;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Vellum\Exceptions\InvalidFrontMatterException;

/**
 * Splits YAML frontmatter from a Markdown body.
 *
 * @phpstan-type FrontMatter array<string, mixed>
 * @phpstan-type ParsedFrontMatter array{matter: FrontMatter, body: string}
 */
final class FrontMatterParser
{
    /**
     * Parse frontmatter for a file, naming the file in any YAML errors.
     *
     * @return ParsedFrontMatter
     */
    public function parseFile(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw InvalidFrontMatterException::forFile($path, 'unable to read file');
        }

        return $this->parse($contents, $path);
    }

    /**
     * @return ParsedFrontMatter
     */
    public function parse(string $contents, ?string $path = null): array
    {
        $label = $path ?? 'inline content';

        // An editor-written BOM sits before the opening ---, which would
        // otherwise make the whole block parse as body text.
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        if (! preg_match('/\A---\r?\n/', $contents)) {
            return [
                'matter' => [],
                'body' => $contents,
            ];
        }

        if (! preg_match('/\A---\r?\n(.*?)\r?\n---\r?\n?(.*)\z/s', $contents, $matches)) {
            throw InvalidFrontMatterException::forFile(
                $label,
                'opening --- found but closing --- is missing',
            );
        }

        $yaml = trim($matches[1]);
        $body = $matches[2];

        if ($yaml === '') {
            return [
                'matter' => [],
                'body' => $body,
            ];
        }

        try {
            $matter = Yaml::parse($yaml);
        } catch (ParseException $exception) {
            throw InvalidFrontMatterException::forFile($label, $exception->getMessage(), previous: $exception);
        }

        if ($matter === null) {
            $matter = [];
        }

        if (! is_array($matter)) {
            throw InvalidFrontMatterException::forFile($label, 'frontmatter must be a YAML mapping');
        }

        /** @var FrontMatter $matter */
        return [
            'matter' => $matter,
            'body' => $body,
        ];
    }
}
