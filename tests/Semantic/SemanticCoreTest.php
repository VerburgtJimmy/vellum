<?php

declare(strict_types=1);

use Vellum\Semantic\ArrayTable;
use Vellum\Semantic\Encoder;
use Vellum\Semantic\FileVectorCache;
use Vellum\Semantic\Index;
use Vellum\Semantic\Pruner;
use Vellum\Semantic\Quantizer;
use Vellum\Semantic\SemanticBuilder;
use Vellum\Semantic\SemanticSet;
use Vellum\Semantic\WordPieceTokenizer;

function parityFixture(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/../fixtures/semantic/wordpiece-parity.json'), true, flags: JSON_THROW_ON_ERROR);
}

function fixtureTokenizer(): WordPieceTokenizer
{
    $fixture = parityFixture();
    $vocab = [];

    foreach ($fixture['vocab'] as $token => $id) {
        $vocab[(string) $token] = $id;
    }

    return new WordPieceTokenizer($vocab, $fixture['unknown']);
}

/**
 * A deterministic table over the fixture vocabulary.
 */
function fixtureTable(int $dims = 8): ArrayTable
{
    mt_srand(11);
    $rows = [];

    foreach (parityFixture()['vocab'] as $id) {
        $rows[$id] = array_map(static fn (): float => mt_rand(-1000, 1000) / 1000, range(1, $dims));
    }

    return new ArrayTable($rows, $dims);
}

it('tokenizes exactly as Hugging Face tokenizers does', function (): void {
    $tokenizer = fixtureTokenizer();
    $cases = parityFixture()['cases'];

    expect(count($cases))->toBeGreaterThan(200);

    foreach ($cases as $case) {
        expect($tokenizer->ids($case['text']))->toBe($case['ids'], 'Tokenizer parity failed for: '.$case['text']);
    }
});

it('keeps ids when restricted to a subset, and falls back to pieces for pruned words', function (): void {
    $tokenizer = new WordPieceTokenizer(['[UNK]' => 0, 'doc' => 1, '##s' => 2, 'd' => 3, '##o' => 4, '##c' => 5, 'docs' => 6]);

    expect($tokenizer->ids('docs'))->toBe([6])
        ->and($tokenizer->restrictedTo([1 => true, 2 => true])->ids('docs'))->toBe([1, 2])
        ->and($tokenizer->restrictedTo([3 => true, 4 => true, 5 => true])->ids('docs'))->toBe([0])
        ->and($tokenizer->restrictedTo([3 => true, 4 => true, 5 => true, 2 => true])->ids('docs'))->toBe([3, 4, 5, 2])
        ->and($tokenizer->restrictedTo([])->unknownId())->toBe(0);
});

it('round-trips int8 and int4 records within one step of the scale', function (int $bits): void {
    $quantizer = new Quantizer($bits);
    $vector = [0.9, -0.45, 0.0, 0.12, -0.9, 0.3, 0.77];
    $record = $quantizer->pack($vector);
    $restored = $quantizer->unpack($record, count($vector));
    $step = 0.9 / ($bits === 8 ? 127 : 7);

    expect(strlen($record))->toBe($quantizer->recordBytes(count($vector)));

    foreach ($vector as $i => $value) {
        expect(abs($restored[$i] - $value))->toBeLessThanOrEqual($step / 2 + 1e-6);
    }
})->with([8, 4]);

it('packs int4 at half the bytes of int8', function (): void {
    expect((new Quantizer(4))->recordBytes(256))->toBe(4 + 128)
        ->and((new Quantizer(8))->recordBytes(256))->toBe(4 + 256)
        ->and((new Quantizer(4))->recordBytes(7))->toBe(4 + 4);
});

it('refuses other bit widths', function (): void {
    new Quantizer(16);
})->throws(InvalidArgumentException::class);

it('pools, skipping tokens without a row, and normalises', function (): void {
    $rows = [1 => [3.0, 0.0], 2 => [0.0, 4.0]];

    expect(Encoder::pool([1, 2, 99], $rows, 2))->toEqualWithDelta([0.6, 0.8], 1e-9)
        ->and(Encoder::pool([99], $rows, 2))->toBeNull();
});

