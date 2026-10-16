<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

/**
 * The addresses a server object stands for.
 *
 * A server's url can hold variables, https://{region}.api.example.com, each
 * with a default and sometimes a list of the values it may take. A request
 * goes to an address with every variable filled in, so this works those out:
 * the default first, then each other value the spec allows.
 */
final class Servers
{
    /** Enough for a few regions and environments, without listing a matrix. */
    private const MAX = 16;

    /**
     * The address with every variable at its default, or null for a server
     * that has none to give.
     *
     * @param  array<array-key, mixed>  $server
     */
    public static function default(array $server): ?string
    {
        return self::urls($server)[0] ?? null;
    }

    /**
     * Every address the server can be, default first. A variable with no
     * list of values is only ever its default: anything a reader types into
     * it could name another host, and these addresses are the ones the
     * forwarding route may reach.
     *
     * @param  array<array-key, mixed>  $server
     * @return list<string>
     */
    public static function urls(array $server): array
    {
        $url = $server['url'] ?? null;

        if (! is_string($url) || trim($url) === '') {
            return [];
        }

        $variables = is_array($server['variables'] ?? null) ? $server['variables'] : [];
        $urls = [rtrim($url, '/')];

        foreach ($variables as $name => $variable) {
            if (! is_array($variable) || ! array_key_exists('default', $variable) || ! is_scalar($variable['default'])) {
                continue;
            }

            $default = (string) $variable['default'];
            $values = is_array($variable['enum'] ?? null) ? array_map(strval(...), array_filter($variable['enum'], is_scalar(...))) : [];
            $values = array_values(array_unique([$default, ...$values]));
            $next = [];

            foreach ($urls as $partial) {
                foreach ($values as $value) {
                    $next[] = str_replace('{'.$name.'}', $value, $partial);
                }
            }

            $urls = array_slice($next, 0, self::MAX);
        }

        return array_values(array_unique($urls));
    }

    /**
     * The server's variables, for showing what can be changed and to what.
     *
     * @param  array<array-key, mixed>  $server
     * @return list<array{name: string, default: string, values: list<string>, description: string|null}>
     */
    public static function variables(array $server): array
    {
        $out = [];

        foreach (is_array($server['variables'] ?? null) ? $server['variables'] : [] as $name => $variable) {
            if (! is_array($variable) || ! is_scalar($variable['default'] ?? null)) {
                continue;
            }

            $out[] = [
                'name' => (string) $name,
                'default' => (string) $variable['default'],
                'values' => is_array($variable['enum'] ?? null) ? array_values(array_map(strval(...), array_filter($variable['enum'], is_scalar(...)))) : [],
                'description' => is_string($variable['description'] ?? null) && trim($variable['description']) !== '' ? $variable['description'] : null,
            ];
        }

        return $out;
    }
}
