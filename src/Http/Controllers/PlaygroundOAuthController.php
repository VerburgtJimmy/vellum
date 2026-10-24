<?php

declare(strict_types=1);

namespace Vellum\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vellum\OpenApi\Playground;

/**
 * The address an OAuth provider sends a reader back to, for the playground
 * that sent them. It is the same for every operation, so it is the one to
 * register with the provider.
 */
final class PlaygroundOAuthController extends Controller
{
    public function __invoke(): Response
    {
        if (! (bool) config('vellum.openapi.enabled') || ! Playground::enabled()) {
            abort(404);
        }

        return response()
            ->view('vellum::pages.oauth-callback')
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
