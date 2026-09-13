@props([
    'navigation' => [],
    'document' => null,
    'searchInSidebar' => false,
    'searchHash' => null,
    'versions' => [],
    'currentVersion' => null,
    'versionHrefs' => [],
])

<div data-vellum-mobile-sidebar class="contents">
    <x-vellum::ui.dialog variant="sheet" side="right" :show-footer="false" :show-close="false" class="contents">
        <x-slot:trigger>
            {{ $trigger ?? '' }}
            @unless (isset($trigger))
                <button
                    type="button"
                    data-vellum-button
                    aria-label="Open navigation"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    {!! \Vellum\Support\Icons::list(['class' => 'h-5 w-5']) !!}
                </button>
            @endunless
        </x-slot:trigger>

        <x-slot:content>
            <div data-vellum-mobile-drawer class="flex h-full min-h-0 flex-col">
                <div class="flex items-center gap-1.5 px-4 pb-2 pt-4 text-muted-foreground">
                    <button
                        type="button"
                        data-vellum-dialog-close
                        data-vellum-button
                        x-on:click="close()"
                        aria-label="Close"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-border text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        {!! \Vellum\Support\Icons::x(['class' => 'h-4 w-4']) !!}
                    </button>
                    <div class="ms-auto flex items-center">
                        <x-vellum::docs.theme-toggle variant="pair" />
                    </div>
                </div>

                <x-vellum::docs.sidebar
                    :navigation="$navigation"
                    :document="$document"
                    :search-in-sidebar="false"
                    :chrome="false"
                    class="min-h-0 w-full flex-1 border-0 bg-transparent"
                />
            </div>
        </x-slot:content>
    </x-vellum::ui.dialog>
</div>
