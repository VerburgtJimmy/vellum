<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Vellum\Answers\Questions\QuestionGenerators;
use Vellum\Answers\Sections;
use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;
use Vellum\Content\Document;
use Vellum\Semantic\SafetensorsTable;
use Vellum\Semantic\WordPieceTokenizer as WordPiece;
use Vellum\Tests\Evaluation\Bm25;
use Vellum\Tests\Evaluation\Metrics;
use Vellum\Tests\Evaluation\Vectors;

/*
 * Second evaluation, on the held-out set. Run with:
 *
 *   VELLUM_EVAL=1 VELLUM_EVAL_MODELS=storage/vellum/models vendor/bin/pest tests/Evaluation
 *
 * VELLUM_EVAL_SET=golden runs the first set instead, for reference only.
 * VELLUM_EVAL_SET=third runs the third set, which decides boosted against
 * weighted RRF: RRF wins if paraphrase R@1 improves by 5 points or more with
 * literal R@5 unchanged, both on the int4 vocab.
 *
 * Fixed before any run, and not to be tuned against results:
 * - potion-base-8M, no PCA; vocab = docs tokens + first 1000 alphabetic words
 *   of the model's frequency-ordered vocab + single characters.
 * - Vocab int8 or int4 with a float32 scale per row; section vectors int8.
 * - Generated questions appended to section text, for BM25 and the embedding.
 * - Primary ranking: cosine + 0.1 * BM25 / best BM25 + 0.1 if the section's
 *   own heading (or the page title, for a page's opening section) appears in
 *   the query as a phrase.
 * - Comparison: weighted RRF, semantic 2, BM25 1, k 60.
 *
 * Go: held-out paraphrase R@5 >= 50%, literal R@5 >= plain BM25's, semantic
 * file <= 600 KB gzipped, build < 5 s with no fit step.
 */

const EVAL2_MODEL = 'potion-base-8M';
const EVAL2_COMMON = 1000;
const EVAL2_TERM_BOOST = 0.1;
const EVAL2_TITLE_BOOST = 0.1;
const EVAL2_RRF_K = 60;
const EVAL2_RRF_WEIGHTS = ['semantic' => 2.0, 'bm25' => 1.0];

