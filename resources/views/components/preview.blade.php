@php
    use Vellum\Support\Cn;
@endphp

<div class="vellum-preview" data-vellum-preview x-data="vellumPreview(@js($id))">
    <x-vellum::ui.tabs :default="'preview'" class="vellum-preview-tabs">
        @if ($showCode)
            <x-vellum::ui.tabs.list>
                <x-vellum::ui.tabs.trigger value="preview">Preview</x-vellum::ui.tabs.trigger>
                <x-vellum::ui.tabs.trigger value="code">Code</x-vellum::ui.tabs.trigger>
            </x-vellum::ui.tabs.list>
        @endif

        <x-vellum::ui.tabs.content value="preview">
            {{-- srcdoc rather than a src: the frame travels with the page, so
                 a static export needs no extra file and no route. --}}
            <iframe
                data-vellum-preview-frame
                x-ref="frame"
                title="Preview of {{ $name }}"
                loading="lazy"
                sandbox="allow-scripts"
                srcdoc="{{ $document }}"
                @if ($height !== null) style="height: {{ $height }}px" @endif
                :style="height ? 'height: ' + height + 'px' : null"
            ></iframe>
        </x-vellum::ui.tabs.content>

        @if ($showCode)
            <x-vellum::ui.tabs.content value="code">
                {!! $code !!}
            </x-vellum::ui.tabs.content>
        @endif
    </x-vellum::ui.tabs>
</div>
