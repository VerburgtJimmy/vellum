#!/usr/bin/env python3
"""Print the CHANGELOG section for a version. Exits 1 when there is none."""
import re
import sys

version = sys.argv[1].lstrip('v')
changelog = open(sys.argv[2] if len(sys.argv) > 2 else 'CHANGELOG.md').read()

pattern = re.compile(
    r'^##\s*\[?' + re.escape(version) + r'\]?[^\n]*\n(.*?)(?=^##\s|\Z)',
    re.S | re.M,
)
match = pattern.search(changelog)

if match is None:
    sys.exit(1)

body = match.group(1).strip()

if body == '':
    sys.exit(1)

print(body)
