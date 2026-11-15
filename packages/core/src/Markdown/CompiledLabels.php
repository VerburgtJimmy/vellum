<?php

declare(strict_types=1);

namespace Vellum\Markdown;

use Vellum\Support\Translate;

/**
 * The few words the Markdown compiler writes into a page, such as a code
 * block's copy button, are written in English, so a compiled page serves
 * every locale. They are put in the reader's language as the page is served.
 */
final class CompiledLabels
{
    /**
     * Each label as the compiler writes it, with the key of its line and the
     * markup around it.
     */
    private const LABELS = [
        ['code.copy', 'Copy code', 'aria-label="%s"'],
        ['code.copy_heading_link', 'Copy link to heading', 'aria-label="%s"'],
        ['code.copied', 'Copied', '<span class="vellum-heading-anchor-copied">%s</span>'],
        ['code.terminal', 'Terminal', '<span class="vellum-code-title">%s</span>'],
    ];

    public static function english(string $key): string
    {
        foreach (self::LABELS as [$name, $english]) {
            if ($name === $key) {
                return $english;
            }
        }

        throw new \InvalidArgumentException('No compiled label ['.$key.'].');
    }

    public static function translate(string $html): string
    {
        $replace = [];

        foreach (self::LABELS as [$key, $english, $markup]) {
            $line = Translate::line('vellum-core::'.$key);

            if ($line !== $english) {
                $replace[sprintf($markup, $english)] = sprintf($markup, e($line));
            }
        }

        return $replace === [] ? $html : strtr($html, $replace);
    }
}
