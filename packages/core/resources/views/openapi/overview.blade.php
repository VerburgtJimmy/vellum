@if ($spec->description() !== null)
    <div class="vellum-api-intro">{!! $markdown($spec->description()) !!}</div>
@endif

@if ($spec->apiVersion() !== null)
    <p class="vellum-api-version">Version <code>{{ $spec->apiVersion() }}</code></p>
@endif

@if ($spec->servers() !== [])
    <h2 id="servers" class="vellum-heading">Servers</h2>
    <ul class="vellum-api-servers">
        @foreach ($spec->servers() as $server)
            <li>
                <code>{{ is_string($server['url'] ?? null) ? $server['url'] : '' }}</code>
                @if (is_string($server['description'] ?? null) && $server['description'] !== '')
                    <span>{{ $server['description'] }}</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif

@if ($spec->securitySchemes() !== [])
    <h2 id="authentication" class="vellum-heading">Authentication</h2>
    <ul class="vellum-api-schemes">
        @foreach ($spec->securitySchemes() as $name => $scheme)
            <li>
                <code>{{ $name }}</code>
                <span>{{ is_string($scheme['type'] ?? null) ? $scheme['type'] : 'unknown' }}@if (is_string($scheme['scheme'] ?? null)) ({{ $scheme['scheme'] }})@endif</span>
                @if (is_string($scheme['description'] ?? null) && $scheme['description'] !== '')
                    <span>{{ $scheme['description'] }}</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif

@foreach ($groups as $group)
    <h2 id="group-{{ $group->slug }}" class="vellum-heading">{{ $group->name }}</h2>

    @if ($group->description !== null)
        <div class="vellum-api-group-description">{!! $markdown($group->description) !!}</div>
    @endif

    <ul class="vellum-api-index">
        @foreach ($group->operations as $operation)
            <li>
                <a href="{{ $href($group, $operation) }}">
                    <span class="vellum-api-method" data-method="{{ strtolower($operation->method) }}">{{ $operation->method }}</span>
                    <span class="vellum-api-index-title">{{ $operation->title() }}</span>
                    <code class="vellum-api-index-path">{{ $operation->path }}</code>
                </a>
            </li>
        @endforeach
    </ul>
@endforeach
