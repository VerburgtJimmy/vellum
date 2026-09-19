<?php

declare(strict_types=1);

namespace Vellum\Answers\Questions;

/**
 * Runs every generator over a section and returns the distinct questions,
 * lowercased.
 */
final class QuestionGenerators
{
    /**
     * @param  list<QuestionGenerator>  $generators
     */
    public function __construct(private readonly array $generators) {}

    public static function default(): self
    {
        return new self([
            new HeadingQuestions,
            new CommandQuestions,
            new ConfigKeyQuestions,
            new CalloutQuestions,
            new FrontmatterQuestions,
        ]);
    }

    /**
     * @param  array{title: string, own: string, html: string, questions: list<string>}  $section
     * @return list<string>
     */
    public function for(array $section): array
    {
        $questions = [];

        foreach ($this->generators as $generator) {
            foreach ($generator->generate($section) as $question) {
                $question = mb_strtolower(trim($question));

                if ($question !== '') {
                    $questions[$question] = true;
                }
            }
        }

        return array_map('strval', array_keys($questions));
    }
}
