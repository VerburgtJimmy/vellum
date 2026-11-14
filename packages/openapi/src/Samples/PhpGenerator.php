<?php

declare(strict_types=1);

namespace Vellum\OpenApi\Samples;

final class PhpGenerator implements SampleGenerator
{
    public function label(): string
    {
        return 'PHP';
    }

    public function highlight(): string
    {
        return 'php';
    }

    public function generate(SampleRequest $request): string
    {
        $chain = ['Http::'];
        $headers = $request->headers;

        // withToken() reads better than the header it produces.
        if (preg_match('/^Bearer\s+(.+)$/i', $headers['Authorization'] ?? '', $match) === 1) {
            $chain = ['Http::withToken('.$this->quote($match[1]).')'];
            unset($headers['Authorization']);
        }

        unset($headers['Content-Type'], $headers['Accept']);

        if ($headers !== []) {
            $pairs = [];

            foreach ($headers as $name => $value) {
                $pairs[] = '        '.$this->quote($name).' => '.$this->quote($value).',';
            }

            $chain[] = ($chain[0] === 'Http::' ? '' : '    ')."->withHeaders([\n".implode("\n", $pairs)."\n    ])";
        }

        $method = strtolower($request->method);
        $arguments = $this->quote($request->fullUrl());

        // The client has a method of its own for the common verbs. Any other,
        // QUERY or one the API made up, goes through send().
        if (in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'head'], true)) {
            $arguments .= $request->hasBody() ? ', '.$this->phpArray($request->body, 1) : '';
            $call = '->'.$method.'('.$arguments.')';
        } else {
            $options = $request->hasBody() ? ', [\'json\' => '.$this->phpArray($request->body, 1).']' : '';
            $call = '->send('.$this->quote($request->method).', '.$arguments.$options.')';
        }

        $head = array_shift($chain);
        $body = $chain === [] ? '' : "\n".implode("\n", $chain);

        return '$response = '.$head.$body.($body === '' ? '' : "\n    ").$call.';';
    }

    private function phpArray(mixed $value, int $indent): string
    {
        if (! is_array($value)) {
            return $this->scalar($value);
        }

        $pad = str_repeat('    ', $indent + 1);
        $close = str_repeat('    ', $indent);
        $isList = array_is_list($value);
        $lines = [];

        foreach ($value as $key => $item) {
            $prefix = $isList ? '' : $this->quote((string) $key).' => ';
            $lines[] = $pad.$prefix.$this->phpArray($item, $indent + 1).',';
        }

        return $lines === [] ? '[]' : "[\n".implode("\n", $lines)."\n".$close.']';
    }

    private function scalar(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_int($value), is_float($value) => (string) $value,
            default => $this->quote((string) $value),
        };
    }

    /**
     * A single-quoted PHP string holding exactly this value. The values come
     * from a spec, so a quote or a trailing backslash in one must not end the
     * string early in code a reader is about to run.
     */
    private function quote(string $value): string
    {
        return "'".strtr($value, ['\\' => '\\\\', "'" => "\\'"])."'";
    }
}
