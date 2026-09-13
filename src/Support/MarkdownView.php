<?php

declare(strict_types=1);

namespace Vellum\Support;

use Illuminate\Contracts\View\Factory;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Renders a Vellum Blade view as a CommonMark Stringable fragment.
 */
final class MarkdownView
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function render(string $component, array $data): HtmlString
    {
        if (preg_match('/^[a-z]+$/', $component) !== 1) {
            throw new InvalidArgumentException('Invalid markdown component ['.$component.'].');
        }

        $path = dirname(__DIR__, 2).'/resources/views/components/'.$component.'.blade.php';
        $html = app(Factory::class)->file($path, $data)->render();

        return new HtmlString(rtrim($html));
    }
}
