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

After docs or Blade chrome changes, regenerate the demo export:

```bash
bash bin/build-demo
```

`demo-dist` is committed. Cloudflare Workers deploys that folder.

## Style

Match the surrounding PHP, Blade, and Markdown. Do not add agent instruction files (`.cursor/`, `AGENTS.md`, Copilot templates) to this repo.

## Conduct

Be decent. [Contributor Covenant](https://www.contributor-covenant.org/version/2/1/code_of_conduct/) is the expected bar.
