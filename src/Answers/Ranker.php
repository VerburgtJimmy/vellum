<?php

declare(strict_types=1);

namespace Vellum\Answers;

/**
 * Ranks sections for a reader's query: BM25 over each section's title,
 * heading, text and questions, relative to the best hit, plus a lift for a
 * section the query names outright. The browser runs the same arithmetic on
 * the same index, so a result ranks the same there.
 *
 * @phpstan-import-type Record from AnswerIndex
 *
 * @phpstan-type Result array{record: Record, score: float, lexical: float, exact: bool}
 */
final class Ranker
{
    /**
     * What naming a section's heading, page title or alias adds, against 1 for
     * the best lexical hit.
     */
    public const EXACT_BOOST = 1.0;

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
     * @return list<Result>
     */
    public function search(string $query, int $limit = 10): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $expansions = $this->synonyms->expand($query);
        $lexical = $this->lexical->searchTerms($this->terms($query, $expansions));
        $best = $lexical === [] ? 0.0 : max($lexical);
        $normalized = ' '.Synonyms::normalize($query).' ';
        $scored = [];

        foreach ($this->records as $position => $record) {
            $term = $best > 0.0 ? ($lexical[$position] ?? 0.0) / $best : 0.0;
            $exact = $this->namesSection($record, $normalized);

            if ($term <= 0.0 && ! $exact) {
                continue;
            }

            $scored[] = [
                'record' => $record,
                'score' => $term + ($exact ? self::EXACT_BOOST : 0.0),
                'lexical' => $term,
                'exact' => $exact,
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return self::spread($scored, $limit);
    }

    /**
     * The best results, with at most PER_PAGE from any one page before any
     * other page's, so one page with many matching sections cannot fill the
     * list.
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
