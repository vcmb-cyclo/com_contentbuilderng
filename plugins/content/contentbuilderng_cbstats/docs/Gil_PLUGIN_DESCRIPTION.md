# CBStats plugin description — proposed wording

> Codex must locate the real plugin manifest and language description keys before applying these texts. Do not invent a second description system.

## Recommended short description — French

**ContentBuilder NG - CBStats - Content - Statistiques** permet d'afficher dans les contenus Joomla des statistiques dynamiques issues des vues ContentBuilder NG au moyen de balises `{CBStats ...}`, ou de publier les sorties de données prises en charge via URL/API. Le plugin gère les totaux, tableaux, agrégats, JSON, graphiques Pie et Bar, filtres, tris, deltas externes signés avec `add=`, titre du total avec `title=` et libellés d'affichage avec `titles=`. Exemple : `{CBStats id=25 field=Parcours output=pie title="👥 Total des inscrits :"}`. Les réglages API de la vue ainsi que les permissions STATS et de champ restent appliqués.

## Recommended long description — French

CBStats est le plugin de statistiques de ContentBuilder NG. Il interroge de façon générique une vue et ses champs depuis une balise `{CBStats ...}`, applique les filtres exacts, jokers et alternatives, puis produit les outputs `total`, `form_name`, `table`, `sum`, `min`, `max`, `json`, `pie` et `bar`. `add=` applique des deltas externes signés ; si le résultat calculé devient négatif, CBStats utilise temporairement `0` pour les tris, pourcentages et rendus, sans modifier les données sources. `titles=` renomme les libellés affichés avant le tri. Les outputs de données `json`, `total`, `sum`, `min`, `max` et `form_name` sont aussi accessibles par URL/API ; JSON accepte également `add` et `titles`. Les calculs de champs reposent sur une source normalisée commune et respectent les ACL, STATS et permissions de champ.

## Recommended short description — English

**ContentBuilder NG - CBStats - Content statistics** displays dynamic statistics from ContentBuilder NG views in Joomla content through `{CBStats ...}` tags, or publishes supported data outputs through URL/API requests. It supports totals, tables, aggregates, JSON, Pie and Bar charts, filters, sorting, signed external deltas with `add=`, total labels with `title=` and category display labels with `titles=`. Example: `{CBStats id=25 field=Route output=pie title="👥 Total registrations:"}`. View API settings and STATS and field permissions remain enforced.

## Recommended long description — English

CBStats is the statistics plugin for ContentBuilder NG. It generically queries a view and its fields from a `{CBStats ...}` tag, applies exact, wildcard and alternative filters, and produces `total`, `form_name`, `table`, `sum`, `min`, `max`, `json`, `pie` and `bar`. `add=` applies signed external deltas; when a calculated result becomes negative, CBStats temporarily uses `0` for sorting, percentages and rendering without changing source data. `titles=` renames display labels before sorting. The `json`, `total`, `sum`, `min`, `max` and `form_name` data outputs are also available through URL/API requests; JSON also accepts `add` and `titles`. Field statistics share one normalized source and enforce ACLs, STATS and field permissions.

## Recommended short description — German

**ContentBuilder NG - CBStats - Inhalt - Statistiken** zeigt dynamische Statistiken aus ContentBuilder-NG-Ansichten in Joomla-Inhalten über `{CBStats ...}`-Tags an oder veröffentlicht unterstützte Datenausgaben über URL/API-Anfragen. Das Plugin unterstützt Gesamtzahlen, Tabellen, Aggregate, JSON, Kreis- und Balkendiagramme, Filter, Sortierungen, vorzeichenbehaftete externe Deltas mit `add=`, Gesamtbezeichnungen mit `title=` und Kategoriebezeichnungen mit `titles=`. Beispiel: `{CBStats id=25 field=Strecke output=pie title="👥 Gesamtzahl der Anmeldungen:"}`. API-Einstellungen der Ansicht sowie STATS- und Feldberechtigungen bleiben wirksam.

## Application rules

When updating the real plugin:

1. The official element is `contentbuilderng_cbstats`; `contentbuilderng_stats` is retained only as an installer migration source.
2. Update manifest description keys and corresponding `fr-FR`, `en-GB` and `de-DE` language strings.
3. Do not claim `json`, `pie` or `bar` is available until the corresponding pass is actually implemented and validated.
4. Document the exact outputs implemented and validated in the current release.
5. Keep short descriptions short enough for Joomla extension listings and administrator views.
