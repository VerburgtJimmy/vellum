<?php

declare(strict_types=1);

namespace Vellum\Answers;

use Vellum\Answers\Extractors\AnswerExtractors;
use Vellum\Answers\Questions\QuestionGenerators;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;

/**
 * Every search section of one docs version, with what search needs to find it
 * and answer from it: its text, the questions it answers, the phrases it can
 * be found by, and an answer quoted from the docs. Each record keeps the access
 * level of its page; a reader is only ever given the records they may see.
 *
 * @phpstan-type Record array{
 *     id: string,
 *     page: string,
 *     anchor: string,
 *     parent: string,
 *     version: string|null,
 *     access: string,
 *     url: string,
 *     title: string,
 *     heading: string,
 *     text: string,
 *     questions: list<string>,
 *     names: list<string>,
 *     aliases: list<string>,
 *     answer: array<string, mixed>|null,
 *     passage: string|null,
 *     updated: string|null
 * }
 * @phpstan-type Group array{terms: list<string>, access: string}
 */
final class AnswerIndex
{
    public const FILE = 'answers.json';

    /**
     * @param  list<Record>  $sections
     * @param  list<Group>  $synonyms  page title and alias groups
     */
    public function __construct(
        public readonly array $sections,
        public readonly array $synonyms,
    ) {}

    /**
     * @param  list<Document>  $documents
     * @param  array<string, list<string>>  $extraQuestions  section id => questions (from the LLM cache)
     */
    public static function build(array $documents, ContentRepository $repository, array $extraQuestions = []): self
    {
        $updated = [];

        foreach ($documents as $document) {
            $updated[$document->slug] = $document->updated;
        }

        $generators = QuestionGenerators::default();
        $extractors = AnswerExtractors::default();
        $records = [];
        $synonyms = [];

        foreach (Sections::from($documents) as $section) {
            $id = $section['page'].'#'.$section['anchor'];
            $questions = $generators->for($section);

            foreach ($extraQuestions[$id] ?? [] as $question) {
                $question = mb_strtolower(trim($question));

                if ($question !== '' && ! in_array($question, $questions, true)) {
                    $questions[] = $question;
                }
            }

            $href = $repository->hrefFor($section['page'], $section['version']);

            $records[] = [
                'id' => $id,
                'page' => $section['page'],
                'anchor' => $section['anchor'],
                'parent' => $section['parent'],
                'version' => $section['version'],
                'access' => $section['access'],
                'url' => $section['anchor'] === '' ? $href : $href.'#'.$section['anchor'],
                'title' => $section['title'],
                'heading' => $section['heading'],
                'text' => $section['text'],
                'questions' => $questions,
                'names' => Aliases::names($section),
                'aliases' => Aliases::terms($section),
                'answer' => $extractors->for($section),
                'passage' => AnswerExtractors::passage($section),
                'updated' => $updated[$section['page']] ?? null,
            ];

            if ($section['anchor'] === '' && $section['aliases'] !== []) {
                $synonyms[] = ['terms' => [$section['title'], ...$section['aliases']], 'access' => $section['access']];
            }
        }

        return new self($records, $synonyms);
    }

    /**
     * The text a section is embedded and indexed from: its words, then the
     * questions it answers, so a reader's question lands near its answer.
     *
     * @param  Record  $record
     */
    public static function text(array $record): string
    {
        return trim(implode(' ', [$record['title'], $record['heading'], $record['text'], ...$record['questions']]));
    }

    /**
     * The same index with more questions on the sections named, keeping the
     * generated ones and dropping repeats.
     *
     * @param  array<string, list<string>>  $extra  section id => questions
     */
    public function withQuestions(array $extra): self
    {
        $sections = [];

        foreach ($this->sections as $record) {
            foreach ($extra[$record['id']] ?? [] as $question) {
                $question = mb_strtolower(trim($question));

                if ($question !== '' && ! in_array($question, $record['questions'], true)) {
                    $record['questions'][] = $question;
                }
            }

            $sections[] = $record;
        }

        return new self($sections, $this->synonyms);
    }

    /**
     * What a reader holding these access levels may see.
     *
     * @param  list<string>  $access
     */
    public function forAccess(array $access): self
    {
        $allowed = array_flip($access);

        return new self(
            array_values(array_filter($this->sections, static fn (array $record): bool => isset($allowed[$record['access']]))),
            array_values(array_filter($this->synonyms, static fn (array $group): bool => isset($allowed[$group['access']]))),
        );
    }

    /**
     * Synonyms for queries against this index: the built-in list and the page
     * groups this index holds.
     */
    public function synonyms(): Synonyms
    {
        return Synonyms::withPages(array_map(
            static fn (array $group): array => ['title' => $group['terms'][0], 'aliases' => array_slice($group['terms'], 1)],
            $this->synonyms,
        ));
    }

    /**
     * The built-in synonym groups and this index's page groups, as plain lists
     * of terms.
     *
     * @return list<list<string>>
     */
    public function synonymGroups(): array
    {
        /** @var list<list<string>> $builtIn */
        $builtIn = require dirname(__DIR__, 2).'/resources/synonyms.php';

        return [...$builtIn, ...array_map(static fn (array $group): array => $group['terms'], $this->synonyms)];
    }

    public function save(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $directory.'/'.self::FILE,
            json_encode(['sections' => $this->sections, 'synonyms' => $this->synonyms], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    public static function load(string $directory): ?self
    {
        $path = $directory.'/'.self::FILE;
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($data) || ! is_array($data['sections'] ?? null) || ! is_array($data['synonyms'] ?? null)) {
            return null;
        }

        /** @var list<Record> $sections */
        $sections = array_values($data['sections']);
        /** @var list<Group> $synonyms */
        $synonyms = array_values($data['synonyms']);

        return new self($sections, $synonyms);
    }
}
