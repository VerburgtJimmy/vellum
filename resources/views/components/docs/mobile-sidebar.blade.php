@props([
    'navigation' => [],
    'document' => null,
])

<div data-vellum-mobile-sidebar class="contents">
    <x-vellum::ui.dialog variant="sheet" :show-footer="false" class="contents">
        <x-slot:trigger>
            {{ $trigger ?? '' }}
            @unless (isset($trigger))
                <button
                    type="button"
                    data-vellum-button
                    aria-label="Open navigation"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                        <path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/>
                    </svg>
                </button>
            @endunless
        </x-slot:trigger>

        <x-slot:title>Menu</x-slot:title>

        <x-slot:content>
            <div class="-mx-1 -mt-1">
                <x-vellum::docs.sidebar :navigation="$navigation" :document="$document" class="w-full border-0" />
            </div>
        </x-slot:content>
    </x-vellum::ui.dialog>
</div>
