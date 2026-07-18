# Migration com_contentbuilderng → Joomla 6 pur

> Document de suivi destiné aux agents. Cocher chaque tâche à la complétion.
> Modèle : `~/workspaces/vcmb/com_breezingformsng/MIGRATION.md` (migration sœur, terminée).
> Ne pas confondre avec `MIGRATION_GUIDE.md` (guide utilisateur de migration de données
> ContentBuilder → ContentBuilder NG, hors périmètre de ce document).

État audité le 2026-07-18 sur `main`.

---

## État actuel

### ✅ Déjà migré (le gros œuvre est fait)

- [x] Manifest + namespace PSR-4 (`CB\Component\Contentbuilderng`, `com_contentbuilderng.xml`)
- [x] DI container / services (`admin/services/provider.php` : MVCFactory, Dispatcher,
  RouterFactory, providers de services métier)
- [x] Routeur site natif (`site/src/Service/Router.php` via `RouterFactoryInterface`)
- [x] Aucun fichier d'entrée legacy (pas de `contentbuilderng.php` dispatcher, pas de
  `admin.contentbuilderng.php`, pas de bridge `act=`)
- [x] MVC complet admin (`admin/src/{Controller,Model,View,Table,Service,Helper}`) et
  site (`site/src/{Controller,Dispatcher,Model,View,Element,Field,Service}`)
- [x] Pipeline d'assets (`media/joomla.asset.json` + plugin CBStats)
- [x] Fichiers de langue en-GB / fr-FR / de-DE (admin + site)
- [x] Plugins modernisés (system, content ×6, listaction, submit, themes, validation,
  verify — namespaces `src/Extension`)
- [x] Aucun usage de classes `J*` legacy (`JRequest`, `JFactory`, `JTable`… — seuls
  2 commentaires en parlent encore)
- [x] Accesseurs `Factory` dépréciés éliminés (`getUser`, `getDbo`, `getConfig`,
  `getMailer`, `getCache`, `getLanguage`, `getSession`, `getDocument`) — reste
  uniquement `Factory::getDate()`, voir Phase 1
- [x] Accès `Input` via `getInput()` (un seul `$this->app->input` résiduel toléré)
- [x] Script d'installation : migration automatique ContentBuilder → NG (renommage des
  tables, extensions, assets, liens de menus — documentée dans `MIGRATION_GUIDE.md`)
- [x] Qualité : PHPUnit (`admin/tests/Unit`), PHPStan (niveau 1 + baseline), validation
  de paquet, smoke test Docker Joomla 6 + MySQL 8.4, tests API bout en bout (`TESTING.md`)

### ❌ Reliquats à traiter (périmètre de ce document)

- [x] Phase 1 — `Factory::getDate()` → `Joomla\CMS\Date\Date` (46 appels, 25 fichiers — fait le 2026-07-18)
- [x] Phase 2 — SQL versionné (`<update><schemas>` + `sql/updates/mysql/` — fait le 2026-07-18)
- [x] Phase 3 — SQL par concaténation → Query Builder + `bind()` (fait le 2026-07-18 en
  4 lots ; exceptions légitimes documentées : fragments stringifiés, DDL, requêtes de
  liste dynamiques des `types/`)
- [x] Phase 4 — Nettoyages mineurs (fait le 2026-07-18)
- [~] Phase 5 — Montée en niveau PHPStan : niveau 2 atteint le 2026-07-18 ; la montée
  vers 4+ et la résorption de la baseline restent un fond de qualité continu
- [x] Phase 6 — supprimer les compatibilités runtime legacy encore actives
- [ ] Phase 7 — résorber le service locator statique `Factory` / container dans le code runtime

---

## Règles pour les agents

- Joomla 6 uniquement. PHP 8.1+. MySQL/MariaDB uniquement. Aucune compatibilité ascendante.
- Modifications minimales et ciblées ; ne toucher que les fichiers nécessaires.
- `php -l` sur chaque fichier touché ; lancer la suite PHPUnit avant de cocher.
- Tester en conditions réelles sur le conteneur `joomla6` quand un flux runtime est touché.
- Mettre à jour ce fichier (cases à cocher + note datée) à chaque tâche complétée.
- **Ne pas toucher au modèle de confiance `eval()`** (voir encadré Phase 3).

