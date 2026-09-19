<?php

declare(strict_types=1);

namespace Vellum\Answers\Extractors;

/**
 * Rows of a config table (first column headed "Key"): key, type when the table
 * has one, default, and the one-line description. The card picks the row whose
 * key the reader asked about.
 */
final class ConfigAnswer implements AnswerExtractor
{
    private const DESCRIPTION = ['purpose', 'description', 'why', 'use it for', 'what it does', 'meaning'];

    public function extract(array $section): ?array
    {
        foreach (Html::tables($section['html']) as $table) {
            if (strcasecmp($table['headers'][0] ?? '', 'Key') !== 0) {
                continue;
            }

            $rows = [];

            foreach ($table['rows'] as $row) {
                $entry = ['key' => $row[$table['headers'][0]] ?? ''];

                foreach ($row as $header => $value) {
                    $name = strtolower($header);

                    if ($name === 'type' || $name === 'default') {
                        $entry[$name] = $value;
                    } elseif (in_array($name, self::DESCRIPTION, true)) {
                        $entry['description'] = $value;
                    }
                }

                if ($entry['key'] !== '') {
                    $rows[] = $entry;
                }
            }

            if ($rows !== []) {
                return ['type' => 'config', 'rows' => $rows];
            }
        }

        return null;
    }
}
