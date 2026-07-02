#!/usr/bin/env python3
"""Contrôle CI des fichiers de langue (en-GB, fr-FR, de-DE).

Vérifie pour chaque répertoire ``language`` :
  - clefs dupliquées dans un même fichier .ini ;
  - lignes malformées (pas de ``=`` ou valeur non entourée de guillemets) ;
  - parité des clefs entre les langues lorsque le fichier traduit existe.

Un fichier fr-FR/de-DE absent est signalé en avertissement, pas en erreur.

Usage: python3 scripts/check-translations.py
"""

import os
import re
import sys

LANGS = ('en-GB', 'fr-FR', 'de-DE')
LINE_RE = re.compile(r'^[A-Z0-9_.\-]+\s*=\s*".*"\s*(?:;.*)?$')


def parse_ini(path):
    keys = {}
    errors = []
    with open(path, encoding='utf-8') as handle:
        for number, line in enumerate(handle, 1):
            stripped = line.strip()
            if not stripped or stripped.startswith(';') or stripped.startswith('['):
                continue
            if not LINE_RE.match(stripped):
                errors.append(f'{path}:{number}: ligne malformée: {stripped[:80]}')
                continue
            key = stripped.split('=', 1)[0].strip()
            if key in keys:
                errors.append(f'{path}:{number}: clef dupliquée {key} (première occurrence ligne {keys[key]})')
            else:
                keys[key] = number
    return set(keys), errors


def main():
    errors = []
    warnings = []
    for root, dirs, _files in os.walk('.'):
        dirs[:] = [d for d in dirs if d not in ('vendor', 'node_modules', '.git')]
        if os.path.basename(root) != 'language':
            continue
        en_dir = os.path.join(root, 'en-GB')
        if not os.path.isdir(en_dir):
            continue
        for name in sorted(os.listdir(en_dir)):
            if not name.endswith('.ini'):
                continue
            en_keys, en_errors = parse_ini(os.path.join(en_dir, name))
            errors.extend(en_errors)
            for lang in LANGS[1:]:
                path = os.path.join(root, lang, name)
                if not os.path.isfile(path):
                    warnings.append(f'{path}: traduction absente')
                    continue
                keys, lang_errors = parse_ini(path)
                errors.extend(lang_errors)
                for key in sorted(en_keys - keys):
                    errors.append(f'{path}: clef manquante {key}')
                for key in sorted(keys - en_keys):
                    errors.append(f'{path}: clef orpheline {key} (absente de en-GB)')

    for warning in warnings:
        print(f'::warning::{warning}')
    for error in errors:
        print(f'::error::{error}')
    if errors:
        print(f'{len(errors)} erreur(s) de traduction.', file=sys.stderr)
        return 1
    print('Traductions OK.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
