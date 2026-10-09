<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

/**
 * What a page needs to let a reader send an operation's request from it:
 * where it goes, what can be filled in, and how the reader signs it.
 *
 * It is data, not a form. The page carries it on the button that opens the
 * playground, and whatever opens builds its form from it.
 *
 * @phpstan-type Field array{name: string, required: bool, description: string|null, schema: array<string, mixed>, example: mixed}
 */
final class Playground
{
    /** A schema nests this far in the data. Deeper, a field is edited as JSON. */
    private const MAX_DEPTH = 6;

    public static function enabled(): bool
    {
        return (bool) config('vellum.openapi.playground', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function data(Operation $operation, Spec $spec, ?string $server): array
    {
        $parameters = [];

        foreach ($operation->parametersByLocation() as $location => $group) {
            foreach ($group as $parameter) {
                $name = is_string($parameter['name'] ?? null) ? $parameter['name'] : '';

                if ($name === '') {
                    continue;
                }

                $schema = is_array($parameter['schema'] ?? null) ? $parameter['schema'] : [];

                $parameters[] = [
                    'in' => $location,
                    'name' => $name,
                    'required' => ! empty($parameter['required']),
                    'description' => is_string($parameter['description'] ?? null) ? $parameter['description'] : null,
                    'schema' => self::slim($schema, 0),
                    'example' => $parameter['example'] ?? $schema['example'] ?? $schema['default'] ?? null,
                ];
            }
        }

        return [
            'title' => $operation->title(),
            'method' => $operation->method,
            'path' => $operation->path,
            'servers' => $this->servers($operation, $spec, $server),
            'parameters' => $parameters,
            'bodies' => $this->bodies($operation),
            'security' => $this->security($operation, $spec),
        ];
    }

    /**
     * The servers a request can go to, the one the samples use first.
     *
     * @return list<string>
     */
    private function servers(Operation $operation, Spec $spec, ?string $preferred): array
    {
        $urls = $preferred === null ? [] : [$preferred];

        foreach ([...$operation->servers, ...$spec->servers()] as $server) {
            if (is_string($server['url'] ?? null) && $server['url'] !== '') {
                $urls[] = rtrim($server['url'], '/');
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @return list<array{contentType: string, schema: array<string, mixed>, example: mixed}>
     */
    private function bodies(Operation $operation): array
    {
        $content = is_array($operation->requestBody['content'] ?? null) ? $operation->requestBody['content'] : [];
        $bodies = [];

        foreach ($content as $type => $media) {
            if (! is_string($type) || ! is_array($media)) {
                continue;
            }

            $schema = is_array($media['schema'] ?? null) ? $media['schema'] : [];

            $bodies[] = [
                'contentType' => $type,
                'schema' => self::slim($schema, 0),
                'example' => ExampleGenerator::forMedia($media),
            ];
        }

        return $bodies;
    }

    /**
     * How the reader signs the request: every scheme the operation accepts,
     * reduced to the three ways a browser can send a credential.
     *
     * @return list<array{name: string, type: string, in?: string, key?: string, description: string|null}>
     */
    private function security(Operation $operation, Spec $spec): array
    {
        $schemes = $spec->securitySchemes();
        $out = [];

        foreach ($operation->security ?? $spec->defaultSecurity() as $requirement) {
            foreach (array_keys($requirement) as $name) {
                if (! is_string($name) || isset($out[$name])) {
                    continue;
                }

                $scheme = $schemes[$name] ?? [];
                $type = is_string($scheme['type'] ?? null) ? $scheme['type'] : '';
                $description = is_string($scheme['description'] ?? null) ? $scheme['description'] : null;

                if ($type === 'apiKey') {
                    $out[$name] = [
                        'name' => $name,
                        'type' => 'apiKey',
                        'in' => is_string($scheme['in'] ?? null) ? $scheme['in'] : 'header',
                        'key' => is_string($scheme['name'] ?? null) ? $scheme['name'] : 'X-Api-Key',
                        'description' => $description,
                    ];

                    continue;
                }

                $basic = $type === 'http' && is_string($scheme['scheme'] ?? null) && strtolower($scheme['scheme']) === 'basic';

                // OAuth 2 and OpenID Connect end in a token too. The reader
                // gets one elsewhere and pastes it.
                $out[$name] = ['name' => $name, 'type' => $basic ? 'basic' : 'bearer', 'description' => $description];
            }
        }

        return array_values($out);
    }

    /**
     * A schema with only what a form needs, cut off where it repeats itself
     * or goes too deep, so the page does not carry the spec many times over.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function slim(array $schema, int $depth): array
    {
        if (SchemaView::isRecursive($schema) || $depth >= self::MAX_DEPTH) {
            return ['type' => 'json'];
        }

        $out = ['type' => self::type($schema)];

        foreach (['format', 'enum', 'default', 'description', 'nullable'] as $key) {
            if (array_key_exists($key, $schema) && (is_scalar($schema[$key]) || is_array($schema[$key]))) {
                $out[$key] = $schema[$key];
            }
        }

        if ($out['type'] === 'object' && is_array($schema['properties'] ?? null)) {
            $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];
            $out['properties'] = [];

            foreach ($schema['properties'] as $name => $property) {
                if (is_string($name) && is_array($property)) {
                    $out['properties'][$name] = self::slim($property, $depth + 1);
                }
            }

            $out['required'] = array_values(array_filter($required, is_string(...)));
        }

        if ($out['type'] === 'array' && is_array($schema['items'] ?? null)) {
            $out['items'] = self::slim($schema['items'], $depth + 1);
        }

        return $out;
    }

    /**
     * One type a form control can be chosen by. Anything that is a choice
     * between schemas, or has no type, is edited as JSON.
     *
     * @param  array<string, mixed>  $schema
     */
    private static function type(array $schema): string
    {
        if (isset($schema['oneOf']) || isset($schema['anyOf'])) {
            return 'json';
        }

        $type = $schema['type'] ?? null;

        if (is_array($type)) {
            $type = array_values(array_filter($type, static fn (mixed $one): bool => $one !== 'null'))[0] ?? null;
        }

        if (is_string($type) && in_array($type, ['string', 'integer', 'number', 'boolean', 'object', 'array'], true)) {
            return $type;
        }

        return is_array($schema['properties'] ?? null) ? 'object' : 'json';
    }
}
