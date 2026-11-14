@php
    use Vellum\OpenApi\SchemaView;

    $schema = is_array($schema ?? null) ? $schema : [];
    $depth = $depth ?? 0;
    $anchor = $anchor ?? '';
    $body = SchemaView::body($schema);
    $variants = SchemaView::variants($body);
    $discriminator = SchemaView::discriminator($body);
    $properties = SchemaView::properties($body);
@endphp

@if ($variants !== [])
    <div class="vellum-api-variants">
        @if ($discriminator !== null)
            <p class="vellum-api-field-meta vellum-api-discriminator"><code>{{ $discriminator['property'] }}</code> says which of these it is.</p>
        @endif
        @foreach ($variants as $index => $variant)
            <details class="vellum-api-variant" @if ($index === 0) open @endif>
                <summary class="vellum-api-toggle">{{ $variant['label'] }}@if (isset($discriminator['values'][$index])) <code class="vellum-api-variant-value">{{ $discriminator['property'] }}: {{ $discriminator['values'][$index] }}</code>@endif @if (($discriminator['default'] ?? null) === $index)<span class="vellum-api-variant-default">default</span>@endif</summary>
                {{-- Each variant numbered, so a field in two of them has two addresses. --}}
                @include('vellum::openapi.schema', ['schema' => $variant['schema'], 'depth' => $depth + 1, 'anchor' => $anchor === '' ? '' : $anchor.'-'.($index + 1)])
            </details>
        @endforeach
    </div>
@elseif ($properties !== [])
    <div class="vellum-api-fields" data-depth="{{ $depth }}">
        @foreach ($properties as $property)
            @include('vellum::openapi.field', [
                'name' => $property['name'],
                'schema' => $property['schema'],
                'required' => $property['required'],
                'description' => $property['description'],
                'depth' => $depth,
                'extraChips' => [],
                'anchor' => $anchor,
            ])
        @endforeach
    </div>
@elseif ($schema !== [])
    <p class="vellum-api-field-meta">{{ SchemaView::type($schema) }}</p>
@endif
