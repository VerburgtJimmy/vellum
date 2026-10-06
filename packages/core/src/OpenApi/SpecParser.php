<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Vellum\Exceptions\InvalidSpecException;

/**
 * Reads an OpenAPI 3.0 or 3.1 document from disk into a resolved Spec.
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
    private const METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

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

        if (! str_starts_with($version, '3.0') && ! str_starts_with($version, '3.1')) {
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
        }

        return $operations;
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
    ): Operation {
        $own = is_array($operation['parameters'] ?? null) ? array_values($operation['parameters']) : [];
        $servers = is_array($operation['servers'] ?? null) ? array_values($operation['servers']) : $sharedServers;

        return new Operation(
            method: strtoupper($method),
            path: $path,
            operationId: is_string($operation['operationId'] ?? null) ? $operation['operationId'] : null,
            summary: is_string($operation['summary'] ?? null) ? $operation['summary'] : null,
            description: is_string($operation['description'] ?? null) ? $operation['description'] : null,
            tags: $this->strings($operation['tags'] ?? []),
            deprecated: (bool) ($operation['deprecated'] ?? false),
            parameters: $this->mergeParameters($sharedParameters, $own),
            requestBody: is_array($operation['requestBody'] ?? null) ? $operation['requestBody'] : null,
            responses: is_array($operation['responses'] ?? null) ? $operation['responses'] : [],
            security: is_array($operation['security'] ?? null)
                ? array_values(array_filter($operation['security'], is_array(...)))
                : null,
            servers: array_values(array_filter($servers, is_array(...))),
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