it('reports the second evaluation', function (): void {
    $models = rtrim((string) getenv('VELLUM_EVAL_MODELS'), '/');
    $set = in_array(getenv('VELLUM_EVAL_SET'), ['golden', 'third'], true) ? (string) getenv('VELLUM_EVAL_SET') : 'heldout';
    $docs = (string) realpath(__DIR__.'/../../docs');
    $golden = match ($set) {
        'golden' => Yaml::parseFile($docs.'/questions.yml'),
        'heldout' => Yaml::parseFile(__DIR__.'/heldout.yml'),
        // The third set's own paraphrases, with every literal question so far.
        'third' => [
            ...array_values(array_filter([...Yaml::parseFile($docs.'/questions.yml'), ...Yaml::parseFile(__DIR__.'/heldout.yml')], static fn (array $e): bool => $e['kind'] === 'literal')),
            ...Yaml::parseFile(__DIR__.'/third.yml'),
        ],
    };
    $blank = array_filter($golden, static fn (array $entry): bool => trim((string) $entry['q']) === '');

    if ($blank !== []) {
        $this->fail(count($blank).' held-out questions are still blank.');
    }

    $repository = new ContentRepository(contentPath: $docs, store: new CompiledStore($this->cachePath()));
    $documents = $repository->buildAll();

    $rankings = [];
    $sizes = [];
    $timings = [];
    $sections = [];

    foreach (['int8' => 8, 'int4' => 4] as $scheme => $bits) {
        $out = sys_get_temp_dir().'/vellum-semantic-'.$scheme.'.bin';
        $started = hrtime(true);
        $build = eval2Build($documents, "{$models}/".EVAL2_MODEL, $bits, $out);
        $timings[$scheme] = (hrtime(true) - $started) / 1e9;
        $sizes[$scheme] = ['bytes' => filesize($out), 'gz' => strlen((string) gzencode((string) file_get_contents($out), 9)), 'tokens' => count($build['tokens'])];
        $sections = $build['sections'];

        $plain = new Bm25(array_map(static fn (array $s): array => ['title' => $s['title'], 'heading' => $s['heading'], 'text' => $s['text']], $sections), ['title' => 3.0, 'heading' => 2.0, 'text' => 1.0]);
        $expanded = new Bm25(array_map(static fn (array $s): array => ['title' => $s['title'], 'heading' => $s['heading'], 'text' => $s['text'].' '.implode(' ', $s['generated'])], $sections), ['title' => 3.0, 'heading' => 2.0, 'text' => 1.0]);

        foreach ($golden as $index => $entry) {
            $bm25 = $expanded->search($entry['q']);
            $semantic = eval2Cosines($entry['q'], $build);
            arsort($semantic);

            if ($scheme === 'int8') {
                $rankings['BM25 (today)'][$index] = array_keys($plain->search($entry['q']));
                $rankings['BM25 + expansion'][$index] = array_keys($bm25);
            }

            $rankings["Semantic, {$scheme} vocab"][$index] = array_keys($semantic);

            $best = $bm25 === [] ? 0.0 : max($bm25);
            $queryTerms = ' '.implode(' ', Bm25::terms($entry['q'])).' ';
            $boosted = [];

            foreach ($semantic as $id => $cosine) {
                $own = $sections[$id]['own'] !== '' ? $sections[$id]['own'] : $sections[$id]['title'];
                $phrase = implode(' ', Bm25::terms($own));
                $title = $phrase !== '' && str_contains($queryTerms, ' '.$phrase.' ');
                $boosted[$id] = $cosine
                    + ($best > 0 ? EVAL2_TERM_BOOST * ($bm25[$id] ?? 0.0) / $best : 0.0)
                    + ($title ? EVAL2_TITLE_BOOST : 0.0);
            }

            arsort($boosted);
            $rankings["Semantic + BM25 boost, {$scheme} vocab"][$index] = array_keys($boosted);

            $fused = [];

            foreach (['semantic' => array_keys($semantic), 'bm25' => array_keys($bm25)] as $signal => $list) {
                foreach ($list as $rank => $id) {
                    $fused[$id] = ($fused[$id] ?? 0.0) + EVAL2_RRF_WEIGHTS[$signal] / (EVAL2_RRF_K + $rank + 1);
                }
            }

            arsort($fused);
            $rankings["Weighted RRF, {$scheme} vocab"][$index] = array_keys($fused);
        }
    }

    $report = eval2Report($set, $rankings, $golden, $sections, $sizes, $timings);
    file_put_contents(__DIR__."/second-evaluation-{$set}.md", $report);
    fwrite(STDERR, $report);

    expect($rankings)->not->toBeEmpty();
})->skip(fn (): bool => getenv('VELLUM_EVAL') !== '1', 'Set VELLUM_EVAL=1 and VELLUM_EVAL_MODELS to run the second evaluation.');

/**
 * Everything the build step does, from compiled documents to a written file.
 *
 * @param  list<Document>  $documents
 * @return array{sections: list<array<string, mixed>>, tokens: list<string>, tokenizer: WordPiece, vocab: array<int, list<float>>, vectors: array<int, list<float>>, dims: int}
 */
