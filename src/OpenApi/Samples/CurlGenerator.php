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
        $lines = ['curl -X '.$request->method.' "'.$request->fullUrl().'"'];

        foreach ($request->headers as $name => $value) {
            $lines[] = '  -H "'.$name.': '.$value.'"';
        }

        if ($request->hasBody()) {
            // Single quotes around the JSON, so double quotes inside it stand.
            $lines[] = "  -d '".$request->json()."'";
        }

        return implode(" \\\n", $lines);
    }
}
