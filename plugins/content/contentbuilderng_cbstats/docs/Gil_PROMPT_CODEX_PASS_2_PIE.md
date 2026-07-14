# Prompt Codex — Pass 2 Pie

Travaille dans le dépôt ContentBuilder NG, principalement sur :

`plugins/content/contentbuilderng_cbstats`

Lis impérativement :

- `AGENTS.md` racine ;
- `plugins/content/contentbuilderng_cbstats/AGENTS.md` ;
- `docs/Gil_CBSTATS_SPECIFICATION.md` ;
- `docs/Gil_02_PIE.md` ;
- `docs/Gil_05_TESTS_AND_ACCEPTANCE.md`.

Précondition obligatoire : le moteur générique commun et `output=json` de la Pass 1 sont déjà implémentés et validés.

Mission unique : ajouter `output=pie` en réutilisant le moteur commun existant.

Ne duplique aucune logique de calcul, filtrage, regroupement ou comptage.

N'implémente pas `output=bar` dans cette passe.

Avant modification :

- vérifie la précondition ;
- inspecte les conventions d'assets du dépôt et du manifest réel ;
- vérifie si Chart.js ou une bibliothèque compatible existe déjà ;
- annonce les fichiers prévus.

Respecte la spécification Pie, notamment : rendu responsive, largeur maximale cible 300 px sans fixer la card, pourcentage dans les secteurs quand lisible, détails dans la légende et le tooltip, wording générique sans hardcoder « inscrits », IDs uniques et assets chargés une seule fois.

Après implémentation :

- exécute les tests/checks pertinents ;
- vérifie plusieurs graphiques sur une même page ;
- mets à jour `debug=1`, langues et documentation canonique ;
- mets à jour la documentation principale ContentBuilder NG et la référence CB/API existante.

Termine par un rapport précis : fichiers modifiés, tests, compatibilité, documentation, risques restants.
