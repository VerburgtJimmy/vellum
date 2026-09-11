@php
    $depth = $depth ?? 0;
@endphp

@foreach ($nodes as $node)
    @php
        $id = $node['id'] ?? '';
        $text = $node['text'] ?? '';
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $pad = match (true) {
            $depth <= 0 => 'pl-3',
            $depth === 1 => 'pl-5',
            default => 'pl-7',
        };
    @endphp
    <a
        href="#{{ $id }}"
        data-vellum-toc-link
        x-on:click.prevent="scrollTo(@js($id))"
        class="block border-l-2 py-1 text-muted-foreground transition-colors hover:text-foreground {{ $pad }}"
        :class="activeId === @js($id) ? 'border-primary text-foreground font-medium' : 'border-transparent'"
    >{{ $text }}</a>
    @if ($children !== [])
        @include('vellum::components.docs.partials.toc-items', ['nodes' => $children, 'depth' => $depth + 1])
    @endif
@endforeach
