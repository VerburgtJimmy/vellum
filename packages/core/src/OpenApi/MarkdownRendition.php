<?php

declare(strict_types=1);

namespace Vellum\OpenApi;

use Closure;

/**
 * A reference page as Markdown, for the readers that ask for a page that way:
 * the raw Markdown URL, Copy Markdown and llms-full.txt.
 *
 * A generated page has no Markdown file behind it, and the spec it came from
 * is not what anyone means by "this page as Markdown". So the page is written
 * out again here, in the plainest form that keeps what it says: tables for
 * fields, fenced blocks for samples.
 *
 * @phpstan-import-type Page from PageBuilder
 */
final class MarkdownRendition
{
    /** Fields below this many levels are named by their parent's type only. */
    private const MAX_DEPTH = 4;

    /**
     * @param  list<Group>  $groups
     * @param  Closure(Group, Operation): string  $href
     */
    public function overview(string $title, Spec $spec, array $groups, Closure $href): string
    {
        $out = ['# '.$title];

        if ($spec->description() !== null) {
            $out[] = trim($spec->description());
        }

        if ($spec->apiVersion() !== null) {
            $out[] = 'Version `'.$spec->apiVersion().'`';
        }

        if ($spec->servers() !== []) {
            $out[] = '## Servers';
            $out[] = implode("\n", array_map(
                fn (array $server): string => '- '.self::code(self::text($server['url'] ?? ''))
                    .(self::text($server['description'] ?? '') === '' ? '' : ': '.self::line(self::text($server['description'] ?? ''))),
                $spec->servers(),
            ));
        }

        foreach ($groups as $group) {
            $out[] = str_repeat('#', min(2 + $group->depth, 4)).' '.self::line($group->label());

            if ($group->description !== null) {
                $out[] = trim($group->description);
            }

            if ($group->operations === []) {
                continue;
            }

            $out[] = implode("\n", array_map(
                static fn (Operation $operation): string => '- ['.self::line($operation->title()).']('.$href($group, $operation).') '
                    .self::code($operation->method.' '.$operation->path),
                $group->operations,
            ));
        }

        return implode("\n\n", $out)."\n";
    }

    /**
     * @param  Page  $page
     */
    public function operation(array $page): string
    {
        $operation = $page['operation'];
        $out = ['# '.self::line($operation->title())];

        if ($operation->deprecated) {
            $out[] = '**Deprecated.**';
        }

        $out[] = $operation->webhook !== null
            ? self::code($operation->method).' webhook '.self::code($operation->webhook).'. The API sends this request to an address you give it.'
            : self::code($operation->method.' '.$operation->path);

        if ($operation->description !== null && trim($operation->description) !== '') {
            $out[] = trim($operation->description);
        }

        if ($page['security'] !== []) {
            $out[] = '## Authorization';
            $out[] = implode("\n", array_map(
                static fn (array $scheme): string => '- '.self::code($scheme['name']).': '.self::line($scheme['hint']).' '.self::code($scheme['detail']),
                $page['security'],
            ));
        }

        foreach ($operation->parametersByLocation() as $location => $parameters) {
            $out[] = '## '.ucfirst($location).' parameters';
            $rows = [];

            foreach ($parameters as $parameter) {
                $schema = is_array($parameter['schema'] ?? null) ? $parameter['schema'] : [];
                $rows[] = [
                    self::code(self::text($parameter['name'] ?? '')),
                    SchemaView::type($schema),
                    empty($parameter['required']) ? 'No' : 'Yes',
                    self::text($parameter['description'] ?? ''),
                ];
            }

            $out[] = self::table(['Name', 'Type', 'Required', 'Description'], $rows);
        }

        $content = is_array($operation->requestBody['content'] ?? null) ? $operation->requestBody['content'] : [];

        if ($content !== []) {
            $out[] = $operation->webhook !== null ? '## Payload' : '## Request body';

            foreach ($content as $type => $media) {
                $out[] = self::code((string) $type);
                $out[] = $this->schema(is_array($media) && is_array($media['schema'] ?? null) ? $media['schema'] : []);
            }
        }

        if ($operation->responses !== []) {
            $out[] = '## Responses';

            foreach ($operation->responses as $status => $response) {
                $response = is_array($response) ? $response : [];
                $out[] = '### '.$status;

                if (self::text($response['description'] ?? '') !== '') {
                    $out[] = self::text($response['description'] ?? '');
                }

                $media = is_array($response['content'] ?? null) ? reset($response['content']) : null;

                if (is_array($media) && is_array($media['schema'] ?? null)) {
                    $out[] = $this->schema($media['schema']);
                }
            }
        }

        if ($operation->callbacks !== []) {
            $out[] = '## Callbacks';

            foreach ($operation->callbacks as $callback) {
                $call = $callback['operation'];
                $out[] = '### '.self::line($call->summary ?? $callback['name']);
                $out[] = self::code($call->method.' '.$call->path);

                $content = is_array($call->requestBody['content'] ?? null) ? $call->requestBody['content'] : [];
                $media = $content === [] ? null : reset($content);

                if (is_array($media) && is_array($media['schema'] ?? null)) {
                    $out[] = $this->schema($media['schema']);
                }
            }
        }

        if ($page['samples'] !== []) {
            $out[] = '## Request samples';

            foreach ($page['samples'] as $sample) {
                $out[] = self::line($sample['label']).":\n\n".self::fence($sample['code'], $sample['highlight']);
            }
        }

        if ($page['responseExamples'] !== []) {
            $out[] = '## Example responses';

            foreach ($page['responseExamples'] as $status => $example) {
                $out[] = $status.":\n\n".self::fence($example, 'json');
            }
        }

        return implode("\n\n", array_filter($out, static fn (string $block): bool => $block !== ''))."\n";
    }

