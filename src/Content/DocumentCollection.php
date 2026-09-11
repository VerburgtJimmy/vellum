<?php

declare(strict_types=1);

namespace Vellum\Content;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Ordered collection of documents keyed by slug.
 *
 * @implements IteratorAggregate<string, Document>
 */
final class DocumentCollection implements Countable, IteratorAggregate
{
    /** @var array<string, Document> */
    private array $documents = [];

    /**
     * @param  iterable<Document>  $documents
     */
    public function __construct(iterable $documents = [])
    {
        foreach ($documents as $document) {
            $this->add($document);
        }
    }

    public function add(Document $document): void
    {
        $this->documents[$document->slug] = $document;
    }

    public function get(string $slug): ?Document
    {
        return $this->documents[$slug] ?? null;
    }

    public function has(string $slug): bool
    {
        return isset($this->documents[$slug]);
    }

    /**
     * @return list<Document>
     */
    public function all(): array
    {
        return array_values($this->documents);
    }

    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_keys($this->documents);
    }

    public function count(): int
    {
        return count($this->documents);
    }

    /**
     * @return Traversable<string, Document>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->documents);
    }
}
