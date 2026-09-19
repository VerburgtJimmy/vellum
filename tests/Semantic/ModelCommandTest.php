<?php

declare(strict_types=1);

use Vellum\Semantic\ModelDownloader;
use Vellum\Semantic\SafetensorsTable;

beforeEach(function (): void {
    $this->hub = sys_get_temp_dir().'/vellum-hub-'.$this->fixtureId();
    $this->models = sys_get_temp_dir().'/vellum-models-'.$this->fixtureId();

    $this->publish = function (string $repository, string $weights, string $licence = 'mit'): void {
        @mkdir($this->hub.'/api/models/'.dirname($repository), 0755, true);
        @mkdir($this->hub.'/'.$repository.'/resolve/main', 0755, true);
        file_put_contents($this->hub.'/api/models/'.$repository, json_encode(['cardData' => ['license' => $licence]]));
        file_put_contents($this->hub.'/'.$repository.'/resolve/main/config.json', '{"hidden_dim": 2}');
        file_put_contents($this->hub.'/'.$repository.'/resolve/main/tokenizer.json', '{"model": {"vocab": {}}}');
        file_put_contents($this->hub.'/'.$repository.'/resolve/main/model.safetensors', $weights);
    };

    $this->app->instance(ModelDownloader::class, new ModelDownloader('file://'.$this->hub));
});

afterEach(function (): void {
    $this->deleteDirectory($this->hub);
    $this->deleteDirectory($this->models);
});

function safetensors(array $rows): string
{
    $dims = count($rows[0]);
    $data = '';

    foreach ($rows as $row) {
        $data .= pack('g*', ...$row);
    }

    $header = json_encode(['embeddings' => ['dtype' => 'F32', 'shape' => [count($rows), $dims], 'data_offsets' => [0, strlen($data)]]]);

    return pack('P', strlen($header)).$header.$data;
}

it('downloads the model files and reports the licence and sizes', function (): void {
    ($this->publish)('minishlab/tiny', safetensors([[1.0, 2.0], [3.0, 4.0]]));

    $this->artisan('vellum:model', ['--model' => 'tiny', '--path' => $this->models])
        ->expectsOutputToContain('Fetched minishlab/tiny')
        ->expectsOutputToContain('licence: mit')
        ->expectsOutputToContain('model.safetensors')
        ->assertSuccessful();

    $table = new SafetensorsTable($this->models.'/tiny/model.safetensors');

    expect($table->rows)->toBe(2)
        ->and($table->dims)->toBe(2)
        ->and($table->rows([1]))->toBe([1 => [3.0, 4.0]])
        ->and(is_file($this->models.'/tiny/'.ModelDownloader::MANIFEST))->toBeTrue();
});

it('does nothing when the files are already present, unless forced', function (): void {
    ($this->publish)('minishlab/tiny', safetensors([[1.0, 2.0]]));
    $this->artisan('vellum:model', ['--model' => 'tiny', '--path' => $this->models])->assertSuccessful();

    ($this->publish)('minishlab/tiny', safetensors([[5.0, 6.0]]));

    $this->artisan('vellum:model', ['--model' => 'tiny', '--path' => $this->models])
        ->expectsOutputToContain('Already present: minishlab/tiny')
        ->assertSuccessful();

    expect((new SafetensorsTable($this->models.'/tiny/model.safetensors'))->rows([0]))->toBe([[1.0, 2.0]]);

    $this->artisan('vellum:model', ['--model' => 'tiny', '--path' => $this->models, '--force' => true])
        ->expectsOutputToContain('Fetched minishlab/tiny')
        ->assertSuccessful();

    expect((new SafetensorsTable($this->models.'/tiny/model.safetensors'))->rows([0]))->toBe([[5.0, 6.0]]);
});

it('downloads again when a file has been damaged', function (): void {
    ($this->publish)('minishlab/tiny', safetensors([[1.0, 2.0]]));
    $this->artisan('vellum:model', ['--model' => 'tiny', '--path' => $this->models])->assertSuccessful();

    file_put_contents($this->models.'/tiny/model.safetensors', 'truncated');

    $this->artisan('vellum:model', ['--model' => 'tiny', '--path' => $this->models])
        ->expectsOutputToContain('Fetched minishlab/tiny')
        ->assertSuccessful();
});

it('fails clearly for a model that does not exist', function (): void {
    $this->artisan('vellum:model', ['--model' => 'missing', '--path' => $this->models])
        ->expectsOutputToContain('Cannot find the model minishlab/missing')
        ->assertFailed();
});

it('fails when the weights are not a safetensors table', function (): void {
    ($this->publish)('acme/broken', 'not a model');

    $this->artisan('vellum:model', ['--model' => 'acme/broken', '--path' => $this->models])
        ->expectsOutputToContain('is not a safetensors file')
        ->assertFailed();
});

it('refuses rows outside the table', function (): void {
    ($this->publish)('minishlab/tiny', safetensors([[1.0, 2.0]]));
    $this->artisan('vellum:model', ['--model' => 'tiny', '--path' => $this->models])->assertSuccessful();

    (new SafetensorsTable($this->models.'/tiny/model.safetensors'))->rows([1]);
})->throws(RuntimeException::class, 'outside a table of 1');
