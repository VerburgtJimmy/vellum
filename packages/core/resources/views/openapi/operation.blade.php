@php
    use Vellum\OpenApi\SchemaView;

    $parameters = $operation->parametersByLocation();
    $requestBody = is_array($operation->requestBody['content'] ?? null) ? $operation->requestBody['content'] : [];
    $statusClass = static function (string $status): string {
        $first = substr($status, 0, 1);

        return in_array($first, ['2', '3', '4', '5'], true) ? $first.'xx' : 'default';
    };
@endphp

<div class="vellum-api-operation" data-vellum-operation="{{ $operation->headingId() }}">
    <div class="vellum-api-doc">
        <div class="vellum-api-endpoint">
            <span class="vellum-api-method" data-method="{{ strtolower($operation->method) }}">{{ $operation->method }}</span>
            <code class="vellum-api-path">{!! $path($operation->path) !!}</code>
            @if ($operation->deprecated)
                <span class="vellum-api-deprecated">Deprecated</span>
            @endif
        </div>

        @if ($operation->description !== null)
            <div class="vellum-api-description">{!! $markdown($operation->description) !!}</div>
        @endif

        @if ($security !== [])
            <h2 id="authorization" class="vellum-heading vellum-api-section">Authorization</h2>
            <div class="vellum-api-fields">
                @foreach ($security as $scheme)
                    <div class="vellum-api-field">
                        <div class="vellum-api-field-head">
                            <code class="vellum-api-field-name">{{ $scheme['name'] }}</code>
                            <span class="vellum-api-field-type">{{ $scheme['detail'] }}</span>
                        </div>
                        <p class="vellum-api-field-description">{{ $scheme['hint'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        @foreach ($parameters as $location => $group)
            <h2 id="{{ $location }}-parameters" class="vellum-heading vellum-api-section">{{ ucfirst($location) }} parameters</h2>
            <div class="vellum-api-fields" data-depth="0">
                @foreach ($group as $parameter)
                    @include('vellum::openapi.field', [
                        'name' => is_string($parameter['name'] ?? null) ? $parameter['name'] : '',
                        'schema' => is_array($parameter['schema'] ?? null) ? $parameter['schema'] : [],
                        'required' => ! empty($parameter['required']),
                        'description' => $parameter['description'] ?? null,
                        'depth' => 0,
                        'extraChips' => array_key_exists('example', $parameter)
                            ? ['Example' => SchemaView::literal($parameter['example'])]
                            : [],
                    ])
                @endforeach
            </div>
        @endforeach

        @if ($requestBody !== [])
            <h2 id="request-body" class="vellum-heading vellum-api-section">Request body</h2>

            @if (is_string($operation->requestBody['description'] ?? null))
                <p class="vellum-api-lede">{{ $operation->requestBody['description'] }}</p>
            @endif

            @foreach ($requestBody as $type => $media)
                <p class="vellum-api-media-line"><code class="vellum-api-media">{{ $type }}</code></p>
                @include('vellum::openapi.schema', ['schema' => is_array($media) ? ($media['schema'] ?? []) : [], 'depth' => 0])
            @endforeach
        @endif

        @if ($operation->responses !== [])
            <h2 id="responses" class="vellum-heading vellum-api-section">Responses</h2>

            @foreach ($operation->responses as $status => $response)
                @php
                    $response = is_array($response) ? $response : [];
                    $content = is_array($response['content'] ?? null) ? $response['content'] : [];
                    $media = $content === [] ? null : reset($content);
                @endphp
                <h3 class="vellum-api-response-heading">
                    <span class="vellum-api-status" data-status="{{ $statusClass((string) $status) }}">{{ $status }}</span>
                    @if ($content !== [])<code class="vellum-api-media">{{ array_key_first($content) }}</code>@endif
                </h3>

                @if (is_string($response['description'] ?? null) && $response['description'] !== '')
                    <p class="vellum-api-lede">{{ $response['description'] }}</p>
                @endif

                @if (is_array($media))
                    @include('vellum::openapi.schema', ['schema' => $media['schema'] ?? [], 'depth' => 0])
                @endif
            @endforeach
        @endif
    </div>

    @if ($sampleTabs !== '' || $responseTabs !== '')
        <aside class="vellum-api-examples" aria-label="Examples">
            @if ($sampleTabs !== '')
                <div class="vellum-api-samples">{!! $sampleTabs !!}</div>
            @endif
            @if ($responseTabs !== '')
                <div class="vellum-api-response-examples">{!! $responseTabs !!}</div>
            @endif
        </aside>
    @endif
</div>
