<?php

declare(strict_types=1);

namespace Vellum\Semantic;

/**
 * The query side of a semantic file: tokenise with the vocabulary the file
 * carries, pool the rows, and score every section by cosine. This is the
 * reference the browser's copy is held to.
 */
final class SemanticQuery
{
    private readonly WordPieceTokenizer $tokenizer;

    /** @var array<int, list<float>> */
    private readonly array $rows;

    private readonly int $dims;

    /** @var list<string> */
    public readonly array $ids;

    /** @var list<list<float>> */
    private readonly array $vectors;

    public readonly string $attribution;

    public function __construct(string $bytes)
    {
        $file = SemanticSet::read($bytes);
        $vocab = ['[UNK]' => -1];

        foreach ($file['tokens'] as $index => $token) {
            $vocab[$token] = $index;
        }

        $this->tokenizer = new WordPieceTokenizer($vocab);
        $this->rows = $file['vocab'];
        $this->dims = $file['dims'];
        $this->ids = $file['ids'];
        $this->vectors = array_map(Encoder::normalize(...), $file['sections']);
        $this->attribution = $file['attribution'];
    }

    public static function fromFile(string $path): ?self
    {
        return is_file($path) ? new self((string) file_get_contents($path)) : null;
    }

    /**
     * @return list<float>|null
     */
    public function encode(string $text): ?array
    {
        return Encoder::pool($this->tokenizer->ids($text), $this->rows, $this->dims);
    }

    /**
     * Cosine per section id. A query with no known token scores nothing.
     *
     * @return array<string, float>
     */
    public function scores(string $query): array
    {
        $vector = $this->encode($query);

        if ($vector === null) {
            return [];
        }

        $scores = [];

        foreach ($this->vectors as $index => $section) {
            $scores[$this->ids[$index]] = Encoder::dot($vector, $section);
        }

        return $scores;
    }
}
