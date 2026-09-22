<?php

declare(strict_types=1);

use Vellum\Answers\AnswerIndex;
use Vellum\Answers\SearchCheck;
use Vellum\Content\ContentRepository;

beforeEach(function (): void {
    $this->writeDoc('commands.md', "---\ntitle: Commands\n---\n## Build\n\nRun the build.\n\n```bash\nphp artisan vellum:build\n```\n");
    $this->writeDoc('theming.md', "---\ntitle: Theming\n---\n## Radius\n\nRounded corners come from radius.\n");

    $repository = ContentRepository::fromConfig();
    $this->index = AnswerIndex::build($repository->buildAll(), $repository);
    $this->questionsFile = $this->docsPath().'/'.SearchCheck::FILE;
});

function writeQuestions(string $path, string $yaml): string
{
    file_put_contents($path, $yaml);

    return $path;
}

it('has nothing to say without a questions file', function (): void {
    expect(SearchCheck::read($this->docsPath().'/'.SearchCheck::FILE))->toBeNull();
});

it('ignores entries that name no question or no page', function (): void {
    $path = writeQuestions($this->questionsFile, "- q: Real question\n  page: commands\n- page: commands\n- q: ''\n  page: commands\n- Not a mapping\n");

    expect(SearchCheck::read($path)?->questions)->toBe([['q' => 'Real question', 'page' => 'commands']]);
});

it('is null for a file that is not a list of questions', function (): void {
    expect(SearchCheck::read(writeQuestions($this->questionsFile, "not: a list\n")))->toBeNull()
        ->and(SearchCheck::read(writeQuestions($this->questionsFile, "\t- broken: [yaml\n")))->toBeNull();
});

it('counts a question its target answers in the top five', function (): void {
    $check = SearchCheck::read(writeQuestions($this->questionsFile, "- q: how do i build the docs\n  page: commands\n  section: build\n"));

    $result = $check?->run($this->index);

    expect($result['asked'])->toBe(1)
        ->and($result['found'])->toBe(1)
        ->and($result['missed'])->toBe([])
        ->and($result['broken'])->toBe([]);
});

it('takes any of also as the answer', function (): void {
    $check = SearchCheck::read(writeQuestions($this->questionsFile, "- q: how do i build the docs\n  page: theming\n  also:\n    - page: commands\n      section: build\n"));

    expect($check?->run($this->index)['found'])->toBe(1);
});

it('reports a question whose target is gone as broken, not as a miss', function (): void {
    $check = SearchCheck::read(writeQuestions($this->questionsFile, "- q: how do i build the docs\n  page: commands\n  section: the-old-heading\n- q: where did the page go\n  page: removed\n"));

    $result = $check?->run($this->index);

    expect($result['asked'])->toBe(0)
        ->and($result['broken'])->toHaveCount(2)
        ->and($result['broken'][0])->toContain('commands#the-old-heading')
        ->and($result['missed'])->toBe([]);
});

it('reports a question nothing answers as a miss', function (): void {
    $check = SearchCheck::read(writeQuestions($this->questionsFile, "- q: rounded corners\n  page: commands\n  section: build\n"));

    $result = $check?->run($this->index);

    expect($result['asked'])->toBe(1)
        ->and($result['found'])->toBe(0)
        ->and($result['missed'][0])->toContain('wanted commands#build');
});
