@php use Vellum\OpenApi\SchemaView; @endphp

<ul class="vellum-api-fields" data-depth="0">
    @foreach ($parameters as $parameter)
        @php
            $schema = is_array($parameter['schema'] ?? null) ? $parameter['schema'] : [];
            $enum = SchemaView::enumValues($schema);
            $default = $schema['default'] ?? null;
            $example = $parameter['example'] ?? null;
        @endphp
        <li class="vellum-api-field">
            <div class="vellum-api-field-head">
                <code class="vellum-api-field-name">{{ $parameter['name'] ?? '' }}</code>
                <span class="vellum-api-field-type">{{ SchemaView::type($schema) }}</span>
                @if (! empty($parameter['required']))
                    <span class="vellum-api-required">required</span>
                @endif
            </div>

            @if (! empty($parameter['description']))
                <p class="vellum-api-field-description">{{ $parameter['description'] }}</p>
            @endif

            @if ($default !== null)
                <p class="vellum-api-field-meta">Default <code>{{ SchemaView::literal($default) }}</code></p>
            @endif

            @if ($enum !== [])
                <p class="vellum-api-field-meta">One of
                    @foreach ($enum as $value)<code>{{ $value }}</code>@if (! $loop->last), @endif @endforeach
                </p>
            @endif

            @if ($example !== null)
                <p class="vellum-api-field-meta">Example <code>{{ SchemaView::literal($example) }}</code></p>
            @endif
        </li>
    @endforeach
</ul>
