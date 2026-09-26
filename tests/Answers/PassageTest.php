<?php

declare(strict_types=1);

use Vellum\Answers\Passage;
use Vellum\Answers\Sections;
use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;

it('quotes up to three sentences as the passage, ignoring callouts', function (): void {
    $this->writeDoc('page.md', "---\ntitle: P\n---\n:::note\nNot this.\n:::\n\nOne is here. Two e.g. this. Three now. Four never.\n");
    [$section] = Sections::from((new ContentRepository(contentPath: $this->docsPath(), store: new CompiledStore($this->cachePath())))->buildAll());

    expect(Passage::of($section))->toBe('One is here. Two e.g. this. Three now.')
        ->and(Passage::sentences('Version 0.6.1 ships. Next.'))->toBe(['Version 0.6.1 ships.', 'Next.']);
});
