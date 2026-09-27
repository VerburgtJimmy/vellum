<?php

declare(strict_types=1);

namespace Vellum\Search;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * Scout record for a page or heading. Not stored in a SQL table.
 *
 * @property string $id
 * @property string $title
 * @property string $content
 * @property string $url
 * @property string $description
 * @property string $access
 * @property string|null $version
 */
final class SearchableDocument extends Model
{
    use Searchable;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $guarded = [];

    public function getTable(): string
    {
        return 'vellum_search_documents';
    }

    public function searchableAs(): string
    {
        $index = config('vellum.search.scout.index', 'vellum');

        return is_string($index) && $index !== '' ? $index : 'vellum';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->getKey(),
            'title' => (string) $this->getAttribute('title'),
            'content' => (string) $this->getAttribute('content'),
            'url' => (string) $this->getAttribute('url'),
            'description' => (string) $this->getAttribute('description'),
            'access' => (string) ($this->getAttribute('access') ?: 'guest'),
            'version' => $this->getAttribute('version'),
        ];
    }
}
