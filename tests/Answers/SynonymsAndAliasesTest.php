<?php

declare(strict_types=1);

use Vellum\Answers\Aliases;
use Vellum\Answers\Sections;
use Vellum\Answers\Synonyms;
use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;

it('normalises text without breaking dotted keys, paths or dotfiles', function (): void {
    expect(Synonyms::normalize('Where is `route.prefix`, the .env, and dark-mode?'))->toBe('where is route.prefix the env and dark-mode')
        ->and(Synonyms::normalize('resources/docs/ files.'))->toBe('resources/docs files');
});

it('expands a term to the rest of its group, matching whole phrases only', function (): void {
    $synonyms = new Synonyms([['dark mode', 'night mode', 'dark theme'], ['card', 'tile']]);

    expect($synonyms->expand('start in night mode'))->toBe(['dark theme', 'dark mode'])
        ->and($synonyms->expand('a nightmode toggle'))->toBe([])
        ->and($synonyms->expand('turn a tile into a card'))->toBe([])
        ->and($synonyms->expand('tiles'))->toBe([]);
});

it('ships built-in groups seeded from the held-out misses', function (): void {
    $synonyms = Synonyms::withPages([]);

    expect($synonyms->expand('can i use this commercially'))->toContain('licence', 'license')
        ->and($synonyms->expand('text between my link tiles'))->toContain('cards')
        ->and($synonyms->expand('what is in the outline on the right'))->toContain('table of contents')
        ->and($synonyms->expand('where do i put the .env value'))->toContain('dotenv');
});

it('adds a group per page from its title and frontmatter aliases', function (): void {
    $synonyms = Synonyms::withPages([['title' => 'Billing', 'aliases' => ['Invoices', 'payments']]]);

    expect($synonyms->expand('where are my invoices'))->toBe(['payments', 'billing']);
});

it('finds a section by its heading, inline code in prose, and page aliases', function (): void {
    $this->writeDoc('page.md', "---\ntitle: Billing\naliases:\n  - Invoices\n  - 7\n---\nSet `billing.currency` here.\n\n```php\n'not.this' => 1,\n```\n\n## Refunds\n\nUse `refund()`.\n");
    $sections = Sections::from((new ContentRepository(contentPath: $this->docsPath(), store: new CompiledStore($this->cachePath())))->buildAll());

    expect($sections[0]['aliases'])->toBe(['Invoices'])
        ->and(Aliases::for($sections[0]))->toBe(['billing', 'invoices', 'billing.currency'])
        ->and(Aliases::for($sections[1]))->toBe(['refunds', 'refund']);
});
