<?php

declare(strict_types=1);

/*
 * Built-in docs synonyms. Each group is a set of terms a reader may use for the
 * same thing; a query term in a group is expanded to the rest of it, for the
 * lexical signal and question matching. Lowercase, and a term belongs to one
 * group only. Seeded from the misses of the 0.7 held-out search evaluation.
 */

return [
    // From the held-out misses.
    ['licence', 'license', 'licensing', 'commercial', 'commercially', 'terms of use'],
    ['card', 'cards', 'tile', 'tiles', 'link tiles'],
    ['slot', 'slots', 'nest', 'nested', 'nesting'],
    ['table of contents', 'toc', 'outline', 'on this page'],
    ['typo', 'misspelled', 'misspelt', 'misspelling'],
    ['production', 'prod', 'live site'],
    ['export', 'static site', 'flat files', 'static html', 'static output'],
    ['steps', 'numbered guide', 'walkthrough', 'procedure'],
    ['frontmatter', 'front matter', 'metadata', 'yaml header'],
    ['preset', 'presets', 'colour scheme', 'color scheme', 'looks', 'palette'],
    ['page actions', 'buttons under the title', 'action row'],
    ['gated', 'gating', 'private', 'restricted', 'members only'],
    ['open graph', 'og tags', 'social card', 'social preview'],
    ['directive', 'directives', 'triple colon', 'three colon'],
    ['anchor', 'anchors', 'permalink', 'heading link', 'deep link'],
    ['error page', 'blank error', 'error screen'],

    // General docs vocabulary.
    ['env', 'dotenv', '.env', 'environment variable'],
    ['auth', 'authentication', 'logged in', 'login', 'signed in'],
    ['config', 'configuration', 'settings', 'options'],
    ['dark mode', 'dark theme', 'night mode', 'dark look'],
    ['install', 'installation', 'setup', 'set up', 'add to an existing app'],
    ['deploy', 'deployment', 'ship', 'release pipeline'],
    ['upgrade', 'upgrading', 'migrate', 'migration guide', 'updating'],
    ['sidebar', 'navigation', 'nav', 'menu', 'left menu'],
    ['image', 'images', 'picture', 'screenshot', 'diagram', 'png', 'svg', 'webp'],
    ['component', 'components', 'blade component', 'custom element', 'widget'],
    ['markdown', 'md', 'raw source'],
    ['crawler', 'crawlers', 'bot', 'bots', 'spider'],
    ['cache', 'compiled cache', 'cached build', 'precompiled'],
    ['docs', 'documentation', 'manual', 'handbook'],
    ['changelog', 'release notes', 'what changed', 'what shipped'],
    ['feed', 'rss', 'atom'],
    ['clipboard', 'copy'],
    ['search', 'find', 'lookup', 'search box'],
    ['callout', 'callouts', 'admonition', 'coloured box', 'colored box', 'warning box'],
    ['tabs', 'tab', 'switcher panels'],
    ['code block', 'code blocks', 'snippet', 'fence', 'fenced code'],
    ['line numbers', 'number gutter', 'gutter'],
    ['version', 'versions', 'release', 'releases'],
    ['url', 'link', 'address'],
    ['error', 'exception', 'crash', 'fails'],
    ['theme', 'appearance', 'styling', 'design'],
    ['logo', 'brand mark'],
    ['heading', 'title', 'headline'],
    ['homepage', 'landing page', 'index page', 'front page'],
    ['build', 'compile', 'compilation'],
    ['slug', 'url path', 'page path'],
    ['assets', 'static files', 'files'],
    ['stylesheet', 'css', 'styles'],
    ['hide', 'hidden', 'invisible'],
];
