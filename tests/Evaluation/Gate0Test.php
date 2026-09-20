<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Vellum\Answers\Bm25;
use Vellum\Answers\Sections;
use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;
use Vellum\Semantic\SafetensorsTable;
use Vellum\Semantic\WordPieceTokenizer as WordPiece;
use Vellum\Tests\Evaluation\Vectors;

/*
 * Gate 0: does a pruned, projected, int8 static embedding keep its recall on
 * Vellum's own docs? Run with:
 *
 *   VELLUM_EVAL=1 VELLUM_EVAL_MODELS=/path/to/models vendor/bin/pest tests/Evaluation
 *
 * Every parameter is fixed up front (MiniSearch's BM25 defaults, RRF k = 60,
 * 3000 common tokens); nothing here is tuned to the golden questions.
 */

const GATE0_COMMON_TOKENS = 3000;
const GATE0_RRF_K = 60;

it('reports recall and size for each retrieval variant', function (): void {
    $models = rtrim((string) getenv('VELLUM_EVAL_MODELS'), '/');
    $docs = realpath(__DIR__.'/../../docs');
    $repository = new ContentRepository(contentPath: $docs, store: new CompiledStore($this->cachePath()));
    $sections = Sections::from($repository->buildAll());

    /** @var list<array{q: string, page: string, section?: string, kind: string, also?: list<array{page: string, section?: string}>}> $golden */
    $golden = Yaml::parseFile($docs.'/questions.yml');

    $documents = array_map(static fn (array $s): array => [
        'title' => $s['title'],
        'heading' => $s['heading'],
        'text' => $s['text'],
    ], $sections);

    // Crude heading questions: the fallback's question signal until the real generators exist.
    $questions = [];

    foreach ($sections as $id => $section) {
        $subject = mb_strtolower($section['heading'] !== '' ? $section['heading'] : $section['title']);

        foreach (["how do i {$subject}", "what is {$subject}", "where do i configure {$subject}"] as $question) {
            $questions[] = ['section' => $id, 'q' => $question];
        }
    }

    $lexical = new Bm25($documents, ['title' => 3.0, 'heading' => 2.0, 'text' => 1.0]);
    $asked = new Bm25(array_map(static fn (array $q): array => ['q' => $q['q']], $questions), ['q' => 1.0]);

    $rankings = [];

    foreach ($golden as $index => $entry) {
        $rankings['BM25'][$index] = array_keys($lexical->search($entry['q']));
        $bySection = [];

        foreach ($asked->search($entry['q']) as $questionId => $score) {
            $section = $questions[$questionId]['section'];
            $bySection[$section] = max($bySection[$section] ?? 0.0, $score);
        }

        arsort($bySection);
        $rankings['Questions'][$index] = array_keys($bySection);
        $rankings['BM25 + questions'][$index] = gate0Fuse([$rankings['BM25'][$index], $rankings['Questions'][$index]]);
    }

    $sizes = [];
    $timings = [];

    foreach (['potion-retrieval-32M', 'potion-base-8M'] as $model) {
        $full = WordPiece::fromTokenizerJson("{$models}/{$model}/tokenizer.json");
        $table = new SafetensorsTable("{$models}/{$model}/model.safetensors");
        $vocab = $full->vocab();
        $unknown = $vocab['[UNK]'];
        $texts = gate0Texts($sections);

        // Full table: every token the docs or the questions use, at full precision.
        $needed = [];

        foreach (array_merge($texts, array_column($questions, 'q'), array_column($golden, 'q')) as $text) {
            foreach ($full->ids($text) as $id) {
                $needed[$id] = true;
            }
        }

        unset($needed[$unknown]);
        $rows = $table->rows(array_keys($needed));
        $rankings["{$model} full"] = gate0Semantic($full, $rows, $table->dims, $texts, $questions, $golden);
        $sizes["{$model} full"] = ['bytes' => $table->rows * $table->dims * 4, 'gz' => null, 'tokens' => $table->rows];

        // Pruned: docs tokens, the most common whole words, and single characters.
        $started = hrtime(true);
        $keep = [];

        foreach (array_merge($texts, array_column($questions, 'q')) as $text) {
            foreach ($full->ids($text) as $id) {
                $keep[$id] = true;
            }
        }

        $docsTokens = count($keep);
        $byId = array_flip($vocab);
        ksort($byId);
        $common = 0;

        foreach ($byId as $id => $token) {
            if ($id < $vocab['the']) {
                continue;
            }

            if ($common >= GATE0_COMMON_TOKENS) {
                break;
            }

            if (preg_match('/^[a-z]+$/', (string) $token) === 1) {
                if (! isset($keep[$id])) {
                    $keep[$id] = true;
                }

                $common++;
            }
        }

        foreach ($vocab as $token => $id) {
            if (preg_match('/^(##)?[a-z0-9]$/', (string) $token) === 1) {
                $keep[$id] = true;
            }
        }

        unset($keep[$unknown]);
        $pruned = new WordPiece(array_filter($vocab, static fn (int $id): bool => isset($keep[$id]) || $id === $unknown));
        $prunedRows = $table->rows(array_keys($keep));
        $readSeconds = (hrtime(true) - $started) / 1e9;
        $tokens = array_map(static fn (int $id): string => (string) $byId[$id], array_keys($keep));

        $rankings["{$model} pruned"] = gate0Semantic($pruned, $prunedRows, $table->dims, $texts, $questions, $golden);
        $sizes["{$model} pruned"] = gate0Size($tokens, $prunedRows, $texts, $questions, $pruned, false);
        $sizes["{$model} pruned"]['docs'] = $docsTokens;

        foreach ([64, 96] as $dims) {
            $started = hrtime(true);
            [$mean, $components, $captured] = Vectors::pca($prunedRows, $dims);
            $pcaSeconds = (hrtime(true) - $started) / 1e9;
            $projected = array_map(static fn (array $row): array => Vectors::project($row, $mean, $components), $prunedRows);

            $rankings["{$model} pruned + PCA {$dims}"] = gate0Semantic($pruned, $projected, $dims, $texts, $questions, $golden);
            $sizes["{$model} pruned + PCA {$dims}"] = gate0Size($tokens, $projected, $texts, $questions, $pruned, false);

            $started = hrtime(true);
            $quantised = array_map(Vectors::int8(...), $projected);
            $rankings["{$model} pruned + PCA {$dims} + int8"] = gate0Semantic($pruned, $quantised, $dims, $texts, $questions, $golden, true);
            $sizes["{$model} pruned + PCA {$dims} + int8"] = gate0Size($tokens, $quantised, $texts, $questions, $pruned, true);
            $timings["{$model} PCA {$dims}"] = ['read' => $readSeconds, 'pca' => $pcaSeconds, 'rest' => (hrtime(true) - $started) / 1e9, 'captured' => $captured];
        }

        foreach (["{$model} full", "{$model} pruned + PCA 64 + int8", "{$model} pruned + PCA 96 + int8"] as $variant) {
            foreach ($golden as $index => $entry) {
                $rankings["BM25 + questions + {$variant}"][$index] = gate0Fuse([
                    $rankings['BM25'][$index],
                    $rankings['Questions'][$index],
                    $rankings[$variant][$index],
                ]);
            }
        }
    }

    $report = gate0Report($rankings, $golden, $sections, $sizes, $timings);
    file_put_contents(__DIR__.'/gate0.md', $report);
    fwrite(STDERR, $report);

    expect($rankings)->not->toBeEmpty();
})->skip(fn (): bool => getenv('VELLUM_EVAL') !== '1', 'Set VELLUM_EVAL=1 and VELLUM_EVAL_MODELS to run the Gate 0 evaluation.');

