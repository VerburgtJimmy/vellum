<?php

declare(strict_types=1);

namespace Vellum\Answers\Extractors;

/**
 * "X is ..." as the first sentence of the first paragraph, where X is six
 * words or fewer: the sentence that says what the section is about.
 */
final class DefinitionAnswer implements AnswerExtractor
{
    public function extract(array $section): ?array
    {
        $lead = Html::leadSentence($section['html']);

        if ($lead === null || preg_match('/^(.{1,60}?)\s(?:is|are)\s/u', $lead['sentence'], $match) !== 1 || str_word_count($match[1]) > 6) {
            return null;
        }

        return ['type' => 'definition', ...$lead];
    }
}
