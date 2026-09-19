<?php

declare(strict_types=1);

namespace Vellum\Answers\Extractors;

/**
 * The first list in a section of lists, up to five items: "PHP 8.4+" and
 * "Laravel 11, 12, or 13" answer what the requirements are.
 */
final class ListAnswer implements AnswerExtractor
{
    public function extract(array $section): ?array
    {
        if (preg_match('/<(ul|ol)\b[^>]*>(.*?)<\/\1>/s', $section['html'], $list) !== 1) {
            return null;
        }

        preg_match_all('/<li\b[^>]*>(.*?)<\/li>/s', $list[2], $items);
        $items = array_values(array_filter(array_map(Html::text(...), array_slice($items[1], 0, 5)), static fn (string $item): bool => $item !== ''));

        return $items === [] ? null : ['type' => 'list', 'items' => $items];
    }
}
