<?php

declare(strict_types=1);

use Vellum\Answers\AnswerIndex;
use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;

beforeEach(function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\ndescription: Where to start.\nupdated: 2026-09-01\n---\nVellum is a docs package.\n\n## Install\n\n```bash\ncomposer require jimmyverburgt/vellum\n```\n");
    $this->writeDoc('billing.md', "---\ntitle: Billing\naccess: auth\naliases:\n  - Supercalifragilistic invoices\n---\nRefunds take five days.\n");

    $this->index = function (array $extra = []): AnswerIndex {
        $repository = new ContentRepository(contentPath: $this->docsPath(), store: new CompiledStore($this->cachePath()));

        return AnswerIndex::build($repository->buildAll(), $repository, $extra);
    };
});

// The guest-leak guard for answers: nothing from a gated page, not even its
// aliases, reaches a reader without access.
it('never gives a guest a gated page\'s sections, questions or aliases', function (): void {
    $guest = ($this->index)()->forAccess(['guest']);
    $member = ($this->index)()->forAccess(['guest', 'auth']);
    $guestJson = json_encode($guest->sections).json_encode($guest->synonyms);

    expect(array_column($guest->sections, 'id'))->toBe(['#', '#install'])
        ->and(array_column($member->sections, 'id'))->toBe(['billing#', '#', '#install'])
        ->and($guest->synonyms)->toBe([])
        ->and($member->synonyms)->toBe([['terms' => ['Billing', 'Supercalifragilistic invoices'], 'access' => 'auth']])
        ->and($guestJson)->not->toContain('Refunds')
        ->and($guestJson)->not->toContain('Supercalifragilistic')
        ->and($guest->synonyms()->expand('supercalifragilistic invoices'))->toBe([])
        ->and($member->synonyms()->expand('supercalifragilistic invoices'))->toBe(['billing']);

    $this->artisan('vellum:build')->assertSuccessful();
    $stored = AnswerIndex::load($this->cachePath().'/answers');

    expect($stored)->not->toBeNull()
        ->and(json_encode($stored->forAccess(['guest'])->sections))->not->toContain('Refunds');
});

it('records what search needs for each section', function (): void {
    $home = ($this->index)()->sections[1];
    $install = ($this->index)()->sections[2];

    expect($home)->toMatchArray([
        'id' => '#',
        'page' => '',
        'anchor' => '',
        'access' => 'guest',
        'url' => '/docs',
        'title' => 'Home',
        'answer' => ['type' => 'definition', 'sentence' => 'Vellum is a docs package.'],
        'passage' => 'Vellum is a docs package.',
        'updated' => '2026-09-01',
    ])
        ->and($home['questions'])->toContain('what is home')
        ->and($install['url'])->toBe('/docs#install')
        ->and($install['parent'])->toBe('install')
        ->and($install['answer'])->toBe(['type' => 'command', 'command' => 'composer require jimmyverburgt/vellum'])
        ->and($install['questions'])->toContain('how do i install jimmyverburgt/vellum')
        ->and($install['aliases'])->toBe(['install']);
});

it('merges extra questions without repeating generated ones', function (): void {
    $install = ($this->index)(['#install' => ['How do I add Vellum to my app?', 'what is install']])->sections[2];

    expect($install['questions'])->toContain('how do i add vellum to my app?')
        ->and(array_count_values($install['questions'])['what is install'])->toBe(1);
});

it('embeds a section from its words followed by its questions', function (): void {
    $text = AnswerIndex::text(($this->index)()->sections[2]);

    expect($text)->toStartWith('Home Install')
        ->and($text)->toContain('composer require jimmyverburgt/vellum')
        ->and($text)->toEndWith('how do i install jimmyverburgt/vellum');
});

it('reports the answers index in vellum:build, and skips it when answers are off', function (): void {
    config(['vellum.answers.semantic' => false]);

    $this->artisan('vellum:build')->expectsOutputToContain('answers: 3 sections')->assertSuccessful();

    config(['vellum.answers.enabled' => false]);
    $this->deleteDirectory($this->cachePath().'/answers');

    $this->artisan('vellum:build')->doesntExpectOutputToContain('answers:')->assertSuccessful();

    expect(is_dir($this->cachePath().'/answers'))->toBeFalse();
});
