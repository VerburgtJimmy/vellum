@props(['key'])
@php
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? '';
    }
@endphp
{{ $value }}
