<?php

declare(strict_types=1);

namespace Vellum\OpenApi\Samples;

use Vellum\OpenApi\ExampleGenerator;
use Vellum\OpenApi\Operation;

/**
 * Builds the request once, then asks each configured language to write it.
 *
 * @phpstan-type Sample array{key: string, label: string, highlight: string, code: string}
 */
final class SampleBuilder
{
    /** @var array<string, class-string<SampleGenerator>> */
    private const BUILT_IN = [
        'curl' => CurlGenerator::class,
        'php' => PhpGenerator::class,
        'javascript' => JavaScriptGenerator::class,
    ];

    /**
     * @param  list<array{name: string, hint: string, detail: string}>  $security
     * @return list<Sample>
     */
    public function build(Operation $operation, ?string $serverUrl, array $security): array
    {
        $request = $this->request($operation, $serverUrl, $security);
        $samples = [];

        foreach ($this->generators() as $key => $generator) {
            $samples[] = [
                'key' => $key,
                'label' => $generator->label(),
                'highlight' => $generator->highlight(),
                'code' => $generator->generate($request),
            ];
        }

        return $samples;
    }

    /**
     * @param  list<array{name: string, hint: string, detail: string}>  $security
     */
    private function request(Operation $operation, ?string $serverUrl, array $security): SampleRequest
    {
        $grouped = $operation->parametersByLocation();
        $headers = [];
        $query = [];

        foreach ($security as $scheme) {
            [$name, $value] = array_pad(explode(':', $scheme['detail'], 2), 2, '');
            $headers[trim($name)] = trim($value);
        }

        foreach ($grouped['header'] ?? [] as $parameter) {
            if (is_string($parameter['name'] ?? null)) {
                $headers[$parameter['name']] = $this->parameterValue($parameter);
            }
        }

        foreach ($grouped['query'] ?? [] as $parameter) {
            // Optional query parameters clutter a sample; show what is needed.
            if (! empty($parameter['required']) && is_string($parameter['name'] ?? null)) {
                $query[$parameter['name']] = $this->parameterValue($parameter);
            }
        }

        [$body, $contentType] = $this->body($operation);

        if ($contentType !== null) {
            $headers['Content-Type'] = $contentType;
        }

        return new SampleRequest(
            method: $operation->method,
            url: ($serverUrl ?? '').$operation->path,
            headers: $headers,
            query: $query,
            body: $body,
            contentType: $contentType,
        );
    }

    /**
     * @return array{0: mixed, 1: string|null}
     */
    private function body(Operation $operation): array
    {
        $content = is_array($operation->requestBody['content'] ?? null) ? $operation->requestBody['content'] : [];

        if ($content === []) {
            return [null, null];
        }

        // JSON when it is on offer, since that is what the sample can show.
        foreach ($content as $type => $media) {
            if (is_string($type) && str_contains($type, 'json') && is_array($media)) {
                return [ExampleGenerator::forMedia($media), $type];
            }
        }

        $type = (string) array_key_first($content);
        $media = $content[$type];

        return [is_array($media) ? ExampleGenerator::forMedia($media) : null, $type];
    }

    /**
     * @param  array<string, mixed>  $parameter
     */
    private function parameterValue(array $parameter): string
    {
        if (array_key_exists('example', $parameter)) {
            return (string) $this->stringify($parameter['example']);
        }

        $schema = is_array($parameter['schema'] ?? null) ? $parameter['schema'] : [];

        return (string) $this->stringify(ExampleGenerator::fromSchema($schema));
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => (string) json_encode($value),
            $value === null => '',
            default => (string) $value,
        };
    }

    /**
     * @return array<string, SampleGenerator>
     */
    private function generators(): array
    {
        $configured = config('vellum.openapi.samples', ['curl']);
        $configured = is_array($configured) ? $configured : ['curl'];
        $out = [];

        foreach ($configured as $key => $value) {
            // Either a built-in key, or a key mapped to a class of your own.
            $name = is_string($key) ? $key : (string) $value;
            $class = is_string($key) ? $value : (self::BUILT_IN[$value] ?? null);

            if (! is_string($class) || ! class_exists($class) || ! is_a($class, SampleGenerator::class, true)) {
                continue;
            }

            $out[$name] = new $class;
        }

        return $out;
    }
}
