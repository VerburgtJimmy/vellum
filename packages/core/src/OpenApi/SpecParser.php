<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Vellum\Exceptions\InvalidSpecException;

/**
 * Reads an OpenAPI 3.0, 3.1 or 3.2 document from disk into a resolved Spec.
 *
 * Format comes from the file extension, falling back to sniffing the first
 * non-blank character, because generated specs are not always named well.
 */
final class SpecParser
{
    /**
     * Methods a path item may define. Anything else at that level is a
     * sibling like summary, parameters or servers.
     */
    private const METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace', 'query'];

    public function parseFile(string $path): Spec
    {
        if (! is_file($path)) {
            throw InvalidSpecException::unreadable($path);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw InvalidSpecException::unreadable($path);
        }

        return $this->parse($contents, $path);
    }

    public function parse(string $contents, string $path): Spec
    {
        $document = $this->decode($contents, $path);

        $version = $document['openapi'] ?? null;

        if (! is_string($version) || trim($version) === '') {
            throw InvalidSpecException::missing($path, 'an "openapi" version string');
        }

        // 3.2 only adds to 3.1, so a reader of 3.1 reads it; what it adds is
        // read where it is understood and passed over where it is not.
        if (! str_starts_with($version, '3.0') && ! str_starts_with($version, '3.1') && ! str_starts_with($version, '3.2')) {
            throw InvalidSpecException::unsupportedVersion($path, $version);
        }

        if (! array_key_exists('paths', $document)) {
            throw InvalidSpecException::missing($path, 'a "paths" object');
        }

        if (! is_array($document['paths'])) {
            throw InvalidSpecException::missing($path, 'a "paths" object');
        }

        $resolver = new RefResolver($path);
        $document = $resolver->resolve($document);

        return new Spec(
            path: $path,
            version: $version,
            document: $document,
            operations: $this->operations($document),
            warnings: $resolver->warnings(),
            webhooks: $this->webhooks($document),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $contents, string $path): array
    {
        if (trim($contents) === '') {
            throw InvalidSpecException::unparsable($path, 'the file is empty');
        }

        $document = $this->looksLikeJson($contents, $path)
            ? $this->decodeJson($contents, $path)
            : $this->decodeYaml($contents, $path);

        if (! is_array($document)) {
            throw InvalidSpecException::unparsable($path, 'the document is not an object');
        }

        /** @var array<string, mixed> $document */
        return $document;
    }

    private function looksLikeJson(string $contents, string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'json') {
            return true;
        }

        if ($extension === 'yaml' || $extension === 'yml') {
            return false;
        }

        return str_starts_with(ltrim($contents), '{');
    }

    private function decodeJson(string $contents, string $path): mixed
    {
        try {
            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw InvalidSpecException::unparsable($path, $e->getMessage(), $e);
        }
    }

    private function decodeYaml(string $contents, string $path): mixed
    {
        try {
            return Yaml::parse($contents);
        } catch (ParseException $e) {
            throw InvalidSpecException::unparsable($path, $e->getMessage(), $e);
        }
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<Operation>
     */
    private function operations(array $document): array
    {
        $paths = is_array($document['paths'] ?? null) ? $document['paths'] : [];
        $operations = [];

        foreach ($paths as $path => $item) {
            if (! is_string($path) || ! is_array($item)) {
                continue;
            }

            // A path item may carry parameters and servers for every method on it.
            $shared = is_array($item['parameters'] ?? null) ? array_values($item['parameters']) : [];
            $sharedServers = is_array($item['servers'] ?? null) ? array_values($item['servers']) : [];

            foreach (self::METHODS as $method) {
                if (! is_array($item[$method] ?? null)) {
                    continue;
                }

                $operations[] = $this->operation($method, $path, $item[$method], $shared, $sharedServers);
            }

            // Methods of the API's own, keyed as they are sent. One that
            // repeats a method with a field of its own is the spec's mistake,
            // and the field wins.
            foreach (is_array($item['additionalOperations'] ?? null) ? $item['additionalOperations'] : [] as $method => $operation) {
                $method = (string) $method;

                if (! is_array($operation) || preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $method) !== 1 || in_array(strtolower($method), self::METHODS, true)) {
                    continue;
                }

                $operations[] = $this->operation($method, $path, $operation, $shared, $sharedServers);
            }
        }

        return $operations;
    }

    /**
     * OpenAPI 3.1's webhooks: requests the API sends to a URL the reader
     * gives it. Each is named, not addressed, and the name stands in for a path.
     *
     * @param  array<string, mixed>  $document
     * @return list<Operation>
     */
    private function webhooks(array $document): array
    {
        $webhooks = [];

        foreach (is_array($document['webhooks'] ?? null) ? $document['webhooks'] : [] as $name => $item) {
            foreach (is_array($item) ? $this->methods($item) : [] as $method => $operation) {
                $webhooks[] = $this->operation($method, (string) $name, $operation, $this->shared($item), [], webhook: (string) $name);
            }
        }

        return $webhooks;
    }

    /**
     * Callbacks: requests the API sends back once an operation is made, to
     * an address worked out from it. They are read one level deep: a
     * callback of a callback is not something a reader can act on here.
     *
     * @param  array<string, mixed>  $operation
     * @return list<array{name: string, operation: Operation}>
     */
    private function callbacks(array $operation): array
    {
        $callbacks = [];

        foreach (is_array($operation['callbacks'] ?? null) ? $operation['callbacks'] : [] as $name => $expressions) {
            foreach (is_array($expressions) ? $expressions : [] as $expression => $item) {
                foreach (is_array($item) ? $this->methods($item) : [] as $method => $callback) {
                    unset($callback['callbacks']);

                    $callbacks[] = [
                        'name' => (string) $name,
                        'operation' => $this->operation($method, (string) $expression, $callback, $this->shared($item), []),
                    ];
                }
            }
        }

        return $callbacks;
    }

