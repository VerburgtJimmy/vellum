<?php

declare(strict_types=1);

namespace Vellum\Support;

use Illuminate\Contracts\View\Factory;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Renders a Markdown component's view, vellum::components.{name}, as a
 * CommonMark Stringable fragment.
 *
 * The name resolves through the vellum view namespace, so a copy the app
 * published, or the full package's own version, is used in place of the one
 * core ships.
 */
final class MarkdownView
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function render(string $component, array $data): HtmlString
    {
        $view = match ($component) {
            'callout' => 'vellum::components.callout',
            'card' => 'vellum::components.card',
            'cards' => 'vellum::components.cards',
            'step' => 'vellum::components.step',
            'steps' => 'vellum::components.steps',
            'tabs' => 'vellum::components.tabs',
            default => throw new InvalidArgumentException('Invalid markdown component ['.$component.'].'),
        };

        $html = app(Factory::class)->make($view, $data)->render();

        return new HtmlString(rtrim($html));
    }
}
