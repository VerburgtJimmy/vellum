@props(['number' => '1', 'target' => null])
<div class="vellum-step" data-vellum-step="{{ $number }}">@if (is_string($target) && $target !== '')<a class="vellum-step-indicator" href="#{{ $target }}" tabindex="-1" aria-hidden="true">{{ $number }}</a>@else<div class="vellum-step-indicator" aria-hidden="true">{{ $number }}</div>@endif<div class="vellum-step-content">{!! $slot !!}</div></div>
