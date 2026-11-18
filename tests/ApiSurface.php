<?php

declare(strict_types=1);

namespace Vellum\Tests;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use SplFileInfo;

/**
 * What an app can call in Vellum: every class, with its public methods,
 * properties and constants, one per line.
 *
 * tests/fixtures/api-surface.txt holds these lines as they were at the last
 * release. A line that is there and no longer true is a change that can
 * break an app, and ApiSurfaceTest asks for it to be in the upgrade guide.
 */
final class ApiSurface
{
    /**
     * Where the classes live, by the namespace each directory holds.
     */
    public const ROOTS = [
        'src' => 'Vellum\\',
        'packages/core/src' => 'Vellum\\',
        'packages/openapi/src' => 'Vellum\\OpenApi\\',
    ];

    /**
     * Classes only the framework calls: it finds them by route or by command
     * name, so no app depends on how they are written.
     */
    private const FRAMEWORK_ONLY = ['Vellum\\Http\\Controllers\\', 'Vellum\\Http\\Middleware\\', 'Vellum\\Console\\'];

    /**
     * @param  array<string, string>  $roots  Directory under $base => namespace.
     * @return list<string>
     */
    public static function lines(string $base, array $roots = self::ROOTS): array
    {
        $lines = [];

        foreach ($roots as $directory => $namespace) {
            $root = $base.'/'.$directory;

            if (! is_dir($root)) {
                continue;
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
                /** @var SplFileInfo $file */
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $class = $namespace.str_replace('/', '\\', substr($file->getPathname(), strlen($root) + 1, -4));

                foreach (self::describe($class) as $line) {
                    $lines[$line] = true;
                }
            }
        }

        $lines = array_keys($lines);
        sort($lines);

        return $lines;
    }

    /**
     * What a line is about: its class, and the member when it has one,
     * written as an upgrade note would write it (`Changelog::load`,
     * `Site::$routePrefix`).
     *
     * @return array{class: string, short: string, member: string|null}
     */
    public static function subject(string $line): array
    {
        preg_match('/^\w+ ([\w\\\\]+)(?:::(\$?\w+))?/', $line, $match);

        $class = $match[1] ?? '';
        $short = substr($class, (int) strrpos('\\'.$class, '\\'));

        return [
            'class' => $class,
            'short' => $short,
            'member' => isset($match[2]) ? $short.'::'.$match[2] : null,
        ];
    }

    /**
     * The lines of $before that no longer hold in $now. A method that only
     * gained parameters a caller may leave out still takes every call it took.
     *
     * @param  list<string>  $before
     * @param  list<string>  $now
     * @return list<string>
     */
    public static function broken(array $before, array $now): array
    {
        $methods = [];

        foreach ($now as $line) {
            if (preg_match('/^method ([^(]+)\((.*)\)( static)?$/', $line, $match) === 1) {
                $methods[$match[1]] = [$match[2], $match[3] ?? ''];
            }
        }

        $broken = [];

        foreach (array_diff($before, $now) as $line) {
            if (preg_match('/^method ([^(]+)\((.*)\)( static)?$/', $line, $match) === 1 && isset($methods[$match[1]])) {
                [$parameters, $static] = $methods[$match[1]];
                $added = $match[2] === '' ? $parameters : (str_starts_with($parameters, $match[2].', ') ? substr($parameters, strlen($match[2]) + 2) : null);

                if ($static === ($match[3] ?? '') && ($parameters === $match[2] || ($added !== null && self::optional($added)))) {
                    continue;
                }
            }

            $broken[] = $line;
        }

        return $broken;
    }

    /**
     * Whether every parameter in a list can be left out.
     */
    private static function optional(string $parameters): bool
    {
        foreach (explode(', ', $parameters) as $parameter) {
            if (! str_ends_with($parameter, ' = …') && ! str_contains($parameter, '...$')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function describe(string $class): array
    {
        foreach (self::FRAMEWORK_ONLY as $prefix) {
            if (str_starts_with($class, $prefix)) {
                return [];
            }
        }

        if (! class_exists($class) && ! interface_exists($class) && ! trait_exists($class) && ! enum_exists($class)) {
            return [];
        }

        $reflection = new ReflectionClass($class);

        if (str_contains((string) $reflection->getDocComment(), '@internal')) {
            return [];
        }

        $lines = ['class '.$class];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class || str_contains((string) $method->getDocComment(), '@internal')) {
                continue;
            }

            $parameters = array_map(self::parameter(...), $method->getParameters());

            $lines[] = 'method '.$class.'::'.$method->getName().'('.implode(', ', $parameters).')'
                .($method->isStatic() ? ' static' : '');
        }

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() === $class) {
                $lines[] = 'property '.$class.'::$'.$property->getName();
            }
        }

        foreach ($reflection->getReflectionConstants() as $constant) {
            if ($constant->isPublic() && $constant->getDeclaringClass()->getName() === $class) {
                $lines[] = 'constant '.$class.'::'.$constant->getName();
            }
        }

        return $lines;
    }

    /**
     * A parameter as a caller meets it: its type, its name, and whether it
     * can be left out. The default's value is not part of it, and neither is
     * a return type, since changing either breaks no call.
     */
    private static function parameter(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();
        $written = $type === null ? '' : ($type instanceof ReflectionNamedType ? ($type->allowsNull() && $type->getName() !== 'mixed' && $type->getName() !== 'null' ? '?' : '').$type->getName() : (string) $type).' ';

        return $written
            .($parameter->isVariadic() ? '...' : '')
            .'$'.$parameter->getName()
            .($parameter->isOptional() && ! $parameter->isVariadic() ? ' = …' : '');
    }
}
