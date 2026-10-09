{{-- One row of a schema or a parameter list: name, type, what it means. --}}
@php
    use Vellum\OpenApi\SchemaView;

    $enum = SchemaView::enumValues($schema);
    $chips = [...SchemaView::chips($schema), ...($extraChips ?? [])];
@endphp
<div class="vellum-api-field">
    <div class="vellum-api-field-head">
        <code class="vellum-api-field-name">{{ $name }}<span
            class="vellum-api-marker"
            data-required="{{ $required ? 'true' : 'false' }}"
            title="{{ $required ? 'Required' : 'Optional' }}"
        >{{ $required ? '*' : '?' }}</span></code>
        <span class="vellum-api-field-type">{{ SchemaView::type($schema) }}</span>
    </div>

    @if (is_string($description ?? null) && $description !== '')
        <p class="vellum-api-field-description">{{ $description }}</p>
    @endif

    @if ($chips !== [])
        <p class="vellum-api-chips">
            @foreach ($chips as $label => $value)
                <span class="vellum-api-chip"><span class="vellum-api-chip-label">{{ $label }}</span> <code>{{ $value }}</code></span>
            @endforeach
        </p>
    @endif

    @if ($enum !== [])
        <p class="vellum-api-enum">
            <span class="vellum-api-enum-label">Value in</span>
            @foreach ($enum as $value)
                <code>{{ $value }}</code>
            @endforeach
        </p>
    @endif

    @if (SchemaView::isRecursive($schema))
        <p class="vellum-api-field-meta vellum-api-recursive">Repeats {{ SchemaView::name(SchemaView::body($schema)) ?? SchemaView::name($schema) ?? 'the schema above' }}</p>
    @elseif (SchemaView::expandable($schema))
        <details class="vellum-api-nested" @if (($depth ?? 0) < 1) open @endif>
            <summary class="vellum-api-toggle">Properties</summary>
            @include('vellum::openapi.schema', ['schema' => SchemaView::body($schema), 'depth' => ($depth ?? 0) + 1])
        </details>
    @endif
</div>
