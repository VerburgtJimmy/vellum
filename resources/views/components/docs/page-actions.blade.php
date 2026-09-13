@props([
    'markdownSource' => '',
    'rawUrl' => '',
    'editUrl' => null,
])

@php
    $chatgpt = 'https://chatgpt.com/?q='.rawurlencode('Read '.$rawUrl.' so I can ask questions about it.');
    $claude = 'https://claude.ai/new?q='.rawurlencode('Read '.$rawUrl);
@endphp

<div
    data-vellum-page-actions
    class="mt-4 mb-6 flex flex-wrap items-center gap-1"
    x-data="vellumPageActions(@js($markdownSource))"
>
    <button
        type="button"
        data-vellum-copy-markdown
        data-vellum-button
        class="inline-flex h-8 items-center gap-1.5 rounded-md px-2 text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        x-on:click="copyMarkdown()"
        :aria-label="copiedMarkdown ? 'Copied' : 'Copy Markdown'"
    >
        {!! \Vellum\Support\Icons::copy(['class' => 'h-3.5 w-3.5', 'x-show' => '!copiedMarkdown']) !!}
        {!! \Vellum\Support\Icons::check(['class' => 'h-3.5 w-3.5', 'x-cloak' => '', 'x-show' => 'copiedMarkdown']) !!}
        <span x-text="copiedMarkdown ? 'Copied' : 'Copy Markdown'">Copy Markdown</span>
    </button>

    <div
        class="relative"
        x-data="vellumOpenMenu"
        x-on:keydown.escape.window="if (open) closeMenu()"
        x-on:click.outside="if (open) closeMenu()"
    >
        <button
            type="button"
            x-ref="trigger"
            data-vellum-open-menu
            data-vellum-button
            class="inline-flex h-8 items-center gap-1 rounded-md px-2 text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            aria-haspopup="menu"
            :aria-expanded="open.toString()"
            x-on:click="toggle()"
            x-on:keydown="onTriggerKeydown($event)"
        >
            Open
            {!! \Vellum\Support\Icons::caretDown(['class' => 'h-3.5 w-3.5']) !!}
        </button>

        <div
            x-ref="menu"
            x-cloak
            x-show="open"
            x-transition.opacity
            class="absolute left-0 top-full z-50 mt-1 min-w-[12.5rem] rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md"
            role="menu"
            aria-label="Open page"
            x-on:keydown="onMenuKeydown($event)"
        >
            <a
                href="{{ $chatgpt }}"
                role="menuitem"
                target="_blank"
                rel="noopener noreferrer"
                class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                tabindex="-1"
            >Open in ChatGPT</a>
            <a
                href="{{ $claude }}"
                role="menuitem"
                target="_blank"
                rel="noopener noreferrer"
                class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                tabindex="-1"
            >Open in Claude</a>
            <a
                href="{{ $rawUrl }}"
                role="menuitem"
                class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                tabindex="-1"
            >View raw Markdown</a>
            @if (is_string($editUrl) && $editUrl !== '')
                <a
                    href="{{ $editUrl }}"
                    role="menuitem"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    tabindex="-1"
                >Edit on GitHub</a>
            @endif
        </div>
    </div>
</div>
<hr data-vellum-page-rule class="vellum-page-rule border-0 border-t border-border">
