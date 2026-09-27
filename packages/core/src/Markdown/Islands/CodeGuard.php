<?php

declare(strict_types=1);

namespace Vellum\Markdown\Islands;

/**
 * Temporarily replaces fenced and inline code so island extraction cannot see tags inside them.
 */
final class CodeGuard
{
    private const TOKEN = 'VELLUMCODE_%d_VELLUM';

    /**
     * @return array{markdown: string, codes: list<string>}
     */
    public static function protect(string $markdown): array
    {
        $codes = [];

        $replace = static function (array $match) use (&$codes): string {
            $index = count($codes);
            $codes[] = $match[0];

            return sprintf(self::TOKEN, $index);
        };

        $markdown = preg_replace_callback(
            '/^[ \t]{0,3}(`{3,}|~{3,}).*?\n[\s\S]*?^[ \t]{0,3}\1[ \t]*$/m',
            $replace,
            $markdown,
        ) ?? $markdown;

        $markdown = preg_replace_callback(
            '/(?<!`)(`+)(?!`)(?:(?!`).)+?\1(?!`)/s',
            $replace,
            $markdown,
        ) ?? $markdown;

        return [
            'markdown' => $markdown,
            'codes' => $codes,
        ];
    }

    /**
     * @param  list<string>  $codes
     */
    public static function restore(string $markdown, array $codes): string
    {
        for ($index = count($codes) - 1; $index >= 0; $index--) {
            $markdown = str_replace(sprintf(self::TOKEN, $index), $codes[$index], $markdown);
        }

        return $markdown;
    }
}
