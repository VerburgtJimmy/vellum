# Contributing

Thanks for helping out. For anything larger than a small fix, please open an issue first so
we can agree on the approach before you write the code.

## Setup

```bash
composer install
npm ci
```

You need PHP 8.4. CI runs the test suite on Laravel 11, 12 and 13.

## Layout

This repository holds two Composer packages, released together under one version.

- `packages/core` is `jimmyverburgt/vellum-core`: content, frontmatter, navigation,
  versions, gating, the Markdown pipeline and its component views, the compiled cache,
  search and the changelog, with the `vellum:build`, `vellum:index` and `vellum:clear`
  commands. It has no routes, layout, styles or scripts.
- The root is `jimmyverburgt/vellum`, the docs site built on core: routes, controllers,
  layout, theme, assets, and the install and export commands. It includes core through
  `replace`, so installing it never pulls in a second copy.

Both share the `Vellum\` namespace, and the tests for both live in `tests/`. Core must
not use anything from the root package; `tests/Core/BoundaryTest.php` fails if it does.
A view both packages ship, such as `components/tabs.blade.php`, renders in the root
package's version.

`.github/workflows/split.yml` copies `packages/core` to the read-only
`VerburgtJimmy/vellum-core` repository on every push to `master` and every tag. Changes to
core are made here.

## Checks

Run these before opening a pull request:

```bash
composer test
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=512M
npm run build
git diff --exit-code -- resources/dist
npm run check:size
```

The public site is a separate Laravel app (`vellum-site`) that installs this package and points `vellum.path` at `vendor/jimmyverburgt/vellum/docs`, so it serves `docs/` directly. Docs changes go live when a new package version is released and that app is updated. There is no static export to regenerate in this repo.

## Releasing

Maintainers only.

```bash
git tag v0.5.0
git push origin v0.5.0
```

Packagist reads the version from the Git tag, so do not add a `version` field to
either `composer.json`. Each repository only needs to be submitted to
[packagist.org/packages/submit](https://packagist.org/packages/submit) once: this one for
`jimmyverburgt/vellum`, and `VerburgtJimmy/vellum-core` for `jimmyverburgt/vellum-core`.
The same tag is split to `vellum-core`, so both packages release as one version.

Pushing the tag triggers `.github/workflows/release.yml`, which publishes a GitHub release
using the matching `## [x.y.z]` section of `CHANGELOG.md` as the notes. Write that section
before tagging. If it is missing, the release is still created with generated notes.

`.gitattributes` keeps tests, CI config and build tooling out of the released archive.
Before tagging, check any new files at the repo root against it.

The test suite does not run the site in a browser, so check these by hand before tagging:

- Build and serve the docs, open search with `Ctrl+K` or `⌘K`, type a query, move through
  the results with the arrow keys and open one with Enter. The browser console should show
  no errors.
- Export the docs with `export.base_url` set to a path such as `/handbook/`, serve the
  export from that path, and repeat the search check.
- Run Lighthouse on a docs page, for mobile and desktop.

After the release, update `vellum-site` with `composer update jimmyverburgt/vellum`, so the
public docs and changelog show the new version.

## Style

Match the surrounding PHP, Blade and Markdown. Do not add agent instruction files (`.cursor/`, `AGENTS.md`, Copilot templates) to this repo.

## Conduct

Please be respectful. This project follows the [Contributor Covenant 2.1](CODE_OF_CONDUCT.md).
