<?php

declare(strict_types=1);

namespace Vellum\OpenApi\Samples;

/**
 * Everything a sample needs, worked out once so each language only has to
 * decide how to write it down.
 */
final readonly class SampleRequest
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, string>  $query
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers = [],
        public array $query = [],
        public mixed $body = null,
        public ?string $contentType = null,
    ) {}

    public function fullUrl(): string
    {
        return $this->query === [] ? $this->url : $this->url.'?'.http_build_query($this->query);
    }

    public function hasBody(): bool
    {
        return $this->body !== null;
    }

    public function json(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): string
    {
        return (string) json_encode($this->body, $flags);
    }
}
