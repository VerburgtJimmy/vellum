@props([
    'currentVersion' => null,
    'staticExport' => false,
])

@php
    use Vellum\Support\Assets;

    $hotkey = (string) config('vellum.search.hotkey', 'k');
    $driver = $staticExport ? 'answers' : (\Vellum\Search\SearchDriver::isScout() ? 'scout' : 'answers');
    $prefix = trim((string) config('vellum.route.prefix', 'docs'), '/');
    $versioned = is_string($currentVersion) && $currentVersion !== '' && (bool) config('vellum.versions.enabled')
        ? ['version' => $currentVersion]
        : [];
    $semanticOn = (bool) config('vellum.answers.enabled', true) && (bool) config('vellum.answers.semantic', true);

    if ($staticExport) {
        $answersUrl = '/'.$prefix.'/_vellum/answers.json';
        $semanticUrl = $semanticOn ? '/'.$prefix.'/_vellum/semantic.bin' : null;
        $scoutUrl = null;
    } else {
        $answersUrl = route('vellum.answers', $versioned);
        $semanticUrl = $semanticOn ? route('vellum.answers.semantic', $versioned) : null;
        $scoutUrl = route('vellum.search', $versioned);
    }
@endphp

<div
    data-vellum-search
    data-vellum-search-hotkey="{{ $hotkey }}"
    data-vellum-search-driver="{{ $driver }}"
    data-vellum-search-url="{{ $answersUrl }}"
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
                    semantic: @js($semanticUrl),
                    semanticModule: @js($semanticOn ? Assets::semanticJsUrl() : null),
                    scout: @js($scoutUrl),
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
                        :aria-activedescendant="options.length ? 'vellum-search-option-' + active : ''"
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
                    <template x-if="ready && query.trim() && results.length === 0 && ! card">
                        <p class="px-3 py-6 text-center text-sm text-muted-foreground">No results</p>
                    </template>

                    <template x-if="card">
                        <div class="vellum-answer-card p-1">
                            <a
                                id="vellum-search-option-0"
                                :href="card.url"
                                role="option"
                                :aria-selected="(active === 0).toString()"
                                class="block rounded-md border border-border bg-card p-3 hover:border-accent-foreground/30"
                                :class="active === 0 && 'border-accent-foreground/40 bg-accent'"
                                x-on:mouseenter="active = 0">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Answer</p>
                                <p class="mt-1 text-sm font-semibold text-foreground" x-text="card.breadcrumb"></p>

                                <template x-if="card.content.kind === 'code' || card.content.kind === 'sentence-code'">
                                    <div class="mt-2">
                                        <p x-show="card.content.text" class="mb-2 text-sm text-foreground" x-text="card.content.text"></p>
                                        <pre class="vellum-answer-code overflow-x-auto rounded-md bg-muted p-2 text-xs"><code x-text="card.content.code"></code></pre>
                                    </div>
                                </template>

                                <template x-if="card.content.kind === 'config'">
                                    <dl class="mt-2 space-y-1 text-xs">
                                        <template x-for="row in card.content.rows.slice(0, 5)" :key="row.key">
                                            <div class="flex flex-wrap gap-x-2">
                                                <dt class="font-mono text-foreground" x-text="row.key"></dt>
                                                <dd class="flex flex-wrap gap-x-2 text-muted-foreground">
                                                    <span x-show="row.default" x-text="'default ' + row.default"></span>
                                                    <span x-show="row.type" x-text="row.type"></span>
                                                    <span x-show="row.description" class="text-foreground/80" x-text="row.description"></span>
                                                </dd>
                                            </div>
                                        </template>
                                    </dl>
                                </template>

                                <template x-if="card.content.kind === 'row'">
                                    <dl class="mt-2 flex flex-wrap gap-x-3 text-xs">
                                        <template x-for="(value, name) in card.content.row" :key="name">
                                            <div class="flex gap-1">
                                                <dt class="text-muted-foreground" x-text="name + ':'"></dt>
                                                <dd class="text-foreground" x-text="value"></dd>
                                            </div>
                                        </template>
                                    </dl>
                                </template>

                                <template x-if="card.content.kind === 'text'">
                                    <p class="mt-2 text-sm text-foreground" x-text="card.content.text"></p>
                                </template>

                                <p x-show="card.passage" class="mt-2 line-clamp-3 text-xs text-muted-foreground" x-text="card.passage"></p>
                            </a>
                        </div>
                    </template>

                    <template x-for="(hit, index) in results" :key="hit.id">
                        <div class="px-1 py-0.5">
                            <a
                                :id="'vellum-search-option-' + (card ? index + 1 : index)"
                                :href="hit.url"
                                role="option"
                                class="block rounded-md px-3 py-2 text-sm hover:bg-accent"
                                :class="(card ? index + 1 : index) === active && 'bg-accent'"
                                :aria-selected="((card ? index + 1 : index) === active).toString()"
                                x-on:mouseenter="active = card ? index + 1 : index"
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
