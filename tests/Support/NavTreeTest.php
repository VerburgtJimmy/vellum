<?php

declare(strict_types=1);

use Vellum\Support\NavTree;

it('renders the tree once for both copies, and again for a different tree', function (): void {
    $none = static fn (array $node): bool => false;
    $guest = [['type' => 'page', 'slug' => 'intro', 'title' => 'Intro', 'href' => '/docs/intro', 'access' => 'guest']];
    $member = [...$guest, ['type' => 'page', 'slug' => 'plans', 'title' => 'Plans', 'href' => '/docs/plans', 'access' => 'auth']];

    $first = NavTree::render($guest, 'intro', $none);

    expect(NavTree::render($guest, 'intro', $none))->toBe($first)
        ->and($first)->toContain('aria-current="page"')
        ->and(NavTree::render($member, 'intro', $none))->toContain('Plans')
        ->and(NavTree::render($guest, 'intro', $none))->not->toContain('Plans');
});

it('shows the same tree in the sidebar and the mobile drawer', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('guide.md', "---\ntitle: Guide\n---\nG");

    $html = (string) $this->get('/docs/guide')->assertOk()->getContent();

    expect(substr_count($html, 'href="/docs/guide"'))->toBeGreaterThanOrEqual(2)
        ->and(substr_count($html, 'aria-current="page"'))->toBeGreaterThanOrEqual(2);
});