function eval2Build(array $documents, string $model, int $bits, string $out): array
{
    $sections = Sections::from($documents);

    foreach ($sections as $id => $section) {
        $sections[$id]['generated'] = QuestionGenerators::default()->for($section);
    }

    $full = WordPiece::fromTokenizerJson($model.'/tokenizer.json');
    $vocab = $full->vocab();
    $unknown = $vocab['[UNK]'];
    $texts = array_map(static fn (array $s): string => trim($s['title'].' '.$s['heading'].' '.$s['text'].' '.implode(' ', $s['generated'])), $sections);
    $ids = array_map($full->ids(...), $texts);
    $keep = [];

    foreach ($ids as $list) {
        foreach ($list as $id) {
            $keep[$id] = true;
        }
    }

    $byId = array_flip($vocab);
    ksort($byId);
    $common = 0;

    foreach ($byId as $id => $token) {
        if ($id < $vocab['the'] || $common >= EVAL2_COMMON) {
            if ($common >= EVAL2_COMMON) {
                break;
            }

            continue;
        }

        if (preg_match('/^[a-z]+$/', (string) $token) === 1) {
            $keep[$id] = true;
            $common++;
        }
    }

    foreach ($vocab as $token => $id) {
        if (preg_match('/^(##)?[a-z0-9]$/', (string) $token) === 1) {
            $keep[$id] = true;
        }
    }

    unset($keep[$unknown]);
    ksort($keep);
    $table = new SafetensorsTable($model.'/model.safetensors');
    $rows = $table->rows(array_keys($keep));
    $dims = $table->dims;

    $binary = '';
    $stored = [];

    foreach ($rows as $id => $row) {
        [$packed, $restored] = eval2Quantise($row, $bits);
        $binary .= $packed;
        $stored[$id] = $restored;
    }

    $vectors = [];

    foreach ($ids as $sectionId => $list) {
        $vector = Vectors::pool($list, $rows, $dims) ?? array_fill(0, $dims, 0.0);
        [$packed, $restored] = eval2Quantise($vector, 8);
        $binary .= $packed;
        $vectors[$sectionId] = $restored;
    }

    $tokens = array_map(static fn (int $id): string => (string) $byId[$id], array_keys($keep));
    $header = json_encode(['model' => basename($model), 'dims' => $dims, 'bits' => $bits, 'tokens' => count($tokens), 'sections' => count($sections)]);
    file_put_contents($out, pack('V', strlen((string) $header)).$header.implode("\n", $tokens)."\0".$binary);

    $pruned = new WordPiece(array_filter($vocab, static fn (int $id): bool => isset($keep[$id]) || $id === $unknown));

    return ['sections' => $sections, 'tokens' => $tokens, 'tokenizer' => $pruned, 'vocab' => $stored, 'vectors' => $vectors, 'dims' => $dims];
}

/**
 * Pack a vector with a float32 scale per row, and return what the reader gets back.
 *
 * @param  list<float>  $vector
 * @return array{0: string, 1: list<float>}
 */
function eval2Quantise(array $vector, int $bits): array
{
    $levels = $bits === 8 ? 127 : 7;
    $max = max(array_map('abs', $vector)) ?: 1.0;
    $scale = $max / $levels;
    $q = array_map(static fn (float $v): int => (int) max(-$levels, min($levels, round($v / $scale))), $vector);

    if ($bits === 8) {
        $packed = pack('g', $scale).pack('c*', ...$q);
    } else {
        $bytes = '';

        for ($i = 0; $i < count($q); $i += 2) {
            $bytes .= chr((($q[$i] + 8) << 4) | (($q[$i + 1] ?? 0) + 8));
        }

        $packed = pack('g', $scale).$bytes;
    }

    return [$packed, array_map(static fn (int $v): float => $v * $scale, $q)];
}

/**
 * @param  array{tokenizer: WordPiece, vocab: array<int, list<float>>, vectors: array<int, list<float>>, dims: int}  $build
 * @return array<int, float>
 */
function eval2Cosines(string $query, array $build): array
{
    $vector = Vectors::pool($build['tokenizer']->ids($query), $build['vocab'], $build['dims']);

    if ($vector === null) {
        return [];
    }

    $scores = [];

    foreach ($build['vectors'] as $id => $section) {
        $scores[$id] = Vectors::dot($vector, Vectors::normalize($section));
    }

    return $scores;
}

