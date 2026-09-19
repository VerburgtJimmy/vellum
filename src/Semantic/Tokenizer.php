<?php

declare(strict_types=1);

namespace Vellum\Semantic;

/**
 * Text to vocabulary ids.
 */
interface Tokenizer
{
    /**
     * @return list<int>
     */
    public function ids(string $text): array;

    /**
     * @return array<string, int> token => id
     */
    public function vocab(): array;

    /**
     * A tokenizer over a subset of this one's vocabulary. Ids keep their values.
     *
     * @param  array<int, true>  $ids
     */
    public function restrictedTo(array $ids): self;

    public function unknownId(): int;
}
