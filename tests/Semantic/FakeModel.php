<?php

declare(strict_types=1);

namespace Vellum\Tests\Semantic;

/**
 * A model directory over the parity fixture's vocabulary with deterministic
 * random vectors, laid out the way vellum:model leaves a real one.
 */
final class FakeModel
{
    public static function write(string $directory, int $dims = 8): void
    {
        $fixture = json_decode((string) file_get_contents(__DIR__.'/../fixtures/semantic/wordpiece-parity.json'), true, flags: JSON_THROW_ON_ERROR);
        $rows = max($fixture['vocab']) + 1;

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($directory.'/tokenizer.json', json_encode(['model' => [
            'type' => 'WordPiece',
            'unk_token' => $fixture['unknown'],
            'vocab' => $fixture['vocab'],
        ]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        mt_srand(5);
        $data = '';

        for ($i = 0; $i < $rows * $dims; $i++) {
            $data .= pack('g', mt_rand(-1000, 1000) / 1000);
        }

        $header = json_encode(['embeddings' => ['dtype' => 'F32', 'shape' => [$rows, $dims], 'data_offsets' => [0, strlen($data)]]]);
        file_put_contents($directory.'/model.safetensors', pack('P', strlen((string) $header)).$header.$data);
        file_put_contents($directory.'/manifest.json', json_encode(['repository' => 'acme/fake-model', 'licence' => 'mit', 'files' => []]));
    }
}
