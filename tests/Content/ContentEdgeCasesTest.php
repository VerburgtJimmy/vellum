<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Vellum\Content\Access;
use Vellum\Content\ContentRepository;
use Vellum\Exceptions\DuplicateSlugException;
use Vellum\Support\Str;

it('treats the reserved access words case-insensitively', function (string $written, string $expected): void {
    expect(Access::normalize($written))->toBe($expected);
})->with([
    ['auth', 'auth'],
    ['Auth', 'auth'],
    ['AUTH', 'auth'],
    [' auth ', 'auth'],
    ['guest', 'guest'],
    ['Guest', 'guest'],
    ['', 'guest'],
    ['   ', 'guest'],
]);

it('leaves a gate name exactly as written', function (): void {
    expect(Access::normalize('ViewBilling'))->toBe('ViewBilling')
        ->and(Access::normalize('view-billing'))->toBe('view-billing');
});

it('hides a page written as "access: Auth" from guests but not from users', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('secret.md', "---\ntitle: Secret\naccess: Auth\n---\nNope");

    $this->get('/docs/secret')->assertNotFound();

    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Ada']));

    $this->get('/docs/secret')->assertOk()->assertSee('Nope', false);
});

it('still routes a capitalised gate name to the gate', function (): void {
    Gate::define('ViewBilling', fn ($user = null): bool => false);

    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('billing.md', "---\ntitle: Billing\naccess: ViewBilling\n---\nMoney");

    $this->get('/docs/billing')->assertNotFound();

    Gate::define('ViewBilling', fn ($user = null): bool => true);

    $this->get('/docs/billing')->assertOk()->assertSee('Money', false);
});

it('reads frontmatter from a file that starts with a byte order mark', function (): void {
    $this->writeDoc('bom.md', "\xEF\xBB\xBF---\ntitle: With BOM\ndescription: Still parsed\n---\nBody text");

    $document = ContentRepository::fromConfig()->find('bom');

    // Without the BOM strip the title falls back to the filename ("Bom") and
    // the whole frontmatter block renders as body text.
    expect($document)->not->toBeNull()
        ->and($document->title)->toBe('With BOM')
        ->and($document->description)->toBe('Still parsed')
        ->and($document->html)->toContain('Body text')
        ->and($document->html)->not->toContain('title: With BOM');

    $this->get('/docs/bom')->assertOk();
});

it('refuses to build when two files claim the same slug', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('guides.md', "---\ntitle: One\n---\nOne");
    $this->writeDoc('guides/index.md', "---\ntitle: Two\n---\nTwo");

    expect(fn () => ContentRepository::fromConfig()->buildAll())
        ->toThrow(DuplicateSlugException::class, '/guides');
});

it('names both files in a slug collision', function (): void {
    $this->writeDoc('a.md', "---\ntitle: A\nslug: shared\n---\nA");
    $this->writeDoc('b.md', "---\ntitle: B\nslug: shared\n---\nB");

    try {
        ContentRepository::fromConfig()->buildAll();
        $this->fail('Expected a DuplicateSlugException.');
    } catch (DuplicateSlugException $exception) {
        expect($exception->getMessage())->toContain('a.md')
            ->and($exception->getMessage())->toContain('b.md');
    }
});

it('builds without complaint when every slug is unique', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('guides/deploy.md', "---\ntitle: Deploy\n---\nShip");

    expect(ContentRepository::fromConfig()->buildAll())->toHaveCount(2);
});

it('does not render a second h1 when the title falls back to the body heading', function (): void {
    $this->writeDoc('fallback.md', "# Fallback Title\n\nSome body copy.");

    $response = $this->get('/docs/fallback')->assertOk();
    $content = $response->getContent();

    expect(substr_count((string) $content, '<h1'))->toBe(1)
        ->and($content)->toContain('Fallback Title')
        ->and($content)->toContain('Some body copy.');
});

it('keeps a heading that is not the first thing in the body', function (): void {
    $this->writeDoc('later.md', "---\ntitle: Set In Frontmatter\n---\nIntro copy.\n\n# A Later Heading\n");

    $content = (string) $this->get('/docs/later')->assertOk()->getContent();

    expect($content)->toContain('A Later Heading')
        ->and($content)->toContain('Intro copy.');
});

it('leaves a leading heading alone when the frontmatter sets a title', function (): void {
    $this->writeDoc('both.md', "---\ntitle: Frontmatter Wins\n---\n# Body Heading\n\nCopy.");

    $content = (string) $this->get('/docs/both')->assertOk()->getContent();

    expect($content)->toContain('Frontmatter Wins')
        ->and($content)->toContain('Body Heading');
});

it('does not strip a hash that is not a heading', function (): void {
    expect(Str::withoutLeadingHeading("#NotAHeading\n\nBody"))->toBe("#NotAHeading\n\nBody")
        ->and(Str::withoutLeadingHeading("Body\n\n# Heading"))->toBe("Body\n\n# Heading");
});

it('accepts a numeric frontmatter title', function (): void {
    // YAML parses an unquoted 2024 as an int, which used to be discarded in
    // favour of the title-cased filename.
    $this->writeDoc('year.md', "---\ntitle: 2024\n---\nBody");

    expect(ContentRepository::fromConfig()->find('year')->title)->toBe('2024');
});

it('still falls back to the filename for a title it cannot use', function (): void {
    $this->writeDoc('some-page.md', "---\ntitle: []\n---\nBody");

    expect(ContentRepository::fromConfig()->find('some-page')->title)->toBe('Some Page');
});
