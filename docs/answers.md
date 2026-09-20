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

## Where the answer comes from

Each section also carries a short answer taken from its own text: the first that applies of a shell command, the rows of a config table, the option row its heading names, an "X is ..." definition, or its opening sentence with the code block it introduces.

See [Configuration](/docs/getting-started/configuration#answers) for every key.