it('keeps common words and single characters in the base vocabulary', function (): void {
    $tokenizer = fixtureTokenizer();
    $base = (new Pruner(10))->base($tokenizer);
    $byId = array_flip($tokenizer->vocab());
    $tokens = array_map(static fn (int $id): string => (string) $byId[$id], array_keys($base));

    expect($tokens)->toContain('the', 'a', '##s')
        ->and($base)->not->toHaveKey($tokenizer->unknownId())
        ->and(count(array_filter($tokens, static fn (string $t): bool => strlen($t) > 1 && ! str_starts_with($t, '##'))))->toBeLessThanOrEqual(10);
});

it('ranks by cosine', function (): void {
    $index = new Index(['a' => [1.0, 0.0], 'b' => [0.6, 0.8], 'c' => [0.0, 1.0]]);

    expect(array_keys($index->search([1.0, 0.1])))->toBe(['a', 'b', 'c'])
        ->and($index->search([0.0, 1.0], 1))->toHaveKey('c');
});

it('builds a set whose files only carry what a group may see', function (): void {
    $builder = new SemanticBuilder(fixtureTokenizer(), fixtureTable(), new Pruner(5), new Quantizer(4), new Quantizer(8), model: 'fixture', attribution: 'Fixture model, MIT');

    $set = $builder->build([
        ['id' => 'guide#', 'text' => 'Install the package with composer', 'group' => 'guest'],
        ['id' => 'billing#', 'text' => 'supercalifragilisticexpialidocious invoices', 'group' => 'auth'],
    ]);

    $guest = SemanticSet::read($set->forGroups(['guest']));
    $member = SemanticSet::read($set->forGroups(['guest', 'auth']));

    expect($guest['ids'])->toBe(['guide#'])
        ->and($member['ids'])->toBe(['guide#', 'billing#'])
        ->and($guest['attribution'])->toBe('Fixture model, MIT')
        ->and($guest['dims'])->toBe(8)
        ->and(count($guest['vocab']))->toBe(count($guest['tokens']))
        ->and(count($guest['sections']))->toBe(1)
        ->and($guest['tokens'])->toContain('install');

    $private = array_diff($member['tokens'], $guest['tokens']);

    expect($private)->not->toBeEmpty();

    foreach ($private as $token) {
        expect(fixtureTokenizer()->ids('supercalifragilisticexpialidocious invoices'))->toContain(fixtureTokenizer()->vocab()[$token]);
    }
});

it('saves and loads a set without changing its files', function (): void {
    $set = (new SemanticBuilder(fixtureTokenizer(), fixtureTable(), new Pruner(5), new Quantizer(4), new Quantizer(8)))
        ->build([['id' => 'guide#', 'text' => 'Install the package', 'group' => 'guest']]);
    $directory = sys_get_temp_dir().'/vellum-semantic-'.$this->fixtureId();

    $set->save($directory);
    $loaded = SemanticSet::load($directory);

    expect($loaded)->not->toBeNull()
        ->and($loaded->forGroups(['guest']))->toBe($set->forGroups(['guest']));

    $this->deleteDirectory($directory);
});

it('only encodes changed sections on the next build', function (): void {
    $path = sys_get_temp_dir().'/vellum-vectors-'.$this->fixtureId().'/vectors.ser';
    $build = function (array $documents) use ($path): SemanticBuilder {
        $builder = new SemanticBuilder(fixtureTokenizer(), fixtureTable(), new Pruner(5), new Quantizer(4), new Quantizer(8), new FileVectorCache($path), 'fixture');
        $builder->build($documents);

        return $builder;
    };

    $documents = [
        ['id' => 'a#', 'text' => 'Install the package', 'group' => 'guest'],
        ['id' => 'b#', 'text' => 'Open the site', 'group' => 'guest'],
    ];

    expect($build($documents)->encoded)->toBe(2);

    $documents[1]['text'] = 'Open the docs site';

    expect($build($documents)->encoded)->toBe(1)
        ->and($build($documents)->encoded)->toBe(0);

    $this->deleteDirectory(dirname($path));
});