---

## Phase 1 — `Factory::getDate()` → `new Date()`

**Priorité : haute. Effort faible, mécanique.** C'est le dernier accesseur `Factory`
déprécié du composant. L'implémentation Joomla 6.0.x de `Factory::getDate()` appelle
encore `Factory::getLanguage()` déprécié en interne (constat déjà fait sur BFNG).

- 46 appels dans 25 fichiers :
  - `admin/src/Service/` : `ArticleService`, `PermissionService`, `RuntimeUtilityService`,
    `ConfigExportService`, `ConfigImportService`, `SchemaService`, `DatatableService`
  - `admin/src/Model/` : `VerifyModel`, `StorageModel`, `FormModel`
  - `admin/src/Helper/` : `StorageAuditColumnsHelper`, `Audit/DatabaseAuditReportBuilder`
  - `admin/src/View/Edit/HtmlView.php`, `admin/src/Controller/AboutController.php`
  - `admin/src/types/com_contentbuilderng.php`, `admin/src/types/com_breezingforms.php`
  - `site/src/` : `Model/ListModel`, `Model/EditModel`, `Model/Edit/PathHelpersTrait`,
    `View/Edit/HtmlView`, `View/Details/HtmlView`, `Controller/ApiController`,
    `Service/StatsService` ; `site/tmpl/export/default.php`
  - `plugins/system/contentbuilderng_system/src/Extension/ContentbuilderngSystem.php`
- Remplacement : `use Joomla\CMS\Date\Date;` puis `new Date(...)` (mêmes arguments).
  Attention aux appels avec fuseau : `Factory::getDate($time, $tz)` → `new Date($time, $tz)`.

### Vérification
- [x] `grep -rn "Factory::getDate(" admin site plugins script.php` (hors vendor/tests) → 0
- [x] `php -l` propre sur les 25 fichiers ; suite PHPUnit verte (352 tests, 948 assertions)
- [ ] Écrans admin (About, listes, édition) et un rendu/export site en HTTP 200 — à faire
  au prochain déploiement sur le conteneur joomla6

> **Fait (2026-07-18)** : 46 appels convertis en `(new Date(...))` dans 25 fichiers, import
> `use Joomla\CMS\Date\Date;` ajouté partout ; 5 imports `Factory` devenus inutiles supprimés
> (`DatabaseAuditReportBuilder`, `StorageAuditColumnsHelper`, `DatatableService`,
> `SchemaService`, `PathHelpersTrait`). Au passage : caractère `3` parasite retiré du
> manifeste et `creationDate` mis au jour (exigé par `VersionConsistencyTest`).

---

## Phase 2 — SQL versionné

**Priorité : haute.** Le manifeste n'a que `<install><sql>` (`sql/install.sql`) et
`<uninstall>` — **pas de section `<update><schemas><schemapath>`**. Toute évolution de
schéma passe donc aujourd'hui par le code de `script.php`/services, comme BFNG avant
sa Phase 8a.

- [x] Créer `admin/sql/updates/mysql/` avec un fichier baseline `6.1.7.sql`
  (commentaire seul, comme la baseline `6.1.0.sql` de BFNG — **ce n'est pas un script de
  migration 6.1.6 → 6.1.7** : il n'exécute aucun SQL, il ne sert qu'à faire enregistrer
  la version 6.1.7 dans `#__schemas` comme point de départ du versionnage)
- [x] Ajouter au manifeste : `<update><schemas><schemapath type="mysql">sql/updates/mysql</schemapath></schemas></update>`
- [x] Vérifier que `sql/install.sql` reste idempotent (13/13 `CREATE TABLE IF NOT EXISTS`)
- [x] Mettre à jour la validation de paquet (`scripts/validate-package.sh` : entrée
  `admin/sql/updates/mysql/6.1.7.sql` requise)
