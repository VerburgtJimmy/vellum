# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-09-11

Initial public release. Pre-1.0: the API may change.

### Added

- Laravel package that serves documentation from Markdown (`resources/docs` by default); supports Laravel 11, 12, and 13
- `vellum:install` to publish config, starter stubs, and public dist assets
- `vellum:build` / `vellum:clear` for an OPcache-friendly compile cache
- Markdown extensions: callouts, tabs, steps, cards, fenced code blocks, inline code highlighting, images, external links, heading anchors
- Docs UI: sidebar navigation, table of contents, breadcrumbs, search (MiniSearch), light/dark/system theme
- Optional version folders with latest redirect and version switcher
- `vellum:export` for static HTML output suitable for GitHub Pages or any static host
- Config surface in `config/vellum.php` (path, routes, versions, theme, search, cache, export)
- GitHub Actions CI (Pest on Laravel 11/12/13, Pint, PHPStan, frontend build / size gate)
- Demo site workflow that exports package stubs to GitHub Pages

### Publishing note

Tag `v0.1.0` and push the tag, then submit `https://github.com/VerburgtJimmy/vellum` on Packagist if the package is not listed yet. Do not hardcode `"version"` in `composer.json`.

[0.1.0]: https://github.com/VerburgtJimmy/vellum/releases/tag/v0.1.0