    /**
     * A schema as one table, nested fields named by their path.
     *
     * @param  array<string, mixed>  $schema
     */
    private function schema(array $schema): string
    {
        $rows = $this->rows(SchemaView::body($schema), '', 0);

        if ($rows === []) {
            return $schema === [] ? '' : self::code(SchemaView::type($schema));
        }

        return self::table(['Field', 'Type', 'Required', 'Description'], $rows);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<list<string>>
     */
    private function rows(array $schema, string $prefix, int $depth): array
    {
        $rows = [];

        foreach (SchemaView::properties($schema) as $property) {
            $name = $prefix.$property['name'];
            $enum = SchemaView::enumValues($property['schema']);
            $description = $property['description'] ?? '';

            if ($enum !== []) {
                $description = trim($description.' One of: '.implode(', ', array_map(self::code(...), $enum)).'.');
            }

            $rows[] = [self::code($name), $property['type'], $property['required'] ? 'Yes' : 'No', $description];

            if ($property['expandable'] && $depth < self::MAX_DEPTH) {
                $isList = SchemaView::properties($property['schema']) === [];

                array_push($rows, ...$this->rows(
                    SchemaView::body($property['schema']),
                    $name.($isList ? '[].' : '.'),
                    $depth + 1,
                ));
            }
        }

        return $rows;
    }

    /**
     * @param  list<string>  $head
     * @param  list<list<string>>  $rows
     */
    private static function table(array $head, array $rows): string
    {
        $line = static fn (array $cells): string => '| '.implode(' | ', array_map(self::cell(...), $cells)).' |';

        return implode("\n", [
            $line($head),
            '| '.implode(' | ', array_fill(0, count($head), '---')).' |',
            ...array_map($line, $rows),
        ]);
    }

    /** A table cell is one line, and a pipe in it would start another cell. */
    private static function cell(string $value): string
    {
        return str_replace('|', '\\|', self::line($value));
    }

    private static function line(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /** Inline code, fenced by more backticks than the value holds. */
    private static function code(string $value): string
    {
        preg_match_all('/`+/', $value, $runs);
        $ticks = str_repeat('`', max(0, ...array_map(strlen(...), $runs[0] === [] ? [''] : $runs[0])) + 1);
        $pad = str_starts_with($value, '`') || str_ends_with($value, '`') ? ' ' : '';

        return $ticks.$pad.self::line($value).$pad.$ticks;
    }

    private static function fence(string $code, string $language): string
    {
        $fence = str_repeat('`', max(3, PageBuilder::longestFence($code) + 1));

        return $fence.$language."\n".$code."\n".$fence;
    }
}
