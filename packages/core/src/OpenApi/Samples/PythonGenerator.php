<?php

declare(strict_types=1);

namespace Vellum\OpenApi\Samples;

/**
 * The request with requests, the library most Python that calls an API uses.
 */
final class PythonGenerator implements SampleGenerator
{
    private const SHORTHAND = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'];

    public function label(): string
    {
        return 'Python';
    }

    public function highlight(): string
    {
        return 'python';
    }

    public function generate(SampleRequest $request): string
    {
        $method = strtolower($request->method);

        // requests has a function for the common verbs. Any other, QUERY or
        // one the API made up, goes through request().
        $arguments = in_array($method, self::SHORTHAND, true)
            ? ['    '.$this->quote($request->fullUrl())]
            : ['    '.$this->quote($request->method), '    '.$this->quote($request->fullUrl())];
        $call = in_array($method, self::SHORTHAND, true) ? 'requests.'.$method : 'requests.request';
        $headers = $request->headers;

        // requests writes the Content-Type of a json= body itself.
        if ($request->hasBody()) {
            unset($headers['Content-Type']);
        }

        if ($headers !== []) {
            $arguments[] = '    headers='.$this->literal($headers, 1);
        }

        if ($request->hasBody()) {
            $arguments[] = '    json='.$this->literal($request->body, 1);
        }

        return "import requests\n\nresponse = ".$call."(\n".implode(",\n", $arguments).",\n)";
    }

    /**
     * A Python literal for a value: dicts, lists, strings, numbers, True,
     * False and None, set out one item to a line.
     */
    private function literal(mixed $value, int $indent): string
    {
        if (is_array($value)) {
            // An empty object and an empty list are one value once decoded,
            // as they are in the other samples' JSON.
            if ($value === []) {
                return '[]';
            }

            $pad = str_repeat('    ', $indent + 1);
            $close = str_repeat('    ', $indent);
            $list = array_is_list($value);
            $lines = [];

            foreach ($value as $key => $item) {
                $lines[] = $pad.($list ? '' : $this->quote((string) $key).': ').$this->literal($item, $indent + 1).',';
            }

            return ($list ? '[' : '{')."\n".implode("\n", $lines)."\n".$close.($list ? ']' : '}');
        }

        return match (true) {
            $value === null => 'None',
            $value === true => 'True',
            $value === false => 'False',
            is_int($value) => (string) $value,
            is_float($value) => is_finite($value) ? var_export($value, true) : 'None',
            default => $this->quote((string) $value),
        };
    }

    /**
     * A single-quoted Python string holding exactly this value. It comes from
     * a spec, which may be someone else's file, so nothing in it may end the
     * string or be read as an escape of its own.
     */
    private function quote(string $value): string
    {
        $escaped = '';

        foreach (mb_str_split($value) as $character) {
            $escaped .= match (true) {
                $character === '\\' => '\\\\',
                $character === "'" => "\\'",
                $character === "\n" => '\\n',
                $character === "\r" => '\\r',
                $character === "\t" => '\\t',
                mb_ord($character) < 0x20 || mb_ord($character) === 0x7F => sprintf('\\x%02x', mb_ord($character)),
                default => $character,
            };
        }

        return "'".$escaped."'";
    }
}
