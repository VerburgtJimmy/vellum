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
        $lines = ['curl -X '.preg_replace('/[^A-Z]/', '', strtoupper($request->method)).' '.$this->quote($request->fullUrl())];

        foreach ($request->headers as $name => $value) {
            $lines[] = '  -H '.$this->quote($name.': '.$value);
        }

        if ($request->hasBody()) {
            $lines[] = '  -d '.$this->quote($request->json());
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
