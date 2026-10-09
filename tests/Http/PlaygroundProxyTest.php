<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Vellum\Content\ContentRepository;
use Vellum\Http\PlaygroundProxy;

require_once dirname(__DIR__).'/OpenApi/helpers.php';

function proxyReady(string $cachePath, array $config = [], array $spec = []): void
{
    enableOpenApi($cachePath, apiSpec($spec), ['proxy' => true, ...$config]);
    ContentRepository::fromConfig()->buildAll();
}

beforeEach(function (): void {
    $this->writeDoc('index.md', "---\ntitle: Home\n---\nHi");
    Http::preventStrayRequests();

    $this->forward = fn (array $payload) => $this
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->postJson('/docs/_vellum/playground', $payload);
});

it('is not there unless it was turned on', function (): void {
    enableOpenApi($this->cachePath(), apiSpec());
    ContentRepository::fromConfig()->buildAll();

    ($this->forward)(['method' => 'GET', 'url' => 'https://api.example.com/pets'])->assertNotFound();

    expect((string) $this->get('/docs/api')->getContent())->not->toContain('data-vellum-playground-proxy');
});

it('is not there without a reference, or without the playground', function (array $config): void {
    proxyReady($this->cachePath(), $config);

    ($this->forward)(['method' => 'GET', 'url' => 'https://api.example.com/pets'])->assertNotFound();
})->with([
    'reference off' => [['enabled' => false]],
    'playground off' => [['playground' => false]],
]);

it('is not there for a reader who may not open the reference', function (): void {
    proxyReady($this->cachePath(), ['access' => 'auth']);

    ($this->forward)(['method' => 'GET', 'url' => 'https://api.example.com/pets'])->assertNotFound();
});

it('names itself on the page when it is on', function (): void {
    proxyReady($this->cachePath());

    expect((string) $this->get('/docs/api')->assertOk()->getContent())
        ->toContain('data-vellum-playground-proxy="/docs/_vellum/playground"');
});

it('sits behind the docs middleware, so a post needs the proof any post does', function (): void {
    proxyReady($this->cachePath());

    $route = Route::getRoutes()->getByName('vellum.playground.proxy');

    // Laravel does not check the token while tests run, so this checks that
    // the group which does the checking is on the route.
    expect($route->gatherMiddleware())->toContain('web', 'throttle:60,1')
        ->and($route->methods())->toBe(['POST']);
});

it('takes JSON only, which another site cannot post from a visitor\'s browser', function (): void {
    proxyReady($this->cachePath());
    Http::fake();

    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->post('/docs/_vellum/playground', ['method' => 'GET', 'url' => 'https://api.example.com/pets'])
        ->assertStatus(415);

    Http::assertNothingSent();
});

it('sends the request and gives back an account of the answer', function (): void {
    proxyReady($this->cachePath());

    Http::fake(['api.example.com/*' => Http::response(
        '{"id":7}',
        201,
        ['Content-Type' => 'application/json', 'X-Request-Id' => 'abc', 'Set-Cookie' => 'session=secret'],
    )]);

    $response = ($this->forward)([
        'method' => 'post',
        'url' => 'https://api.example.com/pets?dry=1',
        'headers' => ['Authorization' => 'Bearer t', 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
        'body' => '{"name":"Rex"}',
    ])->assertOk()->assertHeader('Cache-Control', 'no-store, private');

    expect($response->json('status'))->toBe(201)
        ->and($response->json('statusText'))->toBe('Created')
        ->and($response->json('body'))->toBe('{"id":7}')
        ->and($response->json('type'))->toBe('application/json')
        ->and($response->json('size'))->toBe(8)
        ->and($response->json('truncated'))->toBeFalse()
        // A cookie the API sets is not for this site's reader.
        ->and(array_column($response->json('headers'), 0))->toContain('X-Request-Id')->not->toContain('Set-Cookie');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.example.com/pets?dry=1'
        && $request->body() === '{"name":"Rex"}'
        && $request->header('Authorization') === ['Bearer t']
        && $request->header('Content-Type') === ['application/json']);
});

it('forwards nothing of the reader that the reader did not put in the request', function (): void {
    proxyReady($this->cachePath());
    Http::fake(['*' => Http::response('ok')]);

    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->withUnencryptedCookie('laravel_session', 'the-readers-session')
        ->withHeaders(['Referer' => 'https://docs.test/private', 'X-Forwarded-For' => '10.0.0.1'])
        ->postJson('/docs/_vellum/playground', [
            'method' => 'GET',
            'url' => 'https://api.example.com/pets',
            'headers' => [
                'Cookie' => 'a=b',
                'Host' => 'internal',
                'X-XSRF-TOKEN' => 't',
                'X-Forwarded-For' => '127.0.0.1',
                'Proxy-Authorization' => 'x',
                "X-Split\r\nInjected" => 'x',
                'X-Break' => "a\r\nInjected: yes",
                'X-Fine' => 'kept',
            ],
        ])->assertOk();

    Http::assertSent(function (Request $request): bool {
        $names = array_map(strtolower(...), array_keys($request->headers()));

        return in_array('x-fine', $names, true)
            && array_intersect($names, ['cookie', 'x-xsrf-token', 'x-forwarded-for', 'proxy-authorization', 'referer', 'x-break', 'injected']) === []
            && ! str_contains(json_encode($request->headers()), 'the-readers-session')
            && ! str_contains(json_encode($request->headers()), 'internal');
    });
});

