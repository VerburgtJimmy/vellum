<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Directive;

/**
 * Parses the opening ::: fence info string into name, optional title, and attributes.
 */
final class DirectiveInfo
{
    /**
     * @param  array<string, string>  $attributes
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $title = null,
        public readonly array $attributes = [],
    ) {}

    public static function parse(string $info): ?self
    {
        $info = trim($info);

        if ($info === '' || ! preg_match('/^([a-zA-Z][\w-]*)/', $info, $nameMatch)) {
            return null;
        }

        $name = strtolower($nameMatch[1]);
        $rest = trim(substr($info, strlen($nameMatch[0])));
        $title = null;

        if ($rest !== '' && str_starts_with($rest, '[')) {
            $end = strpos($rest, ']');
            if ($end === false) {
                return null;
            }

            $title = substr($rest, 1, $end - 1);
            $rest = trim(substr($rest, $end + 1));
        }

        $attributes = [];
        if ($rest !== '') {
            $attributes = AttributeParser::parse($rest);
        }

        return new self($name, $title, $attributes);
    }
}
