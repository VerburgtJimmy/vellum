@props([])

@if (! $slot->isEmpty())
    <footer data-vellum-footer class="mt-16 border-t border-border py-8 text-sm text-muted-foreground">
        {{ $slot }}
    </footer>
@endif
