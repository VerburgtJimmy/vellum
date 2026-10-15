<?php

declare(strict_types=1);

namespace Vellum\OpenApi\Samples;

use Vellum\OpenApi\ExampleGenerator;
use Vellum\OpenApi\Operation;

/**
 * Builds the request once, then asks each configured language to write it.
 *
 * @phpstan-type Sample array{key: string, label: string, highlight: string, code: string}
 */
final class SampleBuilder
{
    /** @var array<string, class-string<SampleGenerator>> */
    private const BUILT_IN = [
        'curl' => CurlGenerator::class,
        'php' => PhpGenerator::class,
        'javascript' => JavaScriptGenerator::class,
        'python' => PythonGenerator::class,
    ];

    /**
     * @param  list<array{name: string, hint: string, detail: string}>  $security
     * @return list<Sample>
     */
    public function build(Operation $operation, ?string $serverUrl, array $security): array
    {
        $request = $this->request($operation, $serverUrl, $security);
        $samples = [];
        $written = [];

        // What the API's authors wrote comes first, and stands in for the
        // generated sample in the same language: they know their API better.
        foreach ($operation->codeSamples as $index => $sample) {
            $language = self::language($sample['lang']);
            $written[$language['key'] ?? 'spec-'.$index] = true;

            $samples[] = [
                'key' => $language['key'] ?? 'spec-'.$index,
                'label' => $sample['label'],
                'highlight' => $language['highlight'],
                'code' => $sample['source'],
            ];
        }

        foreach ($this->generators() as $key => $generator) {
            if (isset($written[$key])) {
                continue;
            }

            $samples[] = [
                'key' => $key,
                'label' => $generator->label(),
                'highlight' => $generator->highlight(),
                'code' => $generator->generate($request),
            ];
        }

        return $samples;
    }

    /**
     * @param  list<array{name: string, hint: string, detail: string}>  $security
     */
    private function request(Operation $operation, ?string $serverUrl, array $security): SampleRequest
    {
        $grouped = $operation->parametersByLocation();
        $headers = [];
        $query = [];

        foreach ($security as $scheme) {
            [$name, $value] = array_pad(explode(':', $scheme['detail'], 2), 2, '');
            $headers[trim($name)] = trim($value);
        }

        foreach ($grouped['header'] ?? [] as $parameter) {
            if (is_string($parameter['name'] ?? null)) {
                $headers[$parameter['name']] = $this->parameterValue($parameter);
            }
        }

        foreach ($grouped['query'] ?? [] as $parameter) {
            // Optional query parameters clutter a sample; show what is needed.
            if (! empty($parameter['required']) && is_string($parameter['name'] ?? null)) {
                $query[$parameter['name']] = $this->parameterValue($parameter);
            }
        }

        [$body, $contentType] = $this->body($operation);

        if ($contentType !== null) {
            $headers['Content-Type'] = $contentType;
        }

        return new SampleRequest(
            method: $operation->method,
            url: ($serverUrl ?? '').$operation->path,
            headers: $headers,
            query: $query,
            body: $body,
            contentType: $contentType,
        );
    }

    /**
     * @return array{0: mixed, 1: string|null}
     */
    private function body(Operation $operation): array
    {
        $content = is_array($operation->requestBody['content'] ?? null) ? $operation->requestBody['content'] : [];

        if ($content === []) {
            return [null, null];
        }

        // JSON when it is on offer, since that is what the sample can show.
        foreach ($content as $type => $media) {
            if (is_string($type) && str_contains($type, 'json') && is_array($media)) {
                return [ExampleGenerator::forMedia($media), $type];
            }
        }

        $type = (string) array_key_first($content);
        $media = $content[$type];

        return [is_array($media) ? ExampleGenerator::forMedia($media) : null, $type];
    }

    /**
     * @param  array<string, mixed>  $parameter
     */
    private function parameterValue(array $parameter): string
    {
        // A parameter gives its examples as a media type does.
        return $this->stringify(ExampleGenerator::forMedia($parameter));
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => (string) json_encode($value),
            $value === null => '',
            default => (string) $value,
        };
    }

    /**
     * The generated sample a written one stands in for, and how to highlight
     * it, from the language its author named.
     *
     * @return array{key: string|null, highlight: string}
     */
    private static function language(string $lang): array
    {
        $lang = strtolower($lang);

        return match (true) {
            in_array($lang, ['curl', 'shell', 'bash', 'sh', 'console'], true) => ['key' => 'curl', 'highlight' => 'bash'],
            in_array($lang, ['php', 'laravel'], true) => ['key' => 'php', 'highlight' => 'php'],
            in_array($lang, ['javascript', 'js', 'node', 'node.js', 'nodejs'], true) => ['key' => 'javascript', 'highlight' => 'js'],
            in_array($lang, ['python', 'py', 'python3'], true) => ['key' => 'python', 'highlight' => 'python'],
            in_array($lang, ['typescript', 'ts'], true) => ['key' => null, 'highlight' => 'ts'],
            in_array($lang, ['c#', 'csharp', 'cs'], true) => ['key' => null, 'highlight' => 'csharp'],
            default => ['key' => null, 'highlight' => preg_match('/^[a-z0-9+#-]+$/', $lang) === 1 ? $lang : 'text'],
        };
    }

    /**
     * @return array<string, SampleGenerator>
     */
    private function generators(): array
    {
        $configured = config('vellum.openapi.samples', ['curl']);
        $configured = is_array($configured) ? $configured : ['curl'];
        $out = [];

        foreach ($configured as $key => $value) {
            // Either a built-in key, or a key mapped to a class of your own.
            $name = is_string($key) ? $key : (string) $value;
            $class = is_string($key) ? $value : (self::BUILT_IN[$value] ?? null);

            if (! is_string($class) || ! class_exists($class) || ! is_a($class, SampleGenerator::class, true)) {
                continue;
            }

            $out[$name] = new $class;
        }

        return $out;
    }
}