function eval2Report(string $set, array $rankings, array $golden, array $sections, array $sizes, array $timings): string
{
    $pct = static fn (float $v): string => sprintf('%.0f%%', $v * 100);
    $recalls = array_map(static fn (array $ranking): array => Metrics::recall($ranking, $golden, $sections), $rankings);
    $counts = array_count_values(array_column($golden, 'kind'));

    $out = "# Second evaluation ({$set} set)\n\n";
    $out .= sprintf("%d sections, %d questions (%d literal, %d paraphrase). Model %s.\n\n", count($sections), count($golden), $counts['literal'] ?? 0, $counts['paraphrase'] ?? 0, EVAL2_MODEL);
    $out .= "| Ranking | R@1 | R@5 | Literal R@1 | Literal R@5 | Paraphrase R@1 | Paraphrase R@5 |\n| --- | --- | --- | --- | --- | --- | --- |\n";

    foreach ($recalls as $name => $r) {
        $out .= sprintf("| %s | %s | %s | %s | %s | %s | %s |\n", $name, $pct($r['all'][1]), $pct($r['all'][5]), $pct($r['literal'][1] ?? 0.0), $pct($r['literal'][5] ?? 0.0), $pct($r['paraphrase'][1] ?? 0.0), $pct($r['paraphrase'][5] ?? 0.0));
    }

    $out .= "\n| Semantic file | Tokens | Size | Gzipped | Build |\n| --- | --- | --- | --- | --- |\n";

    foreach ($sizes as $scheme => $size) {
        $out .= sprintf("| %s vocab, int8 sections | %s | %.0f KB | %.0f KB | %.2f s |\n", $scheme, number_format($size['tokens']), $size['bytes'] / 1024, $size['gz'] / 1024, $timings[$scheme]);
    }

    $plainLiteral = $recalls['BM25 (today)']['literal'][5] ?? 0.0;
    $out .= "\n## Go criteria, primary ranking\n\n";

    foreach (['int8', 'int4'] as $scheme) {
        foreach (["Semantic + BM25 boost, {$scheme} vocab", "Weighted RRF, {$scheme} vocab"] as $name) {
            $r = $recalls[$name];
            $out .= sprintf(
                "- %s: paraphrase R@5 %s (need 50%%) %s; literal R@5 %s vs BM25 %s %s; file %.0f KB gz (max 600) %s; build %.2f s (max 5) %s\n",
                $name,
                $pct($r['paraphrase'][5] ?? 0.0), ($r['paraphrase'][5] ?? 0.0) >= 0.5 ? 'pass' : 'FAIL',
                $pct($r['literal'][5] ?? 0.0), $pct($plainLiteral), ($r['literal'][5] ?? 0.0) >= $plainLiteral ? 'pass' : 'FAIL',
                $sizes[$scheme]['gz'] / 1024, $sizes[$scheme]['gz'] <= 600 * 1024 ? 'pass' : 'FAIL',
                $timings[$scheme], $timings[$scheme] < 5 ? 'pass' : 'FAIL',
            );
        }
    }

    if ($set === 'third') {
        $boosted = $recalls['Semantic + BM25 boost, int4 vocab'];
        $rrf = $recalls['Weighted RRF, int4 vocab'];
        $gain = (($rrf['paraphrase'][1] ?? 0.0) - ($boosted['paraphrase'][1] ?? 0.0)) * 100;
        $same = abs(($rrf['literal'][5] ?? 0.0) - ($boosted['literal'][5] ?? 0.0)) < 1e-9;
        $out .= sprintf(
            "\n## Third-set decision\n\nParaphrase R@1: boosted %s, weighted RRF %s (%+.0f points, need +5). Literal R@5: boosted %s, weighted RRF %s (%s). Winner: %s.\n",
            $pct($boosted['paraphrase'][1] ?? 0.0), $pct($rrf['paraphrase'][1] ?? 0.0), $gain,
            $pct($boosted['literal'][5] ?? 0.0), $pct($rrf['literal'][5] ?? 0.0), $same ? 'unchanged' : 'changed',
            $gain >= 5 - 1e-9 && $same ? 'weighted RRF' : 'boosted',
        );
    }

    $primary = $rankings['Semantic + BM25 boost, int8 vocab'];
    $out .= "\n## Paraphrase misses at 5, semantic + BM25 boost, int8\n\n";

    foreach ($golden as $index => $entry) {
        if ($entry['kind'] !== 'paraphrase') {
            continue;
        }

        $top = array_slice($primary[$index] ?? [], 0, 5);

        if (array_filter($top, static fn (int $id): bool => Metrics::hits($entry, $sections[$id])) !== []) {
            continue;
        }

        $names = array_map(static fn (int $id): string => $sections[$id]['page'].($sections[$id]['anchor'] !== '' ? '#'.$sections[$id]['anchor'] : ''), array_slice($top, 0, 3));
        $out .= sprintf("- %s (want %s%s): %s\n", $entry['q'], $entry['page'], isset($entry['section']) ? '#'.$entry['section'] : '', implode(', ', $names));
    }

    return $out;
}
