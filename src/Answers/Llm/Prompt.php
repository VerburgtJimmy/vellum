<?php

declare(strict_types=1);

namespace Vellum\Answers\Llm;

use RuntimeException;

/**
 * The one prompt, the schema its answer must follow, and the parser for it.
 * Changing the prompt changes VERSION, which invalidates cached questions.
 */
final class Prompt
{
    public const VERSION = 1;

    public const COUNT = 5;

    public const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'questions' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['questions'],
        'additionalProperties' => false,
    ];

    public static function for(string $site, string $page, string $heading, string $text): string
    {
        $location = $heading === '' ? $page : "{$page} > {$heading}";

        return <<<PROMPT
        Below is one section of the documentation for {$site}, from the page "{$location}".

        Write the questions a reader would type into the docs search box when this section is the answer they need. Write them the way readers phrase things when they do not know the docs' own terms: plain words, short, as a question or a few keywords. Cover different reasons someone would land here. Every question must be answered by this section alone.

        Return exactly five questions.

        <section>
        {$text}
        </section>
        PROMPT;
    }

    /**
     * @return list<string>
     */
    public static function parse(string $json): array
    {
        $data = json_decode($json, true);
        $questions = is_array($data) && is_array($data['questions'] ?? null) ? $data['questions'] : null;

        if ($questions === null) {
            throw new RuntimeException('The answer was not the expected JSON');
        }

        $clean = [];

        foreach ($questions as $question) {
            if (is_string($question) && trim($question) !== '') {
                $clean[] = trim($question);
            }
        }

        return array_slice($clean, 0, self::COUNT);
    }
}
