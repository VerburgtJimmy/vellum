<?php

declare(strict_types=1);

namespace Vellum\OpenApi\Samples;

final class JavaScriptGenerator implements SampleGenerator
{
    public function label(): string
    {
        return 'JavaScript';
    }

    public function highlight(): string
    {
        return 'js';
    }

    public function generate(SampleRequest $request): string
    {
        $options = ['  method: '.$this->quote($request->method)];

        if ($request->headers !== []) {
            $pairs = [];

            foreach ($request->headers as $name => $value) {
                $pairs[] = '    '.$this->quote($name).': '.$this->quote($value).',';
            }

            $options[] = "  headers: {\n".implode("\n", $pairs)."\n  }";
        }

        if ($request->hasBody()) {
            $json = $this->indent($request->json());
            $options[] = '  body: JSON.stringify('.$json.')';
        }

        return 'const response = await fetch('.$this->quote($request->fullUrl()).", {\n"
            .implode(",\n", $options)
            ."\n})";
    }

    private function indent(string $json): string
    {
        $lines = explode("\n", $json);

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $lines[$index] = '  '.$line;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * A single-quoted JavaScript string holding exactly this value, for the
     * same reason the other languages quote theirs: it comes from a spec.
     */
    private function quote(string $value): string
    {
        return "'".strtr($value, [
            '\\' => '\\\\',
            "'" => "\\'",
            "\n" => '\\n',
            "\r" => '\\r',
            "\u{2028}" => '\\u2028',
            "\u{2029}" => '\\u2029',
        ])."'";
    }
}
