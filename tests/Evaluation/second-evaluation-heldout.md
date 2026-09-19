# Second evaluation (heldout set)

152 sections, 50 questions (20 literal, 30 paraphrase). Model potion-base-8M.

| Ranking | R@1 | R@5 | Literal R@1 | Literal R@5 | Paraphrase R@1 | Paraphrase R@5 |
| --- | --- | --- | --- | --- | --- | --- |
| BM25 (today) | 42% | 66% | 85% | 100% | 13% | 43% |
| BM25 + expansion | 40% | 68% | 80% | 100% | 13% | 47% |
| Semantic, int8 vocab | 44% | 68% | 70% | 95% | 27% | 50% |
| Semantic + BM25 boost, int8 vocab | 48% | 72% | 80% | 100% | 27% | 53% |
| Weighted RRF, int8 vocab | 54% | 70% | 85% | 95% | 33% | 53% |
| Semantic, int4 vocab | 44% | 68% | 70% | 95% | 27% | 50% |
| Semantic + BM25 boost, int4 vocab | 46% | 70% | 80% | 100% | 23% | 50% |
| Weighted RRF, int4 vocab | 56% | 72% | 85% | 100% | 37% | 53% |

| Semantic file | Tokens | Size | Gzipped | Build |
| --- | --- | --- | --- | --- |
| int8 vocab, int8 sections | 2,326 | 644 KB | 578 KB | 0.23 s |
| int4 vocab, int8 sections | 2,326 | 353 KB | 283 KB | 0.24 s |

## Go criteria, primary ranking

- Semantic + BM25 boost, int8 vocab: paraphrase R@5 53% (need 50%) pass; literal R@5 100% vs BM25 100% pass; file 578 KB gz (max 600) pass; build 0.23 s (max 5) pass
- Weighted RRF, int8 vocab: paraphrase R@5 53% (need 50%) pass; literal R@5 95% vs BM25 100% FAIL; file 578 KB gz (max 600) pass; build 0.23 s (max 5) pass
- Semantic + BM25 boost, int4 vocab: paraphrase R@5 50% (need 50%) pass; literal R@5 100% vs BM25 100% pass; file 283 KB gz (max 600) pass; build 0.24 s (max 5) pass
- Weighted RRF, int4 vocab: paraphrase R@5 53% (need 50%) pass; literal R@5 100% vs BM25 100% pass; file 283 KB gz (max 600) pass; build 0.24 s (max 5) pass

## Paraphrase misses at 5, semantic + BM25 boost, int8

- generate flat files i can upload to any host (want commands#vellumexport): why#one-source-two-ways-to-ship-it, why#what-living-in-the-app-adds, seo#static-export
- text between my link tiles turns into its own tile (want components/cards#only-cards-belong-inside): writing/markdown#links, components/cards#syntax, extending
- my numbered guide shows the wrong numbering (want components/steps#rules-worth-knowing): writing/code-blocks#line-numbers, writing/code-blocks#highlighted-lines, writing/markdown#what-you-get
- can i use this commercially, what are the terms (want credits#licences): why#compared-with-the-usual-choices, page-actions#changing-them, search#minisearch
- can i nest one custom element inside another (want extending#slots): components/tabs#other-directives-inside-a-panel, components/callouts#contents, components/steps#blocks-inside-a-step
- typo in a component name, what happens on prod (want extending#missing-components): troubleshooting#component-x-name-is-not-in-vellumcomponentsnamespaces, troubleshooting#unknown-docs-component-x-name, troubleshooting#unclosed-docs-component-x-name
- which fields go at the top of a page between the dashes (want getting-started/configuration#frontmatter): writing/markdown#headings-and-anchors, writing/navigation#section-headings, seo#per-page
- hide the buttons under the title (want page-actions#changing-them): theming#the-rest, getting-started/configuration#frontmatter, components/callouts#titles
- do private pages end up in the offline search bundle (want search#export): seo#sitemap, seo#robotstxt, writing/markdown#what-you-get
- which looks come out of the box (want theming#presets): theming#the-rest, writing/navigation#gating-and-the-sidebar, troubleshooting#failures-that-are-quiet
- build fails on a triple colon block (want troubleshooting#unknown-docs-directive-name-in-file): commands#vellumbuild, components/steps#blocks-inside-a-step, components/callouts#contents
- my metadata prints as normal text on the page instead of being applied (want troubleshooting#failures-that-are-quiet): seo#per-page, writing/navigation#metajson, theming#contrast
- benefits of not using a static generator (want why#what-living-in-the-app-adds): seo#static-export, export, components/callouts#the-five-types
- can i link to a specific paragraph on a page (want writing/markdown#headings-and-anchors): page-actions#edit-on-github, seo#per-page, page-actions#open-in-chatgpt--open-in-claude
