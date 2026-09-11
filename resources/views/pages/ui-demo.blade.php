@extends('vellum::layouts.docs')

@section('title', 'UI primitives · '.config('vellum.name'))

@section('content')
    <div class="mx-auto flex max-w-3xl flex-col gap-10 px-6 py-10">
        <header class="flex flex-col gap-2">
            <h1 class="text-3xl font-semibold tracking-tight">UI primitives</h1>
            <p class="text-muted-foreground">Manual keyboard walkthrough for every Vellum primitive.</p>
        </header>

        <section class="flex flex-col gap-3" aria-labelledby="demo-button">
            <h2 id="demo-button" class="text-lg font-medium">Button</h2>
            <div class="flex flex-wrap items-center gap-2">
                <x-vellum::ui.button>Default</x-vellum::ui.button>
                <x-vellum::ui.button variant="secondary">Secondary</x-vellum::ui.button>
                <x-vellum::ui.button variant="outline">Outline</x-vellum::ui.button>
                <x-vellum::ui.button variant="ghost">Ghost</x-vellum::ui.button>
                <x-vellum::ui.button size="sm">Small</x-vellum::ui.button>
                <x-vellum::ui.button size="icon" aria-label="Icon button">+</x-vellum::ui.button>
                <x-vellum::ui.button href="#demo-button">Link button</x-vellum::ui.button>
            </div>
        </section>

        <x-vellum::ui.separator />

        <section class="flex flex-col gap-3" aria-labelledby="demo-badge">
            <h2 id="demo-badge" class="text-lg font-medium">Badge</h2>
            <div class="flex flex-wrap items-center gap-2">
                <x-vellum::ui.badge>Default</x-vellum::ui.badge>
                <x-vellum::ui.badge variant="secondary">Secondary</x-vellum::ui.badge>
                <x-vellum::ui.badge variant="outline">Outline</x-vellum::ui.badge>
                <x-vellum::ui.badge variant="destructive">Destructive</x-vellum::ui.badge>
            </div>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="demo-kbd">
            <h2 id="demo-kbd" class="text-lg font-medium">Kbd</h2>
            <p class="text-sm text-muted-foreground">Press <x-vellum::ui.kbd>Ctrl</x-vellum::ui.kbd> + <x-vellum::ui.kbd>K</x-vellum::ui.kbd> to search.</p>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="demo-separator">
            <h2 id="demo-separator" class="text-lg font-medium">Separator</h2>
            <div class="flex h-8 items-center gap-3">
                <span class="text-sm">Left</span>
                <x-vellum::ui.separator orientation="vertical" class="h-4" />
                <span class="text-sm">Right</span>
            </div>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="demo-scroll">
            <h2 id="demo-scroll" class="text-lg font-medium">Scroll area</h2>
            <x-vellum::ui.scroll-area class="h-32 rounded-md border border-border p-3">
                <div class="space-y-2 text-sm">
                    @foreach (range(1, 12) as $line)
                        <p>Scrollable line {{ $line }}</p>
                    @endforeach
                </div>
            </x-vellum::ui.scroll-area>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="demo-collapsible">
            <h2 id="demo-collapsible" class="text-lg font-medium">Collapsible</h2>
            <x-vellum::ui.collapsible>
                <x-slot:trigger>
                    <x-vellum::ui.button variant="outline">Toggle collapsible</x-vellum::ui.button>
                </x-slot:trigger>
                <x-slot:content>
                    <p class="mt-2 text-sm text-muted-foreground">Collapsible content is visible when expanded.</p>
                </x-slot:content>
            </x-vellum::ui.collapsible>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="demo-accordion">
            <h2 id="demo-accordion" class="text-lg font-medium">Accordion</h2>
            <x-vellum::ui.accordion type="single" default="one" class="w-full">
                <x-vellum::ui.accordion.item value="one">
                    <x-slot:trigger>First item</x-slot:trigger>
                    <x-slot:content>Content for the first accordion item.</x-slot:content>
                </x-vellum::ui.accordion.item>
                <x-vellum::ui.accordion.item value="two">
                    <x-slot:trigger>Second item</x-slot:trigger>
                    <x-slot:content>Content for the second accordion item.</x-slot:content>
                </x-vellum::ui.accordion.item>
            </x-vellum::ui.accordion>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="demo-tabs">
            <h2 id="demo-tabs" class="text-lg font-medium">Tabs</h2>
            <x-vellum::ui.tabs default="account">
                <x-vellum::ui.tabs.list>
                    <x-vellum::ui.tabs.trigger value="account">Account</x-vellum::ui.tabs.trigger>
                    <x-vellum::ui.tabs.trigger value="password">Password</x-vellum::ui.tabs.trigger>
                </x-vellum::ui.tabs.list>
                <x-vellum::ui.tabs.content value="account">
                    <p class="text-sm text-muted-foreground">Account tab panel. Use arrow keys on the tab list.</p>
                </x-vellum::ui.tabs.content>
                <x-vellum::ui.tabs.content value="password">
                    <p class="text-sm text-muted-foreground">Password tab panel.</p>
                </x-vellum::ui.tabs.content>
            </x-vellum::ui.tabs>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="demo-dialog">
            <h2 id="demo-dialog" class="text-lg font-medium">Dialog</h2>
            <x-vellum::ui.dialog>
                <x-slot:trigger>
                    <x-vellum::ui.button>Open dialog</x-vellum::ui.button>
                </x-slot:trigger>
                <x-slot:title>Example dialog</x-slot:title>
                <x-slot:description>Focus is trapped. Press Escape or click outside to close.</x-slot:description>
                <x-slot:content>
                    <p class="text-sm">Dialog body content.</p>
                </x-slot:content>
                <x-slot:footer>
                    <x-vellum::ui.button variant="outline" x-on:click="close()">Close</x-vellum::ui.button>
                </x-slot:footer>
            </x-vellum::ui.dialog>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="demo-popover">
            <h2 id="demo-popover" class="text-lg font-medium">Popover</h2>
            <x-vellum::ui.popover>
                <x-slot:trigger>
                    <x-vellum::ui.button variant="outline">Open popover</x-vellum::ui.button>
                </x-slot:trigger>
                <x-slot:content>
                    <p class="text-sm">Anchored popover content.</p>
                </x-slot:content>
            </x-vellum::ui.popover>
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="demo-tooltip">
            <h2 id="demo-tooltip" class="text-lg font-medium">Tooltip</h2>
            <x-vellum::ui.tooltip>
                <x-slot:trigger>
                    <x-vellum::ui.button variant="secondary">Hover or focus</x-vellum::ui.button>
                </x-slot:trigger>
                <x-slot:content>Tooltip label</x-slot:content>
            </x-vellum::ui.tooltip>
        </section>
    </div>
@endsection
