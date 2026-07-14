# Prompt Codex — Pass 3 Bar

Travaille dans le dépôt ContentBuilder NG, principalement sur :

`plugins/content/contentbuilderng_cbstats`

Lis impérativement :

- `AGENTS.md` racine ;
- `plugins/content/contentbuilderng_cbstats/AGENTS.md` ;
- `docs/Gil_CBSTATS_SPECIFICATION.md` ;
- `docs/Gil_03_BAR.md` ;
- `docs/Gil_05_TESTS_AND_ACCEPTANCE.md`.

Préconditions : le moteur commun, `output=json` et `output=pie` sont déjà implémentés et validés.

Mission unique : ajouter `output=bar` en réutilisant exactement le même moteur normalisé.

Ne duplique aucune logique de calcul.

Respecte la spécification Bar : histogramme horizontal, `indexAxis: 'y'` si compatible avec la version réelle de Chart.js, détails génériques, valeurs sur les barres seulement si lisibles, coexistence Pie + Bar, IDs uniques et assets chargés une seule fois.

Avant modification : vérifie les préconditions, inspecte le code/assets existants et annonce les fichiers prévus.

Après implémentation :

- exécute les tests/checks pertinents ;
- teste plusieurs Bar et le mélange Pie + Bar ;
- mets à jour `debug=1`, langues et documentation canonique ;
- mets à jour la documentation principale ContentBuilder NG et la référence CB/API existante.

Termine par un rapport précis : fichiers modifiés, tests, compatibilité, documentation, risques restants.
