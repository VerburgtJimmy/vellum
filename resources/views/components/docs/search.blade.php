@props([
    'currentVersion' => null,
    'staticExport' => false,
])

@php

    $hotkey = (string) config('vellum.search.hotkey', 'k');
    $driver = $staticExport ? 'answers' : (\Vellum\Search\SearchDriver::isScout() ? 'scout' : 'answers');
    $prefix = trim((string) config('vellum.route.prefix', 'docs'), '/');
    $versioned = is_string($currentVersion) && $currentVersion !== '' && (bool) config('vellum.versions.enabled')
        ? ['version' => $currentVersion]
        : [];
    if ($staticExport) {
        $answersUrl = '/'.$prefix.'/_vellum/answers.json';
        $scoutUrl = null;
    } else {
        $answersUrl = route('vellum.answers', $versioned);
        $scoutUrl = route('vellum.search', $versioned);
    }
@endphp

<div
    data-vellum-search
    data-vellum-search-hotkey="{{ $hotkey }}"
    data-vellum-search-driver="{{ $driver }}"
    data-vellum-search-url="{{ $answersUrl }}"
    @if ($staticExport) data-vellum-search-home="/{{ $prefix }}" @endif
    x-data="vellumSearchHotkey(@js($hotkey))"
    x-on:vellum-search-open.window="openSearch()"
    x-on:vellum-search-close.window="closeSearch()"
>
    <x-vellum::ui.dialog
        variant="search"
        :show-footer="false"
        :show-close="false"
        class="contents"
    >
        <x-slot:content>
            <div
                class="flex flex-col"
                x-data="vellumSearchDialog({
                    driver: @js($driver),
                    answers: @js($answersUrl),
                    scout: @js($scoutUrl),
                    static: @js((bool) $staticExport),
                    prefix: @js('/'.$prefix),
                })"
                x-init="showRecent(); ensureIndex(); $nextTick(() => $refs.query?.focus())"
                x-on:keydown.arrow-down.prevent="move(1)"
                x-on:keydown.arrow-up.prevent="move(-1)"
                x-on:keydown.enter.prevent="go()"
            >
                <label class="sr-only" for="vellum-search-query">Search</label>
                <div class="flex flex-row items-center gap-2 p-3">
                    {!! \Vellum\Support\Icons::magnifyingGlass(['class' => 'h-5 w-5 shrink-0 text-muted-foreground']) !!}
                    <input
                        id="vellum-search-query"
                        x-ref="query"
                        type="search"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded="true"
                        aria-controls="vellum-search-results"
                        :aria-activedescendant="results.length ? 'vellum-search-option-' + active : ''"
                        autocomplete="off"
                        autocorrect="off"
                        spellcheck="false"
                        placeholder="Search"
                        class="w-0 flex-1 bg-transparent text-lg placeholder:text-muted-foreground focus-visible:outline-none"
                        x-model="query"
                        x-on:input.debounce.150ms="runSearch()"
                    >
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-border px-2 py-1 font-mono text-xs text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                        aria-label="Close search"
                        x-on:click="closeSearch()"
                    >ESC</button>
                </div>

                <div class="sr-only" aria-live="polite" x-text="status"></div>

                <div
                    id="vellum-search-results"
                    class="vellum-scroll-area max-h-[min(60vh,28rem)] p-1"
                    :class="{ 'border-t border-border': results.length > 0 || query.trim() !== '' }"
                    role="listbox"
                    aria-label="Search results"
                >
                    <template x-if="query.trim() && loading && ! ready">
                        <p class="px-3 py-6 text-center text-sm text-muted-foreground">Loading…</p>
                    </template>
                    <template x-if="ready && query.trim() && results.length === 0">
                        <p class="px-3 py-6 text-center text-sm text-muted-foreground">No results for “<span x-text="query.trim()"></span>”</p>
                    </template>

                    <template x-for="group in groups" :key="group.key">
                        <div role="group" :aria-label="group.title" class="vellum-search-group">
                            <div class="flex items-center gap-2 px-3 pt-2 pb-0.5 text-xs font-medium text-muted-foreground" aria-hidden="true">
                                <span x-show="showingRecent">{!! \Vellum\Support\Icons::clock(['class' => 'h-3.5 w-3.5']) !!}</span>
                                <span x-show="! showingRecent">{!! \Vellum\Support\Icons::file(['class' => 'h-3.5 w-3.5']) !!}</span>
                                <span x-text="group.title"></span>
                            </div>
                            <template x-for="hit in group.hits" :key="hit.id">
                                <a
                                    :id="'vellum-search-option-' + hit.index"
                                    :href="hit.url"
                                    role="option"
                                    class="vellum-search-hit"
                                    :class="hit.index === active && 'is-active'"
                                    :aria-selected="(hit.index === active).toString()"
                                    x-on:mouseenter="active = hit.index"
                                    x-on:click="remember(hit)"
                                >
                                    <span class="flex items-center gap-2">
                                        <span class="text-muted-foreground" x-show="hit.heading">{!! \Vellum\Support\Icons::hash(['class' => 'h-4 w-4']) !!}</span>
                                        <span class="text-muted-foreground" x-show="! hit.heading">{!! \Vellum\Support\Icons::file(['class' => 'h-4 w-4']) !!}</span>
                                        <span class="min-w-0 flex-1 truncate font-medium text-foreground" x-text="hit.label"></span>
                                        <span class="vellum-search-enter text-xs text-muted-foreground" aria-hidden="true">↵</span>
                                    </span>
                                    <span x-show="hit.passage" class="mt-0.5 block line-clamp-2 text-muted-foreground" x-html="hit.passage"></span>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="hidden items-center gap-4 border-t border-border px-3.5 py-2 text-xs text-muted-foreground md:flex" aria-hidden="true">
                    <span><kbd class="vellum-search-key">↑</kbd><kbd class="vellum-search-key">↓</kbd> to move</span>
                    <span><kbd class="vellum-search-key">↵</kbd> to open</span>
                    <span><kbd class="vellum-search-key">esc</kbd> to close</span>
                </div>
            </div>
        </x-slot:content>
    </x-vellum::ui.dialog>
</div>
