<?php

declare(strict_types=1);

namespace Vellum\Http;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Vellum\Http\Controllers\LlmsTxtController;

/**
 * /llms.txt and /llms-full.txt at the site root, where agents look first.
 *
 * The site root belongs to the host app, so these only go in when the app
 * has not claimed the path itself. The service provider calls this once the
 * app has booted, after the app's own route files have loaded, so an app
 * route is already in the table and can be seen. An app route registered
 * later still wins: the router keys routes by domain and URI, and a second
 * route for the same key replaces the first.
 */
final class RootLlmsTxtRoutes
{
    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const ROUTES = [
        'llms.txt' => ['rootIndex', 'vellum.llms.root'],
        'llms-full.txt' => ['rootFull', 'vellum.llms.full.root'],
    ];

    public static function register(Router $router): void
    {
        if (! (bool) config('vellum.agents.llms_txt_root', true)) {
            return;
        }

        $domain = config('vellum.route.domain');
        $domain = is_string($domain) && $domain !== '' ? $domain : null;
        /** @var list<string> $middleware */
        $middleware = config('vellum.route.middleware', ['web']);

        foreach (self::ROUTES as $uri => [$action, $name]) {
            if (self::taken($router, $uri, $domain)) {
                continue;
            }

            $registrar = $router->middleware($middleware)->name($name);

            if ($domain !== null) {
                $registrar->domain($domain);
            }

            $registrar->get('/'.$uri, [LlmsTxtController::class, $action]);
        }
    }

    /**
     * Whether a GET route already answers this path on the docs domain. A
     * route without a domain answers on every domain, so it counts too.
     */
    private static function taken(Router $router, string $uri, ?string $domain): bool
    {
        foreach ($router->getRoutes()->get('GET') as $route) {
            /** @var Route $route */
            if ($route->uri() === $uri && in_array($route->getDomain(), [null, $domain], true)) {
                return true;
            }
        }

        return false;
    }
}
