<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vellum\Support\Assets;

/**
 * Serves compiled package assets when they are not published to public/.
 */
final class AssetController extends Controller
{
    public function css(Request $request): Response
    {
        return $this->file($request, Assets::cssPath(), 'text/css; charset=UTF-8');
    }

    public function js(Request $request): Response
    {
        return $this->file($request, Assets::jsPath(), 'text/javascript; charset=UTF-8');
    }

    public function search(Request $request): Response
    {
        return $this->file($request, Assets::searchJsPath(), 'text/javascript; charset=UTF-8');
    }

    public function anchor(Request $request): Response
    {
        return $this->file($request, Assets::anchorJsPath(), 'text/javascript; charset=UTF-8');
    }

    public function focus(Request $request): Response
    {
        return $this->file($request, Assets::focusJsPath(), 'text/javascript; charset=UTF-8');
    }

    private function file(Request $request, string $path, string $contentType): Response
    {
        if (! is_file($path)) {
            abort(404);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            abort(404);
        }

        $headers = [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Vary' => 'Accept-Encoding',
        ];

        $acceptsGzip = str_contains(strtolower($request->header('Accept-Encoding', '')), 'gzip');

        if ($acceptsGzip) {
            $encoded = gzencode($contents, 9);

            if ($encoded !== false) {
                $headers['Content-Encoding'] = 'gzip';

                return response($encoded, 200, $headers);
            }
        }

        return response($contents, 200, $headers);
    }
}
