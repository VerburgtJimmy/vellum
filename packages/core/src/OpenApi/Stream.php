<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

/**
 * A body that travels one item at a time: server-sent events, JSON Lines and
 * the like. OpenAPI 3.2 describes one item with itemSchema. A spec written
 * for an older version names the media type and describes what it can with
 * schema, so the media type alone is enough to know a stream when it is one.
 */
final class Stream
{
    /** What one item of each sequential media type is called. */
    private const ITEMS = [
        'text/event-stream' => 'event',
        'application/jsonl' => 'line',
        'application/x-jsonlines' => 'line',
        'application/x-ndjson' => 'line',
        'application/ndjson' => 'line',
        'application/json-seq' => 'record',
    ];

    private const JSON = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * What one item of this media type is called, or null when it is not a
     * stream.
     *
     * @param  array<array-key, mixed>  $media
     */
    public static function item(string $type, array $media): ?string
    {
        $name = strtolower(trim(explode(';', $type)[0]));

        return self::ITEMS[$name] ?? (is_array($media['itemSchema'] ?? null) ? 'item' : null);
    }

    /**
     * The schema to show for a media type: one item's when it has one.
     *
     * @param  array<array-key, mixed>  $media
     * @return array<string, mixed>
     */
    public static function schema(array $media): array
    {
        $schema = $media['itemSchema'] ?? $media['schema'] ?? null;

        /** @var array<string, mixed> */
        return is_array($schema) ? $schema : [];
    }

    /**
     * The media type of the stream an operation answers with, to ask for it
     * by. Only when a stream is what it answers with first.
     */
    public static function answer(Operation $operation): ?string
    {
        foreach ($operation->responses as $status => $response) {
            $content = is_array($response) && is_array($response['content'] ?? null) ? $response['content'] : [];
            $type = array_key_first($content);

            if ($type === null || ! str_starts_with((string) $status, '2')) {
                continue;
            }

            $media = is_array($content[$type]) ? $content[$type] : [];

            return self::item((string) $type, $media) !== null ? (string) $type : null;
        }

        return null;
    }

    /**
     * An example as it travels. What a spec wrote out in full is used as it
     * is. Anything else is items, written the way this media type sends them.
     *
     * @param  array<string, mixed>  $media
     */
    public static function example(array $media, string $item): string
    {
        foreach (is_array($media['examples'] ?? null) ? $media['examples'] : [] as $example) {
            if (is_array($example) && is_string($example['serializedValue'] ?? null)) {
                return rtrim($example['serializedValue']);
            }
        }

        $declared = ExampleGenerator::declared($media);

        if (is_string($declared)) {
            return rtrim($declared);
        }

        $value = ExampleGenerator::forMedia($media);
        $items = $declared !== null && is_array($value) && array_is_list($value) ? $value : [$value];

        // An id or a retry made up from a schema says nothing about the API.
        if ($declared === null && is_array($value) && ! array_is_list($value)) {
            $items = [array_diff_key($value, array_filter(['id' => ($value['id'] ?? null) === 'string', 'retry' => ($value['retry'] ?? null) === 0]))];
        }

        return $item === 'event'
            ? implode("\n\n", array_map(self::event(...), $items))
            : implode("\n", array_map(self::encode(...), $items));
    }

    private static function event(mixed $item): string
    {
        // Named as an event's fields, or the data alone with no name for them.
        $fields = is_array($item) && (array_key_exists('data', $item) || array_key_exists('event', $item)) ? $item : ['data' => $item];
        $lines = [];

        foreach (['event', 'id', 'retry'] as $field) {
            if (is_scalar($fields[$field] ?? null)) {
                $lines[] = $field.': '.$fields[$field];
            }
        }

        if (array_key_exists('data', $fields)) {
            $data = is_string($fields['data']) ? $fields['data'] : self::encode($fields['data']);

            // A line break in the data is a line of its own on the wire.
            foreach (preg_split('/\R/', $data) ?: [] as $line) {
                $lines[] = 'data: '.$line;
            }
        }

        return implode("\n", $lines);
    }

    private static function encode(mixed $value): string
    {
        return (string) json_encode($value, self::JSON);
    }
}
