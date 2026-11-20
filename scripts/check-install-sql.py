#!/usr/bin/env python3
"""Reject INSERT statements without column lists in tracked SQL files."""

import re
import subprocess
import sys
from pathlib import Path

INSERT_WITHOUT_COLUMNS = re.compile(
    r'\bINSERT\s+(?:IGNORE\s+)?INTO\s+`?[\w#]+`?\s+VALUES\b',
    re.IGNORECASE,
)


def main():
    paths = subprocess.run(
        ['git', 'ls-files', '*.sql'], check=True, capture_output=True, text=True
    ).stdout.splitlines()
    errors = []

    for path in paths:
        if '/vendor/' in path or '/libraries/' in path:
            continue
        source = Path(path).read_text(encoding='utf-8')
        for match in INSERT_WITHOUT_COLUMNS.finditer(source):
            line = source.count('\n', 0, match.start()) + 1
            errors.append(f'{path}:{line}: INSERT without a column list')

    for error in errors:
        print(error, file=sys.stderr)
    if errors:
        return 1
    print('SQL INSERT statements OK.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