/**
 * @param  list<array{title: string, heading: string, text: string}>  $sections
 * @return list<string>
 */
function gate0Texts(array $sections): array
{
    return array_map(static fn (array $s): string => trim($s['title'].' '.$s['heading'].' '.$s['text']), $sections);
}

/**
 * Reciprocal rank fusion.
 *
 * @param  list<list<int>>  $lists
 * @return list<int>
 */
function gate0Fuse(array $lists): array
{
    $scores = [];

    foreach ($lists as $list) {
        foreach (array_values($list) as $rank => $id) {
            $scores[$id] = ($scores[$id] ?? 0.0) + 1 / (GATE0_RRF_K + $rank + 1);
        }
    }

    arsort($scores);

    return array_keys($scores);
}

/**
 * Rank sections by the best cosine of the query against the section or any of its questions.
 *
 * @param  array<int, list<float>>  $rows
 * @param  list<string>  $texts
 * @param  list<array{section: int, q: string}>  $questions
 * @param  list<array{q: string}>  $golden
 * @return array<int, list<int>>
 */
function gate0Semantic(WordPiece $tokenizer, array $rows, int $dims, array $texts, array $questions, array $golden, bool $int8 = false): array
{
    $store = static function (?array $vector) use ($int8): ?array {
        return $vector === null || ! $int8 ? $vector : Vectors::int8($vector);
    };

    $sectionVectors = array_map(static fn (string $text): ?array => $store(Vectors::pool($tokenizer->ids($text), $rows, $dims)), $texts);
    $questionVectors = array_map(static fn (array $q): ?array => $store(Vectors::pool($tokenizer->ids($q['q']), $rows, $dims)), $questions);
    $rankings = [];

    foreach ($golden as $index => $entry) {
        $query = Vectors::pool($tokenizer->ids($entry['q']), $rows, $dims);
        $scores = [];

        if ($query !== null) {
            foreach ($sectionVectors as $id => $vector) {
                if ($vector !== null) {
                    $scores[$id] = Vectors::dot($query, $vector);
                }
            }

            foreach ($questionVectors as $qid => $vector) {
                if ($vector !== null) {
                    $section = $questions[$qid]['section'];
                    $scores[$section] = max($scores[$section] ?? -1.0, Vectors::dot($query, $vector));
                }
            }
        }

        arsort($scores);
        $rankings[$index] = array_keys($scores);
    }

    return $rankings;
}

