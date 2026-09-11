<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Directive;

/**
 * Parses key=value pairs from directive attribute strings.
 */
final class AttributeParser
{
    /**
     * @return array<string, string>
     */
    public static function parse(string $input): array
    {
        $attributes = [];

        if ($input === '') {
            return $attributes;
        }

        if (preg_match_all('/(\w+)=(?:"([^"]*)"|\'([^\']*)\'|(\S+))/', $input, $matches, PREG_SET_ORDER) === false) {
            return $attributes;
        }

        foreach ($matches as $match) {
            $key = $match[1];
            $double = $match[2] ?? '';
            $single = $match[3] ?? '';
            $bare = $match[4] ?? '';

            $attributes[$key] = $double !== '' ? $double : ($single !== '' ? $single : $bare);
        }

        return $attributes;
    }
}