    /**
     * The operations of a path item, by method.
     *
     * @param  array<array-key, mixed>  $item
     * @return array<string, array<string, mixed>>
     */
    private function methods(array $item): array
    {
        $out = [];

        foreach (self::METHODS as $method) {
            if (is_array($item[$method] ?? null)) {
                $out[$method] = $item[$method];
            }
        }

        return $out;
    }

    /**
     * @param  array<array-key, mixed>  $item
     * @return list<mixed>
     */
    private function shared(array $item): array
    {
        return is_array($item['parameters'] ?? null) ? array_values($item['parameters']) : [];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  list<mixed>  $sharedParameters
     * @param  list<mixed>  $sharedServers
     */
    private function operation(
        string $method,
        string $path,
        array $operation,
        array $sharedParameters,
        array $sharedServers,
        ?string $webhook = null,
    ): Operation {
        $own = is_array($operation['parameters'] ?? null) ? array_values($operation['parameters']) : [];
        $servers = is_array($operation['servers'] ?? null) ? array_values($operation['servers']) : $sharedServers;

        return new Operation(
            // The fixed methods in capitals, as they are sent. One of the API's
            // own is sent as the spec spells it.
            method: in_array($method, self::METHODS, true) ? strtoupper($method) : $method,
            path: $path,
            operationId: is_string($operation['operationId'] ?? null) ? $operation['operationId'] : null,
            summary: is_string($operation['summary'] ?? null) ? $operation['summary'] : null,
            description: is_string($operation['description'] ?? null) ? $operation['description'] : null,
            tags: $this->strings($operation['tags'] ?? []),
            deprecated: (bool) ($operation['deprecated'] ?? false),
            parameters: $this->queryString($this->mergeParameters($sharedParameters, $own)),
            requestBody: is_array($operation['requestBody'] ?? null) ? $operation['requestBody'] : null,
            responses: is_array($operation['responses'] ?? null) ? $operation['responses'] : [],
            security: is_array($operation['security'] ?? null)
                ? array_values(array_filter($operation['security'], is_array(...)))
                : null,
            servers: array_values(array_filter($servers, is_array(...))),
            codeSamples: $this->codeSamples($operation['x-codeSamples'] ?? $operation['x-code-samples'] ?? []),
            webhook: $webhook,
            callbacks: $this->callbacks($operation),
        );
    }

    /**
     * Path-level parameters apply to every method, unless the method declares
     * one with the same name and location, which replaces it.
     *
     * @param  list<mixed>  $shared
     * @param  list<mixed>  $own
     * @return list<array<string, mixed>>
     */
    private function mergeParameters(array $shared, array $own): array
    {
        $merged = [];

        foreach ([...$shared, ...$own] as $parameter) {
            if (! is_array($parameter)) {
                continue;
            }

            $name = is_string($parameter['name'] ?? null) ? $parameter['name'] : null;
            $in = is_string($parameter['in'] ?? null) ? $parameter['in'] : null;
            $key = $name === null || $in === null ? count($merged) : $in.':'.$name;

            $merged[$key] = $parameter;
        }

        return array_values($merged);
    }

    /**
     * OpenAPI 3.2 can describe the whole query string as one parameter, with
     * a schema under a media type. Where that is a form-encoded object, its
     * properties are what a reader fills in and what a sample writes, so it
     * is read as a query parameter for each of them. Otherwise it stays one
     * parameter, typed by its schema, in with the query parameters.
     *
     * @param  list<array<string, mixed>>  $parameters
     * @return list<array<string, mixed>>
     */
    private function queryString(array $parameters): array
    {
        $out = [];

        foreach ($parameters as $parameter) {
            if (($parameter['in'] ?? null) !== 'querystring') {
                $out[] = $parameter;

                continue;
            }

            $content = is_array($parameter['content'] ?? null) ? $parameter['content'] : [];
            $type = array_key_first($content);
            $media = $type === null ? [] : $content[$type];
            $schema = is_array($media['schema'] ?? null) ? $media['schema'] : [];
            $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

            if (is_string($type) && str_contains(strtolower($type), 'x-www-form-urlencoded') && $properties !== []) {
                $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];

                foreach ($properties as $name => $property) {
                    $out[] = array_filter([
                        'name' => (string) $name,
                        'in' => 'query',
                        'required' => in_array($name, $required, true),
                        'description' => is_array($property) && is_string($property['description'] ?? null) ? $property['description'] : null,
                        'schema' => is_array($property) ? $property : [],
                    ], static fn (mixed $value): bool => $value !== null);
                }

                continue;
            }

            $out[] = [...$parameter, 'in' => 'query', 'schema' => $schema];
        }

        return $out;
    }

    /**
     * Samples the API's authors wrote themselves, as Redoc reads them: a list
     * of a language, an optional label and the code.
     *
     * @return list<array{lang: string, label: string, source: string}>
     */
    private function codeSamples(mixed $value): array
    {
        $out = [];

        foreach (is_array($value) ? $value : [] as $sample) {
            if (! is_array($sample) || ! is_string($sample['source'] ?? null) || trim($sample['source']) === '') {
                continue;
            }

            $lang = is_string($sample['lang'] ?? null) ? trim($sample['lang']) : '';
            $label = is_string($sample['label'] ?? null) && trim($sample['label']) !== '' ? trim($sample['label']) : $lang;

            $out[] = ['lang' => $lang, 'label' => $label === '' ? 'Example' : $label, 'source' => rtrim($sample['source'])];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }
}
