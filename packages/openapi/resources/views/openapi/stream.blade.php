{{-- Said once above a schema, when what it describes is one item of many. --}}
@php
    $item = is_array($media ?? null) ? \Vellum\OpenApi\Stream::item($type, $media) : null;
    $itemName = $item === null ? '' : __('vellum-openapi::reference.stream.items.'.$item);
@endphp
@if ($item !== null)
    <p class="vellum-api-stream">{{ __('vellum-openapi::reference.stream.sent', ['item' => $itemName]) }}@if (\Vellum\OpenApi\Stream::schema($media) !== []) {{ __('vellum-openapi::reference.stream.each', ['item' => $itemName]) }}@endif</p>
@endif
