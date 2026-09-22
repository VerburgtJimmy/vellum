<?php

declare(strict_types=1);

namespace Vellum\Answers;

use Vellum\Semantic\SemanticQuery;

/**
 * Ranks sections for a reader's question. The semantic score leads and the
 * lexical score is a boost on top of it, which is the ranking the 0.7
 * evaluation settled on. The browser runs the same arithmetic on the same
 * files, so a result ranks the same there.
 *
 * @phpstan-import-type Record from AnswerIndex
 *
 * @phpstan-type Result array{record: Record, score: float, cosine: float, lexical: float, exact: bool}
 */
final class Ranker
{
    /**
     * Weight of the lexical score, relative to the best lexical hit.
     */
    public const TERM_BOOST = 0.1;

    /**
     * Weight of a query that names a section's heading or one of its aliases.
     */
    public const EXACT_BOOST = 0.1;

    /**
     * What an expanded term counts for, against 1 for one the reader typed.
     */
    public const SYNONYM_WEIGHT = 0.5;

    /**
     * Results from one page, at most, so a page with many similar sections
     * cannot fill the list.
     */
    public const PER_PAGE = 2;

    private readonly Bm25 $lexical;

    private readonly Synonyms $synonyms;

    /** @var list<Record> */
    private readonly array $records;

    public function __construct(
        private readonly AnswerIndex $index,
        private readonly ?SemanticQuery $semantic = null,
    ) {
        $this->records = $index->sections;
        $this->synonyms = $index->synonyms();
        $this->lexical = new Bm25(
            array_map(static fn (array $record): array => [
                'title' => $record['title'],
                'heading' => $record['heading'],
                'text' => $record['text'].' '.implode(' ', $record['questions']),
            ], $this->records),
            ['title' => 3.0, 'heading' => 2.0, 'text' => 1.0],
        );
    }

    /**
     * @return array{results: list<Result>, card: Result|null, confidence: float}
     */
    public function search(string $query, int $limit = 10): array
    {
        $query = trim($query);

        if ($query === '') {
            return ['results' => [], 'card' => null, 'confidence' => 0.0];
        }

        $expansions = $this->synonyms->expand($query);
        $lexical = $this->lexical->searchTerms($this->terms($query, $expansions));
        $best = $lexical === [] ? 0.0 : max($lexical);

        // The words the reader typed, and the words the docs use for them, are
        // embedded together: a question about "night mode" is then also asked
        // in the docs' own terms.
        $cosines = $this->semantic?->scores(trim($query.' '.implode(' ', $expansions))) ?? [];
        $normalized = ' '.Synonyms::normalize($query).' ';
        $scored = [];

        foreach ($this->records as $position => $record) {
            $cosine = $cosines[$record['id']] ?? 0.0;
            $term = $best > 0.0 ? ($lexical[$position] ?? 0.0) / $best : 0.0;
            $exact = $this->namesSection($record, $normalized);

            if ($cosine <= 0.0 && $term <= 0.0 && ! $exact) {
                continue;
            }

            $scored[] = [
                'record' => $record,
                'score' => $cosine + self::TERM_BOOST * $term + ($exact ? self::EXACT_BOOST : 0.0),
                'cosine' => $cosine,
                'lexical' => $term,
                'exact' => $exact,
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $results = self::spread($scored, $limit);
        $confidence = self::confidence($scored);
        $threshold = (float) config('vellum.answers.card_threshold', 0.75);

        return [
            'results' => $results,
            'card' => $confidence >= $threshold ? ($results[0] ?? null) : null,
            'confidence' => $confidence,
        ];
    }

    /**
     * How sure the top result is, from 0 to 1: how close the query is to it,
     * how far ahead of the runner-up it is, and whether the query named it.
     * Only this decides whether a card is shown.
     *
     * @param  list<Result>  $scored
     */
    public static function confidence(array $scored): float
    {
        if ($scored === []) {
            return 0.0;
        }

        $top = $scored[0];
        $second = $scored[1]['score'] ?? 0.0;
        $lead = $top['score'] > 0.0 ? ($top['score'] - $second) / $top['score'] : 0.0;

        return min(1.0, max(0.0,
            0.6 * max(0.0, $top['cosine'])
            + 0.25 * min(1.0, $lead / 0.2)
            + 0.15 * ($top['exact'] || $top['lexical'] >= 1.0 ? 1.0 : 0.0),
        ));
    }

    /**
     * The best results, with at most PER_PAGE from any one page before any
     * other page's: five sections of the same page are one answer, not five.
     * What the cap holds back is not dropped, only moved below the rest, so a
     * page that is genuinely the answer still shows its other sections.
     *
     * @param  list<Result>  $scored
     * @return list<Result>
     */
    public static function spread(array $scored, int $limit): array
    {
        $results = [];
        $held = [];
        $seen = [];

        foreach ($scored as $hit) {
            $page = $hit['record']['page'];
            $seen[$page] = ($seen[$page] ?? 0) + 1;

            if ($seen[$page] > self::PER_PAGE) {
                $held[] = $hit;

                continue;
            }

            $results[] = $hit;
        }

        return array_slice([...$results, ...$held], 0, $limit);
    }

    /**
     * The query's terms, with the terms its words are also known by at half
     * weight.
     *
     * @param  list<string>  $expansions
     * @return array<string, float>
     */
    private function terms(string $query, array $expansions): array
    {
        $weights = [];

        foreach (Bm25::terms($query) as $term) {
            $weights[$term] = 1.0;
        }

        foreach ($expansions as $phrase) {
            foreach (Bm25::terms($phrase) as $term) {
                $weights[$term] ??= self::SYNONYM_WEIGHT;
            }
        }

        return $weights;
    }

    /**
     * Does the query name this section outright: its heading, its page title,
     * or an alias its frontmatter gave it? A term it merely mentions does not
     * count, or every section that mentions a command would claim it.
     *
     * @param  Record  $record
     */
    private function namesSection(array $record, string $normalizedQuery): bool
    {
        foreach ($record['names'] as $alias) {
            if ($alias !== '' && str_contains($normalizedQuery, ' '.$alias.' ')) {
                return true;
            }
        }

        return false;
    }

    public function index(): AnswerIndex
    {
        return $this->index;
    }
}
