@php
    $depth = $depth ?? 0;
@endphp

@foreach ($nodes as $node)
    @php
        $id = $node['id'] ?? '';
        $text = $node['text'] ?? '';
        $level = (int) ($node['level'] ?? 2);
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $tocLevel = max(0, $level - 2);
    @endphp
    <a
        href="#{{ $id }}"
        data-vellum-toc-link
        data-vellum-toc-id="{{ $id }}"
        data-vellum-toc-level="{{ $tocLevel }}"
        x-on:click.prevent="scrollTo(@js($id))"
        class="vellum-toc-link relative z-10 block py-1 text-[13px] leading-snug text-muted-foreground transition-colors hover:font-semibold hover:text-foreground"
        :class="activeId === @js($id) ? 'is-active font-semibold text-foreground' : 'text-muted-foreground'"
        style="--vellum-toc-level: {{ $tocLevel }}"
    >{{ $text }}</a>
    @if ($children !== [])
        @include('vellum::components.docs.partials.toc-items', ['nodes' => $children, 'depth' => $depth + 1])
    @endif
@endforeach
