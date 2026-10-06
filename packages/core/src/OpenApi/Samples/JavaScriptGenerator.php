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
        $options = ["  method: '".$request->method."'"];

        if ($request->headers !== []) {
            $pairs = [];

            foreach ($request->headers as $name => $value) {
                $pairs[] = "    '{$name}': '{$value}',";
            }

            $options[] = "  headers: {\n".implode("\n", $pairs)."\n  }";
        }

        if ($request->hasBody()) {
            $json = $this->indent($request->json());
            $options[] = '  body: JSON.stringify('.$json.')';
        }

        return "const response = await fetch('".$request->fullUrl()."', {\n"
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
}
