# Second evaluation (heldout set)

157 sections, 50 questions (20 literal, 30 paraphrase). Model potion-base-8M. This run includes the questions a model wrote at build time, which the first run did not have.

| Ranking | R@1 | R@5 | Literal R@1 | Literal R@5 | Paraphrase R@1 | Paraphrase R@5 |
| --- | --- | --- | --- | --- | --- | --- |
| BM25 (today) | 36% | 62% | 75% | 95% | 10% | 40% |
| BM25 + expansion | 44% | 68% | 80% | 90% | 20% | 53% |
| Semantic, int8 vocab | 50% | 74% | 70% | 95% | 37% | 60% |
| Semantic + BM25 boost, int8 vocab | 54% | 72% | 80% | 95% | 37% | 57% |
| Weighted RRF, int8 vocab | 58% | 72% | 85% | 95% | 40% | 57% |
| Semantic, int4 vocab | 52% | 72% | 70% | 95% | 40% | 57% |
| Semantic + BM25 boost, int4 vocab | 54% | 72% | 80% | 95% | 37% | 57% |
| Weighted RRF, int4 vocab | 58% | 72% | 85% | 95% | 40% | 57% |

| Semantic file | Tokens | Size | Gzipped | Build |
| --- | --- | --- | --- | --- |
| int8 vocab, int8 sections | 2,498 | 690 KB | 620 KB | 0.35 s |
| int4 vocab, int8 sections | 2,498 | 378 KB | 304 KB | 0.30 s |

## Go criteria, primary ranking

- Semantic + BM25 boost, int8 vocab: paraphrase R@5 57% (need 50%) pass; literal R@5 95% vs BM25 95% pass; file 620 KB gz (max 600) FAIL; build 0.35 s (max 5) pass
- Weighted RRF, int8 vocab: paraphrase R@5 57% (need 50%) pass; literal R@5 95% vs BM25 95% pass; file 620 KB gz (max 600) FAIL; build 0.35 s (max 5) pass
- Semantic + BM25 boost, int4 vocab: paraphrase R@5 57% (need 50%) pass; literal R@5 95% vs BM25 95% pass; file 304 KB gz (max 600) pass; build 0.30 s (max 5) pass
- Weighted RRF, int4 vocab: paraphrase R@5 57% (need 50%) pass; literal R@5 95% vs BM25 95% pass; file 304 KB gz (max 600) pass; build 0.30 s (max 5) pass

## Paraphrase misses at 5, semantic + BM25 boost, int8

- generate flat files i can upload to any host (want commands#vellumexport): seo#static-export, export, why#how-it-works
- what command does my deploy pipeline need and when does it blow up (want commands#vellumbuild): getting-started/installation#production, getting-started/upgrade#05-to-06, why#what-it-does-not-do
- text between my link tiles turns into its own tile (want components/cards#only-cards-belong-inside): writing/markdown#links, page-actions#for-agents, components/cards#syntax
- can i use this commercially, what are the terms (want credits#licences): comparisons, comparisons#larecipe, search#minisearch
- typo in a component name, what happens on prod (want extending#missing-components): troubleshooting#unknown-docs-component-x-name, troubleshooting#unclosed-docs-component-x-name, troubleshooting#unknown-docs-directive-name-in-file
- which fields go at the top of a page between the dashes (want getting-started/configuration#frontmatter): writing/navigation#section-headings, writing/navigation#keep-the-rest, writing/markdown#headings-and-anchors
- hide the buttons under the title (want page-actions#changing-them): writing/navigation#gating-and-the-sidebar, writing/code-blocks#title, theming#the-rest
- do private pages end up in the offline search bundle (want search#export): getting-started/configuration#search, seo#robotstxt, writing/navigation#keep-the-rest
- which looks come out of the box (want theming#presets): writing/navigation#gating-and-the-sidebar, theming#the-rest, troubleshooting#failures-that-are-quiet
- build fails on a triple colon block (want troubleshooting#unknown-docs-directive-name-in-file): commands#vellumbuild, writing/code-blocks#showing-directive-syntax, components/steps#blocks-inside-a-step
- my metadata prints as normal text on the page instead of being applied (want troubleshooting#failures-that-are-quiet): page-actions#raw-markdown, seo#per-page, writing/markdown#no-blade
- benefits of not using a static generator (want why#what-living-in-the-app-adds): seo#static-export, export, components/cards#syntax
- will my config still work on the next release (want why#what-05-commits-to): getting-started/installation#next, getting-started/configuration, why#stability
