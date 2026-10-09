<?php

declare(strict_types=1);

namespace Vellum\OpenApi\Samples;

final class CurlGenerator implements SampleGenerator
{
    public function label(): string
    {
        return 'cURL';
    }

    public function highlight(): string
    {
        return 'bash';
    }

    public function generate(SampleRequest $request): string
    {
        // Quoting keeps the shell out of it, but curl reads its arguments
        // too. An address that starts with a dash is an option unless it
        // follows --url, a header that starts with @ names a file to read
        // headers from, and so does a body after -d.
        $url = $request->fullUrl();
        $lines = ['curl -X '.preg_replace('/[^A-Z]/', '', strtoupper($request->method)).' '
            .(str_starts_with($url, '-') ? '--url ' : '').$this->quote($url)];

        foreach ($request->headers as $name => $value) {
            if (preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', (string) $name) === 1) {
                $lines[] = '  -H '.$this->quote($name.': '.$value);
            }
        }

        if ($request->hasBody()) {
            $json = $request->json();
            $lines[] = '  '.(str_starts_with($json, '@') ? '--data-raw' : '-d').' '.$this->quote($json);
        }

        return implode(" \\\n", $lines);
    }

    /**
     * A reader pastes this into a shell, and the values come from a spec that
     * may not be theirs. Inside single quotes the shell expands nothing, so
     * the only character to deal with is the single quote itself.
     */
    private function quote(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }
}
