# Vellum OpenAPI

The API reference for [Vellum](https://github.com/VerburgtJimmy/vellum), Markdown documentation for Laravel. It turns an OpenAPI 3.0, 3.1 or 3.2 spec, from a file or from Scramble, into reference pages: one per operation, with parameters, bodies, responses and request samples, stored beside the docs' own pages so search, links and access treat them the same way.

Most apps want the full package, which includes this one and adds the playground that sends requests from the page:

```bash
composer require jimmyverburgt/vellum
```

Require it beside core when you render the docs yourself:

```bash
composer require jimmyverburgt/vellum-core jimmyverburgt/vellum-openapi
```

See [API reference](https://github.com/VerburgtJimmy/vellum/blob/master/docs/api-reference.md) and [Core on its own](https://github.com/VerburgtJimmy/vellum/blob/master/docs/core.md) in the docs.

This repository is a read-only copy of `packages/openapi` in [VerburgtJimmy/vellum](https://github.com/VerburgtJimmy/vellum). Open issues and pull requests there.

## License

MIT. See [LICENSE.md](LICENSE.md).