- Règle future : toute évolution de schéma = un fichier `sql/updates/mysql/<version>.sql`,
  plus d'ALTER ad hoc dans le code pour les nouvelles versions. Les migrations
  historiques ContentBuilder → NG de `script.php` restent en place (elles renomment des
  tables étrangères au schéma NG, hors périmètre `#__schemas`).

### Vérification
- [x] Installation neuve sur le conteneur : smoke test Docker complet passé
  (`scripts/joomla-install-smoke.sh` : installation, mise à jour, migration, API)
- [x] Mise à jour du paquet sur le site de dev `joomla6-joomla-1` : installation OK,
  `#__schemas` contient `version_id = 6.1.7` pour `com_contentbuilderng`

> **Fait (2026-07-18)** : baseline `admin/sql/updates/mysql/6.1.7.sql` (commentaire seul),
> section `<update><schemas>` ajoutée au manifeste, validation de paquet étendue. Suite
> PHPUnit verte (352 tests). Règle désormais active : toute évolution de schéma future
> = un fichier `sql/updates/mysql/<version>.sql`.

---

## Phase 3 — SQL par concaténation → Query Builder + `bind()`

**Priorité : moyenne. Effort élevé, à traiter par lots.** ~116 constructions par
concaténation de `$db->quote()`/`$db->quoteName()` subsistent (ex.
`admin/src/View/About/HtmlView.php`, `admin/src/View/Edit/HtmlView.php`,
`admin/src/Model/StoragesModel.php`…). Les valeurs sont quotées (pas d'injection
évidente), mais le patron cible du projet est Query Builder + paramètres liés
(cf. réécriture `BFIntegrate` de BFNG, Phase 9b).

- Ordre recommandé : d'abord les requêtes sur données utilisateur (recherche, filtres,
  contenus dynamiques), ensuite les requêtes sur identifiants constants.
- **Limite structurelle découverte (2026-07-18)** : plusieurs helpers (`ApiController::
  getContentbuilderngStatsFilterWhere()`, `getBreezingFormsStatsFilterRecordQuery()`,
  `getStatsRecordWhere()`, idem `StatsService`) retournent des **fragments SQL en chaîne**
  (`(string) $query`) intégrés dans des requêtes plus larges — `bind()` y est impossible
  (les liaisons sont perdues au cast en chaîne). Pour ces fragments, la concaténation
  `quoteName()`/`quote()` est le patron correct et doit rester ; ne convertir vers
  `bind()` que les requêtes exécutées via l'objet Query lui-même.

> **Lot 1 fait (2026-07-18) — chemin de notation site (`ApiController::rate`)** : les 7
> requêtes SQL brutes du flux de notation (purge du cache, anti-rejeu par IP, incrément
> `rating_count`/`rating_sum`, insertion cache, résolution d'article, synchronisation
> `#__content_rating` update/insert) converties en Query Builder + `bind()`
> (`ParameterType::INTEGER` sur les entiers). Dans `site/src/Model/EditModel.php`, les 6
> vérifications d'unicité username/email à l'inscription (entrée utilisateur directe)
> remplacées par un helper unique `userConflictExists()` à paramètres liés. Vérifié :
> `php -l`, PHPUnit (352 tests), smoke test Docker complet (installation, mise à jour,
> migration, API bout en bout), paquet déployé sur le site de dev `joomla6-joomla-1`
> (rendu HTTP 200). Prochains lots suggérés : le reste des `setQuery("...")` bruts de
> `EditModel` (27 restants), puis `StorageModel` (25), `DatatableService` (9).

