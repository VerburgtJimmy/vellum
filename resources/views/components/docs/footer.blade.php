@props([])

<footer data-vellum-footer class="mt-16 border-t border-border py-8 text-sm text-muted-foreground">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p>&copy; {{ date('Y') }} {{ config('vellum.name', 'Docs') }}</p>
        <p class="text-xs">Built with Vellum</p>
    </div>
</footer>
