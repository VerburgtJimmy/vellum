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
                <code>{{ $server['url'] ?? '' }}</code>
                @if (! empty($server['description']))<span> — {{ $server['description'] }}</span>@endif
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
                <span>{{ $scheme['type'] ?? 'unknown' }}@if (! empty($scheme['scheme'])) ({{ $scheme['scheme'] }})@endif</span>
                @if (! empty($scheme['description']))<span> — {{ $scheme['description'] }}</span>@endif
            </li>
        @endforeach
    </ul>
@endif

<h2 id="groups" class="vellum-heading">{{ count($groups) === 1 ? 'Endpoints' : 'Endpoint groups' }}</h2>

<div class="vellum-cards vellum-api-groups">
    @foreach ($groups as $group)
        <a class="vellum-card" href="{{ $href($group) }}">
            <span class="vellum-card-title">{{ $group->name }}</span>
            <span class="vellum-card-body">{{ $group->methodCount() }} {{ $group->methodCount() === 1 ? 'endpoint' : 'endpoints' }}</span>
        </a>
    @endforeach
</div>

@if ($spec->webhookNames() !== [])
    {{-- 0.6 lists webhooks by name only; rendering them is out of scope. --}}
    <h2 id="webhooks" class="vellum-heading">Webhooks</h2>
    <p>This API defines webhooks: {{ implode(', ', $spec->webhookNames()) }}.</p>
@endif
