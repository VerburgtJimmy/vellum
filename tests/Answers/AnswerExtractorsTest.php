<?php

declare(strict_types=1);

use Vellum\Answers\Extractors\AnswerExtractors;
use Vellum\Answers\Extractors\CodeAnswer;
use Vellum\Answers\Extractors\CommandAnswer;
use Vellum\Answers\Extractors\ConfigAnswer;
use Vellum\Answers\Extractors\DefinitionAnswer;
use Vellum\Answers\Extractors\DescriptionAnswer;
use Vellum\Answers\Extractors\Html;
use Vellum\Answers\Extractors\ListAnswer;
use Vellum\Answers\Extractors\SentenceAnswer;
use Vellum\Answers\Extractors\TableRowAnswer;
use Vellum\Answers\Sections;
use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;

beforeEach(function (): void {
    // Compile one page and return its sections, so extractors see real markup.
    $this->sectionsOf = function (string $markdown): array {
        $this->writeDoc('page.md', $markdown);

        return Sections::from((new ContentRepository(contentPath: $this->docsPath(), store: new CompiledStore($this->cachePath())))->buildAll());
    };
});

it('answers with the first shell block, without comments or blank lines', function (): void {
    $section = ($this->sectionsOf)("---\ntitle: Build\n---\n```php\n\$x = 1;\n```\n\n```bash\n# compile\nphp artisan vellum:build\n\nphp artisan vellum:index\n```\n")[0];

    expect((new CommandAnswer)->extract($section))->toBe(['type' => 'command', 'command' => "php artisan vellum:build\nphp artisan vellum:index"]);
});

it('answers with config rows from a table headed Key', function (): void {
    $section = ($this->sectionsOf)("---\ntitle: Config\n---\n| Key | Type | Default | Purpose |\n| --- | --- | --- | --- |\n| `checks.strict` | bool | `false` | Fail the build on broken links |\n| `repo` | string | `null` | Edit link base |\n")[0];

    expect((new ConfigAnswer)->extract($section))->toBe(['type' => 'config', 'rows' => [
        ['key' => 'checks.strict', 'type' => 'bool', 'default' => 'false', 'description' => 'Fail the build on broken links'],
        ['key' => 'repo', 'type' => 'string', 'default' => 'null', 'description' => 'Edit link base'],
    ]]);
});

it('answers with the option row the heading names', function (): void {
    $section = ($this->sectionsOf)("---\ntitle: Tabs\n---\n## The persist attribute\n\n| Attribute | Purpose |\n| --- | --- |\n| `code` | Force code tabs |\n| `persist` | localStorage key |\n")[1];

    expect((new TableRowAnswer)->extract($section))->toBe(['type' => 'row', 'row' => ['Attribute' => 'persist', 'Purpose' => 'localStorage key']]);
});

it('answers with a definition only when the subject is short', function (): void {
    [$short] = ($this->sectionsOf)("---\ntitle: Gating\n---\nAccess is a frontmatter key. It takes guest or auth.\n");
    [$long] = ($this->sectionsOf)("---\ntitle: Gating\n---\nEvery page that you write in the docs folder with a key is gated.\n");

    expect((new DefinitionAnswer)->extract($short))->toBe(['type' => 'definition', 'sentence' => 'Access is a frontmatter key.'])
        ->and((new DefinitionAnswer)->extract($long))->toBeNull();
});

it('skips labels and pointers, and brings the code block a colon introduces', function (): void {
    [$section] = ($this->sectionsOf)("---\ntitle: Title\n---\nSee Other. Name the file it comes from:\n\n```php title=\"web.php\"\nRoute::get('/');\n```\n");

    expect((new SentenceAnswer)->extract($section))->toBe([
        'type' => 'sentence',
        'sentence' => 'Name the file it comes from:',
        'code' => "Route::get('/');",
        'language' => 'php',
    ]);
});

it('falls back to the description, a list, then a code block', function (): void {
    $sections = ($this->sectionsOf)("---\ntitle: Setup\ndescription: How to set it up.\n---\n| A | B |\n| --- | --- |\n| 1 | 2 |\n\n## Requirements\n\n- PHP 8.4+\n- Laravel 11, 12, or 13\n\n## Config\n\n```php\n'answers' => [],\n```\n");

    expect(AnswerExtractors::default()->for($sections[0]))->toBe(['type' => 'sentence', 'sentence' => 'How to set it up.'])
        ->and(AnswerExtractors::default()->for($sections[1]))->toBe(['type' => 'list', 'items' => ['PHP 8.4+', 'Laravel 11, 12, or 13']])
        ->and(AnswerExtractors::default()->for($sections[2]))->toBe(['type' => 'code', 'code' => "'answers' => [],", 'language' => 'php'])
        ->and((new DescriptionAnswer)->extract($sections[1]))->toBeNull()
        ->and((new ListAnswer)->extract($sections[2]))->toBeNull()
        ->and((new CodeAnswer)->extract($sections[1]))->toBeNull();
});

it('prefers a command over prose, in the order of the plan', function (): void {
    [$section] = ($this->sectionsOf)("---\ntitle: Deploy\n---\nVellum is a package. Run this:\n\n```bash\nphp artisan vellum:build\n```\n");

    expect(AnswerExtractors::default()->for($section)['type'])->toBe('command');
});

it('quotes up to three sentences as the passage, ignoring callouts', function (): void {
    [$section] = ($this->sectionsOf)("---\ntitle: P\n---\n:::note\nNot this.\n:::\n\nOne is here. Two e.g. this. Three now. Four never.\n");

    expect(AnswerExtractors::passage($section))->toBe('One is here. Two e.g. this. Three now.')
        ->and(Html::sentences('Version 0.6.1 ships. Next.'))->toBe(['Version 0.6.1 ships.', 'Next.']);
});