it('goes only to the servers the spec names', function (string $url): void {
    proxyReady($this->cachePath(), [], ['servers' => [['url' => 'https://api.example.com/v2']]]);
    Http::fake();

    ($this->forward)(['method' => 'GET', 'url' => $url])
        ->assertStatus(422)
        ->assertJsonPath('error', 'That address is not one of the servers this API reference names.');

    Http::assertNothingSent();
})->with([
    'another host' => ['https://evil.test/v2/pets'],
    'this machine' => ['http://127.0.0.1/v2/pets'],
    'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
    'another scheme' => ['http://api.example.com/v2/pets'],
    'another port' => ['https://api.example.com:8443/v2/pets'],
    'a host that only starts the same' => ['https://api.example.com.evil.test/v2/pets'],
    'the host as a username' => ['https://api.example.com@evil.test/v2/pets'],
    'a path beside the base' => ['https://api.example.com/v20/pets'],
    'a path above the base' => ['https://api.example.com/admin'],
    'a path that climbs out' => ['https://api.example.com/v2/../admin'],
    'a climb that is encoded' => ['https://api.example.com/v2/%2e%2e/admin'],
    'a climb that is encoded twice' => ['https://api.example.com/v2/%252e%252e/admin'],
    'a backslash' => ['https://api.example.com\\@evil.test/v2/pets'],
    'a file' => ['file:///etc/passwd'],
    'not an address' => ['pets'],
]);

it('reaches what is under a server the spec names', function (string $url): void {
    proxyReady($this->cachePath(), [], ['servers' => [['url' => 'https://api.example.com/v2/']]]);
    Http::fake(['*' => Http::response('ok')]);

    ($this->forward)(['method' => 'GET', 'url' => $url])->assertOk();
})->with([
    ['https://api.example.com/v2'],
    ['https://API.example.com/v2/pets/7?x=../y'],
    ['https://api.example.com:443/v2/pets%20and%20more'],
]);

it('counts an operation of its own server, and the configured one, among them', function (): void {
    proxyReady($this->cachePath(), ['base_url' => 'https://staging.example.com'], [
        'paths' => ['/pets' => ['get' => ['servers' => [['url' => 'https://files.example.com'], ['url' => '/relative'], ['url' => 'https://{region}.example.com']]]]],
    ]);

    $servers = ContentRepository::fromConfig()->find('api')->frontmatter['openapi_servers'];

    // A relative server is this origin, which the browser reaches itself.
    // One with a variable in its host is not an address yet.
    expect($servers)->toBe(['https://staging.example.com', 'https://api.example.com', 'https://files.example.com']);
});

it('shows a redirect and does not follow it', function (): void {
    proxyReady($this->cachePath());
    Http::fake(['*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/'])]);

    $response = ($this->forward)(['method' => 'GET', 'url' => 'https://api.example.com/pets'])->assertOk();

    expect($response->json('status'))->toBe(302);
    Http::assertSentCount(1);
});

it('refuses what the playground would not send', function (array $payload, int $status): void {
    proxyReady($this->cachePath());
    Http::fake();

    ($this->forward)($payload)->assertStatus($status);

    Http::assertNothingSent();
})->with([
    'a method of its own' => [['method' => 'CONNECT', 'url' => 'https://api.example.com/pets'], 422],
    'no address' => [['method' => 'GET'], 422],
    'a body that is not text' => [['method' => 'POST', 'url' => 'https://api.example.com/pets', 'body' => ['a' => 1]], 422],
    'a body too large' => [['method' => 'POST', 'url' => 'https://api.example.com/pets', 'body' => str_repeat('a', PlaygroundProxy::MAX_REQUEST + 1)], 413],
]);

it('reads no more of a response than it will show, and none of one that is not text', function (): void {
    proxyReady($this->cachePath());

    Http::fake([
        'api.example.com/big' => Http::response(str_repeat('a', PlaygroundProxy::MAX_RESPONSE + 5000), 200, ['Content-Type' => 'text/plain']),
        'api.example.com/image' => Http::response("\x89PNG\r\n", 200, ['Content-Type' => 'image/png']),
    ]);

    $big = ($this->forward)(['method' => 'GET', 'url' => 'https://api.example.com/big'])->assertOk();
    $image = ($this->forward)(['method' => 'GET', 'url' => 'https://api.example.com/image'])->assertOk();

    expect($big->json('truncated'))->toBeTrue()
        ->and(strlen($big->json('body')))->toBe(PlaygroundProxy::MAX_RESPONSE)
        ->and($image->json('body'))->toBeNull()
        ->and($image->json('type'))->toBe('image/png');
});

it('says so when the server does not answer', function (): void {
    proxyReady($this->cachePath());
    Http::fake(fn () => throw new ConnectionException('refused'));

    ($this->forward)(['method' => 'GET', 'url' => 'https://api.example.com/pets'])
        ->assertStatus(502)
        ->assertJsonPath('error', 'No response. The server may be down, or the address wrong.');
});

it('lets one reader send only so many a minute', function (): void {
    proxyReady($this->cachePath());
    Http::fake(['*' => Http::response('ok')]);

    foreach (range(1, 60) as $ignored) {
        ($this->forward)(['method' => 'GET', 'url' => 'https://api.example.com/pets'])->assertOk();
    }

    ($this->forward)(['method' => 'GET', 'url' => 'https://api.example.com/pets'])->assertStatus(429);
});
