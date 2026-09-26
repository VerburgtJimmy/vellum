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
                x-init="ensureIndex(); $nextTick(() => $refs.query?.focus())"
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
                    class="max-h-[min(60vh,28rem)] overflow-y-auto"
                    :class="{ 'border-t border-border': query.trim() !== '' }"
                    role="listbox"
                    aria-label="Search results"
                >
                    <template x-if="loading && ! ready">
                        <p class="px-3 py-6 text-center text-sm text-muted-foreground">Loading index...</p>
                    </template>
                    <template x-if="ready && query.trim() && results.length === 0">
                        <p class="px-3 py-6 text-center text-sm text-muted-foreground">No results</p>
                    </template>

                    <template x-for="(hit, index) in results" :key="hit.id">
                        <div class="px-1 py-0.5">
                            <a
                                :id="'vellum-search-option-' + index"
                                :href="hit.url"
                                role="option"
                                class="block rounded-md px-3 py-2 text-sm hover:bg-accent"
                                :class="index === active && 'bg-accent'"
                                :aria-selected="(index === active).toString()"
                                x-on:mouseenter="active = index"
                            >
                                <span class="font-medium text-foreground" x-text="hit.breadcrumb"></span>
                                <span x-show="hit.passage" class="mt-0.5 block line-clamp-2 text-muted-foreground" x-html="hit.passage"></span>
                            </a>
                        </div>
                    </template>
                </div>
            </div>
        </x-slot:content>
    </x-vellum::ui.dialog>
</div>
