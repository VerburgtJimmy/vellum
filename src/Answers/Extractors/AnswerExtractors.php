<?php

declare(strict_types=1);

namespace Vellum\Answers\Extractors;

/**
 * The first extractor with something to say wins, in the order of the plan:
 * command, config row, option row, definition, first sentence. Sections with
 * no prose fall back to the page description, a list, then a code block.
 */
final class AnswerExtractors
{
    /**
     * @param  list<AnswerExtractor>  $extractors
     */
    public function __construct(private readonly array $extractors) {}

    public static function default(): self
    {
        return new self([
            new CommandAnswer,
            new ConfigAnswer,
            new TableRowAnswer,
            new DefinitionAnswer,
            new SentenceAnswer,
            new DescriptionAnswer,
            new ListAnswer,
            new CodeAnswer,
        ]);
    }

    /**
     * @param  array{title: string, own: string, html: string, description?: string}  $section
     * @return array<string, mixed>|null
     */
    public function for(array $section): ?array
    {
        foreach ($this->extractors as $extractor) {
            $answer = $extractor->extract($section);

            if ($answer !== null) {
                return $answer;
            }
        }

        return null;
    }

    /**
     * Up to three sentences of the section's first paragraph, the passage a
     * card quotes under its answer.
     *
     * @param  array{html: string}  $section
     */
    public static function passage(array $section, int $sentences = 3): ?string
    {
        $paragraph = Html::firstParagraph($section['html']);

        return $paragraph === null ? null : implode(' ', array_slice(Html::sentences($paragraph), 0, $sentences));
    }
}
