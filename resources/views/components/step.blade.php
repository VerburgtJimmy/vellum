@props(['number' => '1'])
<div class="vellum-step" data-vellum-step="{{ $number }}"><div class="vellum-step-indicator" aria-hidden="true">{{ $number }}</div><div class="vellum-step-content">{!! $slot !!}</div></div>
