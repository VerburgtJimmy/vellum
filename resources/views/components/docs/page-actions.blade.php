@props([
    'markdownSource' => '',
    'rawUrl' => '',
    'editUrl' => null,
])

@php
    $chatgpt = 'https://chatgpt.com/?q='.rawurlencode(__('vellum::page.actions.chatgpt_prompt', ['url' => $rawUrl]));
    $claude = 'https://claude.ai/new?q='.rawurlencode(__('vellum::page.actions.claude_prompt', ['url' => $rawUrl]));
@endphp

<div
    data-vellum-page-actions
    class="-mr-2 flex flex-wrap items-center gap-1"
    x-data="vellumPageActions(@js($markdownSource))"
>
    <button
        type="button"
        data-vellum-copy-markdown
        data-vellum-button
        class="inline-flex h-8 items-center gap-1.5 rounded-md px-2 text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        x-on:click="copyMarkdown()"
        :aria-label="copiedMarkdown ? @js(__('vellum::page.actions.copied')) : @js(__('vellum::page.actions.copy_markdown'))"
    >
        {!! \Vellum\Support\Icons::copy(['class' => 'h-3.5 w-3.5', 'x-show' => '!copiedMarkdown']) !!}
        {!! \Vellum\Support\Icons::check(['class' => 'h-3.5 w-3.5', 'x-cloak' => '', 'x-show' => 'copiedMarkdown']) !!}
        <span x-text="copiedMarkdown ? @js(__('vellum::page.actions.copied')) : @js(__('vellum::page.actions.copy_markdown'))">{{ __('vellum::page.actions.copy_markdown') }}</span>
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
            {{ __('vellum::page.actions.open') }}
            {!! \Vellum\Support\Icons::caretDown(['class' => 'h-3.5 w-3.5']) !!}
        </button>

        <div
            x-ref="menu"
            x-cloak
            x-show="open"
            x-transition.opacity
            class="absolute right-0 top-full z-50 mt-1 min-w-[12.5rem] rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md"
            role="menu"
            aria-label="{{ __('vellum::page.actions.open_menu') }}"
            x-on:keydown="onMenuKeydown($event)"
        >
            <a
                href="{{ $chatgpt }}"
                role="menuitem"
                target="_blank"
                rel="noopener noreferrer"
                class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                tabindex="-1"
            >{{ __('vellum::page.actions.open_in_chatgpt') }}</a>
            <a
                href="{{ $claude }}"
                role="menuitem"
                target="_blank"
                rel="noopener noreferrer"
                class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                tabindex="-1"
            >{{ __('vellum::page.actions.open_in_claude') }}</a>
            <a
                href="{{ $rawUrl }}"
                role="menuitem"
                class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                tabindex="-1"
            >{{ __('vellum::page.actions.view_markdown') }}</a>
            @if (is_string($editUrl) && $editUrl !== '')
                <a
                    href="{{ $editUrl }}"
                    role="menuitem"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    tabindex="-1"
                >{{ __('vellum::page.actions.edit_on_github') }}</a>
            @endif
        </div>
    </div>
</div>
