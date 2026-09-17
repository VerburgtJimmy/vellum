{{--
    Published by vellum:install as a starting point.

    Styled inline on purpose: a fresh install has no previews.stylesheets, and
    an example that renders unstyled looks like a broken feature rather than a
    starting point. Point stylesheets at your own build and write the next one
    with your own classes.
--}}
<div style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap; font-family:system-ui,sans-serif">
    <button style="padding:0.6rem 1rem; border-radius:0.5rem; border:1px solid #171717; background:#171717; color:#fff; font:500 0.875rem/1 system-ui; cursor:pointer">
        Primary
    </button>
    <button style="padding:0.6rem 1rem; border-radius:0.5rem; border:1px solid #d4d4d4; background:transparent; color:#171717; font:500 0.875rem/1 system-ui; cursor:pointer">
        Secondary
    </button>
    <button disabled style="padding:0.6rem 1rem; border-radius:0.5rem; border:1px solid #e5e5e5; background:#f5f5f5; color:#a3a3a3; font:500 0.875rem/1 system-ui; cursor:not-allowed">
        Disabled
    </button>
</div>
