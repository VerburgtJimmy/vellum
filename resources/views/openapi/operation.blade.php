@php
    use Vellum\OpenApi\SchemaView;

    $parameters = $operation->parametersByLocation();
    $requestBodyContent = is_array($operation->requestBody['content'] ?? null) ? $operation->requestBody['content'] : [];
    $responses = $operation->responses;
    $security = $security ?? [];
    $statusClass = static function (string $status): string {
        $first = substr($status, 0, 1);

        return in_array($first, ['2', '3', '4', '5'], true) ? $first.'xx' : 'default';
    };
@endphp

<section class="vellum-api-operation" data-vellum-operation="{{ $operation->headingId() }}">
    <div class="vellum-api-operation-head">
        <p class="vellum-api-request">
            <span class="vellum-api-method" data-method="{{ strtolower($operation->method) }}">{{ $operation->method }}</span>
            <code class="vellum-api-path">{!! $path($operation->path) !!}</code>
            @if ($operation->deprecated)
                <span class="vellum-api-deprecated">Deprecated</span>
            @endif
        </p>

        <h2 id="{{ $operation->headingId() }}" class="vellum-heading vellum-api-operation-title">{{ $operation->title() }}</h2>

        @if ($operation->description !== null)
            <div class="vellum-api-description">{!! $markdown($operation->description) !!}</div>
        @endif
    </div>

    {{--
      Two columns on desktop, stacked on mobile. The right column is sticky
      within this section only: each operation is its own stacking context, so
      the samples follow their own endpoint and let go at the next one rather
      than sliding over it.
    --}}
    <div class="vellum-api-columns">
        <div class="vellum-api-column vellum-api-column-request">
            @if ($security !== [])
                <h3 class="vellum-api-subhead">Authentication</h3>
                <div class="vellum-api-security">
                    @foreach ($security as $scheme)
                        <x-vellum::ui.tooltip>
                            <x-slot:trigger><code class="vellum-api-scheme">{{ $scheme['name'] }}</code></x-slot:trigger>
                            <x-slot:content>{{ $scheme['hint'] }}</x-slot:content>
                        </x-vellum::ui.tooltip>
                    @endforeach
                </div>
            @endif

            @foreach ($parameters as $location => $group)
                @php $collapsed = count($group) > 8; @endphp
                <h3 class="vellum-api-subhead">{{ ucfirst($location) }} parameters</h3>

                @if ($collapsed)
                    {{-- Past eight, the list is longer than the rest of the section. --}}
                    <x-vellum::ui.collapsible :open="false" class="vellum-api-param-group">
                        <x-slot:trigger>
                            <span class="vellum-api-expand">
                                {!! \Vellum\Support\Icons::caretRight(['class' => 'h-3 w-3 shrink-0 transition-transform', ':class' => "open && 'rotate-90'"]) !!}
                                <span x-text="open ? 'Hide' : 'Show'">Show</span>
                                <span>{{ count($group) }} parameters</span>
                            </span>
                        </x-slot:trigger>
                        <x-slot:content>
                            @include('vellum::openapi.parameters', ['parameters' => $group])
                        </x-slot:content>
                    </x-vellum::ui.collapsible>
                @else
                    @include('vellum::openapi.parameters', ['parameters' => $group])
                @endif
            @endforeach

            @if ($requestBodyContent !== [])
                <h3 class="vellum-api-subhead">Request body</h3>

                @if ($operation->requestBody['description'] ?? null)
                    <p class="vellum-api-field-description">{{ $operation->requestBody['description'] }}</p>
                @endif

                @if (count($requestBodyContent) > 1)
                    <x-vellum::ui.tabs :default="'body-0'" class="vellum-api-content-types">
                        <x-vellum::ui.tabs.list>
                            @foreach (array_keys($requestBodyContent) as $index => $type)
                                <x-vellum::ui.tabs.trigger :value="'body-'.$index">{{ $type }}</x-vellum::ui.tabs.trigger>
                            @endforeach
                        </x-vellum::ui.tabs.list>
                        @foreach (array_values($requestBodyContent) as $index => $media)
                            <x-vellum::ui.tabs.content :value="'body-'.$index">
                                <x-vellum::openapi.schema :schema="$media['schema'] ?? []" />
                            </x-vellum::ui.tabs.content>
                        @endforeach
                    </x-vellum::ui.tabs>
                @else
                    <x-vellum::openapi.schema :schema="reset($requestBodyContent)['schema'] ?? []" />
                @endif
            @endif
        </div>

        <div class="vellum-api-column vellum-api-column-detail">
            <div class="vellum-api-sticky">
                @if ($responses !== [])
                    <h3 class="vellum-api-subhead">Responses</h3>
                    <x-vellum::ui.tabs :default="'status-'.array_key_first($responses)" class="vellum-api-responses">
                        <x-vellum::ui.tabs.list>
                            @foreach ($responses as $status => $response)
                                <x-vellum::ui.tabs.trigger
                                    :value="'status-'.$status"
                                    class="vellum-api-status"
                                    data-status="{{ $statusClass((string) $status) }}"
                                >{{ $status }}</x-vellum::ui.tabs.trigger>
                            @endforeach
                        </x-vellum::ui.tabs.list>
                        @foreach ($responses as $status => $response)
                            <x-vellum::ui.tabs.content :value="'status-'.$status">
                                @if (! empty($response['description']))
                                    <p class="vellum-api-field-description">{{ $response['description'] }}</p>
                                @endif
                                @php $content = is_array($response['content'] ?? null) ? $response['content'] : []; @endphp
                                @if ($content !== [])
                                    @php $media = reset($content); @endphp
                                    <p class="vellum-api-field-meta"><code>{{ array_key_first($content) }}</code></p>
                                    <x-vellum::openapi.schema :schema="$media['schema'] ?? []" />
                                @endif
                            </x-vellum::ui.tabs.content>
                        @endforeach
                    </x-vellum::ui.tabs>
                @endif
            </div>
        </div>
    </div>
</section>