/**
 * Bytes the browser would fetch: token table, vocab vectors, section and question vectors.
 *
 * @param  list<string>  $tokens
 * @param  array<int, list<float>>  $rows
 * @param  list<string>  $texts
 * @param  list<array{q: string}>  $questions
 * @return array{bytes: int, gz: int, tokens: int}
 */
function gate0Size(array $tokens, array $rows, array $texts, array $questions, WordPiece $tokenizer, bool $int8): array
{
    $dims = count(reset($rows));
    $pack = static function (array $vector) use ($int8): string {
        if (! $int8) {
            return pack('g*', ...$vector);
        }

        $max = max(array_map('abs', $vector)) ?: 1.0;

        return pack('g', $max / 127).pack('c*', ...array_map(static fn (float $v): int => (int) round($v * 127 / $max), $vector));
    };

    $binary = implode("\n", $tokens);

    foreach ($rows as $row) {
        $binary .= $pack($row);
    }

    foreach ([...$texts, ...array_column($questions, 'q')] as $text) {
        $binary .= $pack(Vectors::pool($tokenizer->ids($text), $rows, $dims) ?? array_fill(0, $dims, 0.0));
    }

    return ['bytes' => strlen($binary), 'gz' => strlen((string) gzencode($binary, 9)), 'tokens' => count($tokens)];
}

/**
 * @param  array{page: string, section?: string, also?: list<array{page: string, section?: string}>}  $entry
 * @param  array{page: string, anchor: string, parent: string}  $section
 */
function gate0Hits(array $entry, array $section): bool
{
    foreach ([$entry, ...($entry['also'] ?? [])] as $target) {
        if ($section['page'] !== $target['page']) {
            continue;
        }

        $anchor = $target['section'] ?? null;

        if ($anchor === null || $section['anchor'] === $anchor || $section['parent'] === $anchor) {
            return true;
        }
    }

    return false;
}

/**
 * @param  array<string, array<int, list<int>>>  $rankings
 * @return array{all1: float, all5: float, lit1: float, lit5: float, para1: float, para5: float}
 */
