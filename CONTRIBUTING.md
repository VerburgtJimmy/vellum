# Contributing

Open an issue before a large pull request so the work is not a surprise.

## Setup

```bash
composer install
npm ci
```

PHP 8.4. Laravel 11, 12, and 13 are covered in CI.

## Checks

```bash
composer test
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=512M
npm run build
git diff --exit-code -- resources/dist
npm run check:size
```

The public site is a separate Laravel app (`vellum-site`) that installs this package and points `vellum.path` at `vendor/jimmyverburgt/vellum/docs`, so it serves `docs/` live. Docs changes ship by releasing the package and updating that app. There is no static export to regenerate in this repo.

## Releasing

Maintainers only.

```bash
git tag v0.5.0
git push origin v0.5.0
```

Packagist reads the Git tag. Do not add a `version` field to `composer.json`. The repo is
submitted once at [packagist.org/packages/submit](https://packagist.org/packages/submit).

Pushing the tag triggers `.github/workflows/release.yml`, which publishes a GitHub release
using the matching `## [x.y.z]` section of `CHANGELOG.md` as the notes. Write that section
before tagging. Without one the release is still created, from generated notes.

`.gitattributes` keeps tests, CI config and build tooling out of the released archive, so
check anything new at the repo root against it before tagging.

## Style

Match the surrounding PHP, Blade, and Markdown. Do not add agent instruction files (`.cursor/`, `AGENTS.md`, Copilot templates) to this repo.

## Conduct

Be decent. See [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md), which is Contributor Covenant 2.1.
