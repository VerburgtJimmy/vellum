{{--
  One operation. Step 3 of the 0.6 plan fills in the two-column layout,
  parameter tables, schema tree and samples; the heading, its id and the
  badges are already what they will be, because links and the table of
  contents are built from them.
--}}
<section class="vellum-api-operation" data-vellum-operation="{{ $operation->headingId() }}">
    <h2 id="{{ $operation->headingId() }}" class="vellum-heading vellum-api-operation-title">{{ $operation->title() }}</h2>

    <p class="vellum-api-request">
        <span class="vellum-api-method" data-method="{{ strtolower($operation->method) }}">{{ $operation->method }}</span>
        <code class="vellum-api-path">{{ $operation->path }}</code>
        @if ($operation->deprecated)
            <span class="vellum-api-deprecated">Deprecated</span>
        @endif
    </p>

    @if ($operation->description !== null)
        <div class="vellum-api-description">{!! $markdown($operation->description) !!}</div>
    @endif
</section>
