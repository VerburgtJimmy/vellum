@props([
    'searchHash' => null,
    'currentVersion' => null,
    'staticExport' => false,
])

@php
    $hotkey = (string) config('vellum.search.hotkey', 'k');
    $driver = $staticExport ? 'minisearch' : \Vellum\Search\SearchDriver::name();
    $prefix = trim((string) config('vellum.route.prefix', 'docs'), '/');

    if ($staticExport && is_string($searchHash) && $searchHash !== '') {
        $searchUrl = '/'.$prefix.'/_vellum/search-'.$searchHash.'.json';
    } else {
        $params = [];
        if (is_string($currentVersion) && $currentVersion !== '' && (bool) config('vellum.versions.enabled')) {
            $params['version'] = $currentVersion;
        }
        $searchUrl = route('vellum.search', $params);
    }
@endphp

<div
    data-vellum-search
    data-vellum-search-hotkey="{{ $hotkey }}"
    data-vellum-search-driver="{{ $driver }}"
    data-vellum-search-url="{{ $searchUrl }}"
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
                x-data="{
                    query: '',
                    groups: [],
                    flat: [],
                    active: 0,
                    status: '',
                    loading: false,
                    ready: false,
                    searchUrl: @js($searchUrl),
                    driver: @js($driver),
                    async ensureIndex() {
                        if (this.ready || this.loading) {
                            return
                        }
                        if (this.driver === 'scout') {
                            this.ready = true
                            this.status = 'Search ready'
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
                        const q = this.query.trim()
                        if (!q) {
                            this.groups = []
                            this.flat = []
                            this.active = 0
                            this.status = 'Type to search'
                            return
                        }
                        if (this.driver === 'scout') {
                            const documents = await mod.fetchSearchDocuments(this.searchUrl + (this.searchUrl.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(q))
                            this.groups = mod.groupResultsByPage(documents, q)
                        } else {
                            const { index } = await mod.getOrCreateIndex(this.searchUrl)
                            const hits = index.search(q, { boost: { title: 3, headings: 2 }, prefix: true, fuzzy: 0.2 })
                            this.groups = mod.groupResultsByPage(hits, q)
                        }
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
                    closeSearch() {
                        window.dispatchEvent(new CustomEvent('vellum-search-close'))
                    },
                }"
                x-init="ensureIndex(); $nextTick(() => $refs.query?.focus())"
                x-on:keydown.arrow-down.prevent="move(1)"
                x-on:keydown.arrow-up.prevent="move(-1)"
                x-on:keydown.enter.prevent="go()"
            >
                <label class="sr-only" for="vellum-search-input">Search</label>
                <div class="flex flex-row items-center gap-2 p-3">
                    {!! \Vellum\Support\Icons::magnifyingGlass(['class' => 'h-5 w-5 shrink-0 text-muted-foreground']) !!}
                    <input
                        id="vellum-search-input"
                        x-ref="query"
                        type="search"
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
                    class="max-h-[min(60vh,24rem)] overflow-y-auto"
                    :class="{ 'border-t border-border': query.trim() !== '' }"
                    role="listbox"
                    aria-label="Search results"
                >
                    <template x-if="loading && ! ready">
                        <p class="px-3 py-6 text-center text-sm text-muted-foreground">Loading index...</p>
                    </template>
                    <template x-if="ready && query.trim() && groups.length === 0">
                        <p class="px-3 py-6 text-center text-sm text-muted-foreground">No results</p>
                    </template>
                    <template x-for="(group, gi) in groups" :key="group.url">
                        <div class="px-1 py-1">
                            <a
                                :href="group.url"
                                role="option"
                                class="block rounded-md px-3 py-2 text-sm hover:bg-accent"
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
