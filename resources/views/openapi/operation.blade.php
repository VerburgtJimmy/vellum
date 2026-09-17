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
    {{--
      Left column is the documentation, right column is the examples. That
      split is the whole layout: a reader either wants to know what a field
      means, or wants something to paste.
    --}}
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
            @foreach ($security as $scheme)
                <div class="vellum-api-field">
                    <div class="vellum-api-field-head">
                        <code class="vellum-api-field-name">{{ $scheme['name'] }}</code>
                        <span class="vellum-api-field-type">{{ $scheme['detail'] }}</span>
                    </div>
                    <p class="vellum-api-field-description">{{ $scheme['hint'] }}</p>
                </div>
            @endforeach
        @endif

        @foreach ($parameters as $location => $group)
            <h2 id="{{ $location }}-parameters" class="vellum-heading vellum-api-section">{{ ucfirst($location) }} parameters</h2>

            @if (count($group) > 8)
                {{-- Past eight, the list is longer than everything else here. --}}
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

        @if ($requestBody !== [])
            <h2 id="request-body" class="vellum-heading vellum-api-section">Request body</h2>

            @if ($operation->requestBody['description'] ?? null)
                <p class="vellum-api-lede">{{ $operation->requestBody['description'] }}</p>
            @endif

            @if (count($requestBody) > 1)
                <x-vellum::ui.tabs :default="'body-0'" class="vellum-api-content-types">
                    <x-vellum::ui.tabs.list>
                        @foreach (array_keys($requestBody) as $index => $type)
                            <x-vellum::ui.tabs.trigger :value="'body-'.$index">{{ $type }}</x-vellum::ui.tabs.trigger>
                        @endforeach
                    </x-vellum::ui.tabs.list>
                    @foreach (array_values($requestBody) as $index => $media)
                        <x-vellum::ui.tabs.content :value="'body-'.$index">
                            <x-vellum::openapi.schema :schema="$media['schema'] ?? []" />
                        </x-vellum::ui.tabs.content>
                    @endforeach
                </x-vellum::ui.tabs>
            @else
                <x-vellum::openapi.schema :schema="reset($requestBody)['schema'] ?? []" />
            @endif
        @endif

        @if ($operation->responses !== [])
            <h2 id="responses" class="vellum-heading vellum-api-section">Responses</h2>

            @foreach ($operation->responses as $status => $response)
                @php $content = is_array($response['content'] ?? null) ? $response['content'] : []; @endphp
                <h3 class="vellum-api-response-heading">
                    <span class="vellum-api-status" data-status="{{ $statusClass((string) $status) }}">{{ $status }}</span>
                    @if ($content !== [])<code class="vellum-api-media">{{ array_key_first($content) }}</code>@endif
                </h3>

                @if (! empty($response['description']))
                    <p class="vellum-api-lede">{{ $response['description'] }}</p>
                @endif

                @if ($content !== [])
                    @php $media = reset($content); @endphp
                    <x-vellum::openapi.schema :schema="$media['schema'] ?? []" />
                @endif
            @endforeach
        @endif
    </div>

    {{-- Sticky within the page, which is one operation, so it never rides
         over anything that is not its own. --}}
    <aside class="vellum-api-examples" aria-label="Examples">
        @if ($samples !== [])
            <x-vellum::ui.tabs :default="$samples[0]['key']" class="vellum-api-samples" persist="api-sample">
                <x-vellum::ui.tabs.list>
                    @foreach ($samples as $sample)
                        <x-vellum::ui.tabs.trigger :value="$sample['key']">{{ $sample['label'] }}</x-vellum::ui.tabs.trigger>
                    @endforeach
                </x-vellum::ui.tabs.list>
                @foreach ($samples as $sample)
                    <x-vellum::ui.tabs.content :value="$sample['key']">
                        {!! $code($sample['code'], $sample['highlight']) !!}
                    </x-vellum::ui.tabs.content>
                @endforeach
            </x-vellum::ui.tabs>
        @endif

        @if ($responseExamples !== [])
            <x-vellum::ui.tabs :default="'example-'.array_key_first($responseExamples)" class="vellum-api-response-examples">
                <x-vellum::ui.tabs.list>
                    @foreach ($responseExamples as $status => $example)
                        <x-vellum::ui.tabs.trigger
                            :value="'example-'.$status"
                            class="vellum-api-status"
                            data-status="{{ $statusClass((string) $status) }}"
                        >{{ $status }}</x-vellum::ui.tabs.trigger>
                    @endforeach
                </x-vellum::ui.tabs.list>
                @foreach ($responseExamples as $status => $example)
                    <x-vellum::ui.tabs.content :value="'example-'.$status">
                        {!! $code($example, 'json') !!}
                    </x-vellum::ui.tabs.content>
                @endforeach
            </x-vellum::ui.tabs>
        @endif
    </aside>
</div>
