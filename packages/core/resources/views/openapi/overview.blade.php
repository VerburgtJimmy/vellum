@if ($intro !== '')
    <div class="vellum-api-intro">{!! $intro !!}</div>
@endif

@if ($spec->apiVersion() !== null)
    <p class="vellum-api-version">Version <code>{{ $spec->apiVersion() }}</code></p>
@endif

@if ($spec->servers() !== [])
    <h2 id="servers" class="vellum-heading vellum-api-section">Servers</h2>
    <div class="vellum-api-panel">
        <div class="vellum-api-fields">
            @foreach ($spec->servers() as $server)
                <div class="vellum-api-field">
                    <div class="vellum-api-field-head">
                        <code class="vellum-api-field-name">{{ is_string($server['url'] ?? null) ? $server['url'] : '' }}</code>
                        @if (is_string($server['name'] ?? null) && $server['name'] !== '')
                            <span class="vellum-api-field-type">{{ $server['name'] }}</span>
                        @endif
                    </div>
                    @if (is_string($server['description'] ?? null) && $server['description'] !== '')
                        <p class="vellum-api-field-description">{{ $server['description'] }}</p>
                    @endif
                    @foreach (\Vellum\OpenApi\Servers::variables($server) as $variable)
                        <p class="vellum-api-enum">
                            <span class="vellum-api-enum-label">&#123;{{ $variable['name'] }}&#125; is</span>
                            <code>{{ $variable['default'] }}</code>
                            @foreach (array_diff($variable['values'], [$variable['default']]) as $value)
                                {!! $loop->first ? '<span class="vellum-api-enum-label">or</span>' : '' !!}
                                <code>{{ $value }}</code>
                            @endforeach
                            @if ($variable['description'] !== null)
                                <span class="vellum-api-server-variable">{{ $variable['description'] }}</span>
                            @endif
                        </p>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
@endif

@if ($spec->securitySchemes() !== [])
    <h2 id="authentication" class="vellum-heading vellum-api-section">Authentication</h2>
    <div class="vellum-api-panel">
        <div class="vellum-api-fields">
            @foreach ($spec->securitySchemes() as $name => $scheme)
                <div class="vellum-api-field">
                    <div class="vellum-api-field-head">
                        <code class="vellum-api-field-name">{{ $name }}</code>
                        <span class="vellum-api-field-type">{{ is_string($scheme['type'] ?? null) ? $scheme['type'] : 'unknown' }}@if (is_string($scheme['scheme'] ?? null)) ({{ $scheme['scheme'] }})@endif</span>
                        @if ((bool) ($scheme['deprecated'] ?? false))
                            <span class="vellum-api-deprecated">Deprecated</span>
                        @endif
                    </div>
                    @if (is_string($scheme['description'] ?? null) && $scheme['description'] !== '')
                        <p class="vellum-api-field-description">{{ $scheme['description'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif

@foreach ($groups as $group)
    {{-- A tag inside another is a level further down, as it is in the sidebar. --}}
    @php
        $level = min(2 + $group->depth, 4);
    @endphp
    <h{{ $level }} id="group-{{ $group->slug }}" class="vellum-heading vellum-api-section">{{ $group->label() }}</h{{ $level }}>

    @if ($group->description !== null)
        <div class="vellum-api-group-description">{!! $markdown($group->description) !!}</div>
    @endif

    @if ($group->operations !== [])
    <ul class="vellum-api-index">
        @foreach ($group->operations as $operation)
            <li>
                <a class="vellum-api-index-link" href="{{ $href($group, $operation) }}">
                    <span class="vellum-api-method" data-method="{{ strtolower($operation->method) }}">{{ $operation->method }}</span>
                    <span class="vellum-api-index-title">{{ $operation->title() }}</span>
                    <code class="vellum-api-index-path">{{ $operation->path }}</code>
                </a>
            </li>
        @endforeach
    </ul>
    @endif
@endforeach
