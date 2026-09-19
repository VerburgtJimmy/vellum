<?php

declare(strict_types=1);

use Vellum\Answers\Questions\CalloutQuestions;
use Vellum\Answers\Questions\CommandQuestions;
use Vellum\Answers\Questions\ConfigKeyQuestions;
use Vellum\Answers\Questions\FrontmatterQuestions;
use Vellum\Answers\Questions\HeadingQuestions;
use Vellum\Answers\Questions\QuestionGenerators;
use Vellum\Answers\Sections;
use Vellum\Cache\CompiledStore;
use Vellum\Content\ContentRepository;

beforeEach(function (): void {
    // Compile one page and return its sections, so generators see real markup.
    $this->sectionsOf = function (string $markdown): array {
        $this->writeDoc('page.md', $markdown);
        $repository = new ContentRepository(contentPath: $this->docsPath(), store: new CompiledStore($this->cachePath()));

        return Sections::from($repository->buildAll());
    };
});

function section(string $own, string $html = '', array $questions = [], string $title = 'Page'): array
{
    return ['title' => $title, 'own' => $own, 'html' => $html, 'questions' => $questions];
}

it('asks how for a heading that opens with a verb, and what for a noun', function (): void {
    $headings = new HeadingQuestions;

    expect($headings->generate(section('Remembering the choice')))->toBe(['how do i remembering the choice'])
        ->and($headings->generate(section('Gating')))->toBe(['what is gating', 'where do i configure gating'])
        ->and($headings->generate(section('Code tabs')))->toBe(['what is code tabs', 'where do i configure code tabs'])
        ->and($headings->generate(section('', title: 'Theming')))->toBe(['what is theming', 'where do i configure theming']);
});

it('reads artisan and composer commands from shell blocks, one per line', function (): void {
    $sections = ($this->sectionsOf)("---\ntitle: Deploy\n---\n```bash\ncomposer install --no-dev\nphp artisan config:cache\nphp artisan vellum:build\ncomposer require acme/widgets\n```\n\n```php\nphp artisan ignored:here\n```\n");

    expect((new CommandQuestions)->generate($sections[0]))->toBe([
        'how do i install',
        'how do i install',
        'how do i cache',
        'what does config:cache do',
        'how do i build',
        'what does vellum:build do',
        'how do i require acme/widgets',
        'how do i install acme/widgets',
    ]);
});

it('reads dotted keys in prose and the first column of a Key table, but not file names', function (): void {
    $sections = ($this->sectionsOf)("---\ntitle: Config\n---\nSet `checks.strict`, not `meta.json`.\n\n| Key | Default |\n| --- | --- |\n| `repo` | `null` |\n| `route.prefix` | `docs` |\n\n```php\n'ignored.key' => true,\n```\n");

    expect(ConfigKeyQuestions::keys($sections[0]['html']))->toBe(['checks.strict', 'route.prefix', 'repo'])
        ->and((new ConfigKeyQuestions)->generate($sections[0]))->toContain('what does repo do', 'how do i change checks.strict', 'default of route.prefix');
});

it('turns warning and danger callouts into why-does-it-fail questions', function (): void {
    $sections = ($this->sectionsOf)("---\ntitle: Assets\n---\n:::warning[Assets are not gated]\nAnyone with the URL can load them.\n:::\n\n:::note\nJust a note.\n:::\n");

    expect((new CalloutQuestions)->generate($sections[0]))->toBe(['why does Assets are not gated fail']);
});

it('takes an author\'s own questions from frontmatter onto the opening section', function (): void {
    $sections = ($this->sectionsOf)("---\ntitle: Billing\nquestions:\n  - How are refunds handled?\n  - 42\n---\nIntro.\n\n## Refunds\n\nText.\n");

    expect((new FrontmatterQuestions)->generate($sections[0]))->toBe(['How are refunds handled?'])
        ->and((new FrontmatterQuestions)->generate($sections[1]))->toBe([]);
});

it('combines every generator into distinct lowercase questions', function (): void {
    $sections = ($this->sectionsOf)("---\ntitle: Install\nquestions:\n  - What Is Install\n---\n```bash\nphp artisan vellum:install\n```\n");

    expect(QuestionGenerators::default()->for($sections[0]))->toBe([
        'what is install',
        'where do i configure install',
        'how do i install',
        'what does vellum:install do',
    ]);
});
