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

## Checking search in CI

Search is content, and content rots. A heading that gets renamed, a section that gets split, a page that gets rewritten: any of them can move an answer out of reach without breaking a single link. Put a `questions.yml` next to your Markdown and the build will notice.

```yaml
- q: What does vellum:install publish?
  page: commands
  section: velluminstall
- q: What should a deploy run?
  page: commands
  section: a-deploy
  also:
    - page: getting-started/installation
      section: production
```

`q` is the question as a reader would type it. `page` is the page slug, and `section` the heading id that answers it; leave `section` off and any section of that page counts. `also` lists further places that answer it equally well. Any other keys are yours to use; Vellum ignores them.

Every build asks them and reports:

```
search check: 43 of 58 questions answered in the top 5 (74%)
  missed: How do I highlight lines in a code block? (wanted writing/code-blocks#highlighted-lines)
```

Set `checks.search_min` to the share that must be answered, and a build below it fails. Start by running it once and setting the minimum a little under what you get, so you find out when a rewrite costs you rather than when it drifts.

A question whose target no longer exists is reported separately, as a broken question rather than a missed one, and left out of the count:

```
broken question: shortcut to open the search popup (nothing at search#minisearch)
```

That is the file being wrong, not search, and only you can fix it. `--strict` and `checks.strict` fail the build on one.

The check reads the copy a guest gets, so it never depends on who is running the build. It is on by default and does nothing when there is no `questions.yml`; `vellum:build --check-search` runs it even with `checks.search` off.

`VELLUM_SEARCH_MIN` sets the minimum too, so CI can demand more than a local build does. A run there needs the model, which never changes for a given name and is worth caching:

```yaml
- name: Cache the embedding model
  uses: actions/cache@v4
  with:
    path: storage/vellum/models
    key: vellum-model-potion-base-8M

- run: php artisan vellum:model
- run: php artisan vellum:build --check-search --strict
  env:
    VELLUM_SEARCH_MIN: '0.65'
```

With `docs/.vellum` committed, none of this needs an API key.

:::note[Ordinary questions, not clever ones]
Write down the questions people actually ask you, before you write the page that answers them. A file of twenty real questions is worth more than a hundred invented ones, and it is the only part of search you have to maintain by hand.
:::

## The answer endpoint

`{prefix}/_vellum/answer?q=` runs the same search on the server and returns JSON, for a client that cannot run the browser's copy: an agent, a chat bot, a shell script.

```
GET /docs/_vellum/answer?q=what+does+vellum:clear+remove
```

```json
{
  "query": "what does vellum:clear remove",
  "answer": {
    "url": "/docs/commands#vellumclear",
    "title": "Commands",
    "heading": "vellum:clear",
    "passage": "Removes the compiled pages, the navigation tree, the search index and the rendered component fragments.",
    "updated": "2026-09-20T23:01:13+02:00",
    "score": 1.01,
    "answer": { "type": "command", "command": "php artisan vellum:clear" },
    "confidence": 0.887
  },
  "results": []
}
```

`answer` is the answer card, and is `null` when search is not sure enough, by the same `answers.card_threshold` the dialog uses, so a client can tell "here is the answer" from "here is where to look". `results` is the top five sections, each with its URL, heading path, passage and last-updated date. Add `version=` when versions are on, and an empty `q` is a 400.

It answers from the copy the caller may see, like every other search route, so a gated page is not in the results and its text is not in the answer. Set `agents.answer` to `false` to turn it off.

It is a route, so a [static export](/docs/export) does not have it. An exported site still searches in the browser from the files it ships.
