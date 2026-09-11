@props([
    'searchHash' => null,
])

@php
    $hotkey = (string) config('vellum.search.hotkey', 'k');
    $searchUrl = is_string($searchHash) && $searchHash !== ''
        ? route('vellum.search.hashed', ['hash' => $searchHash])
        : route('vellum.search');
    $hotkeyLabel = strtoupper($hotkey);
@endphp

<div
    data-vellum-search
    data-vellum-search-hotkey="{{ $hotkey }}"
    x-data="vellumSearchHotkey(@js($hotkey))"
>
    <x-vellum::ui.dialog
        :show-footer="false"
        class="contents"
    >
        <x-slot:trigger>
            <button
                type="button"
                data-vellum-search-trigger
                data-vellum-button
                class="inline-flex h-9 items-center gap-2 rounded-md border border-input bg-background px-2.5 text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:min-w-[12rem] md:justify-between"
            >
                <span class="inline-flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                    </svg>
                    <span class="sr-only md:not-sr-only md:inline">Search...</span>
                </span>
                <span class="hidden items-center gap-0.5 md:inline-flex" aria-hidden="true">
                    <x-vellum::ui.kbd>Ctrl</x-vellum::ui.kbd>
                    <x-vellum::ui.kbd>{{ $hotkeyLabel }}</x-vellum::ui.kbd>
                </span>
            </button>
        </x-slot:trigger>

        <x-slot:content>
            <div
                class="flex flex-col gap-3"
                x-data="{
                    query: '',
                    groups: [],
                    flat: [],
                    active: 0,
                    status: '',
                    loading: false,
                    ready: false,
                    searchUrl: @js($searchUrl),
                    async ensureIndex() {
                        if (this.ready || this.loading) {
                            return
                        }
                        this.loading = true
                        this.status = 'Loading search index'
                        try {
                            const mod = await window.VellumSearch.load()
                            await mod.getOrCreateIndex(this.searchUrl)
                            this.ready = true
                            this.status = 'Search ready'
                        } catch (e) {
                            this.status = 'Search unavailable'
                        } finally {
                            this.loading = false
                        }
                    },
                    async runSearch() {
                        await this.ensureIndex()
                        const mod = await window.VellumSearch.load()
                        const { index } = await mod.getOrCreateIndex(this.searchUrl)
                        const q = this.query.trim()
                        if (!q) {
                            this.groups = []
                            this.flat = []
                            this.active = 0
                            this.status = 'Type to search'
                            return
                        }
                        const hits = index.search(q, { boost: { title: 3, headings: 2 }, prefix: true, fuzzy: 0.2 })
                        this.groups = mod.groupResultsByPage(hits, q)
                        this.flat = this.groups.map((g) => ({ url: g.url }))
                        this.active = 0
                        const count = this.groups.length
                        this.status = count === 0 ? 'No results' : (count === 1 ? '1 result' : count + ' results')
                    },
                    move(delta) {
                        if (this.flat.length === 0) {
                            return
                        }
                        this.active = (this.active + delta + this.flat.length) % this.flat.length
                    },
                    go() {
                        const item = this.flat[this.active]
                        if (item?.url) {
                            window.location.href = item.url
                        }
                    },
                }"
                x-init="ensureIndex(); $nextTick(() => $refs.query?.focus())"
                x-on:keydown.arrow-down.prevent="move(1)"
                x-on:keydown.arrow-up.prevent="move(-1)"
                x-on:keydown.enter.prevent="go()"
            >
                <label class="sr-only" for="vellum-search-input">Search</label>
                <input
                    id="vellum-search-input"
                    x-ref="query"
                    type="search"
                    autocomplete="off"
                    autocorrect="off"
                    spellcheck="false"
                    placeholder="Search documentation..."
                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    x-model="query"
                    x-on:input.debounce.150ms="runSearch()"
                >

                <div class="sr-only" aria-live="polite" x-text="status"></div>

                <div class="max-h-[min(60vh,24rem)] overflow-y-auto" role="listbox" aria-label="Search results">
                    <template x-if="loading && ! ready">
                        <p class="px-1 py-6 text-center text-sm text-muted-foreground">Loading index...</p>
                    </template>
                    <template x-if="ready && query.trim() && groups.length === 0">
                        <p class="px-1 py-6 text-center text-sm text-muted-foreground">No results</p>
                    </template>
                    <template x-for="(group, gi) in groups" :key="group.url">
                        <div class="mb-3">
                            <p class="mb-1 px-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground" x-html="group.titleHtml"></p>
                            <a
                                :href="group.url"
                                role="option"
                                class="block rounded-md px-2 py-2 text-sm hover:bg-accent"
                                :class="flat[active]?.url === group.url && 'bg-accent'"
                                :aria-selected="(flat[active]?.url === group.url).toString()"
                                x-on:mouseenter="active = flat.findIndex((f) => f.url === group.url)"
                            >
                                <span class="font-medium text-foreground" x-html="group.titleHtml"></span>
                                <template x-if="group.items[0]">
                                    <span class="mt-0.5 block line-clamp-2 text-muted-foreground" x-html="group.items[0].html"></span>
                                </template>
                            </a>
                        </div>
                    </template>
                </div>
            </div>
        </x-slot:content>
    </x-vellum::ui.dialog>
</div>
