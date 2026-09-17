@php use Vellum\OpenApi\SchemaView; @endphp

<div class="vellum-api-fields" data-depth="0">
    @foreach ($parameters as $parameter)
        @php
            $schema = is_array($parameter['schema'] ?? null) ? $parameter['schema'] : [];
            $enum = SchemaView::enumValues($schema);
            $chips = SchemaView::chips($schema);
            $required = ! empty($parameter['required']);

            if (array_key_exists('example', $parameter)) {
                $chips['Example'] = SchemaView::literal($parameter['example']);
            }
        @endphp
        <div class="vellum-api-field">
            <div class="vellum-api-field-head">
                <code class="vellum-api-field-name">{{ $parameter['name'] ?? '' }}<span
                    class="vellum-api-marker"
                    data-required="{{ $required ? 'true' : 'false' }}"
                    title="{{ $required ? 'Required' : 'Optional' }}"
                >{{ $required ? '*' : '?' }}</span></code>
                <span class="vellum-api-field-type">{{ SchemaView::type($schema) }}</span>
            </div>

            @if (! empty($parameter['description']))
                <p class="vellum-api-field-description">{{ $parameter['description'] }}</p>
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
        </div>
    @endforeach
</div>
