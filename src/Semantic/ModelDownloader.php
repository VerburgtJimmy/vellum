<?php

declare(strict_types=1);

namespace Vellum\Semantic;

use RuntimeException;

/**
 * Fetches a Model2Vec model (config, tokenizer, weights) from the Hugging Face
 * hub into a directory, streaming to disk. A manifest records what was fetched,
 * so a second run with the files intact does nothing.
 */
final class ModelDownloader
{
    public const FILES = ['config.json', 'tokenizer.json', 'model.safetensors'];

    public const MANIFEST = 'manifest.json';

    public function __construct(private readonly string $baseUrl = 'https://huggingface.co') {}

    /**
     * @return array{repository: string, directory: string, licence: string, files: array<string, int>, fetched: bool}
     */
    public function fetch(string $repository, string $directory, bool $force = false): array
    {
        $repository = str_contains($repository, '/') ? $repository : 'minishlab/'.$repository;
        $existing = $force ? null : $this->intact($repository, $directory);

        if ($existing !== null) {
            return [...$existing, 'directory' => $directory, 'fetched' => false];
        }

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}");
        }

        $licence = $this->licence($repository);
        $files = [];

        foreach (self::FILES as $file) {
            $files[$file] = $this->download("{$this->baseUrl}/{$repository}/resolve/main/{$file}", $directory.'/'.$file);
        }

        new SafetensorsTable($directory.'/model.safetensors');

        $manifest = ['repository' => $repository, 'licence' => $licence, 'files' => $files];
        file_put_contents($directory.'/'.self::MANIFEST, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        return [...$manifest, 'directory' => $directory, 'fetched' => true];
    }

    /**
     * @return array{repository: string, licence: string, files: array<string, int>}|null
     */
    private function intact(string $repository, string $directory): ?array
    {
        $path = $directory.'/'.self::MANIFEST;
        $manifest = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($manifest) || ($manifest['repository'] ?? null) !== $repository || ! is_array($manifest['files'] ?? null)) {
            return null;
        }

        $files = [];

        foreach (self::FILES as $file) {
            $size = $manifest['files'][$file] ?? null;

            if (! is_int($size) || ! is_file($directory.'/'.$file) || filesize($directory.'/'.$file) !== $size) {
                return null;
            }

            $files[$file] = $size;
        }

        return ['repository' => $repository, 'licence' => (string) ($manifest['licence'] ?? 'unknown'), 'files' => $files];
    }

    private function licence(string $repository): string
    {
        $json = @file_get_contents("{$this->baseUrl}/api/models/{$repository}", context: $this->context());

        if ($json === false) {
            throw new RuntimeException("Cannot find the model {$repository}");
        }

        $info = json_decode($json, true);
        $licence = is_array($info) ? ($info['cardData']['license'] ?? null) : null;

        return is_string($licence) ? $licence : 'unknown';
    }

    private function download(string $url, string $target): int
    {
        $source = @fopen($url, 'rb', context: $this->context());

        if ($source === false) {
            throw new RuntimeException("Cannot download {$url}");
        }

        $partial = $target.'.part';
        $sink = fopen($partial, 'wb');

        if ($sink === false) {
            fclose($source);

            throw new RuntimeException("Cannot write {$partial}");
        }

        $bytes = stream_copy_to_stream($source, $sink);
        fclose($source);
        fclose($sink);

        if ($bytes === false || $bytes === 0) {
            @unlink($partial);

            throw new RuntimeException("Downloaded nothing from {$url}");
        }

        rename($partial, $target);

        return $bytes;
    }

    /**
     * @return resource
     */
    private function context()
    {
        return stream_context_create(['http' => [
            'header' => "User-Agent: vellum\r\n",
            'follow_location' => 1,
            'max_redirects' => 10,
            'timeout' => 60,
        ]]);
    }
}
