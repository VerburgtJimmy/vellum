@props([
    'open' => false,
    'variant' => 'modal',
    'side' => 'left',
    'showClose' => null,
    'showFooter' => true,
])

@php
    use Vellum\Support\Cn;

    $isSheet = $variant === 'sheet';
    $isSearch = $variant === 'search';
    $showCloseButton = $showClose === null ? ! $isSearch : filter_var($showClose, FILTER_VALIDATE_BOOLEAN);
    $showFooterBar = isset($footer) || filter_var($showFooter, FILTER_VALIDATE_BOOLEAN);

    $sheetFromRight = $isSheet && $side === 'right';
    $panelClasses = $isSheet
        ? ($sheetFromRight
            ? 'fixed inset-y-0 right-0 z-50 flex h-full w-[85%] max-w-[380px] flex-col border-s border-border bg-background shadow-lg'
            : 'fixed inset-y-0 left-0 z-50 flex h-full w-[85%] max-w-[380px] flex-col border-e border-border bg-background shadow-lg')
        : ($isSearch
            ? 'fixed left-1/2 top-4 z-50 w-[calc(100%-1rem)] max-w-screen-sm -translate-x-1/2 overflow-hidden rounded-xl border border-border bg-popover text-popover-foreground shadow-2xl shadow-black/50 md:top-[calc(50%-250px)]'
            : 'fixed left-1/2 top-1/2 z-50 grid w-full max-w-lg -translate-x-1/2 -translate-y-1/2 gap-4 border border-border bg-background p-6 shadow-lg rounded-lg');

    $overlayClasses = $isSearch
        ? 'fixed inset-0 bg-black/50 backdrop-blur-[4px]'
        : ($isSheet
            ? 'fixed inset-0 bg-black/10 backdrop-blur-2xl'
            : 'fixed inset-0 bg-black/50');

    $dialogVariant = $isSheet ? 'sheet' : ($isSearch ? 'search' : 'modal');

    $classes = Cn::merge($attributes->get('class'));
@endphp

<div
    data-vellum-dialog
    data-vellum-dialog-variant="{{ $dialogVariant }}"
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
            <div class="fixed inset-0 z-50" role="presentation" data-vellum-dialog-variant="{{ $dialogVariant }}">
                <div
                    data-vellum-dialog-overlay
                    @if ($isSheet) data-vellum-sheet-overlay @endif
                    class="{{ $overlayClasses }}"
                    @if ($isSheet) :class="entered && 'is-entered'" @endif
                    x-on:click="close()"
                    aria-hidden="true"
                ></div>
                <div
                    data-vellum-dialog-panel
                    role="dialog"
                    aria-modal="true"
                    @if ($isSearch)
                        aria-label="Search documentation"
                    @elseif ($isSheet)
                        aria-label="Documentation navigation"
                    @endif
                    x-trap.noscroll="true"
                    x-on:click.stop
                    class="{{ $panelClasses }}"
                    @if ($isSheet)
                        data-vellum-sheet-side="{{ $sheetFromRight ? 'right' : 'left' }}"
                        :class="entered && 'is-entered'"
                    @endif
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
                            {!! \Vellum\Support\Icons::x(['class' => 'h-4 w-4']) !!}
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

                    <div data-vellum-dialog-content class="{{ $isSheet ? 'flex min-h-0 flex-1 flex-col overflow-hidden' : '' }}">
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
