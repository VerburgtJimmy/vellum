<?php

declare(strict_types=1);

namespace Vellum\Semantic;

/**
 * Builds a SemanticSet from documents: tokenise, prune the vocabulary, read
 * only the rows that survive, pool each document, quantise. No fitting step,
 * so the cost is one pass over the text and one read per kept row.
 */
final class SemanticBuilder
{
    public int $encoded = 0;

    public function __construct(
        private readonly Tokenizer $tokenizer,
        private readonly EmbeddingTable $table,
        private readonly Pruner $pruner,
        private readonly Quantizer $vocabQuantizer,
        private readonly Quantizer $sectionQuantizer,
        private readonly ?VectorCache $cache = null,
        private readonly string $model = '',
        private readonly string $attribution = '',
    ) {}

    /**
     * @param  list<array{id: string, text: string, group: string}>  $documents
     */
    public function build(array $documents): SemanticSet
    {
        $this->encoded = 0;
        $unknown = $this->tokenizer->unknownId();
        $entries = [];
        $groups = [];
        $pending = [];

        foreach ($documents as $index => $document) {
            $key = hash('xxh128', $this->model."\0".$document['text']);
            $entry = $this->cache?->get($key);

            if ($entry === null) {
                $entry = ['ids' => $this->tokenizer->ids($document['text']), 'vector' => null];
                $pending[$index] = $key;
            }

            $entries[$index] = $entry;

            foreach ($entry['ids'] as $id) {
                if ($id !== $unknown) {
                    $groups[$id][$document['group']] = true;
                }
            }
        }

        $base = $this->pruner->base($this->tokenizer);
        $keep = $base + array_fill_keys(array_keys($groups), true);
        ksort($keep);
        $rows = $this->table->rows(array_keys($keep));
        $dims = $this->table->dims();

        foreach ($pending as $index => $key) {
            $entry = $entries[$index];
            $entry['vector'] = Encoder::pool($entry['ids'], $rows, $dims);
            $entries[$index] = $entry;
            $this->cache?->put($key, $entry);
            $this->encoded++;
        }

        $this->cache?->flush();

        $byId = array_flip($this->tokenizer->vocab());
        $tokens = [];
        $tokenGroups = [];
        $vocabRecords = [];

        foreach ($rows as $id => $row) {
            $tokens[] = (string) $byId[$id];
            $tokenGroups[] = isset($base[$id]) ? [] : array_map('strval', array_keys($groups[$id] ?? []));
            $vocabRecords[] = $this->vocabQuantizer->pack($row);
        }

        $sections = [];
        $sectionRecords = [];

        foreach ($documents as $index => $document) {
            $sections[] = ['id' => $document['id'], 'group' => $document['group']];
            $sectionRecords[] = $this->sectionQuantizer->pack($entries[$index]['vector'] ?? array_fill(0, $dims, 0.0));
        }

        return new SemanticSet(
            $this->model,
            $this->attribution,
            $dims,
            $this->vocabQuantizer->bits,
            $this->sectionQuantizer->bits,
            $tokens,
            $tokenGroups,
            $vocabRecords,
            $sections,
            $sectionRecords,
        );
    }
}
