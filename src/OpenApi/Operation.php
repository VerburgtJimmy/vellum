<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Vellum\Support\Slug;

/**
 * One method on one path, with its path-level inheritance already applied.
 *
 * @phpstan-type Parameters list<array<string, mixed>>
 */
final readonly class Operation
{
    /**
     * @param  list<string>  $tags
     * @param  Parameters  $parameters
     * @param  array<string, mixed>|null  $requestBody
     * @param  array<string, mixed>  $responses
     * @param  list<array<array-key, mixed>>|null  $security  Keys are scheme names in a
     *                                                        valid spec, but the document is
     *                                                        JSON and may say otherwise.
     * @param  list<array<string, mixed>>  $servers
     */
    public function __construct(
        public string $method,
        public string $path,
        public ?string $operationId = null,
        public ?string $summary = null,
        public ?string $description = null,
        public array $tags = [],
        public bool $deprecated = false,
        public array $parameters = [],
        public ?array $requestBody = null,
        public array $responses = [],
        public ?array $security = null,
        public array $servers = [],
    ) {}

    /**
     * Heading text, falling back the way readers expect: what the author
     * wrote, then the machine name, then the request line itself.
     */
    public function title(): string
    {
        if ($this->summary !== null && trim($this->summary) !== '') {
            return $this->summary;
        }

        if ($this->operationId !== null && trim($this->operationId) !== '') {
            return $this->operationId;
        }

        return $this->method.' '.$this->path;
    }

    /**
     * Anchor for this operation's heading.
     *
     * Derived from method and path rather than the summary, so editing a
     * summary does not break every link anyone has shared. Path parameters
     * collapse to their names: /pets/{petId} and /pets/{id} are different
     * endpoints and keep different ids.
     */
    public function headingId(): string
    {
        $path = Slug::from(str_replace('/', '-', $this->path));

        return strtolower($this->method).'-'.($path === '' ? 'root' : str_replace('/', '-', $path));
    }

    /**
     * Parameters in the section of the request they belong to.
     *
     * @return array<string, Parameters>
     */
    public function parametersByLocation(): array
    {
        $grouped = ['path' => [], 'query' => [], 'header' => [], 'cookie' => []];

        foreach ($this->parameters as $parameter) {
            $in = is_string($parameter['in'] ?? null) ? $parameter['in'] : null;

            if ($in !== null && array_key_exists($in, $grouped)) {
                $grouped[$in][] = $parameter;
            }
        }

        return array_filter($grouped, static fn (array $group): bool => $group !== []);
    }
}
