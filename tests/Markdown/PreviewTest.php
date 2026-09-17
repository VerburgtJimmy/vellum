<?php

declare(strict_types=1);

use Vellum\Content\ContentRepository;
use Vellum\Exceptions\InvalidPreviewException;

beforeEach(function (): void {
    $this->previewRoot = sys_get_temp_dir().'/vellum-tests/previews-'.$this->fixtureId();

    if (! is_dir($this->previewRoot)) {
        mkdir($this->previewRoot, 0755, true);
    }

    config()->set('vellum.previews.path', $this->previewRoot);

    // Declared here so it keeps the test case's binding: fixtureId() and
    // deleteDirectory() are protected and a global helper cannot reach them.
    $this->preview = function (string $name, string $contents): string {
        $path = $this->previewRoot.'/'.$name.'.blade.php';
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $contents);

        return $path;
    };
});

afterEach(function (): void {
    $this->deleteDirectory($this->previewRoot);
});

it('renders a view from the previews directory into a frame', function (): void {
    ($this->preview)('button', '<button class="btn">Save</button>');
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n:::preview[button]\n:::");

    $html = (string) $this->get('/docs')->assertOk()->getContent();

    expect($html)->toContain('data-vellum-preview-frame')
        ->toContain('srcdoc=')
        // The markup is inside the frame document, HTML escaped into srcdoc.
        ->toContain('&lt;button class=&quot;btn&quot;&gt;Save&lt;/button&gt;');
});

it('shows the view source beside the preview', function (): void {
    ($this->preview)('button', '<button class="btn">Save</button>');
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n:::preview[button]\n:::");

    $html = (string) $this->get('/docs')->assertOk()->getContent();

    expect($html)->toContain('data-value="preview"')
        ->toContain('data-value="code"')
        // Highlighted through the usual code block, so it gets a copy button.
        ->toContain('vellum-code');
});

it('hides the source tab when asked', function (): void {
    ($this->preview)('button', '<button>Save</button>');
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n:::preview[button]{code=\"false\"}\n:::");

    $html = (string) $this->get('/docs')->assertOk()->getContent();

    expect($html)->toContain('data-vellum-preview-frame')
        ->and($html)->not->toContain('data-value="code"');
});

it('takes a height and a padding from the directive', function (): void {
    ($this->preview)('button', '<button>Save</button>');
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n:::preview[button]{height=\"240\" padding=\"none\"}\n:::");

    $html = (string) $this->get('/docs')->assertOk()->getContent();

    expect($html)->toContain('height: 240px')
        ->toContain('padding:0');
});

it('loads the configured stylesheets inside the frame, and nothing into the page', function (): void {
    config()->set('vellum.previews.stylesheets', ['/build/app.css']);
    ($this->preview)('button', '<button>Save</button>');
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n:::preview[button]\n:::");

    $html = (string) $this->get('/docs')->assertOk()->getContent();

    // Escaped, so it is a link inside the frame document rather than a
    // stylesheet the docs page itself loads.
    expect($html)->toContain('&lt;link rel=&quot;stylesheet&quot; href=&quot;/build/app.css&quot;&gt;')
        ->and($html)->not->toContain('<link rel="stylesheet" href="/build/app.css">');
});

it('fails the build when the view is not there, naming the page', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n:::preview[missing]\n:::");

    expect(fn () => ContentRepository::fromConfig()->buildAll())
        ->toThrow(InvalidPreviewException::class, 'index.md');
});

it('fails the build when the preview names no view', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n:::preview\n:::");

    expect(fn () => ContentRepository::fromConfig()->buildAll())
        ->toThrow(InvalidPreviewException::class, 'does not name a view');
});

it('refuses a name that reaches outside the previews directory', function (string $name): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n:::preview[{$name}]\n:::");

    expect(fn () => ContentRepository::fromConfig()->buildAll())
        ->toThrow(InvalidPreviewException::class);
})->with(['../secret', 'a/b', '/etc/passwd', '..']);

it('fails the build when the view itself throws', function (): void {
    ($this->preview)('broken', '@php throw new RuntimeException("nope"); @endphp');
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n:::preview[broken]\n:::");

    expect(fn () => ContentRepository::fromConfig()->buildAll())
        ->toThrow(InvalidPreviewException::class, 'failed to render');
});

it('resolves a view in a subfolder by dot', function (): void {
    ($this->preview)('forms/input', '<input class="field">');
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n:::preview[forms.input]\n:::");

    $html = (string) $this->get('/docs')->assertOk()->getContent();

    expect($html)->toContain('&lt;input class=&quot;field&quot;&gt;');
});

it('keeps the rendered preview out of the search index', function (): void {
    // Source and output differ, so the two can be told apart: the source is a
    // code block on the page like any other and is indexed, while what the
    // frame renders is inside an attribute and is not.
    ($this->preview)('button', "@php \$word = 'render'.'ed'; @endphp<button>{{ \$word }}marker</button>");
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nReal words.\n\n:::preview[button]\n:::");

    $repository = ContentRepository::fromConfig();
    $repository->buildAll();
    $index = (string) $repository->store()->getSearchIndex();

    expect($index)->toContain('Real words')
        ->and($index)->not->toContain('renderedmarker');
});

it('refuses a preview that reads the database', function (): void {
    config()->set('database.default', 'vellum-preview-test');
    config()->set('database.connections.vellum-preview-test', [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    // Compiled once and served as fixed HTML, so a query here would freeze one
    // row of real data into the docs and never change again.
    ($this->preview)('rows', '@php $count = \DB::table("sqlite_master")->count(); @endphp<b>{{ $count }}</b>');
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n:::preview[rows]\n:::");

    expect(fn () => ContentRepository::fromConfig()->buildAll())
        ->toThrow(InvalidPreviewException::class, 'queried the database');
});

it('still renders a preview that only reads config', function (): void {
    config()->set('database.default', 'vellum-preview-test');
    config()->set('database.connections.vellum-preview-test', [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    config()->set('app.name', 'Acme');

    ($this->preview)('name', '<b>{{ config("app.name") }}</b>');
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n:::preview[name]\n:::");

    $html = (string) $this->get('/docs')->assertOk()->getContent();

    expect($html)->toContain('&lt;b&gt;Acme&lt;/b&gt;');
});

it('falls back to the examples the package ships', function (): void {
    // No view of this name in the host application: Vellum's own docs rely on
    // this, since their pages are rendered by whoever installed the package.
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n:::preview[button]\n:::");

    $html = (string) $this->get('/docs')->assertOk()->getContent();

    expect($html)->toContain('data-vellum-preview-frame')
        ->toContain('Primary');
});

it('lets a view of your own win over the shipped example', function (): void {
    ($this->preview)('button', '<button>Mine</button>');
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n:::preview[button]\n:::");

    $html = (string) $this->get('/docs')->assertOk()->getContent();

    expect($html)->toContain('&lt;button&gt;Mine&lt;/button&gt;')
        ->and($html)->not->toContain('Secondary');
});

it('still fails for a name neither side has', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\n:::preview[nothing-like-this]\n:::");

    expect(fn () => ContentRepository::fromConfig()->buildAll())
        ->toThrow(InvalidPreviewException::class, 'nothing-like-this');
});
