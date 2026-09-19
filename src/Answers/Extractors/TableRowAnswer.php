<?php

declare(strict_types=1);

namespace Vellum\Answers\Extractors;

/**
 * A row of a parameter or option table whose first cell is what the heading
 * names: under "## persist", the row for persist.
 */
final class TableRowAnswer implements AnswerExtractor
{
    public function extract(array $section): ?array
    {
        $heading = mb_strtolower(trim($section['own']));

        if ($heading === '') {
            return null;
        }

        foreach (Html::tables($section['html']) as $table) {
            $first = $table['headers'][0] ?? null;

            if ($first === null) {
                continue;
            }

            foreach ($table['rows'] as $row) {
                $cell = mb_strtolower(trim($row[$first] ?? '', ' `:'));

                if ($cell !== '' && preg_match('/(^|[^\w.:-])'.preg_quote($cell, '/').'($|[^\w.:-])/u', $heading) === 1) {
                    return ['type' => 'row', 'row' => $row];
                }
            }
        }

        return null;
    }
}
