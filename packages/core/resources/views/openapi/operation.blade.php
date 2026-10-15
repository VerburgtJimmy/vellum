@php
    use Vellum\OpenApi\SchemaView;

    $parameters = $operation->parametersByLocation();
    $requestBody = is_array($operation->requestBody['content'] ?? null) ? $operation->requestBody['content'] : [];
    $method = strtolower($operation->method);
    $webhook = $operation->webhook !== null;
    $payloadTabs ??= '';
    $statusClass = static function (string $status): string {
        $first = substr($status, 0, 1);

        return in_array($first, ['2', '3', '4', '5'], true) ? $first.'xx' : 'default';
    };
@endphp

{{-- Documentation first, examples second. A stylesheet can set the two side
     by side; read in order they are still a page. --}}
<div class="vellum-api-operation" data-vellum-operation="{{ $operation->headingId() }}">
    <div class="vellum-api-doc">
        <div class="vellum-api-endpoint" data-method="{{ $method }}">
            <span class="vellum-api-method" data-method="{{ $method }}">{{ $operation->method }}</span>
            @if ($webhook)
                <code class="vellum-api-path">{{ $operation->webhook }}</code>
                <span class="vellum-api-kind">Webhook</span>
            @else
                <code class="vellum-api-path">{!! $path($operation->path) !!}</code>
            @endif
            @if ($operation->deprecated)
                <span class="vellum-api-deprecated">Deprecated</span>
            @endif
            {{-- Hidden until a script says it can open something. The data
                 is everything a playground needs to build the request. --}}
            @if ($playground !== null)
                <button type="button" class="vellum-api-try" data-vellum-playground="{{ $playground }}" hidden>Try it</button>
            @endif
        </div>

        @if ($webhook)
            <p class="vellum-api-lede vellum-api-webhook-note">The API sends this request to an address you give it. Your app receives it and answers.</p>
        @endif

        @if ($body !== '')
            <div class="vellum-api-description">{!! $body !!}</div>
        @endif

        @if ($security !== [])
            <h2 id="authorization" class="vellum-heading vellum-api-section">Authorization</h2>
            <div class="vellum-api-panel">
                <div class="vellum-api-fields">
                    @foreach ($security as $scheme)
                        <div class="vellum-api-field">
                            <div class="vellum-api-field-head">
                                <code class="vellum-api-field-name">{{ $scheme['name'] }}</code>
                                <span class="vellum-api-field-type">{{ $scheme['detail'] }}</span>
                                @if ($scheme['deprecated'] ?? false)
                                    <span class="vellum-api-deprecated">Deprecated</span>
                                @endif
                            </div>
                            <p class="vellum-api-field-description">{{ $scheme['hint'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @foreach ($parameters as $location => $group)
            <h2 id="{{ $location }}-parameters" class="vellum-heading vellum-api-section">{{ ucfirst($location) }} parameters</h2>
            <div class="vellum-api-panel">
                <div class="vellum-api-fields" data-depth="0">
                    @foreach ($group as $parameter)
                        @include('vellum::openapi.field', [
                            'name' => is_string($parameter['name'] ?? null) ? $parameter['name'] : '',
                            'schema' => is_array($parameter['schema'] ?? null) ? $parameter['schema'] : [],
                            'required' => ! empty($parameter['required']),
                            'description' => $parameter['description'] ?? null,
                            'depth' => 0,
                            'anchor' => $location,
                            'extraChips' => array_key_exists('example', $parameter)
                                ? ['Example' => SchemaView::literal($parameter['example'])]
                                : [],
                        ])
                    @endforeach
                </div>
            </div>
        @endforeach

        @if ($requestBody !== [])
            @if ($webhook)
                <h2 id="payload" class="vellum-heading vellum-api-section">Payload</h2>
            @else
                <h2 id="request-body" class="vellum-heading vellum-api-section">Request body</h2>
            @endif

            @if (is_string($operation->requestBody['description'] ?? null))
                <p class="vellum-api-lede">{{ $operation->requestBody['description'] }}</p>
            @endif

            @foreach (array_keys($requestBody) as $bodyIndex => $type)
                @php
                    $media = $requestBody[$type];
                @endphp
                <div class="vellum-api-panel">
                    <div class="vellum-api-panel-head">
                        <code class="vellum-api-media">{{ $type }}</code>
                        @if (! empty($operation->requestBody['required']))
                            <span class="vellum-api-panel-note">Required</span>
                        @endif
                    </div>
                    @include('vellum::openapi.schema', ['schema' => is_array($media) ? ($media['schema'] ?? []) : [], 'depth' => 0, 'anchor' => $bodyIndex === 0 ? 'body' : 'body-'.($bodyIndex + 1)])
                </div>
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
                <div class="vellum-api-panel vellum-api-response">
                    <div class="vellum-api-panel-head">
                        <span class="vellum-api-status" data-status="{{ $statusClass((string) $status) }}">{{ $status }}</span>
                        @if (is_string($response['description'] ?? null) && $response['description'] !== '')
                            <span class="vellum-api-panel-title">{{ $response['description'] }}</span>
                        @endif
                        @if ($content !== [])
                            <code class="vellum-api-media">{{ array_key_first($content) }}</code>
                        @endif
                    </div>
                    @if (is_array($media))
                        @include('vellum::openapi.schema', ['schema' => $media['schema'] ?? [], 'depth' => 0, 'anchor' => 'response-'.\Vellum\Support\Slug::from((string) $status)])
                    @endif
                </div>
            @endforeach
        @endif

        @if ($operation->callbacks !== [])
            <h2 id="callbacks" class="vellum-heading vellum-api-section">Callbacks</h2>
            <p class="vellum-api-lede">Requests the API sends back once this one is made, to the address shown.</p>

            @foreach ($operation->callbacks as $index => $callback)
                @php
                    $call = $callback['operation'];
                    $callMethod = strtolower($call->method);
                    $callContent = is_array($call->requestBody['content'] ?? null) ? $call->requestBody['content'] : [];
                    $callMedia = $callContent === [] ? null : reset($callContent);
                @endphp
                <div class="vellum-api-panel vellum-api-callback">
                    <div class="vellum-api-panel-head">
                        <span class="vellum-api-method" data-method="{{ $callMethod }}">{{ $call->method }}</span>
                        <code class="vellum-api-callback-url">{{ $call->path }}</code>
                        <span class="vellum-api-panel-note">{{ $call->summary ?? $callback['name'] }}</span>
                    </div>
                    @if ($call->description !== null && trim($call->description) !== '')
                        <div class="vellum-api-callback-description">{!! $markdown($call->description) !!}</div>
                    @endif
                    @if (is_array($callMedia))
                        @include('vellum::openapi.schema', ['schema' => $callMedia['schema'] ?? [], 'depth' => 0, 'anchor' => 'callback-'.($index + 1)])
                    @endif
                    @if ($call->responses !== [])
                        <p class="vellum-api-callback-answers">
                            <span class="vellum-api-chip-label">Answer with</span>
                            @foreach ($call->responses as $status => $response)
                                <span class="vellum-api-status" data-status="{{ $statusClass((string) $status) }}">{{ $status }}</span>
                            @endforeach
                        </p>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    @if ($sampleTabs !== '' || $responseTabs !== '' || $payloadTabs !== '')
        <aside class="vellum-api-examples" aria-label="Examples">
            @if ($payloadTabs !== '')
                <div class="vellum-api-example vellum-api-samples">
                    <p class="vellum-api-example-label">Payload</p>
                    {!! $payloadTabs !!}
                </div>
            @endif
            @if ($sampleTabs !== '')
                <div class="vellum-api-example vellum-api-samples">
                    <p class="vellum-api-example-label">Request</p>
                    {!! $sampleTabs !!}
                </div>
            @endif
            @if ($responseTabs !== '')
                <div class="vellum-api-example vellum-api-response-examples">
                    <p class="vellum-api-example-label">Response</p>
                    {!! $responseTabs !!}
                </div>
            @endif
        </aside>
    @endif
</div>
