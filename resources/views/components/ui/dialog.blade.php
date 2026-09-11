@props([
    'open' => false,
    'variant' => 'modal',
    'showClose' => null,
    'showFooter' => true,
])

@php
    use Vellum\Support\Cn;

    $isSheet = $variant === 'sheet';
    $showCloseButton = $showClose === null ? true : filter_var($showClose, FILTER_VALIDATE_BOOLEAN);
    $showFooterBar = isset($footer) || filter_var($showFooter, FILTER_VALIDATE_BOOLEAN);

    $panelClasses = $isSheet
        ? 'fixed inset-y-0 left-0 z-50 flex h-full w-[min(100vw,20rem)] flex-col gap-4 border-r border-border bg-background p-4 shadow-lg motion-safe:transition-transform'
        : 'fixed left-1/2 top-1/2 z-50 grid w-full max-w-lg -translate-x-1/2 -translate-y-1/2 gap-4 border border-border bg-background p-6 shadow-lg rounded-lg';

    $classes = Cn::merge($attributes->get('class'));
@endphp

<div
    data-vellum-dialog
    data-vellum-dialog-variant="{{ $isSheet ? 'sheet' : 'modal' }}"
    x-data="vellumDialog(@js((bool) $open))"
    @@keydown.escape.window="if (open) close()"
    {{ $attributes->except('class')->merge(['class' => $classes]) }}
>
    @isset($trigger)
        <div data-vellum-dialog-trigger @@click="show()">
            {{ $trigger }}
        </div>
    @endisset

    {{-- Mount only when open so x-trap is bound after the focus chunk registers --}}
    <template x-teleport="body">
        <template x-if="open">
            <div class="fixed inset-0 z-50" role="presentation">
                <div
                    data-vellum-dialog-overlay
                    class="fixed inset-0 bg-black/50"
                    x-on:click="close()"
                    aria-hidden="true"
                ></div>
                <div
                    data-vellum-dialog-panel
                    role="dialog"
                    aria-modal="true"
                    x-trap.noscroll="true"
                    x-on:click.stop
                    class="{{ $panelClasses }}"
                >
                    @if ($showCloseButton)
                        <button
                            type="button"
                            data-vellum-dialog-close
                            data-vellum-button
                            x-on:click="close()"
                            aria-label="Close"
                            class="absolute right-3 top-3 inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                                <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                            </svg>
                        </button>
                    @endif

                    @if (isset($title) || isset($description))
                        <div class="flex flex-col gap-1.5 pr-8 text-center sm:text-left">
                            @isset($title)
                                <h2 data-vellum-dialog-title class="text-lg font-semibold leading-none tracking-tight">
                                    {{ $title }}
                                </h2>
                            @endisset
                            @isset($description)
                                <p data-vellum-dialog-description class="text-sm text-muted-foreground">
                                    {{ $description }}
                                </p>
                            @endisset
                        </div>
                    @endif

                    <div data-vellum-dialog-content class="{{ $isSheet ? 'min-h-0 flex-1 overflow-y-auto' : '' }}">
                        {{ $content ?? $slot }}
                    </div>

                    @if ($showFooterBar)
                        <div data-vellum-dialog-footer class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            @isset($footer)
                                {{ $footer }}
                            @else
                                <button
                                    type="button"
                                    data-vellum-dialog-close
                                    data-vellum-button
                                    x-on:click="close()"
                                    class="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    Close
                                </button>
                            @endisset
                        </div>
                    @endif
                </div>
            </div>
        </template>
    </template>
</div>
