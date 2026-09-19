"""Regenerate tests/fixtures/semantic/wordpiece-parity.json.

Maintainer tool only; package users never need Python. Needs the Hugging Face
`tokenizers` package and a model fetched with `php artisan vellum:model`:

    python3 scripts/semantic-parity-fixture.py storage/vellum/models/potion-base-8M

The fixture keeps only the vocabulary the cases use. WordPiece over a subset
that contains every piece of the full tokenisation gives the same result, so
the PHP and JS tokenizers can be checked without shipping the model.
"""

import glob
import json
import sys

import tokenizers
import yaml

model = sys.argv[1].rstrip("/")
tokenizer = tokenizers.Tokenizer.from_file(model + "/tokenizer.json")

texts = []
for path in sorted(glob.glob("docs/**/*.md", recursive=True)):
    paragraphs = [p.strip() for p in open(path, encoding="utf-8").read().split("\n\n") if p.strip()]
    texts.extend(paragraphs[:3])

for path in ["docs/questions.yml", "tests/Evaluation/heldout.yml", "tests/Evaluation/third.yml"]:
    for entry in yaml.safe_load(open(path, encoding="utf-8")):
        if entry.get("q"):
            texts.append(entry["q"])

texts += [
    "Héllo naïve café résumé",
    "東京 and 北京 in one line",
    "tab\there, new\nline, carriage\rreturn",
    "zero​width and soft­hyphen",
    "don't can't won't e.g. i.e. U.S.A.",
    "x-vellum::env {{ $a }} @if @php <x-alert type=\"ok\" />",
    "ÅNGSTRÖM İstanbul straße ﬁne ﬂow",
    "Ｆｕｌｌｗｉｄｔｈ ＡＢＣ １２３",
    "Привет мир, مرحبا بالعالم, γειά σου κόσμε",
    "emoji 🚀 and 👍🏽 and ❤️",
    "https://example.com/docs/_vellum/raw/writing/markdown.md?x=1#frag",
    "vellum.components.allowlist.env APP_NAME route.prefix",
    "a" * 120,
    "supercalifragilisticexpialidocious antidisestablishmentarianism",
    "1988 2026-09-19 3.14159 v0.7.0 1,000,000",
    "",
    "   leading and trailing   ",
    "é combining acute, ñ tilde",
]

vocab_by_id = {i: t for t, i in tokenizer.get_vocab().items()}
cases = []
used = set()
for text in texts:
    encoding = tokenizer.encode(text, add_special_tokens=False)
    cases.append({"text": text, "ids": encoding.ids, "tokens": [vocab_by_id[i] for i in encoding.ids]})
    used.update(encoding.ids)

unk = tokenizer.token_to_id("[UNK]")
used.add(unk)
fixture = {
    "source": model.split("/")[-1] + " tokenizer.json, Hugging Face tokenizers " + tokenizers.__version__,
    "unknown": "[UNK]",
    "vocab": {vocab_by_id[i]: i for i in sorted(used)},
    "cases": cases,
}
json.dump(fixture, open("tests/fixtures/semantic/wordpiece-parity.json", "w", encoding="utf-8"), ensure_ascii=False, indent=1)
print(len(cases), "cases,", len(used), "tokens")