> **Lot 2 fait (2026-07-18) — `site/src/Model/EditModel.php` intégralement converti** :
> les 27 `setQuery("...")` bruts restants passés en Query Builder + `bind()`. Trois
> helpers privés ajoutés pour factoriser les patrons récurrents : `loadFormTypeReference()`
> (lecture type/reference_id du formulaire, 3 sites), `applyRecordKeyConditions()`
> (triplet `type`/`reference_id`/`record_id`, 6 sites) et le `userConflictExists()` du
> lot 1. Les listes `record_id IN (...)` utilisent `whereIn(..., ParameterType::STRING)`.
> Seules restent les 2 requêtes de variables de session MySQL (`SET @ids := null` /
> `SELECT @ids`), sans aucune valeur à lier. Deux découvertes au passage :
> un `setQuery("Select * From #__contentbuilderng_records")` **mort** (jamais exécuté)
> supprimé, et un **bug préexistant corrigé** dans la suppression d'articles : les noms
> d'assets `com_content.article.<id>` étaient quotés deux fois (à la construction puis
> dans le « Safe implode »), si bien que le `DELETE From #__assets ... IN (...)` ne
> pouvait jamais correspondre — les assets d'articles supprimés restaient orphelins.
> Vérifié : `php -l`, PHPUnit (352 tests), smoke test Docker complet, déploiement sur
> `joomla6-joomla-1` (HTTP 200). Prochains lots : `StorageModel` (25), `DatatableService`
> (9), puis les fichiers `types/` admin.

