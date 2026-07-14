# Prompt Codex — Pass 1 JSON

Travaille dans le dépôt ContentBuilder NG, principalement sur :

`plugins/content/contentbuilderng_cbstats`

Lis impérativement avant toute modification :

1. le `AGENTS.md` racine du dépôt ;
2. `plugins/content/contentbuilderng_cbstats/AGENTS.md` ;
3. `plugins/content/contentbuilderng_cbstats/docs/Gil_CBSTATS_SPECIFICATION.md` ;
4. `plugins/content/contentbuilderng_cbstats/docs/Gil_01_JSON_PROVIDER.md` ;
5. `plugins/content/contentbuilderng_cbstats/docs/Gil_05_TESTS_AND_ACCEPTANCE.md`.

Mission unique : implémenter le moteur générique commun de statistiques de champ et `output=json`.

Ne développe aucun graphique dans cette passe : pas de Pie, pas de Bar, pas de Chart.js, pas de JavaScript de graphique, pas de CSS de graphique.

Avant de modifier le code :

- inspecte l'implémentation actuelle, notamment `output=table` ;
- identifie précisément les filtres et leurs sémantiques actuelles ;
- identifie les permissions STATS et le comportement `debug=1` ;
- repère les tests, fichiers langue et documentation existants ;
- indique brièvement les fichiers que tu prévois de modifier.

Implémente ensuite uniquement cette mission, avec modifications minimales et sans hardcoding métier.

Préserve strictement les outputs existants : `total`, `form_name`, `table`, `sum`, `min`, `max`.

Après implémentation :

- exécute les tests/checks pertinents disponibles ;
- mets à jour la documentation canonique du plugin et la référence CB/API existante pour `output=json` ;
- mets à jour `debug=1` et les langues nécessaires ;
- ne crée pas de documentation en doublon si des fichiers canoniques existent déjà.

Termine par un rapport précis : fichiers modifiés, comportement ajouté, tests exécutés et résultats, compatibilité vérifiée, documentation mise à jour, risques restants.
