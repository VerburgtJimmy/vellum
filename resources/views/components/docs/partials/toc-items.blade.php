@php
    $depth = $depth ?? 0;
@endphp

@foreach ($nodes as $index => $node)
    @php
        $id = $node['id'] ?? '';
        $text = $node['text'] ?? '';
        $level = (int) ($node['level'] ?? 2);
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $tocLevel = max(0, $level - 2);
        $step = isset($node['step']) ? (int) $node['step'] : null;
        // Joined to the next entry when that entry is the next step of the
        // same block, so the window style can draw the step connector.
        $nextStep = $nodes[$index + 1]['step'] ?? null;
        $joined = $step !== null && $children === [] && $nextStep === $step + 1;
    @endphp
    <a
        href="#{{ $id }}"
        data-vellum-toc-link
        data-vellum-toc-id="{{ $id }}"
        data-vellum-toc-level="{{ $tocLevel }}"
        @if ($step !== null) data-vellum-toc-step="{{ $step }}" @endif
        @if ($joined) data-vellum-toc-joined @endif
        x-on:click.prevent="scrollTo(@js($id))"
        class="vellum-toc-link relative z-10 block min-h-6 py-1.5 text-[13px] leading-snug text-muted-foreground transition-colors hover:text-foreground"
        :class="activeIds.includes(@js($id)) ? 'is-active text-foreground' : 'text-muted-foreground'"
        style="--vellum-toc-level: {{ $tocLevel }}"
    >@if ($step !== null)<span class="vellum-toc-step" aria-hidden="true">{{ $step }}</span>@endif{{ $text }}</a>
    @if ($children !== [])
        @include('vellum::components.docs.partials.toc-items', ['nodes' => $children, 'depth' => $depth + 1])
    @endif
@endforeach
