<?php

declare(strict_types=1);

/*
 * Built-in synonyms for search. Each group is a set of terms that mean the
 * same thing in documentation; a query term in a group is expanded to the
 * rest of it at half weight. Lowercase, and a term belongs to one group only.
 *
 * Keep this list short and generic. A word that means something else in
 * most docs does not belong here, and a page can add its own terms with
 * aliases: in its frontmatter.
 */

return [
    ['env', 'dotenv', '.env', 'environment variable'],
    ['auth', 'authentication', 'logged in', 'login', 'signed in'],
    ['config', 'configuration', 'settings'],
    ['dark mode', 'dark theme', 'night mode'],
    ['install', 'installation', 'setup', 'set up'],
    ['deploy', 'deployment'],
    ['upgrade', 'upgrading', 'migration guide'],
    ['sidebar', 'navigation', 'nav', 'menu'],
    ['image', 'images', 'picture', 'screenshot'],
    ['crawler', 'crawlers', 'bot', 'bots'],
    ['changelog', 'release notes'],
    ['feed', 'rss', 'atom'],
    ['callout', 'callouts', 'admonition'],
    ['code block', 'code blocks', 'snippet', 'fenced code'],
    ['error', 'exception'],
    ['homepage', 'landing page'],
    ['stylesheet', 'css'],
    ['frontmatter', 'front matter', 'yaml header'],
    ['table of contents', 'toc', 'on this page'],
    ['licence', 'license'],
];
