<?php

declare(strict_types=1);

namespace Vellum\Answers;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Vellum\Semantic\SemanticQuery;

/**
 * Asks search the questions the docs say it must answer, at build time.
 *
 * A docs directory can carry a questions.yml: real questions, each naming the
 * page and heading that answers it. The build runs them against the index it
 * just wrote and says how many find their answer, so a rewrite that moves an
 * answer out of reach fails in CI rather than in front of a reader.
 *
 * A question whose target no longer exists is reported separately. That is a
 * broken test, not a search failure, and it is the only thing here an author
 * has to fix by hand.
 */
final class SearchCheck
{
    public const FILE = 'questions.yml';

    /**
     * How far down a reader is assumed to look.
     */
    public const DEPTH = 5;

    /**
     * @param  list<array{q: string, page: string, section?: string, also?: list<array{page: string, section?: string}>}>  $questions
     */
    private function __construct(public readonly array $questions) {}

    /**
     * Reads a questions file, keeping only entries that name a question and a
     * page. Null when the file is absent, empty, or not a list.
     */
    public static function read(string $file): ?self
    {
        if (! is_file($file)) {
            return null;
        }

        try {
            $parsed = Yaml::parseFile($file);
        } catch (ParseException) {
            return null;
        }

        if (! is_array($parsed)) {
            return null;
        }

        $questions = [];

        foreach ($parsed as $entry) {
            if (! is_array($entry) || ! is_string($entry['q'] ?? null) || ! is_string($entry['page'] ?? null)) {
                continue;
            }

            $question = ['q' => trim($entry['q']), 'page' => (string) $entry['page']];

            if (is_string($entry['section'] ?? null)) {
                $question['section'] = $entry['section'];
            }

            foreach (is_array($entry['also'] ?? null) ? $entry['also'] : [] as $also) {
                if (is_array($also) && is_string($also['page'] ?? null)) {
                    $question['also'][] = is_string($also['section'] ?? null)
                        ? ['page' => $also['page'], 'section' => $also['section']]
                        : ['page' => $also['page']];
                }
            }

            if ($question['q'] !== '') {
                $questions[] = $question;
            }
        }

        return $questions === [] ? null : new self($questions);
    }

    /**
     * @return array{asked: int, found: int, missed: list<string>, broken: list<string>}
     */
    public function run(AnswerIndex $index, ?SemanticQuery $semantic = null): array
    {
        $ranker = new Ranker($index, $semantic);
        $found = 0;
        $missed = [];
        $broken = [];

        foreach ($this->questions as $entry) {
            $targets = [$entry, ...($entry['also'] ?? [])];
            $gone = array_values(array_filter(
                $targets,
                static fn (array $target): bool => ! self::exists($index, $target),
            ));

            if (count($gone) === count($targets)) {
                $broken[] = sprintf('%s (nothing at %s)', $entry['q'], implode(', ', array_map(self::label(...), $gone)));

                continue;
            }

            $hit = false;

            foreach (array_slice($ranker->search($entry['q'], self::DEPTH)['results'], 0, self::DEPTH) as $result) {
                $hit = $hit || self::hits($targets, $result['record']);
            }

            $found += $hit ? 1 : 0;

            if (! $hit) {
                $missed[] = sprintf('%s (wanted %s)', $entry['q'], self::label($entry));
            }
        }

        return [
            'asked' => count($this->questions) - count($broken),
            'found' => $found,
            'missed' => $missed,
            'broken' => $broken,
        ];
    }

    /**
     * A section answers a question when it is the target, sits under the
     * target heading, or is any section of a target named by page alone.
     *
     * @param  list<array{page: string, section?: string}>  $targets
     * @param  array<string, mixed>  $record
     */
    private static function hits(array $targets, array $record): bool
    {
        foreach ($targets as $target) {
            if ($record['page'] !== $target['page']) {
                continue;
            }

            $section = $target['section'] ?? null;

            if ($section === null || $record['anchor'] === $section || $record['parent'] === $section) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{page: string, section?: string}  $target
     */
    private static function exists(AnswerIndex $index, array $target): bool
    {
        foreach ($index->sections as $record) {
            if (self::hits([$target], $record)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{page: string, section?: string}  $target
     */
    private static function label(array $target): string
    {
        return ($target['page'] === '' ? '/' : $target['page']).(isset($target['section']) ? '#'.$target['section'] : '');
    }
}
