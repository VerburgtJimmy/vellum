<?php

declare(strict_types=1);

namespace Vellum\Markdown\Islands;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Vellum\Exceptions\UnknownComponentException;
use Vellum\Support\MarkdownView;

/**
 * Bottom-up Blade render of an island tree. Slot HTML is passed as data, never compiled.
 */
final class IslandRenderer
{
    private const VALUE_TAGS = [
        'vellum::env' => 'env',
        'vellum::config' => 'config',
        'vellum::route' => 'route',
    ];

    /**
     * @param  list<Island>  $islands
     */
    public function render(string $html, array $islands): string
    {
        foreach ($islands as $island) {
            $html = $this->replacePlaceholder($html, $island->placeholder(), $this->renderIsland($island));
        }

        return $html;
    }

    /**
     * Replace placeholders with slot HTML only (no Blade). Used for search indexing.
     *
     * @param  list<Island>  $islands
     */
    public function inlineSlots(string $html, array $islands): string
    {
        foreach ($islands as $island) {
            $html = $this->replacePlaceholder(
                $html,
                $island->placeholder(),
                $this->inlineSlots($island->slotHtml, $island->children),
            );
        }

        return $html;
    }

    private function renderIsland(Island $island): string
    {
        $this->assertAllowed($island);

        if ($island->name === 'vellum::tabs') {
            return $this->renderTabs($island);
        }

        $slot = $this->render($island->slotHtml, $island->children);

        $name = $this->safeName($island->name);
        $bound = $this->boundAttributes($island->attributes);
        $attributes = $bound['template'];

        try {
            if ($island->selfClosing) {
                return Blade::render('<x-'.$name.$attributes.' />', $bound['data']);
            }

            return Blade::render(
                '<x-'.$name.$attributes.'>{!! $slot !!}</x-'.$name.'>',
                $bound['data'] + ['slot' => $slot],
            );
        } catch (UnknownComponentException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw UnknownComponentException::missing($island->name, $exception);
        }
    }

    private function renderTabs(Island $island): string
    {
        $items = [];

        foreach ($island->children as $child) {
            if ($child->name !== 'vellum::tab') {
                continue;
            }

            $this->assertAllowed($child);

            $label = $child->attributes['label'] ?? $child->attributes['title'] ?? 'Tab';

            $items[] = [
                'id' => $child->attributes['id'] ?? $this->tabId($label),
                'label' => $label,
                'html' => $this->render($child->slotHtml, $child->children),
            ];
        }

        return (string) MarkdownView::render('tabs', [
            'persist' => $island->attributes['persist'] ?? null,
            'code' => ($island->attributes['code'] ?? '') === 'true',
            'tabs' => $items,
            'slot' => new HtmlString(''),
        ]);
    }

    private function tabId(string $label): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $label) ?? ''));

        return trim($slug, '-') ?: 'tab';
    }

    private function assertAllowed(Island $island): void
    {
        if (! $this->namespaceAllowed($island->name)) {
            throw UnknownComponentException::notAllowed($island->name);
        }

        $kind = self::VALUE_TAGS[$island->name] ?? null;

        if ($kind === null) {
            return;
        }

        $key = $island->attributes['key'] ?? '';
        /** @var array<string, mixed> $allowlist */
        $allowlist = config('vellum.components.allowlist', []);
        $keys = $allowlist[$kind] ?? [];
        $keys = is_array($keys) ? $keys : [];

        if ($key === '' || ! in_array($key, $keys, true)) {
            throw UnknownComponentException::valueTagRefused($island->name, $key);
        }
    }

    private function namespaceAllowed(string $name): bool
    {
        $prefix = str_contains($name, '::') ? explode('::', $name, 2)[0] : '';
        /** @var mixed $configured */
        $configured = config('vellum.components.namespaces', ['vellum']);
        $namespaces = is_array($configured) ? $configured : ['vellum'];

        foreach ($namespaces as $namespace) {
            if (! is_string($namespace) && ! is_int($namespace)) {
                continue;
            }

            $namespace = (string) $namespace;

            if ($namespace === 'app') {
                $namespace = '';
            }

            if ($namespace === $prefix) {
                return true;
            }
        }

        return false;
    }

    private function safeName(string $name): string
    {
        if (preg_match('/^[a-zA-Z][\w.-]*(?:::[a-zA-Z][\w.-]*)?$/', $name) !== 1) {
            throw UnknownComponentException::missing($name);
        }

        return $name;
    }

    /**
     * Build bound attributes so values reach the component as data.
     *
     * Interpolating a value into the template string would let Blade compile
     * `{{ … }}` inside it; docs attributes are quoted strings and stay literal.
     *
     * @param  array<string, string>  $attributes
     * @return array{template: string, data: array<string, string>}
     */
    private function boundAttributes(array $attributes): array
    {
        $template = '';
        $data = [];

        foreach ($attributes as $key => $value) {
            if (preg_match('/^[a-zA-Z_][\w:-]*$/', $key) !== 1) {
                continue;
            }

            $variable = '__vellumAttr'.count($data);
            $data[$variable] = $value;
            $template .= ' :'.$key.'="$'.$variable.'"';
        }

        return ['template' => $template, 'data' => $data];
    }

    private function replacePlaceholder(string $html, string $placeholder, string $replacement): string
    {
        $quoted = preg_quote($placeholder, '/');
        $html = preg_replace('/<p>\s*'.$quoted.'\s*<\/p>/', $replacement, $html) ?? $html;

        return str_replace($placeholder, $replacement, $html);
    }
}
