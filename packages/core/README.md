# Vellum Core

The content engine behind [Vellum](https://github.com/VerburgtJimmy/vellum), Markdown documentation for Laravel. It reads a folder of Markdown and gives you pages, navigation, versions, access rules, a search index and the changelog. Serving them is left to you.

Most apps want the full package, which adds the docs site: routes, layout, theme, search dialog and static export.

```bash
composer require jimmyverburgt/vellum
```

Require core on its own when you render the docs yourself, for example in an Inertia or Livewire front end, or when a package of yours reads the docs:

```bash
composer require jimmyverburgt/vellum-core
```

See [Core on its own](https://github.com/VerburgtJimmy/vellum/blob/master/docs/core.md) in the docs.

This repository is a read-only copy of `packages/core` in [VerburgtJimmy/vellum](https://github.com/VerburgtJimmy/vellum). Open issues and pull requests there.

## License

MIT. See [LICENSE.md](LICENSE.md).
