<?php

declare(strict_types=1);

use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Vellum\Http\Controllers\LlmsTxtController;
use Vellum\Http\RootLlmsTxtRoutes;

/**
 * @return list<RoutingRoute>
 */
function routesFor(Router $router, string $uri): array
{
    return array_values(array_filter(
        $router->getRoutes()->get('GET'),
        static fn (RoutingRoute $route): bool => $route->uri() === $uri,
    ));
}

it('serves both files at the site root as well', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\ndescription: Start here.\n---\nHi");

    $index = $this->get('/llms.txt')->assertOk();
    $full = $this->get('/llms-full.txt')->assertOk();

    expect($index->headers->get('Content-Type'))->toBe('text/plain; charset=UTF-8')
        ->and($index->getContent())->toBe($this->get('/docs/llms.txt')->getContent())
        ->and($full->getContent())->toBe($this->get('/docs/llms-full.txt')->getContent());
});

it('404s the root files when agents.llms_txt_root is off', function (): void {
    config()->set('vellum.agents.llms_txt_root', false);
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->get('/llms.txt')->assertNotFound();
    $this->get('/llms-full.txt')->assertNotFound();
    $this->get('/docs/llms.txt')->assertOk();
});

it('404s the root files when agents.llms_txt is off', function (): void {
    config()->set('vellum.agents.llms_txt', false);
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    $this->get('/llms.txt')->assertNotFound();
});

it('does not register the root routes when the key is off at boot', function (): void {
    config()->set('vellum.agents.llms_txt_root', false);
    $router = new Router(new Dispatcher, $this->app);

    RootLlmsTxtRoutes::register($router);

    expect(routesFor($router, 'llms.txt'))->toBe([]);
});

it('leaves a path the app already routes alone', function (): void {
    $router = new Router(new Dispatcher, $this->app);
    $router->get('/llms.txt', static fn (): string => 'host');

    RootLlmsTxtRoutes::register($router);

    $index = routesFor($router, 'llms.txt');
    $full = routesFor($router, 'llms-full.txt');

    expect($index)->toHaveCount(1)
        ->and($index[0]->getActionName())->toBe('Closure')
        ->and($full)->toHaveCount(1)
        ->and(ltrim($full[0]->getActionName(), '\\'))->toBe(LlmsTxtController::class.'@rootFull');
});

it('lets an app route registered later win', function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");

    Route::get('/llms.txt', static fn (): string => 'host');

    expect($this->get('/llms.txt')->assertOk()->getContent())->toBe('host');
});

it('puts the root routes on the docs domain', function (): void {
    config()->set('vellum.route.domain', 'docs.example.com');
    $router = new Router(new Dispatcher, $this->app);

    RootLlmsTxtRoutes::register($router);

    expect(routesFor($router, 'llms.txt')[0]->getDomain())->toBe('docs.example.com')
        ->and(routesFor($router, 'llms-full.txt')[0]->getDomain())->toBe('docs.example.com');
});

it('treats an app route on another domain as free', function (): void {
    config()->set('vellum.route.domain', 'docs.example.com');
    $router = new Router(new Dispatcher, $this->app);
    $router->domain('shop.example.com')->get('/llms.txt', static fn (): string => 'shop');

    RootLlmsTxtRoutes::register($router);

    expect(routesFor($router, 'llms.txt'))->toHaveCount(2);
});
