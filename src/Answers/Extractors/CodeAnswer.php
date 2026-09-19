<?php

declare(strict_types=1);

namespace Vellum\Answers\Extractors;

/**
 * The last resort: the section's first code block of any language, such as
 * the config array a configuration section shows.
 */
final class CodeAnswer implements AnswerExtractor
{
    public function extract(array $section): ?array
    {
        if (preg_match('/<pre><code class="language-([\w-]+)">(.*?)<\/code><\/pre>/s', $section['html'], $match) !== 1) {
            return null;
        }

        $code = trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $code === '' ? null : ['type' => 'code', 'code' => $code, 'language' => $match[1]];
    }
}
