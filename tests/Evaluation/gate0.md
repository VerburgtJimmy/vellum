# Gate 0 results

152 sections, 62 questions (32 literal, 30 paraphrase).

| Variant | R@1 | R@5 | Literal R@1 | Literal R@5 | Paraphrase R@1 | Paraphrase R@5 | Tokens | Size | Gzipped |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| BM25 | 52% | 66% | 78% | 94% | 23% | 37% |  |  |  |
| Questions | 29% | 44% | 47% | 66% | 10% | 20% |  |  |  |
| BM25 + questions | 34% | 50% | 53% | 75% | 13% | 23% |  |  |  |
| potion-retrieval-32M full | 39% | 69% | 66% | 97% | 10% | 40% | 63,091 | 126182 KB |  |
| potion-retrieval-32M pruned | 37% | 69% | 66% | 97% | 7% | 40% | 4,046 (1664 from docs) | 9336 KB | 8666 KB |
| potion-retrieval-32M pruned + PCA 64 | 37% | 61% | 62% | 94% | 10% | 27% | 4,046 | 1191 KB | 1094 KB |
| potion-retrieval-32M pruned + PCA 64 + int8 | 37% | 61% | 62% | 94% | 10% | 27% | 4,046 | 337 KB | 309 KB |
| potion-retrieval-32M pruned + PCA 96 | 35% | 63% | 59% | 94% | 10% | 30% | 4,046 | 1773 KB | 1636 KB |
| potion-retrieval-32M pruned + PCA 96 + int8 | 35% | 63% | 59% | 94% | 10% | 30% | 4,046 | 482 KB | 442 KB |
| BM25 + questions + potion-retrieval-32M full | 40% | 55% | 62% | 81% | 17% | 27% |  |  |  |
| BM25 + questions + potion-retrieval-32M pruned + PCA 64 + int8 | 42% | 53% | 66% | 78% | 17% | 27% |  |  |  |
| BM25 + questions + potion-retrieval-32M pruned + PCA 96 + int8 | 42% | 52% | 66% | 81% | 17% | 20% |  |  |  |
| potion-base-8M full | 45% | 76% | 69% | 97% | 20% | 53% | 29,528 | 29528 KB |  |
| potion-base-8M pruned | 47% | 73% | 69% | 94% | 23% | 50% | 4,014 (1665 from docs) | 4649 KB | 4309 KB |
| potion-base-8M pruned + PCA 64 | 37% | 66% | 66% | 94% | 7% | 37% | 4,014 | 1183 KB | 1085 KB |
| potion-base-8M pruned + PCA 64 + int8 | 37% | 66% | 66% | 94% | 7% | 37% | 4,014 | 334 KB | 305 KB |
| potion-base-8M pruned + PCA 96 | 40% | 69% | 66% | 97% | 13% | 40% | 4,014 | 1761 KB | 1623 KB |
| potion-base-8M pruned + PCA 96 + int8 | 40% | 71% | 66% | 97% | 13% | 43% | 4,014 | 479 KB | 436 KB |
| BM25 + questions + potion-base-8M full | 40% | 55% | 66% | 81% | 13% | 27% |  |  |  |
| BM25 + questions + potion-base-8M pruned + PCA 64 + int8 | 42% | 55% | 66% | 81% | 17% | 27% |  |  |  |
| BM25 + questions + potion-base-8M pruned + PCA 96 + int8 | 44% | 55% | 66% | 81% | 20% | 27% |  |  |  |

| Build step | Read rows | PCA fit | Quantise | Variance kept |
| --- | --- | --- | --- | --- |
| potion-retrieval-32M PCA 64 | 0.10 s | 20.52 s | 0.19 s | 3408.5584 |
| potion-retrieval-32M PCA 96 | 0.10 s | 26.97 s | 0.26 s | 4636.2644 |
| potion-base-8M PCA 64 | 0.06 s | 5.45 s | 0.20 s | 3202.0705 |
| potion-base-8M PCA 96 | 0.06 s | 7.51 s | 0.28 s | 4105.1327 |

## Paraphrase misses at 5, fused with the 32M int8 64 table

- composer thing to add it to an existing app (want getting-started/installation#require-the-package): why#what-living-in-the-app-adds, troubleshooting#value-tag-x-vellumenv-key-appkey-is-not-in-vellumcomponentsallowlist, why#what-05-commits-to
- i edited a md file, pushed, prod still shows the old text (want troubleshooting#editing-changes-nothing): writing/navigation#metamd, writing/images#formats-the-route-will-serve, writing/images#how-they-are-served
- everything is unstyled plain html, css 404 (want troubleshooting#the-site-has-no-styling): writing/markdown#raw-html-is-removed, credits#interface, components/cards#syntax
- logged out people should not be able to see certain pages (want gating): getting-started/upgrade#05-to-06, troubleshooting#two-docs-files-resolve-to-slug, troubleshooting#search-returns-nothing
- put a specific page first in the sidebar (want writing/navigation#ordering): writing/navigation#gating-and-the-sidebar, page-actions, seo#per-page
- v1 and v2 of my product side by side, both browsable (want versions): getting-started/configuration#site-and-routing, getting-started/installation#production, components/steps#publish-config-stubs-and-assets
- static output only, nothing dynamic on the host (want export): seo#static-export, page-actions#edit-on-github, troubleshooting#editing-changes-nothing
- brand colour instead of the default blue (want theming#accent): getting-started/upgrade#colour-presets, theming#the-rest, theming
- shortcut to open the search popup (want search#minisearch): getting-started/installation#open-the-site, components/steps#open-the-site, getting-started/upgrade#search
- show the current app version number inside the text (want value-tags): why#what-living-in-the-app-adds, components/tabs#other-directives-inside-a-panel, getting-started/upgrade#version-urls
- coloured box to draw attention to a gotcha (want components/callouts): components/cards#a-section-index, getting-started/upgrade#colour-presets, components/tabs#other-directives-inside-a-panel
- switch between package managers in the same snippet (want components/tabs): components/steps#install-the-package, getting-started/installation#require-the-package, components/steps#require-the-package
- how to rank on google (want seo): writing/images#how-they-are-served, getting-started/upgrade#05-to-06, why#what-05-commits-to
- crawlers should skip the markdown urls (want seo#robotstxt): writing/markdown, getting-started/upgrade#version-urls, page-actions#copy-markdown
- use my own blade component inside a page (want extending#namespaces): writing/markdown#no-blade, extending#built-ins-share-blade-views, extending
- can i use @if inside the content (want writing/markdown#no-blade): components/tabs#other-directives-inside-a-panel, theming#the-rest, components/steps#blocks-inside-a-step
- does my old server version still work with it (want getting-started/installation#requirements): getting-started/upgrade#version-urls, versions, why#one-source-two-ways-to-ship-it
- delete the cached build so it starts fresh (want commands#vellumclear): commands#vellumbuild, theming#the-rest, why#compared-with-the-usual-choices
- what makes this worth picking over the react and astro ones (want why#compared-with-the-usual-choices): components/steps#rules-worth-knowing, components/tabs#rules-worth-knowing, components/callouts#the-five-types
- moving up one minor release, anything that will bite me (want getting-started/upgrade#05-to-06): releases, getting-started/upgrade, writing/images#formats-the-route-will-serve
- only users that pass a policy check can open a doc (want gating): page-actions#open-in-chatgpt--open-in-claude, getting-started/installation#open-the-site, components/steps#open-the-site
- external urls should not replace the current window (want writing/markdown#links): getting-started/upgrade#version-urls, theming#the-rest, writing/navigation#external-and-custom-entries
