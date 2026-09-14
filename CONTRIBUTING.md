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

The public site is a separate Laravel app (`vellum-site`) that installs this package and serves `docs/` live. There is no static export to regenerate in this repo.

## Style

Match the surrounding PHP, Blade, and Markdown. Do not add agent instruction files (`.cursor/`, `AGENTS.md`, Copilot templates) to this repo.

## Conduct

Be decent. [Contributor Covenant](https://www.contributor-covenant.org/version/2/1/code_of_conduct/) is the expected bar.
