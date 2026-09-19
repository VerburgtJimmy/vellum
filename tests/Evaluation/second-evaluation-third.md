# Second evaluation (third set)

155 sections, 72 questions (52 literal, 20 paraphrase). Model potion-base-8M.

| Ranking | R@1 | R@5 | Literal R@1 | Literal R@5 | Paraphrase R@1 | Paraphrase R@5 |
| --- | --- | --- | --- | --- | --- | --- |
| BM25 (today) | 56% | 79% | 71% | 94% | 15% | 40% |
| BM25 + expansion | 56% | 76% | 69% | 92% | 20% | 35% |
| Semantic, int8 vocab | 53% | 76% | 67% | 92% | 15% | 35% |
| Semantic + BM25 boost, int8 vocab | 61% | 81% | 77% | 94% | 20% | 45% |
| Weighted RRF, int8 vocab | 58% | 79% | 73% | 90% | 20% | 50% |
| Semantic, int4 vocab | 53% | 78% | 67% | 92% | 15% | 40% |
| Semantic + BM25 boost, int4 vocab | 61% | 81% | 77% | 94% | 20% | 45% |
| Weighted RRF, int4 vocab | 58% | 78% | 73% | 90% | 20% | 45% |

| Semantic file | Tokens | Size | Gzipped | Build |
| --- | --- | --- | --- | --- |
| int8 vocab, int8 sections | 2,301 | 638 KB | 573 KB | 0.26 s |
| int4 vocab, int8 sections | 2,301 | 351 KB | 280 KB | 0.25 s |

## Go criteria, primary ranking

- Semantic + BM25 boost, int8 vocab: paraphrase R@5 45% (need 50%) FAIL; literal R@5 94% vs BM25 94% pass; file 573 KB gz (max 600) pass; build 0.26 s (max 5) pass
- Weighted RRF, int8 vocab: paraphrase R@5 50% (need 50%) pass; literal R@5 90% vs BM25 94% FAIL; file 573 KB gz (max 600) pass; build 0.26 s (max 5) pass
- Semantic + BM25 boost, int4 vocab: paraphrase R@5 45% (need 50%) FAIL; literal R@5 94% vs BM25 94% pass; file 280 KB gz (max 600) pass; build 0.25 s (max 5) pass
- Weighted RRF, int4 vocab: paraphrase R@5 45% (need 50%) FAIL; literal R@5 90% vs BM25 94% FAIL; file 280 KB gz (max 600) pass; build 0.25 s (max 5) pass

## Third-set decision

Paraphrase R@1: boosted 20%, weighted RRF 20% (+0 points, need +5). Literal R@5: boosted 94%, weighted RRF 90% (changed). Winner: boosted.

## Paraphrase misses at 5, semantic + BM25 boost, int8

- landing page for a folder that links out to its children (want components/cards#a-section-index): why#what-you-get, writing/markdown#links, writing/images#formats-the-route-will-serve
- can i put a code snippet or a bulleted list inside the coloured box (want components/callouts#contents): writing/code-blocks#inline-code-with-a-language, writing/code-blocks, theming#contrast
- using the info box from blade instead of markdown, and what if i mistype the kind (want components/callouts#island-form): writing/markdown, writing/markdown#raw-html-is-removed, page-actions#copy-markdown
- what stack is this built on (want credits#foundations): getting-started/installation#production, why#what-it-does-not-do, components/callouts#contents
- does the markdown shorthand and the blade version produce identical html (want extending#built-ins-share-blade-views): writing/markdown, writing/markdown#raw-html-is-removed, extending
- since 0.5 where do i set who can see a page, and does a folder setting apply to its children (want getting-started/upgrade#frontmatter): why#what-it-does-not-do, seo#sitemap, writing/navigation#metajson
- when did the extra syntax beyond plain markdown arrive (want getting-started/upgrade#markdown-plus-components): writing/markdown#raw-html-is-removed, writing/markdown, components/cards#syntax
- the setting i tried to print is being refused (want troubleshooting#value-tag-x-vellumenv-key-appkey-is-not-in-vellumcomponentsallowlist): getting-started/upgrade#05-to-06, troubleshooting#still-stuck, writing/markdown#raw-html-is-removed
- do i need a file that defines the menu tree (want writing/navigation): writing/images#how-they-are-served, getting-started/configuration#site-and-routing, why#what-it-does-not-do
- why keep the manuals in the same repo as the code (want why): page-actions#changing-them, getting-started/upgrade#05-to-06, writing/code-blocks#inline-code-with-a-language
- does the flat file output include a sitemap and which domain does it use (want seo#static-export): seo#sitemap, export, why#how-it-works
