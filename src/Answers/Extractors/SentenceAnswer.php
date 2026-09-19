<?php

declare(strict_types=1);

namespace Vellum\Answers\Extractors;

/**
 * The section's first sentence worth quoting, with the code block it
 * introduces when it ends in a colon.
 */
final class SentenceAnswer implements AnswerExtractor
{
    public function extract(array $section): ?array
    {
        $lead = Html::leadSentence($section['html']);

        return $lead === null ? null : ['type' => 'sentence', ...$lead];
    }
}
