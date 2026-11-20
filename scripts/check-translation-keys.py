#!/usr/bin/env python3
"""Check that statically referenced component and plugin keys exist in en-GB."""

import re
import subprocess
import sys
from pathlib import Path

KEY = re.compile(
    r'\b((?:COM_CONTENTBUILDERNG|PLG_(?:[A-Z0-9]+_)?CONTENTBUILDERNG(?:_[A-Z0-9]+)?)'
    r'_[A-Z0-9_]*[A-Z0-9])\b'
)
INI_KEY = re.compile(r'^([A-Z][A-Z0-9_]*)\s*=')
CALL_ARGUMENT = re.compile(r'\b(_|sprintf|script|plural)\(\s*([\'"])([A-Z][A-Z0-9_]*)\2')
SKIPPED = re.compile(r'/(vendor|libraries|node_modules)/|^(admin/tests|tests|scripts|joomla-stubs)/|^\.')


def tracked(*patterns):
    output = subprocess.run(
        ['git', 'ls-files', *patterns], check=True, capture_output=True, text=True
    ).stdout
    return [path for path in output.splitlines() if path and not SKIPPED.search(path)]


def main():
    keys = set()
    for path in tracked('*en-GB*.ini'):
        for line in Path(path).read_text(encoding='utf-8').splitlines():
            match = INI_KEY.match(line)
            if match:
                keys.add(match.group(1))

    errors = []
    for path in tracked('*.php', '*.js', '*.xml'):
        source = Path(path).read_text(encoding='utf-8', errors='replace')
        calls = {match.start(3): match.group(1) for match in CALL_ARGUMENT.finditer(source)}
        for match in KEY.finditer(source):
            key = match.group(1)
            plural = f'{key}_1' in keys or f'{key}_MORE' in keys
            exact = path.endswith('.xml') or calls.get(match.start(1), 'plural') != 'plural'
            if key not in keys and (exact or not plural):
                line = source.count('\n', 0, match.start()) + 1
                errors.append(f'{path}:{line}: missing en-GB translation key: {key}')

    for error in errors:
        print(error, file=sys.stderr)
    if errors:
        return 1
    print('Translation keys OK.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
