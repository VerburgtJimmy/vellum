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
    <div class="vellum-api-fields" data-depth="{{ $depth }}">
        @foreach ($properties as $property)
            @php
                $child = SchemaView::body($property['schema']);
                $enum = SchemaView::enumValues($property['schema']);
                $chips = SchemaView::chips($property['schema']);
            @endphp
            <div class="vellum-api-field">
                <div class="vellum-api-field-head">
                    <code class="vellum-api-field-name">{{ $property['name'] }}<span
                        class="vellum-api-marker"
                        data-required="{{ $property['required'] ? 'true' : 'false' }}"
                        title="{{ $property['required'] ? 'Required' : 'Optional' }}"
                    >{{ $property['required'] ? '*' : '?' }}</span></code>
                    <span class="vellum-api-field-type">{{ $property['type'] }}</span>
                </div>

                @if ($property['description'] !== null)
                    <p class="vellum-api-field-description">{{ $property['description'] }}</p>
                @endif

                @if ($chips !== [])
                    <p class="vellum-api-chips">
                        @foreach ($chips as $label => $value)
                            <span class="vellum-api-chip"><span class="vellum-api-chip-label">{{ $label }}</span> <code>{{ $value }}</code></span>
                        @endforeach
                    </p>
                @endif

                @if ($enum !== [])
                    <div class="vellum-api-enum">
                        <p class="vellum-api-enum-label">Value in</p>
                        <ul>
                            @foreach ($enum as $value)
                                <li><code>"{{ $value }}"</code></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (SchemaView::isRecursive($property['schema']))
                    @php $repeats = SchemaView::name(SchemaView::body($property['schema'])) ?? SchemaView::name($property['schema']); @endphp
                    <p class="vellum-api-field-meta vellum-api-recursive">Repeats {{ $repeats ?? 'the schema above' }}</p>
                @elseif ($property['expandable'])
                    <x-vellum::ui.collapsible :open="$openByDefault" class="vellum-api-nested">
                        <x-slot:trigger>
                            <span class="vellum-api-expand">
                                {!! \Vellum\Support\Icons::caretRight(['class' => 'h-3 w-3 shrink-0 transition-transform', ':class' => "open && 'rotate-90'"]) !!}
                                <span x-text="open ? 'Hide properties' : 'Show properties'">Show properties</span>
                            </span>
                        </x-slot:trigger>
                        <x-slot:content>
                            <x-vellum::openapi.schema :schema="$child" :depth="$depth + 1" />
                        </x-slot:content>
                    </x-vellum::ui.collapsible>
                @endif
            </div>
        @endforeach
    </div>
@else
    <p class="vellum-api-field-meta">{{ SchemaView::type($schema) }}</p>
@endif
