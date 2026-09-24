<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;

/**
 * An engine that keeps its records in memory, keyed by id.
 */
final class InMemoryScoutEngine extends Engine
{
    /** @var array<string, array<string, mixed>> */
    public array $records = [];

    public function update($models): void
    {
        foreach ($models as $model) {
            $this->records[(string) $model->getScoutKey()] = $model->toSearchableArray();
        }
    }

    public function delete($models): void
    {
        foreach ($models as $model) {
            unset($this->records[(string) $model->getScoutKey()]);
        }
    }

    public function flush($model): void
    {
        $this->records = [];
    }

    public function search(Builder $builder): mixed
    {
        $hits = array_filter($this->records, static function (array $record) use ($builder): bool {
            foreach ($builder->wheres as $field => $value) {
                if (($record[$field] ?? null) !== $value) {
                    return false;
                }
            }

            return stripos($record['title'].' '.$record['content'], (string) $builder->query) !== false;
        });

        return ['hits' => array_values($hits)];
    }

    public function paginate(Builder $builder, $perPage, $page): mixed
    {
        return [];
    }

    public function mapIds($results): Collection
    {
        return collect();
    }

    public function map(Builder $builder, $results, $model): Illuminate\Database\Eloquent\Collection
    {
        return $model->newCollection();
    }

    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        return LazyCollection::empty();
    }

    public function getTotalCount($results): int
    {
        return 0;
    }

    public function createIndex($name, array $options = []): mixed
    {
        return null;
    }

    public function deleteIndex($name): mixed
    {
        return null;
    }
}

beforeEach(function (): void {
    $this->engine = new InMemoryScoutEngine;
    config()->set('vellum.search.driver', 'scout');
    config()->set('scout.driver', 'memory');
    $this->app->singleton(EngineManager::class, fn ($app): EngineManager => new EngineManager($app));
    // Laravel 13 binds an extend() callback to the manager, so $this inside
    // it is not the test.
    $engine = $this->engine;
    $this->app->make(EngineManager::class)->extend('memory', fn (): Engine => $engine);
});

it('drops the public record of a page that moved behind a gate', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    $this->writeDoc('plans.md', "---\ntitle: Plans\n---\nThe acquisition plan");

    $this->artisan('vellum:build')->assertSuccessful();

    expect($this->engine->records)->toHaveKey(':plans');

    unlink($this->docsPath().'/plans.md');
    $this->writeDoc('internal/meta.json', json_encode(['access' => 'auth'], JSON_THROW_ON_ERROR));
    $this->writeDoc('internal/plans.md', "---\ntitle: Plans\n---\nThe acquisition plan");

    $this->artisan('vellum:build')->assertSuccessful();

    $guest = array_filter($this->engine->records, static fn (array $record): bool => $record['access'] === 'guest');

    expect($this->engine->records)->not->toHaveKey(':plans')
        ->and($this->engine->records)->toHaveKey(':internal/plans')
        ->and(array_column($guest, 'title'))->not->toContain('Plans');
});

it('keeps every version in the index when one version is rebuilt', function (): void {
    config()->set('vellum.versions.enabled', true);
    config()->set('vellum.versions.list', ['v1', 'v2']);
    $this->writeDoc('v1/index.md', "---\ntitle: One\n---\nFirst");
    $this->writeDoc('v2/index.md', "---\ntitle: Two\n---\nSecond");

    $this->artisan('vellum:index', ['--docs-version' => 'v2'])->assertSuccessful();

    expect(array_column($this->engine->records, 'version'))->toContain('v1')->toContain('v2');
});

it('answers a search from the engine, leaving out what the reader cannot open', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nDeploy notes");
    $this->writeDoc('ops.md', "---\ntitle: Ops\naccess: auth\n---\nDeploy runbook");

    $this->artisan('vellum:build')->assertSuccessful();

    $titles = collect($this->get('/docs/_vellum/search.json?q=deploy')->assertOk()->json('documents'))->pluck('title')->all();

    expect($titles)->toContain('Home')
        ->and($titles)->not->toContain('Ops');

    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Ada']));

    $titles = collect($this->get('/docs/_vellum/search.json?q=deploy')->assertOk()->json('documents'))->pluck('title')->all();

    expect($titles)->toContain('Ops');
});
