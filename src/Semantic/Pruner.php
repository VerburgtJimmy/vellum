<?php

declare(strict_types=1);

namespace Vellum\Semantic;

/**
 * Chooses the vocabulary rows a site ships: every token its documents use,
 * the most common whole words, and single characters, so any ASCII word a
 * reader types still breaks into pieces that have vectors.
 */
final class Pruner
{
    public function __construct(private readonly int $commonTokens = 1000) {}

    /**
     * Tokens kept whatever the documents say. The vocabulary is taken to be in
     * the frequency order BERT vocabularies use from "the" onwards.
     *
     * @return array<int, true>
     */
    public function base(Tokenizer $tokenizer): array
    {
        $vocab = $tokenizer->vocab();
        $byId = [];

        foreach ($vocab as $token => $id) {
            $byId[$id] = (string) $token;
        }

        ksort($byId);
        $start = $vocab['the'] ?? 0;
        $keep = [];
        $common = 0;

        foreach ($byId as $id => $token) {
            if (preg_match('/^(##)?[a-z0-9]$/', $token) === 1) {
                $keep[$id] = true;
            }

            if ($id >= $start && $common < $this->commonTokens && preg_match('/^[a-z]+$/', $token) === 1) {
                $keep[$id] = true;
                $common++;
            }
        }

        unset($keep[$tokenizer->unknownId()]);

        return $keep;
    }
}
