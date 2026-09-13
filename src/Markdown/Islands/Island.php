<?php

declare(strict_types=1);

namespace Vellum\Markdown\Islands;

/**
 * A compiled component island. Children stay as placeholders in slotHtml until request-time render.
 *
 * @phpstan-type IslandArray array{
 *     id: string,
 *     name: string,
 *     attributes: array<string, string>,
 *     slotHtml: string,
 *     selfClosing: bool,
 *     children: list<array<string, mixed>>
 * }
 */
final readonly class Island
{
    /**
     * @param  array<string, string>  $attributes
     * @param  list<self>  $children
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $attributes,
        public string $slotHtml,
        public array $children,
        public bool $selfClosing,
    ) {}

    public function placeholder(): string
    {
        return self::token($this->id);
    }

    public static function token(string $id): string
    {
        return 'VELLUMISLAND_'.$id.'_VELLUM';
    }

    /**
     * @return IslandArray
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'attributes' => $this->attributes,
            'slotHtml' => $this->slotHtml,
            'selfClosing' => $this->selfClosing,
            'children' => array_map(static fn (self $child): array => $child->toArray(), $this->children),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        if (
            ! isset($data['id'], $data['name'], $data['slotHtml'])
            || ! is_string($data['id'])
            || ! is_string($data['name'])
            || ! is_string($data['slotHtml'])
        ) {
            return null;
        }

        $attributes = [];

        if (isset($data['attributes']) && is_array($data['attributes'])) {
            foreach ($data['attributes'] as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $attributes[$key] = $value;
                }
            }
        }

        $children = [];

        if (isset($data['children']) && is_array($data['children'])) {
            foreach ($data['children'] as $child) {
                if (is_array($child)) {
                    $parsed = self::fromArray($child);

                    if ($parsed !== null) {
                        $children[] = $parsed;
                    }
                }
            }
        }

        return new self(
            id: $data['id'],
            name: $data['name'],
            attributes: $attributes,
            slotHtml: $data['slotHtml'],
            children: $children,
            selfClosing: isset($data['selfClosing']) && $data['selfClosing'] === true,
        );
    }

    /**
     * @return list<self>
     */
    public static function listFromArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $islands = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $island = self::fromArray($item);

                if ($island !== null) {
                    $islands[] = $island;
                }
            }
        }

        return $islands;
    }
}
