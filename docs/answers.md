---
title: Answers
description: How search finds a section from a question, and what the optional model-written questions add.
---

Vellum's search matches a reader's question against the section that answers it, not only against the words on the page. Everything is built by `vellum:build`; no model runs when someone searches.

## Questions a section answers

At build time each section is given the questions it answers, derived from its own structure:

| From | Questions |
| --- | --- |
| Its heading | "what is meta.json", "where do i configure meta.json" |
| A shell command in it | "how do i build", "what does vellum:build do" |
| A config key it documents | "what does checks.strict do", "how do i change checks.strict", "default of checks.strict" |
| A warning or danger callout | "why does assets are not gated fail" |
| A `questions:` list in frontmatter | Whatever you wrote |

Those questions are indexed with the section, so a question phrased like a question lands on the section that answers it.

## Questions written by a model

Derived questions reuse the docs' own words, which is exactly what a stuck reader does not have. Set `answers.llm.provider` and a key, and `vellum:build` asks a model for five more questions per section, phrased the way readers ask them.

```bash
VELLUM_LLM_PROVIDER=anthropic VELLUM_LLM_KEY=sk-ant-... php artisan vellum:build
```

The model is asked once per section. Its answers are cached under `docs/.vellum/questions`, keyed by the section they came from, and that directory is meant to be committed: a deploy, or CI, then needs no key at all. A section whose text changes is asked again on the next build; nothing else is, so the cost below is what filling an empty cache costs, not what a deploy costs.

The default model is `claude-haiku-4-5`. Any model string the provider accepts works: name a larger one in `answers.llm.model` if you would rather spend more, or pin a dated version.

Five sections are asked for at a time. A rate limit or a server error is retried with a wait, and a build that keeps failing stops asking rather than spending on a key that is not working.

:::note[What it cost here]
Vellum's own docs: 157 sections, `claude-haiku-4-5`, about 34,000 tokens in and 19,000 out, **$0.13** to fill an empty cache. On the held-out evaluation set, the share of paraphrased questions whose answer appeared in the top five rose from 47% to **57%**, and the share answered by the very first result from 27% to **37%**. The cached questions add 32 KB to the package.
:::

That is the whole of what a model does here. Answers shown to readers are quoted from your docs; nothing is generated at request time, and search works the same when no provider is set.

## How search works

Search is built at deploy time and runs in the reader's browser. Nothing is sent to a server as they type, and no model runs when they search.

**What the browser fetches.** On the first search of a visit, two files: the answer index, which is every section with its text, its questions and its answer, and the vectors, which are the numbers that let a question match a section that words alone would miss. For these docs they are about 180 KB and 300 KB, both compressed, fetched once and cached. Both are filtered for whoever is asking: a page behind a gate is not in the copy a guest receives, and neither are the words only that page uses.

**How a section is scored.** Each section gets a similarity between the question and its text, from the vectors. A section whose words the reader actually typed is lifted a little above one that only means the same thing, and a section the query names outright, by heading or by an alias, is lifted again. The words the reader typed are widened first: a query with "night mode" in it also searches for "dark mode", from the built-in list and from any `aliases:` a page declares.

**When an answer card appears.** Search scores how sure it is of its top result, from how close the question is to it, how far ahead of the runner-up it is, and whether the query named it. Above `answers.card_threshold` the top result is shown as a card with its answer; below it, the results stand on their own. On Vellum's own question sets about one question in five gets a card, 22 of those 25 cards named the section that answers it, and 24 of 25 were at least on the right page. Raising the threshold shows fewer cards and gets more of them right; lowering it does the opposite. A card states an answer, so a wrong one costs more than a missing one, and the default is set accordingly.

**What the card shows.** The section's answer: the command to run, the config rows, the option row, or the sentence that defines it, with the passage underneath. All of it is quoted from the page; nothing is written for the card.

## Where the answer comes from

Each section carries a short answer taken from its own text: the first that applies of a shell command, the rows of a config table, the option row its heading names, an "X is ..." definition, or its opening sentence with the code block it introduces. A section with none of those falls back to the page description, a list, or a code block.

See [Configuration](/docs/getting-started/configuration#answers) for every key.
