@props(['key'])
@php
    $value = config($key);
    $value = is_scalar($value) || $value === null ? (string) $value : '';
@endphp
{{ $value }}
