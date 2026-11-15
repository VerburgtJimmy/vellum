{{-- One row of a schema or a parameter list: name, type, what it means. --}}
@php
    use Vellum\OpenApi\SchemaView;
    use Vellum\Support\Slug;

    $enum = SchemaView::enumValues($schema);
    $chips = [...SchemaView::chips($schema), ...($extraChips ?? [])];

    // An address for the field, made of the names that lead to it, so a
    // link can point at body-captain-name and land on it.
    $slug = Slug::from(str_replace(['/', '.'], '-', $name));
    $id = ($anchor ?? '') !== '' && $slug !== '' ? $anchor.'-'.$slug : null;
@endphp
<div class="vellum-api-field" @if ($id !== null) id="{{ $id }}" @endif>
    <div class="vellum-api-field-head">
        <code class="vellum-api-field-name">@if ($id !== null)<a class="vellum-api-field-link" href="#{{ $id }}">{{ $name }}</a>@else{{ $name }}@endif<span
            class="vellum-api-marker"
            data-required="{{ $required ? 'true' : 'false' }}"
            title="{{ $required ? __('vellum-openapi::reference.required') : __('vellum-openapi::reference.optional') }}"
        >{{ $required ? '*' : '?' }}</span></code>
        <span class="vellum-api-field-type">{{ SchemaView::type($schema) }}</span>
    </div>

    @if (is_string($description ?? null) && $description !== '')
        <div class="vellum-api-field-description">{!! $describe($description) !!}</div>
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
            <span class="vellum-api-enum-label">{{ array_key_exists('const', $schema) ? __('vellum-openapi::reference.fields.always') : __('vellum-openapi::reference.fields.value_in') }}</span>
            @foreach ($enum as $value)
                <code>{{ $value }}</code>
            @endforeach
        </p>
    @endif

    @if (SchemaView::isRecursive($schema))
        <p class="vellum-api-field-meta vellum-api-recursive">{{ __('vellum-openapi::reference.fields.repeats', ['name' => SchemaView::name(SchemaView::body($schema)) ?? SchemaView::name($schema) ?? __('vellum-openapi::reference.fields.the_schema_above')]) }}</p>
    @elseif (SchemaView::expandable($schema))
        <details class="vellum-api-nested" @if (($depth ?? 0) < 1) open @endif>
            <summary class="vellum-api-toggle">{{ __('vellum-openapi::reference.fields.properties') }}</summary>
            @include('vellum::openapi.schema', ['schema' => SchemaView::body($schema), 'depth' => ($depth ?? 0) + 1, 'anchor' => $id ?? ''])
        </details>
    @endif
</div>