function gate0Recall(array $ranking, array $golden, array $sections): array
{
    $counts = ['all' => [0, 0, 0], 'literal' => [0, 0, 0], 'paraphrase' => [0, 0, 0]];

    foreach ($golden as $index => $entry) {
        $top = array_slice($ranking[$index] ?? [], 0, 5);
        $first = isset($top[0]) && gate0Hits($entry, $sections[$top[0]]);
        $five = false;

        foreach ($top as $id) {
            $five = $five || gate0Hits($entry, $sections[$id]);
        }

        foreach (['all', $entry['kind']] as $bucket) {
            $counts[$bucket][0]++;
            $counts[$bucket][1] += (int) $first;
            $counts[$bucket][2] += (int) $five;
        }
    }

    $rate = static fn (array $c, int $i): float => $c[0] === 0 ? 0.0 : $c[$i] / $c[0];

    return [
        'all1' => $rate($counts['all'], 1), 'all5' => $rate($counts['all'], 2),
        'lit1' => $rate($counts['literal'], 1), 'lit5' => $rate($counts['literal'], 2),
        'para1' => $rate($counts['paraphrase'], 1), 'para5' => $rate($counts['paraphrase'], 2),
    ];
}

function gate0Report(array $rankings, array $golden, array $sections, array $sizes, array $timings): string
{
    $pct = static fn (float $v): string => sprintf('%.0f%%', $v * 100);
    $kb = static fn (?int $b): string => $b === null ? '' : sprintf('%.0f KB', $b / 1024);
    $counts = array_count_values(array_column($golden, 'kind'));

    $out = "# Gate 0 results\n\n";
    $out .= sprintf("%d sections, %d questions (%d literal, %d paraphrase).\n\n", count($sections), count($golden), $counts['literal'] ?? 0, $counts['paraphrase'] ?? 0);
    $out .= "| Variant | R@1 | R@5 | Literal R@1 | Literal R@5 | Paraphrase R@1 | Paraphrase R@5 | Tokens | Size | Gzipped |\n";
    $out .= "| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |\n";

    foreach ($rankings as $name => $ranking) {
        $r = gate0Recall($ranking, $golden, $sections);
        $size = $sizes[$name] ?? null;
        $out .= sprintf(
            "| %s | %s | %s | %s | %s | %s | %s | %s | %s | %s |\n",
            $name, $pct($r['all1']), $pct($r['all5']), $pct($r['lit1']), $pct($r['lit5']), $pct($r['para1']), $pct($r['para5']),
            $size === null ? '' : number_format($size['tokens']).(isset($size['docs']) ? " ({$size['docs']} from docs)" : ''),
            $kb($size['bytes'] ?? null), $kb($size['gz'] ?? null),
        );
    }

    $out .= "\n| Build step | Read rows | PCA fit | Quantise | Variance kept |\n| --- | --- | --- | --- | --- |\n";

    foreach ($timings as $name => $t) {
        $out .= sprintf("| %s | %.2f s | %.2f s | %.2f s | %.4f |\n", $name, $t['read'], $t['pca'], $t['rest'], $t['captured']);
    }

    $out .= "\n## Paraphrase misses at 5, fused with the 32M int8 64 table\n\n";
    $fused = $rankings['BM25 + questions + potion-retrieval-32M pruned + PCA 64 + int8'] ?? [];

    foreach ($golden as $index => $entry) {
        if ($entry['kind'] !== 'paraphrase') {
            continue;
        }

        $top = array_slice($fused[$index] ?? [], 0, 5);

        if (array_filter($top, static fn (int $id): bool => gate0Hits($entry, $sections[$id])) !== []) {
            continue;
        }

        $names = array_map(static fn (int $id): string => $sections[$id]['page'].($sections[$id]['anchor'] !== '' ? '#'.$sections[$id]['anchor'] : ''), array_slice($top, 0, 3));
        $out .= sprintf("- %s (want %s%s): %s\n", $entry['q'], $entry['page'], isset($entry['section']) ? '#'.$entry['section'] : '', implode(', ', $names));
    }

    return $out;
}
