@props([
    'schema' => [],
    'depth' => 0,
])

@php
    use Vellum\OpenApi\SchemaView;

    $schema = is_array($schema) ? $schema : [];
    $body = SchemaView::body($schema);
    $variants = SchemaView::variants($body);
    $properties = SchemaView::properties($body);
    // Depth 0 and 1 are open; deeper nesting is a choice the reader makes.
    $openByDefault = $depth < 1;
@endphp

@if ($variants !== [])
    <x-vellum::ui.tabs :default="'variant-0'" class="vellum-api-variants">
        <x-vellum::ui.tabs.list>
            @foreach ($variants as $index => $variant)
                <x-vellum::ui.tabs.trigger :value="'variant-'.$index">{{ $variant['label'] }}</x-vellum::ui.tabs.trigger>
            @endforeach
        </x-vellum::ui.tabs.list>
        @foreach ($variants as $index => $variant)
            <x-vellum::ui.tabs.content :value="'variant-'.$index">
                <x-vellum::openapi.schema :schema="$variant['schema']" :depth="$depth + 1" />
            </x-vellum::ui.tabs.content>
        @endforeach
    </x-vellum::ui.tabs>
@elseif ($properties !== [])
    <ul class="vellum-api-fields" data-depth="{{ $depth }}">
        @foreach ($properties as $property)
            @php
                $child = SchemaView::body($property['schema']);
                $enum = SchemaView::enumValues($property['schema']);
                $default = $property['schema']['default'] ?? null;
            @endphp
            <li class="vellum-api-field">
                <div class="vellum-api-field-head">
                    <code class="vellum-api-field-name">{{ $property['name'] }}</code>
                    <span class="vellum-api-field-type">{{ $property['type'] }}</span>
                    @if ($property['required'])
                        <span class="vellum-api-required">required</span>
                    @endif
                </div>

                @if ($property['description'] !== null)
                    <p class="vellum-api-field-description">{{ $property['description'] }}</p>
                @endif

                @if ($default !== null)
                    <p class="vellum-api-field-meta">Default <code>{{ SchemaView::literal($default) }}</code></p>
                @endif

                @if ($enum !== [])
                    <p class="vellum-api-field-meta">One of
                        @foreach ($enum as $value)<code>{{ $value }}</code>@if (! $loop->last), @endif @endforeach
                    </p>
                @endif

                @if (SchemaView::isRecursive($property['schema']))
                    @php $repeats = SchemaView::name(SchemaView::body($property['schema'])) ?? SchemaView::name($property['schema']); @endphp
                    <p class="vellum-api-field-meta vellum-api-recursive">Repeats {{ $repeats ?? 'the schema above' }}</p>
                @elseif ($property['expandable'])
                    <x-vellum::ui.collapsible :open="$openByDefault" class="vellum-api-nested">
                        <x-slot:trigger>
                            <span class="vellum-api-expand">
                                {!! \Vellum\Support\Icons::caretRight(['class' => 'h-3 w-3 shrink-0 transition-transform', ':class' => "open && 'rotate-90'"]) !!}
                                <span x-text="open ? 'Hide' : 'Show'">Show</span>
                                <span>{{ $property['name'] }}</span>
                            </span>
                        </x-slot:trigger>
                        <x-slot:content>
                            <x-vellum::openapi.schema :schema="$child" :depth="$depth + 1" />
                        </x-slot:content>
                    </x-vellum::ui.collapsible>
                @endif
            </li>
        @endforeach
    </ul>
@else
    <p class="vellum-api-field-meta">{{ SchemaView::type($schema) }}</p>
@endif
