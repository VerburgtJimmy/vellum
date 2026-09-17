@if ($group->description !== null)
    <div class="vellum-api-group-description">{!! $markdown($group->description) !!}</div>
@endif

@foreach ($group->operations as $operation)
    @include('vellum::openapi.operation', [
        'operation' => $operation,
        'markdown' => $markdown,
        'path' => $path,
        'security' => $securityFor($operation),
    ])
@endforeach
