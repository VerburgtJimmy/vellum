{{--
    A second example, showing that a preview is a normal Blade view: loops and
    conditionals work as anywhere else. What it must not do is read anything
    that changes, since it is compiled once at build time.
--}}
@php
    $levels = ['Stable' => '#166534', 'Beta' => '#854d0e', 'Deprecated' => '#991b1b'];
@endphp

<div style="display:flex; gap:0.5rem; flex-wrap:wrap; font-family:system-ui,sans-serif">
    @foreach ($levels as $label => $colour)
        <span style="padding:0.15rem 0.5rem; border-radius:999px; border:1px solid {{ $colour }}33; background:{{ $colour }}14; color:{{ $colour }}; font:600 0.75rem/1.6 system-ui">
            {{ $label }}
        </span>
    @endforeach
</div>
