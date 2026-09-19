<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Vellum\Support\LlmsTxt;

beforeEach(function (): void {
    config()->set('app.url', 'https://docs.example.com');
    config()->set('vellum.name', 'Acme');
});

it('serves llms.txt with a heading, a summary and one line per page', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\ndescription: Everything about Acme.\n---\nHi");
    $this->writeDoc('install.md', "---\ntitle: Install\ndescription: Get it running.\n---\nBody");
    $this->writeDoc('faq.md', "---\ntitle: FAQ\n---\nBody");
    file_put_contents($this->docsPath().'/meta.json', json_encode(['pages' => ['index', 'install', 'faq']]));

    $response = $this->get('/docs/llms.txt')->assertOk();

    expect($response->headers->get('content-type'))->toBe('text/plain; charset=UTF-8')
        ->and((string) $response->getContent())->toBe(implode("\n", [
            '# Acme',
            '',
            '> Everything about Acme.',
            '',
            '## Docs',
            '',
            '- [Home](https://docs.example.com/docs/_vellum/raw/index.md): Everything about Acme.',
            '- [Install](https://docs.example.com/docs/_vellum/raw/install.md): Get it running.',
            '- [FAQ](https://docs.example.com/docs/_vellum/raw/faq.md)',
            '',
        ]));
});

it('gives each folder and titled separator its own section, loose pages first', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('guides/deploy.md', "---\ntitle: Deploy\n---\nShip");
    $this->writeDoc('guides/nested/deeper.md', "---\ntitle: Deeper\n---\nDown");
    $this->writeDoc('faq.md', "---\ntitle: FAQ\n---\nBody");
    $this->writeDoc('api.md', "---\ntitle: API\n---\nBody");
    file_put_contents($this->docsPath().'/meta.json', json_encode([
        'pages' => ['index', 'guides', 'faq', '---Reference---', 'api', ['title' => 'GitHub', 'href' => 'https://github.com']],
    ]));
    file_put_contents($this->docsPath().'/guides/meta.json', json_encode(['title' => 'Guides']));

    $body = (string) $this->get('/docs/llms.txt')->assertOk()->getContent();

    expect(preg_match_all('/^## .*$/m', $body, $headings))->toBe(3)
        ->and($headings[0])->toBe(['## Docs', '## Guides', '## Reference'])
        ->and($body)->toContain("## Docs\n\n- [Home](https://docs.example.com/docs/_vellum/raw/index.md)\n- [FAQ](https://docs.example.com/docs/_vellum/raw/faq.md)\n")
        ->and($body)->toContain('- [Deploy](https://docs.example.com/docs/_vellum/raw/guides/deploy.md)')
        ->and($body)->toContain('- [Deeper](https://docs.example.com/docs/_vellum/raw/guides/nested/deeper.md)')
        ->and($body)->toContain("## Reference\n\n- [API](https://docs.example.com/docs/_vellum/raw/api.md)")
        // A link in meta.json has no Markdown source to point at.
        ->and($body)->not->toContain('GitHub');
});

it('uses root-relative links when app.url is not an origin', function (): void {
    config()->set('app.url', 'localhost');
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    expect((string) $this->get('/docs/llms.txt')->assertOk()->getContent())
        ->toContain('- [Home](/docs/_vellum/raw/index.md)');
});

it('leaves gated pages out of both files, even for a signed-in reader', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('secret.md', "---\ntitle: Secret\naccess: auth\n---\nPrivate words");
    $this->writeDoc('team/_meta.md', "---\naccess: auth\n---\n");
    $this->writeDoc('team/roster.md', "---\ntitle: Roster\n---\nNames");

    $this->actingAs(new User);
    $this->get('/docs/secret')->assertOk();

    $index = (string) $this->get('/docs/llms.txt')->assertOk()->getContent();
    $full = (string) $this->get('/docs/llms-full.txt')->assertOk()->getContent();

    expect($index)->toContain('[Home]')
        ->not->toContain('Secret')
        ->not->toContain('Roster')
        ->and($full)->toContain('Hi')
        ->not->toContain('Private words')
        ->not->toContain('Names');
});

it('lists the latest version only', function (): void {
    config()->set('vellum.versions', ['enabled' => true, 'latest' => 'v2', 'list' => ['v2', 'v1'], 'labels' => []]);
    $this->writeDoc('v2/index.md', "---\ntitle: Home two\n---\nTwo");
    $this->writeDoc('v2/new.md', "---\ntitle: New\n---\nNew page");
    $this->writeDoc('v1/index.md', "---\ntitle: Home one\n---\nOne");
    $this->writeDoc('v1/old.md', "---\ntitle: Old\n---\nOld page");

    $index = (string) $this->get('/docs/llms.txt')->assertOk()->getContent();
    $full = (string) $this->get('/docs/llms-full.txt')->assertOk()->getContent();

    expect($index)->toContain('- [New](https://docs.example.com/docs/_vellum/raw/new.md)')
        ->not->toContain('Home one')
        ->not->toContain('Old')
        ->not->toContain('v1')
        ->and($full)->toContain("Version: v2\n")
        ->not->toContain('Old page');
});

it('concatenates every source in sidebar order behind a separator', function (): void {
    $index = "---\ntitle: Home\ndescription: Start here.\n---\nHi";
    $deploy = "---\ntitle: Deploy\n---\n# Deploy\n\nShip it.\n";
    $this->writeDoc('index.md', $index);
    $this->writeDoc('deploy.md', $deploy);
    file_put_contents($this->docsPath().'/meta.json', json_encode(['pages' => ['index', 'deploy']]));

    $response = $this->get('/docs/llms-full.txt')->assertOk();
    $separator = LlmsTxt::SEPARATOR;

    expect($response->headers->get('content-type'))->toBe('text/plain; charset=UTF-8')
        ->and((string) $response->getContent())->toBe(implode("\n", [
            '# Acme',
            '',
            '> Start here.',
            '',
            $separator,
            'Title: Home',
            'URL: https://docs.example.com/docs',
            $separator,
            '',
            $index,
            '',
            $separator,
            'Title: Deploy',
            'URL: https://docs.example.com/docs/deploy',
            $separator,
            '',
            rtrim($deploy),
            '',
        ]));
});

it('404s both files when agents.llms_txt is off', function (): void {
    config()->set('vellum.agents.llms_txt', false);
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->get('/docs/llms.txt')->assertNotFound();
    $this->get('/docs/llms-full.txt')->assertNotFound();
});
