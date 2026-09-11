<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vellum\Support\Assets;

/**
 * Serves compiled package assets when they are not published to public/.
 */
final class AssetController extends Controller
{
    public function css(): Response
    {
        return $this->file(Assets::cssPath(), 'text/css; charset=UTF-8');
    }

    public function js(): Response
    {
        return $this->file(Assets::jsPath(), 'text/javascript; charset=UTF-8');
    }

    private function file(string $path, string $contentType): Response
    {
        if (! is_file($path)) {
            abort(404);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            abort(404);
        }

        return response($contents, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
