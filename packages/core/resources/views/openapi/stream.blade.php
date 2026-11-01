{{-- Said once above a schema, when what it describes is one item of many. --}}
@php
    $item = is_array($media ?? null) ? \Vellum\OpenApi\Stream::item($type, $media) : null;
@endphp
@if ($item !== null)
    <p class="vellum-api-stream">Sent as a stream, one {{ $item }} at a time.@if (\Vellum\OpenApi\Stream::schema($media) !== []) Each {{ $item }}:@endif</p>
@endif