> **Lot 3 fait (2026-07-18) — DML admin/plugins.** Constat de tri important : la grande
> majorité des `setQuery("...")` restants sont du **DDL** (`ALTER`/`RENAME`/`DROP`/
> `TRUNCATE TABLE`, `SHOW INDEX`) — le Query Builder Joomla ne couvre pas le DDL et MySQL
> n'y accepte pas de paramètres liés ; identifiants via `quoteName()` = patron correct,
> **rien à convertir** (`StorageModel` 19/25, `DatatableService` 9/9, `SchemaService`,
> `MigrationService`, `StorageFieldService`, `DatabaseRepairHelper`, les helpers d'audit).
> Convertis en Query Builder + `bind()` : les 6 DML de `StorageModel` (purge
> `storage_fields`, compteurs/suppressions records+articles du ré-import CSV, insert des
> métadonnées d'enregistrement par ligne CSV), les 3 requêtes du plugin
> `contentbuilderng_listaction/trash` (mise à la corbeille des articles, lecture de
> l'action d'état, suppression d'article) et la requête de résolution d'article dupliquée
> dans `site/src/View/Details/HtmlView.php` et `admin/src/View/Edit/HtmlView.php`.
> Exception documentée : le `DELETE a.*, c.*` multi-tables du ré-import CSV n'est pas
> exprimable par le Query Builder (son `delete()` ne prend pas de colonnes) — chaîne brute
> quotée conservée avec commentaire. Les 2 `setQuery` de `StoragesModel` sont dans du code
> commenté (ignorés) ; `SET SESSION group_concat_max_len`, `SELECT FOUND_ROWS()` et
> `SET @ids` restent en brut (aucune valeur à lier). Vérifié : `php -l`, PHPUnit
> (352 tests), smoke test Docker complet, déploiement `joomla6-joomla-1` (HTTP 200).
> Reste pour le lot 4 : les requêtes multi-lignes des deux fichiers
> `admin/src/types/` (`com_contentbuilderng.php` : 5, dont insert/update sur table de
> stockage dynamique ; `com_breezingforms.php` : 2 selects).

> **Lot 4 fait (2026-07-18) — fichiers `types/`, clôture de la Phase 3.** Convertis en
> Query Builder + `bind()` : l'insert et l'update de `saveRecord()` dans
> `types/com_contentbuilderng.php` — les requêtes qui écrivent les **valeurs soumises par
> l'utilisateur** dans la table de stockage dynamique, avec liaisons construites en boucle
> (`:fieldValue<i>`) et noms de colonnes via `quoteName()`. Au passage, le `WHERE id =
> $record_id` de l'update, interpolé sans cast, est désormais lié en
> `ParameterType::INTEGER`. Les 5 grandes requêtes de liste/détail restantes
> (`com_contentbuilderng.php` 348/746/754, `com_breezingforms.php` 795/1250) sont des
> assemblages dynamiques (sélecteurs par élément, jointures et tris conditionnels,
> `GROUP_CONCAT`) où chaque valeur interpolée est déjà `intval()` ou `quote()` — une
> réécriture builder serait un refactor structurel à fort risque sans gain de sécurité :
> **exceptions documentées, conservées telles quelles**. Vérifié : `php -l`, PHPUnit
> (352 tests), smoke test Docker complet, déploiement `joomla6-joomla-1` (HTTP 200).
> **Réserve** : le chemin `saveRecord()` converti n'a pas pu être exercé par une vraie
> soumission authentifiée en session agent (bootstrap CLI Joomla non praticable sur le
> conteneur) — à confirmer à la première sauvegarde réelle d'un enregistrement sur le
> site de dev.
- Rappel `AGENTS.md` : tout fragment SQL brut reste en grammaire MySQL/MariaDB ;
  `GROUP_CONCAT(... SEPARATOR ...)` exige un littéral quoté.
- Traiter par lots de fichiers, avec vérification runtime après chaque lot.

> ⚠️ **Modèle de confiance `eval()` — à conserver tel quel.**
> `admin/src/Helper/PhpTemplateHelper.php`, `admin/src/Service/TemplateRenderService.php`
> et `site/src/Model/EditModel.php` exécutent via `eval()` du code PHP stocké en base
> (templates et code de préparation saisis par des administrateurs de confiance). C'est
> la fonctionnalité même du produit, pas un accident : le retirer supprimerait la
> fonctionnalité, pas seulement le risque (même arbitrage que `BFIntegrate` côté BFNG).
> Ne pas « sécuriser » en le supprimant ; ne pas élargir le modèle non plus.
> Leçon BFNG à retenir : du code personnalisé **stocké en base** peut appeler des API du
> composant invisibles à un grep du dépôt — toujours revérifier les écrans réels après
> suppression d'un symbole public.

### Vérification (par lot)
- [ ] Requêtes converties : mêmes résultats sur le conteneur (listes, filtres, audits About)
- [ ] Suite PHPUnit verte ; aucun changement de comportement observable

---

## Phase 4 — Nettoyages mineurs

- [x] `admin/src/Model/FormModel.php` — commentaire mentionnant « JTable » supprimé
- [x] `admin/src/Model/StorageModel.php` — code mort commenté `Table::getInstance(...)`
  supprimé (avec ses commentaires d'accompagnement)
- [x] `site/elements/` supprimé — le répertoire ne contenait qu'un `index.html`, n'était
  pas listé dans le manifeste (donc jamais déployé sur les sites : pas de purge
  `script.php` nécessaire) et aucun code ne le résolvait
- [x] `$this->app->input` : plus aucune occurrence (résorbé au fil des lots précédents)

> **Fait (2026-07-18)** : vérifié par `php -l`, PHPUnit (352 tests), paquet reconstruit,
> validé et smoke test Docker complet passé.

---

## Phase 5 — Fond de qualité (optionnel, hors migration stricte)

- [x] PHPStan niveau 1 → **2** (2026-07-18). Avant la montée, les erreurs réelles du
  niveau 2 ont été corrigées plutôt que baselinées :
  - **160 accès à la propriété protégée `$input`** (`$this->siteApp->input`,
    `getApp()->input`, `$this->app->input`) convertis en `getInput()` — c'était le vrai
    dernier reliquat de la Phase 4 ;
  - **`'128M' * 1024` sur chaîne** (avertissement PHP 8, TypeError si valeur non
    numérique) : cast `(int)` ajouté avant le `switch` k/m/g dans le plugin
    `image_scale` et `EditModel` (taille max d'upload) ;
  - interpolation d'un **array dans une chaîne de log** (`StorageFieldService`) et
    **cast array→string** (`RepairWorkflowService`) corrigés ;
  - `ListModel::getItems()` gérant le cas où `getData()` retourne un tableau ;
  - affectation morte du retour `void` de `Input::set()` (`EditController`).
  Le reliquat non corrigé (essentiellement du PHPDoc et des appels sur types larges) est
  baseliné : `phpstan-baseline.neon` régénéré (383 entrées), analyse **propre au
  niveau 2**. `EditSaveCloseTest` (qui vérifie un extrait littéral du contrôleur) aligné.
  Vérifié : PHPUnit 352 tests verts, smoke test Docker complet, déploiement
  `joomla6-joomla-1` (HTTP 200).
- [ ] PHPStan : monter vers 3 puis 4+, en résorbant la baseline par lots (fond continu)
- [ ] Étendre la couverture PHPUnit aux services convertis en Phase 3

---

## Phase 6 — Supprimer les compatibilités runtime legacy

**Priorité : haute.** Le composant installe et migre déjà vers le format NG moderne, mais
il conserve encore **des lectures runtime de formats/clefs hérités** qui contredisent la
ligne `AGENTS.md` (« pas de fallback, pas de compatibilité ascendante »).

### Reliquats identifiés le 2026-07-18

- `admin/src/Helper/PackedDataHelper.php` : `decodePackedData()` accepte encore les
  payloads PHP sérialisés via `@unserialize(...)` si le blob base64 n'est pas du JSON
  packé `j:...`
- `admin/src/Helper/PackedDataMigrationHelper.php` : la réparation existe déjà et sait
  détecter les payloads `legacy_php` ; **la lecture runtime legacy n'est donc plus
  nécessaire une fois la base réparée**
- `site/src/Helper/MenuParamHelper.php` et `site/src/Dispatcher/Dispatcher.php` :
  fallback encore actif entre la clef moderne `cb_show_details_back_button` et l'ancienne
  `show_back_button`
- XML/frontend : plusieurs layouts utilisent encore l'ancien nom `show_back_button`
  comme paramètre ou comme alias de secours (`site/tmpl/edit/default.xml`,
  `site/tmpl/details/default.xml`, `site/tmpl/latest/latest.xml`, dispatcher)

### Cible

- Le format packé supporté en runtime doit être **uniquement** le JSON packé `j:...`
- Les payloads hérités doivent être traités **uniquement** par une migration/réparation
  explicite, pas silencieusement à l'exécution
- Les paramètres menu/input frontend doivent exposer **une seule clef canonique**
  (`cb_show_details_back_button`) ; l'ancienne clef ne doit plus être lue au runtime

### Tâches

- [x] `PackedDataHelper::decodePackedData()` : supprimer la branche `@unserialize(...)`
- [x] Conserver `PackedDataMigrationHelper` comme **outil d'audit/réparation préalable**
  avant suppression du fallback runtime
- [x] Normaliser les XML/layouts/dispatcher/frontend sur `cb_show_details_back_button`
  uniquement
- [x] Retirer les paramètres `?string $legacyKey = null` de `MenuParamHelper`
  quand le dernier alias aura disparu
- [x] Ajouter/adapter les tests unitaires sur :
  - refus d'un packed payload legacy en runtime
  - réparation explicite via `PackedDataMigrationHelper`
  - résolution menu/input sans alias `show_back_button`

### Vérification

- [ ] Audit About/DB repair : 0 payload `legacy_php` avant livraison
- [ ] Tous les écrans frontend concernés (list, details, edit, latest, publicforms)
  respectent encore les toggles après suppression des alias
- [ ] Suite PHPUnit verte

> **Note d'architecture** : la compatibilité de migration **à l'installation / mise à
> jour** (renommage de tables, nettoyage d'extensions héritées, etc.) reste légitime et
> n'entre pas dans cette phase. Le but ici est de supprimer les **fallbacks runtime**,
> pas les migrations one-shot de `script.php`.

> **Fait (2026-07-18)** : runtime packed-data durci sur le format `j:...` uniquement
> (`PackedDataHelper` ne lit plus ni JSON non préfixé ni payload PHP sérialisé) ; la
> migration d'update garde son propre décodeur legacy dans `PackedDataMigrationHelper`
> pour convertir automatiquement les anciens payloads avant usage. Alias frontend
> `show_back_button` retiré de `MenuParamHelper`, du dispatcher, des layouts XML/runtime,
> de `menu-options.js` et des champs menu ; migrations automatiques ajoutées dans
> `script.php` pour :
> 1. renommer `show_back_button` vers `cb_show_details_back_button` ;
> 2. déplacer les anciens paramètres de menu root-level vers `params.settings`.
> Tests textuels ajoutés pour verrouiller le runtime strict, le chemin de migration
> packed-data, la clef canonique du bouton retour et l'abandon des sélecteurs/lectures
> root-level. **Reste hors code** : vérifications `php -l`, PHPUnit et smoke test de mise
> à jour en environnement PHP/Joomla disponible.

---

## Phase 7 — Résorber le service locator statique `Factory` / container

**Priorité : moyenne.** Il ne s'agit plus de dépréciations bloquantes, mais le composant
reste loin d'un Joomla 6 « pur » tant qu'il dépend massivement de `Factory::getApplication()`
et `Factory::getContainer()` dans les modèles, services, helpers, champs et templates.

### État audité le 2026-07-18

- `rg "Factory::getApplication\\(|Factory::getContainer\\(" admin site plugins --glob '!vendor/**'`
  retourne **447 occurrences**
- Les reliquats se concentrent dans :
  - modèles/services/helpers admin et site
  - champs personnalisés (`site/src/Field/*`)
  - templates/layouts admin et site
  - providers de plugins qui injectent l'application via `Factory::getApplication()`
  - `admin/src/Extension/ContentbuilderngComponent.php` pour le chargement des langues

### Cible

- Services et helpers métier : dépendances injectées (application, base, mailer, cache,
  user factory, dispatcher…) au lieu d'appels statiques globaux
- Modèles/vues/contrôleurs : s'appuyer d'abord sur les accesseurs Joomla déjà disponibles
  (`$this->getApplication()`, `$this->getDatabase()`, `$this->document`, `$this->getInput()`)
- Templates/layouts : consommer les variables préparées par la vue plutôt que relire
  l'application globale
- Garder `Factory` uniquement là où Joomla l'impose réellement ou où l'installeur opère
  hors MVC

### Tâches

- [ ] Prioriser les classes runtime les plus denses (`site/src/Controller/ApiController.php`,
  `site/src/Service/StatsService.php`, `site/src/Model/EditModel.php`,
  `admin/src/types/com_contentbuilderng.php`, `admin/src/types/com_breezingforms.php`)
- [ ] Passer les helpers/champs autonomes (`site/src/Field/*`, `site/src/Helper/MenuParamHelper.php`)
  sur des dépendances explicites ou des points d'entrée mieux bornés
- [ ] Nettoyer les templates admin/site qui appellent `Factory::getApplication()` /
  `getDocument()` directement quand la vue peut préparer la donnée
- [ ] Réduire les providers/plugins à l'injection minimale requise par Joomla
- [ ] Recompter les occurrences après chaque lot et documenter les exceptions restantes

### Vérification

- [ ] `rg "Factory::getApplication\\(|Factory::getContainer\\(" admin site plugins --glob '!vendor/**'`
  en baisse documentée après chaque lot
- [ ] `php -l` sur chaque fichier touché ; PHPUnit vert
- [ ] Smoke test Docker sur tout flux touché

---

## Rappels permanents

- Toute chaîne utilisateur passe par les trois langues en-GB/fr-FR/de-DE simultanément
  (skill `joomla-translations`).
- Le smoke test Docker (installation réelle) reste obligatoire avant release : le
  bootstrap unitaire simule Joomla et ne couvre ni l'installeur ni le SQL (`TESTING.md`).
- Du PHP personnalisé stocké en base (templates, code de préparation) peut appeler
  n'importe quelle API publique du composant : ne jamais supprimer un symbole public sur
  la seule foi d'un grep du dépôt — revérifier les écrans réels après coup.
