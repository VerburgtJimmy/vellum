---
title: Why Vellum
description: In-app docs versus a second documentation project.
---

Most Laravel products eventually grow a docs site. The usual split is a second codebase: a Next.js app, a Mintlify project, or a Wiki that does not know your routes, gates, or config.

Vellum stays in the app.

## Same deploy

Markdown lives in `resources/docs`. `vellum:build` runs next to the rest of the release. There is no extra Node app to host, no separate auth cookie, and no copy of your domain model to keep in sync.

## Same application

Value tags read `env`, `config`, and named routes through an allowlist. Gated pages use Laravel gates and `auth`. Version folders are still your Markdown, just namespaced. The docs can show the billing URL that is actually registered, not a string you pasted last quarter.

## When a second project is still right

Export a static tree with `vellum:export` if the public site must live on GitHub Pages or Cloudflare Workers. That snapshot is a build artifact, not a second source of truth.

If the docs must be edited by people who should never open the Laravel repo, Vellum is the wrong shape. This package is for teams that already ship PHP.

## What 0.5 commits to

Config keys and frontmatter names are frozen. The authoring syntax (`:::`, `<x-…>`, allowlisted value tags) is frozen. Layout chrome can still get bug fixes. See [Upgrade](/docs/getting-started/upgrade).
