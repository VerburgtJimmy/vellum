# Second evaluation (third set)

157 sections, 72 questions (52 literal, 20 paraphrase). Model potion-base-8M. This run includes the questions a model wrote at build time, which the first run did not have.

| Ranking | R@1 | R@5 | Literal R@1 | Literal R@5 | Paraphrase R@1 | Paraphrase R@5 |
| --- | --- | --- | --- | --- | --- | --- |
| BM25 (today) | 56% | 79% | 71% | 94% | 15% | 40% |
| BM25 + expansion | 61% | 75% | 79% | 92% | 15% | 30% |
| Semantic, int8 vocab | 54% | 76% | 69% | 92% | 15% | 35% |
| Semantic + BM25 boost, int8 vocab | 64% | 82% | 81% | 94% | 20% | 50% |
| Weighted RRF, int8 vocab | 60% | 82% | 75% | 94% | 20% | 50% |
| Semantic, int4 vocab | 53% | 78% | 67% | 92% | 15% | 40% |
| Semantic + BM25 boost, int4 vocab | 64% | 82% | 81% | 94% | 20% | 50% |
| Weighted RRF, int4 vocab | 58% | 83% | 73% | 94% | 20% | 55% |

| Semantic file | Tokens | Size | Gzipped | Build |
| --- | --- | --- | --- | --- |
| int8 vocab, int8 sections | 2,498 | 690 KB | 620 KB | 0.29 s |
| int4 vocab, int8 sections | 2,498 | 378 KB | 304 KB | 0.30 s |

## Go criteria, primary ranking

- Semantic + BM25 boost, int8 vocab: paraphrase R@5 50% (need 50%) pass; literal R@5 94% vs BM25 94% pass; file 620 KB gz (max 600) FAIL; build 0.29 s (max 5) pass
- Weighted RRF, int8 vocab: paraphrase R@5 50% (need 50%) pass; literal R@5 94% vs BM25 94% pass; file 620 KB gz (max 600) FAIL; build 0.29 s (max 5) pass
- Semantic + BM25 boost, int4 vocab: paraphrase R@5 50% (need 50%) pass; literal R@5 94% vs BM25 94% pass; file 304 KB gz (max 600) pass; build 0.30 s (max 5) pass
- Weighted RRF, int4 vocab: paraphrase R@5 55% (need 50%) pass; literal R@5 94% vs BM25 94% pass; file 304 KB gz (max 600) pass; build 0.30 s (max 5) pass

## Third-set decision

Paraphrase R@1: boosted 20%, weighted RRF 20% (+0 points, need +5). Literal R@5: boosted 94%, weighted RRF 94% (unchanged). Winner: boosted.

## Paraphrase misses at 5, semantic + BM25 boost, int8

- landing page for a folder that links out to its children (want components/cards#a-section-index): writing/markdown#links, why#what-you-get, writing/navigation
- can i put a code snippet or a bulleted list inside the coloured box (want components/callouts#contents): writing/code-blocks#inline-code-with-a-language, writing/code-blocks#highlighted-lines, writing/code-blocks#title
- using the info box from blade instead of markdown, and what if i mistype the kind (want components/callouts#island-form): writing/markdown, writing/markdown#raw-html-is-removed, page-actions#copy-markdown
- what stack is this built on (want credits#foundations): why#how-it-works, getting-started/installation#production, components/steps#blocks-inside-a-step
- does the markdown shorthand and the blade version produce identical html (want extending#built-ins-share-blade-views): writing/markdown, writing/markdown#raw-html-is-removed, extending
- since 0.5 where do i set who can see a page, and does a folder setting apply to its children (want getting-started/upgrade#frontmatter): writing/navigation#metajson, getting-started/upgrade#keys-added-after-02, seo#sitemap
- when did the extra syntax beyond plain markdown arrive (want getting-started/upgrade#markdown-plus-components): writing/markdown, writing/markdown#raw-html-is-removed, components/cards#syntax
- the setting i tried to print is being refused (want troubleshooting#value-tag-x-vellumenv-key-appkey-is-not-in-vellumcomponentsallowlist): getting-started/configuration#theme, getting-started/upgrade#05-to-06, theming#the-rest
- do i need a file that defines the menu tree (want writing/navigation): writing/images#how-they-are-served, writing/images#paths-resolve-from-the-docs-root, writing/images#formats-the-route-will-serve
- why keep the manuals in the same repo as the code (want why): writing/markdown#no-blade, page-actions#edit-on-github, page-actions#changing-them
