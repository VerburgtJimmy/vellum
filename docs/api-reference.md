---
title: API reference
description: Reference pages generated from an OpenAPI spec, from a file or from Scramble.
---

Vellum can turn an OpenAPI 3.0, 3.1 or 3.2 spec into reference pages. Each operation gets a page of its own with its parameters, request body, responses and request samples, and the reference has an overview page that lists them all.

```php
'openapi' => [
    'enabled' => env('VELLUM_OPENAPI', false),
    'spec' => env('VELLUM_OPENAPI_SPEC'),
],
```

The pages live under `/docs/api`. They are ordinary pages to the rest of Vellum: search finds them, `vellum:export` writes them, and they are listed in `llms.txt` and the sitemap. Their [raw Markdown](/docs/page-actions#raw-markdown) is written for them, with fields as tables and samples as fenced code.

## What a page looks like

An operation's page opens with its request line, the method and the path. Under it, each part of the operation is a panel: authorization, the parameters by where they go, the request body and one panel per response. A nested object opens in place.

Beside the documentation are the request samples and an example of each response, as [code tabs](/docs/components/tabs#code-tabs). On a wide screen they stay in view while the reader scrolls and take the place of the table of contents. On a narrower one they follow the documentation.

The page's description is the opening paragraph of the operation's `description` in the spec. The rest of the description follows the request line.

Where one schema serves both the request and the response, each side shows its own part of it. A property marked `readOnly`, such as an id the server sets, is left out of the request body, its samples and the playground. One marked `writeOnly`, such as a password, is left out of the responses.

A value that can be one of several schemas shows each as a tab. With a `discriminator`, the page says which property decides, the value that picks each one, and which is the `defaultMapping`, and the examples carry that value.

Every field has an address made of the names that lead to it, such as `#body-captain-name` or `#response-200-data-id`. Its name is the link: copy it to point someone at one field.

## Where the spec comes from

### A file

Point `spec` at a JSON or YAML file:

```php
'spec' => base_path('openapi.yaml'),
```

In local development the reference follows the file: save it and reload the page. In production the pages are the ones `vellum:build` wrote, as for every page.

### Scramble, in the same app

With [`dedoc/scramble`](https://scramble.dedoc.co) installed and `spec` left as `null`, Vellum asks Scramble for the spec. Nothing else is needed:

```php
'openapi' => [
    'enabled' => true,
],
```

In local development the reference is built again when a PHP file under `app/` or `routes/` changes, so it follows your controllers and form requests. Set `watch` if the code Scramble reads lives somewhere else:

```php
'watch' => [base_path('app'), base_path('routes'), base_path('modules')],
```

Set `scramble` to `false` to never ask Scramble, even when it is installed.

Scramble has a viewer of its own at `/docs/api`, which is also where the reference lives when Vellum is served under `/docs`. Vellum's pages answer there. To keep Scramble's viewer as well, give it another address in a service provider:

```php
use Dedoc\Scramble\Scramble;

Scramble::registerUiRoute('scramble');
Scramble::registerJsonSpecificationRoute('scramble.json');
```

The servers in the spec come from `APP_URL`, so the playground and the samples send requests to the address set there.

### Scramble, in another project

When the docs are a project of their own, export the spec where the API lives and bring the file over. In the API's project:

```bash
php artisan scramble:export --path=openapi.json
```

Copy `openapi.json` into the docs project and point `spec` at it:

```php
'spec' => base_path('openapi.json'),
```

The docs project does not need Scramble. To keep the two in step, run the export in the API's CI and commit the file to the docs project, or download it there before `vellum:build`.

The same works for a spec from any other tool.

## The sidebar

By default the reference has a sidebar to itself. A switch at the top of the sidebar leads from the docs to the reference and back, and the previous and next links stay inside the one the reader is in.

To list the reference as one more folder at the end of the docs' sidebar instead:

```php
'sidebar' => 'docs',
```

Either way the operations are grouped in a folder per tag, in the order of the spec's `tags` list, with the method beside each title. Set `group_by` to `path` to group by the first segment of the path instead. Operations with no tag go in a folder named by `untagged_label`.

A tag can sit inside another, and its folder then sits inside the other's, on the overview page as well as in the sidebar:

```yaml
tags:
  - name: fleet
    summary: Fleet
  - name: ships
    summary: Ships
    parent: fleet
```

- `summary` is the name a reader sees. Without it the tag's `name` is used.
- `parent` names the tag it sits in. A parent with no operations of its own is listed when a tag inside it has some.
- A tag whose `kind` is anything but `nav`, such as `badge`, says something about an operation but is not where it is listed. The operation goes in its first tag that is for navigation.

`summary`, `parent` and `kind` are OpenAPI 3.2. A spec written for Redoc gets the same from `x-displayName` and `x-tagGroups`.

## Request samples

Every operation has a request sample per language in `samples`. `curl`, `php` (the Laravel HTTP client), `javascript` (fetch) and `python` (requests) are built in. The samples use the spec's first server, or `base_url` when you set it. A server with variables, such as `https://{region}.api.example.com`, is filled in with their defaults. The playground offers each value a variable's `enum` allows, and so does the forwarding route; a variable without an `enum` is only ever its default.

Samples the spec writes itself, under `x-codeSamples` as Redoc reads them, come first:

```yaml
x-codeSamples:
  - lang: curl
    label: cURL
    source: curl https://api.example.com/ships --json @ship.json
  - lang: Go
    source: client.Ships.Register(ctx, ship)
```

A written sample in a language Vellum also generates, such as `curl`, `php`, `javascript` or `python`, takes the place of the generated one. Any other language is added as a tab of its own.

## Webhooks and callbacks

Each of the spec's `webhooks` gets a page in a **Webhooks** folder, after the operations. A webhook is a request the API sends to an address the reader gives it, so its page shows what arrives: the headers, the payload with an example beside it, and what to answer with. It has no request samples and no playground, and the spec's top-level `security` does not apply to it.

An operation's `callbacks` are listed on its own page, under **Callbacks**: the address the API calls, what it sends and the statuses to answer with.

## The playground

Every operation's page has a **Try it** button on its request line. It opens a drawer beside the page, so the operation stays in view while a reader fills in the request, sends it and reads the response.

- The parameters and the body are a form made from the spec. A JSON body can be written field by field or as JSON, and what is written carries over when the reader switches. An object inside the body has a field for each of its own, named with the path to it, such as `author.name`. A list of objects is filled in an item at a time, with **Add item** and **Remove**; an item left empty is not sent.
- The command the request makes is shown as curl and follows what is typed.
- Bearer tokens, basic credentials and API keys are supported. For OAuth 2 and OpenID Connect the reader pastes a token. Credentials are kept for the browser tab and are gone when it closes.
- The response is shown as text, with its status, time, size and headers. It is never rendered as a page.

The request goes from the reader's own browser, which decides two things:

- When the API is on the same origin as the docs, which is the usual case for docs served by the app they describe, it goes as the reader. A reader who is signed in to the app is signed in to the request, and Laravel's CSRF token is sent with it.
- When the API is on another origin, nothing of the reader's is sent, and the API has to allow the docs' origin to read its answers ([CORS](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS)). Otherwise the browser shows no response and the playground says so.

### An API on another origin

When the API does not allow the docs' origin, turn on the forwarding route, and the playground sends such requests through your app instead:

```php
'proxy' => env('VELLUM_OPENAPI_PROXY', false),
```

It is off by default, because it makes your app send requests on a reader's behalf. With it on:

- Only the servers the spec names can be reached, and `base_url` when you set it. A server written as a path, such as `/api`, is your own origin and needs no forwarding.
- A server is reached only at a public address. One on this machine or a private network, such as `localhost`, `10.0.0.5` or a cloud's metadata service, is refused, whether the spec gives the address or a name that leads to it. The request goes to the address that was checked.
- A redirect is shown as the answer and is not followed.
- Nothing of the reader's session with your app is forwarded. No cookies go along, and the only headers that do are `Accept`, `Content-Type`, `Authorization` and those the spec declares as a header parameter or an API key. A header that says where a request came from, such as `X-Forwarded-For`, is never forwarded. The reader's credentials for the API do pass through your server on the way.
- It answers only a reader who may open the reference, behind the docs' [middleware](/docs/getting-started/configuration), and one reader can send 60 requests a minute.
- A file upload is not forwarded. A response is kept up to 2 MB and is given 30 seconds to arrive.

To forward to an API on a private network, as in local development against `http://localhost:8000`, allow it:

```php
'proxy_private' => env('VELLUM_OPENAPI_PROXY_PRIVATE', false),
```

Leave it off in production unless the API really is internal. With it on, anything the spec names on your network is reachable by every reader who may open the reference.

A static export has no app to forward through, so there the playground reaches only an API that allows its origin.

:::warning
A request from the playground is a real request. On a production API, **Try it** on a delete operation deletes.
:::

To leave the playground out:

```php
'playground' => false,
```

## Who may read it

`access` gates the whole reference, with the same values as a page's [`access` frontmatter](/docs/gating):

```php
'access' => 'auth',
```

A reader who may not open the reference is not shown the way to it.

## What a spec can and cannot do

- A description in the spec is Markdown, and Vellum's own directives work in it, a [callout](/docs/components/callouts) for one. Blade components do not: a spec is often someone else's text.
- A `$ref` to another file or a URL is not followed. `vellum:build` warns about each one.
- An operation with the `QUERY` method, or one of the API's own under `additionalOperations`, gets a page like any other. A query string described as one `querystring` parameter is listed as the query parameters it is made of when it is form-encoded.
- The playground cannot read a streamed response, such as server-sent events.
- With [versions](/docs/versions) on, the reference belongs to the latest version only. A spec describes the API as it is.
- A page you wrote yourself under `api/` is still served. A spec whose page would take the same address as one of yours stops the build.

## Configuration

| Key | Default | Purpose |
| --- | --- | --- |
| `openapi.enabled` | `false` | Turns the reference on. |
| `openapi.spec` | `null` | Path to a JSON or YAML spec. `null` asks Scramble. |
| `openapi.scramble` | `true` | Whether to ask Scramble when no spec is set. |
| `openapi.watch` | `null` | Directories whose PHP files Scramble reads. `null` watches `app/` and `routes/`. |
| `openapi.prefix` | `api` | The path segment the reference lives under. |
| `openapi.sidebar` | `separate` | `separate` for a sidebar of its own, `docs` for a folder in the docs' sidebar. |
| `openapi.title` | `API reference` | The reference's name in the sidebar and on its overview. |
| `openapi.group_by` | `tag` | `tag` or `path`. |
| `openapi.untagged_label` | `Other` | The folder for operations without a tag. |
| `openapi.samples` | `curl`, `php`, `javascript`, `python` | Languages of the request samples, in tab order. |
| `openapi.base_url` | `null` | Used in the samples in place of the spec's first server. |
| `openapi.playground` | `true` | Whether a reader can send a request from an operation's page. |
| `openapi.proxy` | `false` | Sends playground requests to another origin through your app. |
| `openapi.proxy_private` | `false` | Lets the forwarding route reach a server on this machine or a private network. |
| `openapi.access` | `guest` | Who may read the reference. |
