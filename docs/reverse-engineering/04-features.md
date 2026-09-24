# 04 — Catalogue des fonctionnalités

## 0. Méthodologie, sources et corrections appliquées à la fusion

Ce catalogue fusionne trois brouillons de rétro-analyse produits séparément
sur `/home/user/com_contentbuilderng` (branche de travail courante), en
éliminant les redondances entre eux et en tranchant les points où l'un des
brouillons corrigeait un autre :

- brouillon back-office (`admin/src/*`, `script.php`) — 16 fonctionnalités ;
- brouillon front-office (`site/src/*`, `media/js/*`) — 8 fonctionnalités +
  inventaire AJAX ;
- brouillon plugins (`plugins/*`, 17 plugins) — 9 groupes de plugins.

**Qualification des affirmations**, reprise sans changement des brouillons
sources et de la convention déjà en usage dans
`docs/reverse-engineering/03-data-model.md` et suivants :

- **Fait observé** : lu directement dans le code, référence `fichier:ligne`.
- **Comportement déduit** : assemblé à partir de plusieurs faits observés.
- **Hypothèse** : interprétation plausible, non entièrement vérifiée.
- **Zone inconnue** : point non tranché, incohérence potentielle, ou dette
  technique à signaler.

Ce document renvoie à `03-data-model.md` (colonnes/tables), `06-api-contracts.md`
(contrats AJAX/API détaillés), `07-security.md` (ACL, CSRF, upload, XSS),
`08-configuration.md` (paramètres `#__extensions.params`), `09-business-rules.md`
(règles métier fines) et `10-dependencies.md` (bibliothèques tierces) plutôt
que de reproduire leur détail colonne par colonne ou paramètre par paramètre.

### Corrections connues appliquées lors de la fusion (à ne pas reproduire sous leur forme d'origine)

1. **Soumission front d'un enregistrement** : traitée par
   `site/src/Model/EditModel.php::store()` (`:767-2294`), **pas** par
   `_buildQuery()` (méthode triviale de 16 lignes, `:356-371`, sans rapport —
   construit uniquement `SELECT * FROM #__contentbuilderng_forms WHERE id = …`).
   `register()` (`:2295-2557`) est appelée depuis `store()`. Toute référence à
   `_buildQuery()` comme « méthode de soumission » dans une documentation
   antérieure est erronée et corrigée ici.
2. **`StorageModel::delete()`** (`admin/src/Model/StorageModel.php:1212-1274`)
   **ne cascade pas** la suppression de `records`/`list_records`/`articles` —
   elle ne supprime que les `storage_fields` du storage, la ligne `storages`
   elle-même, puis `DROP TABLE` si `bytable=0`. Le nettoyage cascade de
   `records`/`list_records`/`articles` (+ purge des articles Joomla liés)
   existe **séparément**, dans `StorageModel::storeCsv()` (`:1700-1748`),
   déclenché par l'option « vider les enregistrements existants avant import »
   d'un **réimport CSV**, pas par la suppression du storage.
3. **Plugins `trash`/`untrash`** (`plugins/contentbuilderng_listaction/*`) —
   ils ne modifient **jamais** `list_records.state_id` (écrit par
   `EditModel`, le composant lui-même) ; ils synchronisent
   **`#__content.state`** (l'article Joomla lié), en réaction au routage
   `list_states.action`. Deux colonnes d'état, deux acteurs distincts.
4. **`site/src/Field/*`** (11 fichiers) — ce ne sont **pas** des types de
   champ pour l'écran d'édition d'un enregistrement. Ce sont des **types de
   champ JForm Joomla** utilisés dans l'écran d'édition d'un **élément de
   menu** pointant vers ce composant (sélecteur de vue, catégorie, thème,
   etc.), rendus côté **back-office** de Joomla (Menus → Éditer un élément)
   bien que la classe PHP réside dans `site/src/Field` pour des raisons
   d'autoload PSR-4 propres au composant. Voir fonctionnalité 38.

### Contradictions entre brouillons non tranchées par cette fusion

Signalées explicitement à chaque endroit concerné, et récapitulées en fin de
document (§ « Contradictions et zones d'incertitude non tranchées ») :
degré de duplication réel `DatatableService` ↔ `StorageModel` (DDL storage),
point d'import exact du groupe `contentbuilderng_listaction` avant
`onAfterArticleCreation`, atomicité de `ConfigImportService::applyPayload()`,
localisation précise du contrôleur qui sert `action=cbstats` (résolu en
partie par recoupement avec `06-api-contracts.md`, voir fonctionnalité 24).

---

## Sommaire

**A. Installation, cycle de vie & administration système**
1. [Installation / mise à jour / désinstallation](#1-installation--mise-à-jour--désinstallation)
2. [Menu admin & navigation (quick tasks)](#2-menu-admin--navigation-quick-tasks)
3. [Écran d'accueil du composant & zone floue `view=edit`](#3-écran-daccueil-du-composant--zone-floue-viewedit)
4. [About / Audit / Repair workflow / Journal](#4-about--audit--repair-workflow--journal)
5. [Config Transfer (export/import de configuration)](#5-config-transfer-exportimport-de-configuration)

**B. Storages & données**
6. [Gestion des Storages (CRUD, import CSV/XLSX, index)](#6-gestion-des-storages-crud-import-csvxlsx-index)
7. [Storage Wizard](#7-storage-wizard)
8. [Storage Fields](#8-storage-fields)
9. [Datatable (création/synchronisation)](#9-datatable-créationsynchronisation)

**C. Formulaires & vues (Forms)**
10. [Gestion des Forms (vues)](#10-gestion-des-forms-vues)
11. [Article Settings](#11-article-settings)
12. [Elements et Element Options](#12-elements-et-element-options)
13. [List States](#13-list-states)
14. [Utilisateurs par vue & permissions applicatives](#14-utilisateurs-par-vue--permissions-applicatives)
15. [Titlesets ({cbstats})](#15-titlesets-cbstats)

**D. Frontend — affichage et navigation**
16. [Affichage liste (`list`)](#16-affichage-liste-list)
17. [Affichage détail (`details`)](#17-affichage-détail-details)
18. [Formulaires publics (`publicforms`)](#18-formulaires-publics-publicforms)
19. [Aide contextuelle (`cblisthelp`/`cbstatshelp`)](#19-aide-contextuelle-cblisthelpcbstatshelp)

**E. Soumission & édition front**
20. [Création/édition d'un enregistrement (`edit`)](#20-créationédition-dun-enregistrement-edit)
21. [Upload de fichier](#21-upload-de-fichier)
22. [Suppression d'un enregistrement](#22-suppression-dun-enregistrement)
23. [Vérification / paiement (`verify`)](#23-vérification--paiement-verify)

**F. API et export**
24. [API/AJAX interne générique (`ApiController`) et notation](#24-apiajax-interne-générique-apicontroller-et-notation)
25. [Export de données (XLSX)](#25-export-de-données-xlsx)

**G. Rendu de contenu — plugins de contenu**
26. [`{CBList}` — liste embarquée](#26-cblist--liste-embarquée)
27. [`{CBStats}` — statistiques](#27-cbstats--statistiques)
28. [`{CBDownload}`](#28-cbdownload)
29. [`{CBImageScale}`](#29-cbimagescale)
30. [Permission Observer](#30-permission-observer)

**H. Workflow d'état de liste et extension de soumission**
31. [États de liste et routage d'action — `trash`/`untrash`](#31-états-de-liste-et-routage-daction--trashuntrash)
32. [Contrat de soumission — groupe `contentbuilderng_submit`](#32-contrat-de-soumission--groupe-contentbuilderng_submit)

**I. Thèmes**
33. [Thèmes visuels (blank/dark/khepri/thoth)](#33-thèmes-visuels-blankdarkkhephrithoth)

**J. Plugin système**
34. [Plugin système `contentbuilderng_system`](#34-plugin-système-contentbuilderng_system)

**K. Anomalies structurelles et code potentiellement mort**
35. [`site/src/Controller/Dispatcher.php` — code mort probable](#35-sitesrccontrollerdispatcherphp--code-mort-probable)
36. [`view=edit` admin — classe `EditModel` admin introuvable](#36-viewedit-admin--classe-editmodel-admin-introuvable)
37. [`MenuService::createBackendMenuItem15/16/3` — code mort probable](#37-menuservicecreatebackendmenuitem151633--code-mort-probable)
38. [Correction terminologique — `site/src/Field/*`](#38-correction-terminologique--sitesrcfield)

[Contradictions et zones d'incertitude non tranchées](#contradictions-et-zones-dincertitude-non-tranchées)

---

# A. Installation, cycle de vie & administration système

## 1. Installation / mise à jour / désinstallation

**Nom** : Cycle de vie de l'extension (installation, mise à jour, désinstallation) — `script.php`.

**Objectif** : installer/mettre à jour de façon idempotente les 13 tables, les
17 plugins, le menu admin, les répertoires média, et réparer automatiquement
le schéma et les extensions historiques (« self-heal ») à chaque passage de
l'installeur Joomla, sans perte de données.

**Acteurs** : Super Administrateur Joomla, via *Extensions › Gérer ›
Installer* (upload ZIP) ou le gestionnaire de mises à jour Joomla.

**Préconditions** : PHP ≥ 8.3, Joomla ≥ 6.0 (**Fait observé**, `script.php:70-71`,
`checkRequirements()`). Droits d'écriture sur les répertoires du composant
avant copie de fichiers (`checkInstalledFilePermissionsBeforeCopy()`, `:588`).

**Déclencheur** : callback Joomla standard `preflight()` → (copie fichiers par
le noyau) → `install()`/`update()` → `postflight()` ; `uninstall()` séparément.

**Flux nominal (mise à jour)** :
1. **Construction** (`:152-183`) : instancie `InstallerService`,
   `MigrationService`, `PluginInstallerService`, `SchemaService` (chargées via
   `require_once` conditionnel à `class_exists(..., false)` pour éviter une
   redéclaration de classe si l'autoload de l'ancienne version est déjà en
   mémoire — piège Joomla documenté en commentaire, `:44-55`). Démarre le
   logger applicatif.
2. **`preflight('update', …)`** (`:188-258`) : purge les fichiers de langue
   `*contentbuilder*` obsolètes, désactive les plugins legacy par ordre de
   priorité, signale les collisions de tables legacy/cible, renomme les
   tables legacy (`contentbuilder_*` → `contentbuilderng_*`, constante
   `LEGACY_TABLE_RENAMES`, `:104-131`), migre les lignes `#__extensions`
   legacy (`contentbuilder`/`com_contentbuilder`/`com_contentbuilder_ng` →
   `com_contentbuilderng`).
3. Joomla copie les fichiers du paquet.
4. **`update()` → `installAndUpdate(..., 'update')`** (`:284-306,539-552`) —
   réserve le travail lourd à `postflight()` (« stable installation state »).
5. **`postflight('update', …)`** (`:350-534`) : manifeste canonique → plugins
   legacy désactivés → nettoyage répertoires/fichiers/langues obsolètes →
   média/upload dir → **self-heal schéma** (`updateDateColumns()`,
   `SchemaService::ensureUniqueConstraints()`, `normalizeExternalStorageModes()`,
   colonnes `forms`/`elements`/`storage_fields` manquantes,
   `migratePackedPayloadsToModernFormat()`) → normalisation menu
   (`updateMenuLinks()`) → **(ré)installation des 17 plugins embarqués**
   (`ensurePluginsInstalled($source, forceUpdate=true)`, `activatePlugins()`)
   → suppression des 5 plugins de validation « core » désormais internalisés
   (`removeCoreValidationPlugins()`) → (branche `update`) retrait des thèmes
   dépréciés, du plugin Ping retiré, dédoublonnage extensions plugin/composant,
   retrait de l'ancienne branche de menu `contentbuilder` → normalisation de
   l'ordre des storages → **vérification anti-« mise à jour fantôme »**
   (`verifyInstalledExtensionConsistency()` — DB mise à jour mais fichiers
   jamais copiés) → purge caches/opcache → message de fin avec durée et lien
   direct vers *À propos › Audit* (`COM_CONTENTBUILDERNG_INSTALLATION_AUDIT_REMINDER`,
   `:518-527`).

**Flux alternatifs** :
- **Installation neuve** (`type='install'`) : même squelette, sans les étapes
  conditionnées à `update` (retrait legacy suppose une installation
  préexistante).
- **Désinstallation** (`uninstall()`, `:308-348`) : **conservatrice** —
  supprime uniquement les entrées `#__menu` du composant et s'assure de la
  cohérence du nœud racine du menu admin. Ne touche à aucune donnée métier
  au-delà de `admin/sql/uninstall.sql` (13 `DROP TABLE`) — voir
  `03-data-model.md §17` (tables de storage internes et articles Joomla
  **non supprimés**).

**Gestion des erreurs** : chaque étape critique enveloppée par `$this->safe()`
(`:971-979`, capture `\Throwable`, log + fallback) sauf les étapes réellement
bloquantes (permissions fichiers avant copie, self-heal en « critical
failure »). Si `hasCriticalFailure()` est vrai en fin de `postflight()`, une
`\RuntimeException` est levée (`:485-491`) — échec visible de l'installeur
plutôt qu'un mode dégradé silencieux.

**Données entrantes** : paquet ZIP, type d'opération, `InstallerAdapter $parent`.
**Données sortantes** : 13 tables créées/mises à jour, plugins installés/activés,
`#__menu` admin normalisé, fichier de log applicatif (rotation, `SHARED_LOG_KEEP_FILES=10`).

**Permissions/ACL** : Super Administrateur (contrainte native du gestionnaire
d'extensions Joomla — aucune vérification `authorise()` propre dans `script.php`).

**Effets de bord** : renommage best-effort de tables historiques ; désactivation
(jamais désinstallation) des plugins legacy (politique explicite, `:11-13`,
« DISABLE ONLY, no uninstall ») ; purge de caches et de l'autoloader.

**Tables** : les 13 tables `#__contentbuilderng_*`, `#__extensions` (composant
+ 17 plugins), `#__menu`, `#__schemas` (natif Joomla).

**Classes** : `script.php:com_contentbuilderngInstallerScript`,
`admin/src/Service/{InstallerService,MigrationService,PluginInstallerService,SchemaService}.php`,
`admin/src/Helper/{PackedDataMigrationHelper,PackedDataHelper}.php`.

**Plugins/événements Joomla** : aucun événement capturé par un plugin CBNG —
le script est lui-même le point d'entrée natif. Les 17 plugins du composant
sont (ré)installés/activés depuis ce script.

**JS/AJAX** : aucun.

**Configuration influente** : constantes de classe (`LEGACY_TABLE_RENAMES`,
`LEGACY_NESTED_MENU_SETTING_KEYS`, `SHARED_LOG_KEEP_FILES`) — pas de
`#__extensions.params` (le composant n'a pas encore ses paramètres à ce stade).

**Messages** : `COM_CONTENTBUILDERNG_INSTALLER_ERROR_*`, `_POSTFLIGHT_FAILED`,
message de succès avec durée + lien audit.

**Cas limites** : mise à jour sautant plusieurs versions intermédiaires →
couverte par le self-heal plutôt que par les seules migrations versionnées
(`03-data-model.md §1.2`) ; échec disque de copie de fichiers détecté a
posteriori par `verifyInstalledExtensionConsistency()`, pas bloqué en amont.

---

## 2. Menu admin & navigation (quick tasks)

**Nom** : intégration au menu d'administration Joomla.

**Objectif** : garantir que le composant expose ses écrans (Storages, Forms,
Titlesets, Users, About, …) comme entrées de sous-menu admin natives, y
compris les « quick tasks » (icônes « + » de création directe).

**Acteurs** : système, exécuté automatiquement à l'installation/mise à jour
(fonctionnalité 1) — aucune action manuelle.

**Flux nominal** : `InstallerService::ensureAdminMenuRootNodeExists()`,
`ensureAdministrationMainMenuEntry()`, `ensureSubmenuQuickTasks()`
(`admin/src/Service/InstallerService.php:421-814`), appelées depuis
`script.php::postflight()`.

**Classes** : `admin/src/Service/InstallerService.php`, `admin/src/Service/MenuService.php`.

Voir fonctionnalité 37 pour le code potentiellement mort associé
(`MenuService::createBackendMenuItem15/16/3`).

---

## 3. Écran d'accueil du composant & zone floue `view=edit`

**Nom** : vue par défaut (`view=contentbuilderng`) et lien « Preview/édition
directe d'un enregistrement de storage » (`view=edit`, admin).

**Objectif (vue par défaut)** : point d'entrée du composant quand aucune vue
n'est spécifiée dans l'URL admin.

**Fait observé** : `admin/src/View/Contentbuilderng/HtmlView.php::display()`
(`:20-59`) ne fait que charger les assets CSS de base puis
`parent::display($tpl)` — le template `admin/tmpl/contentbuilderng/default.php`
est **vide de tout contenu** (aucun HTML, aucune requête). **Conclusion** : il
n'existe **aucun tableau de bord fonctionnel** pour ce composant ; les
administrateurs accèdent en pratique aux sous-menus via les quick tasks
(fonctionnalité 2), jamais via cette page neutre.

Le second volet de cette fiche (`view=edit`, lien « Preview » cassé
suspecté) est traité séparément en fonctionnalité 36 pour ne pas mélanger un
comportement normal (page vide, volontaire) et une anomalie potentielle
(classe manquante).

---

## 4. About / Audit / Repair workflow / Journal

**Nom** : écran « À propos » (`view=about`) — version, bibliothèques, plugins
installés, **audit de base de données**, **flux de réparation guidé**,
visualiseur de journal, point d'entrée vers Config Transfer (fonctionnalité 5).

**Objectif** : centraliser le diagnostic et l'auto-réparation du composant, en
complément du self-heal automatique de `script.php`/`SchemaService`
(fonctionnalité 1) — ici, déclenchement **manuel**, à la demande, avec détail
visible avant/après chaque réparation.

**Acteurs** : Administrateur `core.manage` sur `com_contentbuilderng`, vérifié
à **chaque** tâche du contrôleur.

**Déclencheur** : menu *ContentBuilder NG › À propos*, ou lien direct affiché
en fin d'installation/mise à jour (fonctionnalité 1).

**Flux nominal — audit** :
1. `AboutController::runAudit()` (`:759-799`, task `about.runAudit`) →
   `DatabaseAuditHelper::run()` (369 lignes) — agrège **~15 vérificateurs
   spécialisés** (`admin/src/Helper/Audit/*.php`) : index dupliqués, tables
   historiques orphelines, entrées de menu historiques, encodage/collation,
   charges utiles « packées » à migrer, colonnes d'audit de storage
   manquantes, colonnes d'affichage de formulaire manquantes, extensions
   plugin dupliquées, synchronisation des champs BreezingForms, cohérence
   des références d'éléments, doublons de `records`, enregistrements
   BreezingForms orphelins, catégories d'articles générés incohérentes,
   fichiers de langue obsolètes, répertoires temporaires d'installeur
   périmés, noms d'index de storage non conformes, types de colonne de
   storage suspects, tri sur colonnes date/heure invalides, mode debug
   laissé actif en production, incohérences de permissions frontend,
   protection du répertoire d'upload.
2. Résultat en session (`com_contentbuilderng.about.audit`), journalisé,
   résumé affiché (`_AUDIT_SUMMARY_CLEAN` si 0 erreur/avertissement, sinon
   `_SUMMARY_COUNTS`).
3. Réparation en un clic par ligne : `AboutController::repairAuditIssue()`
   (`:160-206`, whitelist `DIRECT_AUDIT_REPAIR_ISSUES`) →
   `RepairWorkflowService::executeStep($issue)` (`match` vers l'un des 17
   helpers, méthode `::repair()`), ré-exécute l'audit, journalise. **Supporte
   l'AJAX** (`isAjaxCall()`/`respondAjax()`).

**Flux alternatifs — réparations à bouton propre** : `repairLegacyStorageIndexes()`,
`repairUploadDirectoryProtection()`, `repairMissingStorageTable()` (recrée
une table de storage interne disparue — recoupe potentiellement
`DatatableController::create()`, fonctionnalité 9, **Zone inconnue** sur le
degré de duplication), `repairFormThemePlugin()`, `repairFormEditableTemplate()`,
`repairFormDetailsTemplate()`, `repairFormTemplates()`, `repairFormUnknownMarker()`,
`repairGeneratedArticleCategories()`, `repairDebugMode()`, `deleteStaleInstallerTemp()`.

**Flux alternatif — Repair Workflow guidé** : `RepairWorkflowService::createWorkflowState()`
(`:67-135`) construit une **checklist séquentielle de 17 étapes**
(`WORKFLOW_STEPS`), avec pré-check par étape (`buildPrechecks()`, marque
`not_required` si `count=0`). État en session
(`WORKFLOW_STATE_KEY='com_contentbuilderng.about.repair_workflow'`, même
patron que le Storage Wizard, fonctionnalité 7) — permet de dérouler les
étapes une à une avec décision explicite plutôt que « tout réparer d'un
coup ». `advanceToNextPendingStep()` saute les étapes déjà `not_required`/`done`.

**Flux alternatif — Journal** : `AboutController::showLog()` (`:805-826`) lit
la fin du fichier de log applicatif (lecture tronquée par `tailBytes`).

**Gestion des erreurs** : chaque tâche capture `\Throwable`, journalise,
affiche un message dédié — jamais de page d'erreur Joomla brute.

**Permissions/ACL** : `core.manage` sur `com_contentbuilderng`, systématique.

**Effets de bord** : potentiellement des `ALTER TABLE`/`DELETE`/renommages
selon le helper — mêmes risques que les DDL manuels de Storage
(fonctionnalité 6), mais ici **automatisés** sur détection de problème ; pas
de sauvegarde automatique préalable identifiée.

**Configuration influente** : `audit_details_template_empty`,
`audit_field_missing_in_edit` (paramètres composant) gatent certains checks
de `FormAuditService` (audit **par formulaire**, onglet Audit d'une vue,
tab12 — **distinct** de `DatabaseAuditHelper`, audit **global** base de
données).

**Classes** : `admin/src/Controller/AboutController.php`,
`admin/src/Service/RepairWorkflowService.php`,
`admin/src/Helper/DatabaseAuditHelper.php`, `admin/src/Helper/Audit/*.php`
(17 helpers), `admin/src/Helper/DatabaseRepairHelper.php`,
`admin/src/Helper/PackedDataMigrationHelper.php`,
`admin/src/Helper/StorageAuditColumnsHelper.php`,
`admin/src/Helper/FormDisplayColumnsHelper.php`,
`admin/src/Helper/PluginExtensionDedupHelper.php`,
`admin/src/View/About/HtmlView.php`.

**Cas limites** : étape de réparation exécutée alors que le pré-check la
marquait déjà `not_required`/`done` → `executeStep()` l'exécute quand même si
appelée directement par id (pas de garde-fou observé).

---

## 5. Config Transfer (export/import de configuration)

**Nom** : écran « Transfert de configuration » (`view=configtransfer`),
accessible depuis *À propos*.

**Objectif** : exporter/importer en JSON tout ou partie de la configuration
applicative (Vues, Storages et dépendances) pour migration/sauvegarde —
**distinct** d'une sauvegarde de base complète : ne couvre que les tables de
`ConfigExportService::EXPORT_SECTIONS`.

**Acteurs** : Administrateur `core.manage` sur `com_contentbuilderng`.

**Déclencheur** : *À propos* → bouton « Config Transfer » → `mode=export|import`.

**Flux nominal — Export** :
1. `ConfigtransferController::export()` (`:46-49`) vérifie `core.manage` et
   redirige vers `view=configtransfer&mode=export` — le contrôleur ne fait
   **que** router, toute la logique métier est dans `AboutController`.
2. `Configtransfer\HtmlView::display()` charge vues/storages disponibles,
   **hydrate la sélection depuis l'état persisté en session**
   (`hydrateSelectionState()`, clé `com_contentbuilderng.configtransfer.selection`)
   — par défaut (première visite) **tout est présélectionné**.
3. Soumission → `AboutController::exportConfiguration()` (`:832-908`, task
   `about.exportConfiguration`, **pas** `configtransfer.export`) :
   `rememberConfigTransferSelection()`, résout les sections effectives
   (`ConfigExportService::resolveEffectiveSections()` — ajoute
   automatiquement les sections dépendantes : `elements`/`list_states`/
   `resource_access` si `forms` sélectionné, `storage_fields` si `storages`
   sélectionné), `buildPayload()`/`buildSummary()`, téléchargement direct
   (`contentbuilderng-config-YYYYMMDD-His.json`) — **jamais stocké côté
   serveur** au-delà du résumé en session.
4. Option « Inclure le contenu des storages » (`shouldExportStorageContent()`) :
   exporte aussi les **lignes de données** des tables `#__<storage.name>`.

**Flux nominal — Import** :
1. `AboutController::importConfiguration()` (`:914-1007`, task
   `about.importConfiguration`) : upload obligatoire, lecture JSON,
   vérification souple du format (`meta.format`, tolère l'absence mais
   rejette une valeur différente de `cbng-config-export-v1`).
2. `ConfigImportService::filterPayload()` restreint aux sections cochées
   **et** aux noms explicitement sélectionnés — jamais « tout ou rien » au
   niveau fichier.
3. `ConfigImportService::applyPayload($payload, $sections, $importMode)` —
   deux modes : **Merge** (défaut, lignes existantes mises à jour par id,
   sans purge préalable) ; **Replace** (pour les `form_id`/`storage_id`
   explicitement ciblés, supprime au préalable les lignes existantes avant
   réinsertion).
4. Résumé (`rows`/`tables`) → message différencié
   (`_IMPORT_CONFIGURATION_SUCCESS` vs `_NO_CHANGES`).

**Zone inconnue** : aucun rollback transactionnel global identifié pour
l'import (`ConfigImportService.php`, 937 lignes, non lue intégralement dans
les brouillons sources) — en cas d'échec en cours d'`applyPayload()`,
l'atomicité n'est pas confirmée.

**Permissions/ACL** : `core.manage`, vérifié par `ConfigtransferController`
(affichage) **et** `AboutController` (actions elles-mêmes).

**Effets de bord** : un import en mode `replace` **supprime** des données
existantes avant réinsertion — opération destructive si le fichier importé
est incomplet/erroné. Aucune sauvegarde automatique préalable.

**Tables** : `#__contentbuilderng_forms`, `elements`, `list_states`,
`resource_access`, `storages`, `storage_fields`, potentiellement
`#__<storage.name>` (contenu).

**Classes** : `admin/src/Controller/ConfigtransferController.php` (routage),
`admin/src/Controller/AboutController.php` (logique métier),
`admin/src/Service/{ConfigExportService,ConfigImportService}.php`,
`admin/src/View/Configtransfer/HtmlView.php`.

**Cas limites** : fichier JSON sans clé `meta.format` → accepté (tolérance) ;
sélection de sections sans aucune vue/storage ciblé → rejetée après
résolution des sections effectives (`_SELECT_EXPORT_TARGET`).

---

# B. Storages & données

## 6. Gestion des Storages (CRUD, import CSV/XLSX, index)

**Nom** : écran « Storage » (liste `storages` + édition `storage`).

**Objectif** : définir une source de données que CBNG lit/écrit directement :
table interne créée par le composant (`bytable=0`), table existante rattachée
en écriture (`bytable=1`), ou table Joomla/tierce connue en lecture seule
(`bytable=2`). Voir `03-data-model.md §2` pour le détail des 3 modes.

**Acteurs** : Administrateur `core.manage`/`core.edit`/`core.create`/`core.delete`
sur `com_contentbuilderng`.

**Déclencheur** : menu *ContentBuilder NG › Storages* → *Nouveau*/clic sur une
ligne, ou redirection depuis le Storage Wizard (`wizard=1` propagé dans les URLs).

**Flux nominal — création, sans import** :
1. `StoragesController::display()` liste (`StoragesModel::getListQuery()`,
   tri `ordering`, colonne calculée « nombre d'enregistrements »).
2. `StorageController::edit()` (`:131-150`) ouvre le formulaire.
3. Soumission → `StorageController::save()` (`:157-549`) :
   - `forwardPostedReturn()` + `checkToken()`.
   - Préremplissage `name`/`title` depuis `bytable` en mode table externe.
   - **Garde-fou longueur de nom** (`:180-201`) : `#__<name>` ≤ 64 caractères
     MySQL une fois préfixé, sinon `COM_CONTENTBUILDERNG_STORAGE_NAME_TOO_LONG`
     **avant** tout `INSERT`.
   - **Garde-fou unicité** (`:203-232`) : `COM_CONTENTBUILDERNG_STORAGE_NAME_DUPLICATE`.
   - Branche sans CSV (`:234-386`) : `parent::save()` (core Joomla) →
     résolution robuste de l'id effectif → **`$model->ensureDataTable($id, $isNew, $oldName)`**
     et **`$model->syncEditedFieldsFromRequest($id)`**. Toute exception DDL
     de ces deux appels est **remontée** comme message d'erreur plutôt
     qu'avalée (durcissement explicite, `:325-329`). Renommage de table
     physique détecté (`getLastDataTableRename()`) → message
     `COM_CONTENTBUILDERNG_STORAGE_TABLE_RENAMED`.
   - Redirection : `apply` → réédition ; sinon `storages.display` (ou
     `view=storagewizard` si `wizard=1`).
4. **`StorageModel::ensureDataTable()`** (`admin/src/Model/StorageModel.php:628-637`)
   → **`syncStorageDataTableOrBytable()`** (`:779-1011`, méthode-clé) :
   - **`bytable=0`** : `RENAME TABLE` si édition avec renommage (`:806-824`) ;
     sinon **`CREATE TABLE`** (colonnes d'audit `id`, `storage_id`, `user_id`,
     `created`, `created_by`, `modified_user_id`, `modified`, `modified_by`,
     `ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci`) + index
     `idx_user_id` (`:834-859`, n'avale **plus** l'exception).
   - **`bytable=1`** (table existante, écriture) : `ALTER TABLE` pour les
     colonnes système manquantes, insertion des métadonnées
     `storage_fields` pour les colonnes physiques non encore connues (type
     par défaut `StorageColumnTypeHelper::DEFAULT_TYPE`), et si `$isNew` :
     `UPDATE ... SET storage_id = :id` sur toute la table et
     **rétro-création d'une ligne `#__contentbuilderng_records`** par ligne
     physique déjà présente (`:962-1001`, `type='com_contentbuilderng'`,
     `published=1`, sans découpe par lots — contrairement à `synchRecords()`
     du plugin système qui traite par paquets de 500).
   - **`bytable=2`** (table externe connue, lecture seule) : **aucun `ALTER`
     ni `UPDATE`** — seule une colonne `id` existante est exigée, son
     absence lève `COM_CONTENTBUILDERNG_STORAGE_BYTABLE2_MISSING_ID`
     (`:1002-1010`).
   - **`ensureSystemFieldMetadata()`** (`:647-761`) crée, pour chaque colonne
     physique système sans ligne `storage_fields`, une ligne **délibérément
     dépubliée** (`published=0`).

**Flux alternatifs** :
- **Import CSV/Excel** (mêmes tâches `save`/`apply`, `csv_file`, `:390-549`) :
  sauvegarde du storage → `ensureDataTable()` (table créée **avant**
  l'import) → `StorageModel::storeCsv($file, $id)` (`:1277-1382`) — lit
  l'en-tête, crée une `storage_field` par colonne retenue, convertit
  XLSX→CSV si besoin (`convertSpreadsheetFileToCsv()`), puis
  `csvFileToTable()` (`:1619-1818`) insère les lignes par lots dans la table
  physique **et** crée les lignes `records` correspondantes. Option
  **« vider les enregistrements existants avant import »**
  (`$options->dropRecords`) : `TRUNCATE` de la table physique, `DELETE`
  ciblé sur `records` (`type='com_contentbuilderng'`, `reference_id=:storageId`),
  puis un `DELETE a.*, c.* ... INNER JOIN #__content` en SQL brut pour purger
  en une passe les liaisons `#__contentbuilderng_articles` **et** les
  articles Joomla générés associés (`:1700-1748`) — fragment MySQL/MariaDB
  explicite non exprimable via le Query Builder. C'est **ici** (pas dans
  `StorageModel::delete()`) que se trouve le nettoyage cascade de
  `records`/`articles` (voir correction §0.2). Résumé
  (`getLastImportSummary()`, lignes importées/lues, colonnes, lignes vides
  ignorées, durée, éventuel nombre d'enregistrements/liaisons supprimés).
- **Prévisualisation d'en-têtes CSV/XLSX** avant import :
  `StorageController::previewHeaders()` (AJAX POST, `:562-580`).
- **Vérification de colonnes manquantes sur une table existante** :
  `StorageController::checkExistingTableColumns()` (AJAX GET, `:588-622`).
- **Index physiques** (onglet Index) : `addindex()`/`deleteindex()`
  (`:764-821`) → `StorageModel::addIndexFromRequest()`/`deleteIndex()`
  (`ALTER TABLE ... ADD/DROP INDEX`) ; `getPhysicalIndexes()` (`:374-428`,
  lecture seule pour `bytable>0`).
- **Suppression d'enregistrements** depuis l'onglet Data :
  `StorageController::deleteRecord()` (`:633-725`) — réservé à
  `bytable ∈ {0,1}` (`bytable=2` refusé,
  `COM_CONTENTBUILDERNG_READONLY_EXTERNAL_STORAGE_MSG`). Délègue à
  `FormSourceFactory::getForm('com_contentbuilderng', $storageId)->delete()`
  puis nettoie `list_records`/`records`/`articles` par `record_id` IN (…).
- **Publication en masse / tri** dans `storages` : `publish()`/`unpublish()`,
  `orderup()`/`orderdown()`/`saveorder()`, `listDelete()`.
- **AJAX inline sur les champs** (grille de champs, onglet Storage) :
  `ajax_addfield()`, `ajax_update_field_type()`,
  `ajax_update_field_required()`, `ajax_update_field_title()`
  (`:1326-1560`, réponses JSON).
- **Duplication d'un storage** : `StoragesController::copy()` (**Zone
  inconnue** : comportement exact vis-à-vis de la table physique/des champs
  non tracé ligne à ligne dans les brouillons sources).

**Correction (voir §0.2)** : `StorageController::delete()`
(`admin/src/Controller/StorageController.php:862-921`) →
`StorageModel::delete($cid)` (`StorageModel.php:1212-1274`) supprime
seulement les `storage_fields`, la ligne `storages`, puis `DROP TABLE` si
`bytable=0` — **aucune requête ne touche `records`/`list_records`/`articles`**
dans cette méthode. Une documentation antérieure affirmant une cascade sur
ces trois tables à la suppression du storage était erronée.

**Gestion des erreurs** : tous les points DDL sensibles remontent l'exception
au lieu de l'avaler silencieusement (durcissement explicite documenté par le
code lui-même) : nom de table trop long, doublon de nom, `CREATE TABLE`
échoué, `bytable=2` sans colonne `id`, `DROP TABLE` échoué à la suppression.

**Permissions/ACL** : `core.manage`/`core.create`/`core.edit`/`core.delete`
sur `com_contentbuilderng` (ACL standard Joomla) ; vérifications explicites
`authorise('core.manage', …)` dans `checkExistingTableColumns()` et
`authorise('core.edit', …)` dans `deleteRecord()`.

**Effets de bord** : DDL direct sur la base (CREATE/ALTER/DROP/RENAME TABLE)
en dehors de toute migration versionnée — irréversible sans sauvegarde en
cas d'erreur (renommage, `DROP COLUMN`, voir fonctionnalité 8). Échec du
`DROP TABLE` à la suppression : métadonnées déjà supprimées, table physique
orpheline (pas de transaction couvrant toute la méthode `delete()`).

**Tables** : `#__contentbuilderng_storages`, `storage_fields`,
`#__<storage.name>`, `records`, `list_records`, `articles`, `#__content`
(purge groupée, réimport avec « vider »).

**Classes** : `admin/src/Controller/{Storage,Storages}Controller.php`,
`admin/src/Model/{Storage,Storages}Model.php`,
`admin/src/Table/{Storage,StorageFields}Table.php`,
`admin/src/Helper/{StorageColumnTypeHelper,StorageSystemFieldHelper,FormSourceFactory}.php`,
`admin/src/types/com_contentbuilderng.php` (delete des lignes physiques).

**JS** : `admin/tmpl/storage/default.php` embarque un script important
(édition inline des champs, ajout/suppression AJAX, bascule CSV/manuel).

**AJAX** : `task=storage.previewHeaders` (POST), `task=storage.checkExistingTableColumns`
(GET), `task=storage.ajax_addfield`, `task=storage.ajax_update_field_type`,
`task=storage.ajax_update_field_required`, `task=storage.ajax_update_field_title`
(POST, JSON en retour).

**Messages** : `COM_CONTENTBUILDERNG_STORAGE_NAME_TOO_LONG`, `_NAME_DUPLICATE`,
`_TABLE_RENAMED`, `_SAVE_EXTERNAL_TABLE_MISSING`, `_IMPORT_SUMMARY(_DROPPED/_SKIPPED_EMPTY/_DURATION)`,
`_BYTABLE2_MISSING_ID`, `_READONLY_EXTERNAL_STORAGE_MSG`, `_DROP_TABLE_FAILED`.

**Cas limites** : nom normalisé plus long après préfixage d'un nom commençant
par un chiffre (« field » ajouté devant) → contrôle de longueur sur le nom
**normalisé**, pas la saisie brute ; table `bytable=2` sans colonne `id` →
bloqué explicitement.

---

## 7. Storage Wizard

**Nom** : assistant de création guidée d'un storage (`view=storagewizard`).

**Objectif** : guider un administrateur pas à pas de la création d'un
storage jusqu'à un item de menu de site fonctionnel, en réutilisant les
écrans Storage/Form existants plutôt qu'en dupliquant leur logique.

**Acteurs** : Administrateur `core.manage` (`requireManagePermission()`, en
tête de chaque tâche).

**Déclencheur** : menu *ContentBuilder NG › Storage Wizard*.

**Flux nominal** : machine à états tenue en session Joomla par
`StorageWizardService` (clé `com_contentbuilderng.storagewizard`, **jamais
persistée en base**). Étapes (`STEPS`) : `storage → fields → form → menu → done`.
L'étape `storage` a des sous-étapes (`storage_substep`) : `mode` (reprendre
vs créer) → `pick_existing` **ou** `creation_mode` (table interne vs
existante) → `name` → `initialization_mode` (manuel vs fichier, table
interne uniquement).

1. **`begin()`** (`:134-145`) marque `started=true`.
2. **`chooseStorageMode()`** (`:151-173`) : `resume` ou `new`.
3. `resume` → **`selectExistingStorage()`** (`:180-214`) vérifie l'existence
   du storage choisi, saute **directement** à l'étape `fields`.
4. `new` → **`chooseCreationMode()`** (`:220-248`) → **`saveStorageDetails()`**
   (`:297-327`, valide nom/titre via `prepareStorageInput()`, contrôles
   identiques à l'écran Storage direct) → table interne :
   **`chooseInitializationMode()`** (`:254-291`) déclenche **`createStorage()`**
   (`:439-529`) ; table existante : `saveStorage()` (`:362-380`) appelle
   directement `createStorage()`.
5. **`createStorage()`** (`:439-529`) : re-vérifie l'unicité du nom (protection
   double soumission), `StorageModel::save()`, `ensureDataTable()`, avance à
   `STEP_FIELDS`. Si mode « par fichier », redirige vers l'écran Storage réel
   avec `wizard=1&csv_import=1` (réutilise l'UI d'import CSV standard).
6. **`confirmFields()`** (`:551-584`) : exige au moins un champ `storage_fields`
   publié avant `STEP_FORM`.
7. **`createForm()`** (`:591-630`) : délègue à
   `DirectStorageFormProvisioningService::resolveOrCreateFormId($storageId, 'thoth', true)`
   (thème par défaut « thoth ») — crée **ou réutilise** la vue `forms`
   associée. Reste volontairement sur l'étape `form` (pas d'avancement
   automatique).
8. **`confirmForm()`** (`:636-654`) : vérifie `form_id`, avance à `STEP_MENU`.
9. **`createMenu()`** (`:660-699`) : crée un item de menu Joomla de **site**
   pointant vers la liste du storage — réutilise un item existant avec le
   même lien exact dans le même `menutype` plutôt que d'en dupliquer un
   (`findExistingMenuItemId()`). Avance à `STEP_DONE`.
10. **`skipMenu()`** (`:705-715`) : saute directement à `STEP_DONE`.
11. **`finish()`** (`:721-729`) : `StorageWizardService::reset()` puis
    redirection vers `view=storages`.

**Flux alternatifs** : **`back()`** (`:96-116`) recule d'une étape, jamais
en dessous de `STEP_FIELDS` (revenir à `storage` recréerait systématiquement
un nouveau storage — commentaire explicite `:106-109`). **`backSubstep()`**
(`:333-355`) navigation arrière au sein de `STEP_STORAGE`. **`start()`**
(`:121-129`) réinitialisation complète.

**Gestion des erreurs** : chaque tâche valide et redirige avec message
explicite (`rememberStorageInput()` pour préserver la saisie en cas de
doublon). Échecs de `createMenuItem()` capturés (`\Throwable`).

**Permissions/ACL** : `core.manage` à chaque étape.

**Effets de bord** : création d'un item de menu **frontend** (client_id=0)
depuis l'admin.

**Tables** : `storages`, `storage_fields`, `#__<storage.name>`, `forms`, `#__menu`.

**Classes** : `admin/src/Controller/StoragewizardController.php`,
`admin/src/Service/{StorageWizardService,DirectStorageFormProvisioningService}.php`,
`admin/src/Model/StorageModel.php` (réutilisé).

**JS/AJAX** : aucun AJAX identifié — UI pas-à-pas côté serveur (chaque clic =
POST + redirection pleine page).

**Cas limites** : relancer en mode « reprise » sur un storage déjà entièrement
configuré (form + menu) → `createForm()`/`createMenu()` **réutilisent**
l'existant plutôt que dupliquer — l'assistant est idempotent en pratique.

---

## 8. Storage Fields

**Nom** : gestion des champs d'un storage (ajout, édition inline,
suppression, index).

**Objectif** : décrire chaque colonne (ou groupe de colonnes) exposée par un
storage — équivalent BreezingForms pour le mode « storage interne/mappé »
(`03-data-model.md §3`).

**Acteurs** : Administrateur `core.edit`. **Préconditions** : storage
existant, non `bytable=2` pour toute écriture.

**Flux nominal — ajout d'un champ** : deux points d'entrée, même logique DDL :
1. **Depuis l'écran Storage** : `StorageController::addfield()` (`:728-761`)
   → `StorageModel::addFieldFromRequest()` (`:229-364`) : normalise le nom
   (`normalizeFieldIdentifier()`), vérifie l'unicité `(storage_id, name)`,
   calcule l'`ordering`, **ajoute d'abord la colonne physique** (`ALTER
   TABLE ... ADD`, type résolu par `StorageColumnTypeHelper::sqlDefinition()`,
   `required` posé via `enforceRequired()`), **puis** seulement persiste la
   ligne `storage_fields` — en cas d'échec de l'`INSERT` métadonnées après un
   `ALTER` réussi, tente un `DROP COLUMN` de compensation (`:341-360`).
   Retourne `false` sans DDL en mode `bytable` (`:237-240`).
2. **Service dédié** : `StorageFieldService::addField()` (`:18-117`), utilisé
   par `StoragefieldController::add()` (task `storagefield.add`, POST
   `jform[fieldname|fieldtitle|is_group|group_definition]`) — même séquence
   « `ALTER TABLE ADD` puis `INSERT storage_fields` ».

**Flux alternatifs** :
- **Édition inline** (`itemNames`/`itemTitles`/…) : `StorageModel::syncEditedFields()`
  (`:1017-…`, via `syncEditedFieldsFromRequest()` à chaque sauvegarde de
  l'écran Storage) — renomme les colonnes physiques si le nom change
  (storage interne), synchronise titre/type/taille/requis.
- **Ajout de champs système manquants** : `ensureSystemFieldMetadata()`
  (fonctionnalité 6), toujours créées dépubliées.
- **AJAX inline** (grille de champs) : voir fonctionnalité 6.
- **Suppression** : `StoragefieldsModel::delete(array $pks)` (`:329-421`) —
  refuse sur `bytable=2`, filtre les champs système protégés (`id`,
  `storage_id`, `user_id`, `created`, `created_by`, `modified_user_id`,
  `modified`, `modified_by`), puis pour un storage non externe exécute
  **`ALTER TABLE ... DROP COLUMN`** pour chaque champ physiquement présent
  **avant** de supprimer les lignes de métadonnées (`:390-410`). **Confirme**
  la suppression physique à la suppression de la ligne `storage_fields`.
  Reste non confirmé : le cas **dépublication** (`published=0`) seule — aucun
  `DROP COLUMN` observé pour ce cas dans les brouillons sources.
- **Index physiques** : voir fonctionnalité 6.

**Gestion des erreurs** : nettoyage compensatoire (`DROP COLUMN`) en cas
d'échec de la seconde moitié d'une opération en deux temps — pas de vraie
transaction DDL (MySQL ne supporte pas les `ALTER TABLE` transactionnels).

**Effets de bord** : DDL direct, irréversible pour `DROP COLUMN` (perte de
données si le champ contenait déjà des valeurs).

**Tables** : `storage_fields`, `#__<storage.name>`.

**Classes** : `admin/src/Controller/StoragefieldController.php`,
`admin/src/Model/{StoragefieldsModel,StorageModel}.php`,
`admin/src/Service/StorageFieldService.php`,
`admin/src/Helper/StorageColumnTypeHelper.php`,
`admin/src/Table/StorageFieldsTable.php`.

**Messages** : `COM_CONTENTBUILDERNG_FIELDNAME_REQUIRED`, `_FIELD_ADDED`,
`_FIELD_ADD_FAILED`, `_ERROR_MISSING_STORAGE_ID`.

**Cas limites** : nom de champ en doublon sur le même storage → rejeté
silencieusement (`addFieldFromRequest()` retourne `false` sans DDL) ; champ
système inclus par erreur dans une sélection de suppression → filtré
automatiquement, jamais supprimé.

---

## 9. Datatable (création/synchronisation)

**Nom** : actions `datatable.create` / `datatable.sync`.

**Objectif** : forcer explicitement la (re)création de la table physique
d'un storage interne, ou resynchroniser ses colonnes avec les champs
déclarés en métadonnées (rattrapage manuel, en complément du self-heal
automatique de `SchemaService`).

**Flux nominal** : `DatatableController::create()` (`:60-104`) →
`DatatableService::createForStorage($storageId)` — `true` si créée, `false`
si déjà existante (avertissement `COM_CONTENTBUILDERNG_TABLE_ALREADY_EXISTS`,
pas une erreur). `DatatableController::sync()` (`:106-148`) →
`DatatableService::syncColumnsFromFields($storageId)` — nom de table
synchronisé, avertissements cumulables via `getLastSyncWarnings()`.

**Gestion des erreurs** : `\Throwable` capturé, redirection avec
`safeErrorMessage()`.

**Permissions/ACL** : aucune vérification `authorise()` explicite dans ce
contrôleur — **Comportement déduit** : protégé indirectement par le jeton
CSRF (`checkToken()`) et l'accès à l'écran Storage qui l'initie ; **Zone
inconnue** : accès direct par URL non testé en conditions réelles.

**Effets de bord** : identiques à `StorageModel::syncStorageDataTableOrBytable()`
(fonctionnalité 6), via un service dédié séparé (`DatatableService`, 749
lignes). **Zone inconnue** : degré de duplication de logique entre les deux
non recoupé ligne à ligne dans les brouillons sources.

**Tables** : `storages`, `#__<storage.name>`, `storage_fields`.

**Classes** : `admin/src/Controller/DatatableController.php`,
`admin/src/Service/DatatableService.php`.

**Messages** : `COM_CONTENTBUILDERNG_TABLE_CREATED`, `_TABLE_ALREADY_EXISTS`,
`_DATATABLE_SYNCED`, `_ERROR_MISSING_STORAGE_ID`.

---

# C. Formulaires & vues (Forms)

## 10. Gestion des Forms (vues)

**Nom** : écran « Formulaire » / « Vue » (liste `forms` + édition `form`) —
table `#__contentbuilderng_forms`, déjà documentée colonne par colonne dans
`03-data-model.md §4`. Cette fiche couvre le **parcours admin**.

**Objectif** : configurer une « vue » CBNG : reliage à une source de données
(`type`+`reference_id`), affichage liste/détail/édition, e-mails, génération
d'articles Joomla (fonctionnalité 11), quotas, vérification, mode
inscription, notation, debug.

**Acteurs** : Administrateur `core.edit`/`core.create`/`core.delete`.
**Préconditions** : édition → la vue existe ; création → `type`/`reference_id`
pointent vers un storage ou un formulaire BreezingForms existant (résolu par
`FormSourceFactory`).

**Déclencheur** : menu *ContentBuilder NG › Vues* → *Nouveau*/clic, ou
redirection automatique depuis le Storage Wizard.

**Flux nominal** :
1. `FormController::add()` (`:135-146`) redirige vers
   `task=form.display&layout=edit&id=0`. `edit()` (`:110-129`) délègue au core.
2. **Écran d'édition** (`admin/tmpl/form/edit.php`, 14 onglets) : tab0 Vue
   (source, type, reference_id — lecture seule après création), tab9 Options
   avancées, tab2 Texte d'introduction de liste, tab1 États de liste
   (fonctionnalité 13), tab3 Affichage Détails (`details_template`,
   verrouillable), tab5 Affichage Édition (`editable_template`, verrouillable),
   tab10 Article (fonctionnalité 11), tab6 API (`api_allowed`, OpenAPI),
   tab7 Modèles d'e-mail, tab8 Permissions (fonctionnalité 14), tab11 Debug
   (`debug_mode` + 6 sous-flags), tab12 Piste d'audit (badges
   `FormAuditService`, fonctionnalité 4), tab13 Performance, tab14 Données
   (**Hypothèse** : aperçu des enregistrements, non confirmé).
3. Soumission → `FormController::save()` (`:173-183`) — délègue entièrement
   à `parent::save()` (`Table::store()`, `UPDATE` ciblés sur les colonnes
   « détails/options », synchronisation `list_states`, déjà détaillé
   `03-data-model.md §4`). Seule logique propre : si `wizard=1` et tâche ≠
   `apply`/`save2new`/`save2copy`, force la redirection vers
   `view=storagewizard` (`closeLink()`) — sinon `view=forms`.
4. **`getRedirectToItemAppend()`** (`:185-228`) : retrouve l'id sauvegardé
   depuis plusieurs sources et **propage `wizard=1`** dans l'URL de
   redirection (sinon « Appliquer » depuis l'assistant en sortirait,
   commentaire explicite `:221-222`).

**Flux alternatifs** : `save2new()` (sauvegarde + nouvel item vide) ;
`saveorder()`, `listorderup()`/`listorderdown()` (réordonnancement `elements`
ou `forms`) ; `element_flag()`/`element_publish()` (bascule unitaire) ;
`save_labels()` (libellés d'éléments en masse) ; `formpublish()`/`formunpublish()`,
`debug_on()`/`debug_off()`, `form_flag()` (bascules rapides depuis la liste) ;
réparations ciblées depuis l'onglet Audit (`repairThemePlugin()`,
`repairEditableTemplate()`, `repairDetailsTemplate()`, `repairTemplates()`,
`repairEditableFieldItem()` — régénèrent un template via
`TemplateRenderService`/`TemplateSampleService`) ; champs système BreezingForms
(`add_bf_system_field()`/`ajax_add_bf_system_field()`, `remove_bf_system_field()`/
`ajax_remove_bf_system_field()` — hors périmètre storage interne) ;
`FormsController::copy()` (duplication, **Comportement déduit** du nom et de
la signature, corps non lu en détail) ; `FormsController::delete()` (`:139-171`)
→ `FormModel::delete()` → `deleteByIds()` (cascade complète documentée
`03-data-model.md §4`, y compris la branche `articles` sans `WHERE` — voir
Contradictions non tranchées en fin de document) ; `debug_on()`/`debug_off()`
en masse.

**Gestion des erreurs** : `edit()`/`add()` capturent `\Throwable`, retombent
sur `forms.display` avec message `warning`.

**Permissions/ACL** : `core.edit`/`core.create`/`core.delete` (ACL standard
Joomla) — **distinct** du contrôle d'accès **applicatif** front, géré par
`PermissionService` (fonctionnalité 14) et paramétré depuis l'onglet
Permissions.

**Effets de bord** : aucune modification de schéma directe (contrairement à
Storage) — `forms` ne pilote que de la configuration applicative.

**Tables** : `forms`, `elements`, `list_states`.

**Classes** : `admin/src/Controller/{Form,Forms}Controller.php`,
`admin/src/Model/{Form,Forms,Elements}Model.php`, `admin/src/Table/FormTable.php`,
`admin/src/Helper/FormSourceFactory.php`,
`admin/src/Service/{FormSupportService,TemplateRenderService,TemplateSampleService}.php`.

**Plugins/événements Joomla** : aucun événement natif déclenché par la
sauvegarde admin (pas d'`onContentBeforeSave`/`AfterSave` — cohérent avec
l'absence d'UCM, `03-data-model.md §16`).

**JS** : `admin/tmpl/form/{edit_init_scripts,edit_footer_scripts,bf_system_fields_modal_scripts}.php`.

**AJAX** : `ajax_add_bf_system_field()`, `ajax_remove_bf_system_field()`.

**Messages** : `COM_CONTENTBUILDERNG_SAVED`, `_ERROR`,
`_TEMPLATE_LOCKED_RESYNC_*`.

---

## 11. Article Settings

**Nom** : réglages de génération d'articles Joomla (onglet « Article »,
tab10 de l'écran Formulaire).

**Objectif** : configurer si/comment chaque enregistrement soumis via cette
vue doit générer (et rester synchronisé avec) un article Joomla natif
(`#__content`).

**Déclencheur** : `admin/layouts/form/article_tab.php` — layout rendu en
HTML « à la main » (pas un fieldset XML classique comme les autres onglets).

**Flux nominal** : le layout expose 4 blocs :
1. **Création** : `create_articles` (Oui/Non), `delete_articles` (supprimer
   l'article Joomla à la suppression de l'enregistrement, défaut `1`).
2. **Valeurs par défaut** : `title_field`, `default_category` (rendu
   `jform[sectioncategories]` — le nom POST diffère du nom de colonne
   `default_category`, remappé côté modèle, **Comportement déduit** non
   confirmé ligne à ligne), `default_access`, `default_featured`.
3. **Langue** : `default_lang_code`, `article_record_impact_language`,
   `default_lang_code_ignore`.
4. **Publication** : `default_publish_up_days`/`default_publish_down_days`
   (décalage en jours), `article_record_impact_publish`, et
   `protect_upload_directory` pour un `forms.type` BreezingForms.

**Validation JS côté client** (`article_tab.php:262-334`) : si
`create_articles=1`, `default_category` devient **obligatoire** côté
navigateur, avec bascule d'onglet + focus en cas de soumission invalide.
**Aucune contrepartie serveur** identifiée dans `FormController::save()`
(délégation complète au core) — **Zone inconnue** : à vérifier si
`FormModel::save()` revalide `default_category` côté serveur, ou si la seule
protection est ce contrôle JS contournable.

**Bouton « Réinitialiser »** (`cbResetArticleOptions()`, `:389-424`) :
restaure des valeurs par défaut codées en dur (`$articleDefaults`, `:50-64`)
puis soumet automatiquement `form.apply` — remet notamment
`create_articles=0`, `delete_articles=1`, `default_access=0`.

**Flux alternatifs** : aucun autre point d'entrée admin — la génération/
synchronisation effective d'articles est un traitement **front-end**
(`ArticleService::createArticle()`, `03-data-model.md §9`).

**Effets de bord** : aucun à la sauvegarde de la configuration elle-même.

**Tables** : `forms` (colonnes) ; `#__content`/`#__assets`/`articles` affectés
plus tard par `ArticleService` (front), pas par cet onglet directement.

**Classes** : `admin/layouts/form/article_tab.php`,
`admin/src/Model/FormModel.php`, `admin/src/Service/ArticleService.php`.

**Messages** : `COM_CONTENTBUILDERNG_CREATE_ARTICLES_CATEGORY_REQUIRED`,
`_RESET_ARTICLE_OPTIONS_CONFIRM`.

**Cas limites** : vue BreezingForms → champ supplémentaire
(`protect_upload_directory`) absent pour une vue « storage interne ».

---

## 12. Elements et Element Options

**Nom** : onglet « Éléments » d'une vue + modale « Options d'élément »
(`view=elementoptions`).

**Objectif** : configurer, pour chaque couple (vue, champ source), son
affichage/édition/validation/export — déjà détaillé colonne par colonne dans
`03-data-model.md §5`.

**Acteurs** : Administrateur éditant une vue. **Préconditions** : éléments
existants (auto-provisionnés à la synchronisation d'une vue avec sa source,
via `FormSupportService`).

**Flux nominal — modale Options d'élément** :
1. `ElementoptionsController::display()` (`:78-85`) force
   `view=elementoptions`, rend la modale en `tmpl=component`.
2. Soumission → `ElementoptionsController::save()` (`:87-121`) :
   `ElementoptionsModel::store()`, puis en cas de succès
   `resyncLockedTemplatesAfterSave()` (`:49-76`) : si la vue possède un
   `details_template`/`editable_template` **verrouillé**, régénère
   automatiquement via `FormSupportService::resyncLockedTemplates($formId, $userId)`
   — échec capturé (`\Throwable`) en avertissement
   `COM_CONTENTBUILDERNG_TEMPLATE_LOCKED_RESYNC_UNAVAILABLE`, sans jamais
   bloquer la sauvegarde de l'élément.

**Flux alternatifs** : toggles rapides depuis la liste des éléments
(`FormController::elementsUpdate()`/`elementsPublish()`/`element_flag()`/
`element_publish()` → `FormModel::setListEditable()`/`setListListInclude()`/
`setListSearchInclude()`/`setListNotLinkable()`, `:348-488`) ; édition en
masse des libellés (`save_labels()` → `saveElementListSettings()`, `:149-207`) ;
réordonnancement (`saveorder()`, `listorderup()`/`listorderdown()`) ;
synchronisation automatique des champs source (`FormSupportService`,
insertion/mise à jour/suppression des éléments orphelins, déclenchée à
l'ouverture de l'écran Formulaire).

**Données entrantes** : tous les champs `elements` éditables (`options`
JSON, scripts personnalisés PHP `custom_init_script`/`custom_action_script`/
`custom_validation_script`, `validation_message`, `default_value`, `hint`,
`label`, flags `list_include`/`export_include`/`search_include`/`linkable`/
`detail_include`/`api_allowed`/`editable`, `validations` CSV, `published`,
`order_type`, `ordering`).

**Effets de bord** : saisie de scripts PHP personnalisés — **Hypothèse** :
exécutés ultérieurement en `eval`/inclusion dynamique côté front (types
d'élément `admin/src/Elementtypes/*`, hors périmètre de ce catalogue).

**Configuration influente** : `enable_validations` (composant) gate
globalement l'exécution des validations configurées ici, sans les supprimer
de l'écran.

**Tables** : `elements`.

**Classes** : `admin/src/Controller/ElementoptionsController.php`,
`admin/src/Model/{Elementoptions,Elements}Model.php`,
`admin/src/Table/ElementoptionsTable.php`,
`admin/src/Service/{FormSupportService,ElementSettingsStateService}.php`.

**Messages** : `COM_CONTENTBUILDERNG_SAVED`, `_ERROR`,
`_TEMPLATE_LOCKED_RESYNC_UNAVAILABLE`.

---

## 13. List States

**Nom** : onglet « États de liste » (tab1) de l'écran Formulaire.

**Objectif** : définir les états de workflow disponibles pour une vue
(badges colorés : « Validé », « En attente », etc.) — déjà détaillé dans
`03-data-model.md §7`. Voir aussi fonctionnalité 31 (routage `action` vers
`trash`/`untrash`).

**Flux nominal** : `FormModel::save()` (`:1536-1599`) met à jour les états
existants transmis par le formulaire, puis complète automatiquement les
états manquants jusqu'au nombre par défaut (`buildDefaultListStates()`,
`:109-131`) si la vue n'en a pas encore ou en a moins que le standard.

**Flux alternatifs** : aucune suppression individuelle d'état identifiée —
seule la suppression en cascade à la suppression totale de la vue
(`FormModel::deleteByIds():1709`).

Cet onglet ne constitue pas un sous-système technique séparé — voir
fonctionnalité 10 pour le reste du schéma (contrôleur, ACL, classes).

---

## 14. Utilisateurs par vue & permissions applicatives

**Nom** : écran « Utilisateurs » (`view=users`, filtré par `form_id`) —
gestion des quotas/vérifications par (utilisateur Joomla, vue), et moteur
ACL applicatif `PermissionService`.

**Objectif** : piloter, pour une vue donnée, le compteur d'enregistrements
soumis par utilisateur et les indicateurs de vérification vue/nouveau/édition
(`#__contentbuilderng_users`, `03-data-model.md §10`). Distinct de la gestion
native des comptes/groupes Joomla (`com_users`).

**Acteurs** : Administrateur avec accès à la vue concernée. **Préconditions** :
`form_id` valide dans l'URL — l'écran est **toujours** ouvert depuis l'onglet
Permissions d'une vue (`admin/layouts/form/permissions_tab.php`, seul fichier
qui construit un lien `view=users`).

**Flux nominal** :
1. `Users\HtmlView::display()` charge `UsersModel::getItems()`.
2. Actions de liste en masse : **`UsersController::verified_view()`/
   `not_verified_view()`/`verified_new()`/`not_verified_new()`/
   `verified_edit()`/`not_verified_edit()`** (`:96-233`) →
   `UserModel::setListVerifiedView()` etc. — basculent en masse les 3×2
   flags `verified_{view,new,edit}`, avec **création à la volée** de la
   ligne `#__contentbuilderng_users` si absente (`ensureContentbuilderngUserRow()`,
   `:62-85`).
3. **`publish()`/`unpublish()`** (`:249-…`) → `UsersModel::setPublished()`/
   `setUnpublished()` (`:175-251`) — bloque/débloque un utilisateur pour
   cette vue sans supprimer son historique.
4. **`edit()`/`apply()`/`save()`/`cancel()`** : édition individuelle
   (`UserModel::getData()`/`store()` via `CbuserTable`).

**Flux alternatifs** : aucune suppression de ligne individuelle identifiée
(cohérent avec suppression uniquement en cascade à la suppression totale de
la vue).

### PermissionService — moteur ACL applicatif (contexte, pas un écran séparé)

**Fait observé** : `admin/src/Service/PermissionService.php` (741 lignes,
`setPermissions()` en `:225-322`) construit, pour un utilisateur et une vue
donnés, un objet de permissions effectives par jointure
`forms + #__contentbuilderng_users` (LEFT JOIN `form_id`, `userid`) — quotas
(`limit_add`/`limit_edit`, vue vs individuel), gates de vérification
(`verified_view`/`new`/`edit`), consommé par les vues **front** (liste,
détail, édition) pour décider si un visiteur peut voir/soumettre/éditer. Ce
service n'a pas d'écran admin dédié : il **lit** la configuration posée par
l'onglet Permissions de la vue (fonctionnalité 10) et les lignes
`#__contentbuilderng_users` gérées par cet écran.

**Permissions/ACL** : `core.manage`/`core.edit` sur `com_contentbuilderng`
(ACL Joomla standard) — **distinct** du modèle applicatif décrit ci-dessus.

**Tables** : `#__contentbuilderng_users`, `forms` (lecture), `#__users`
(Joomla core, lecture par `userid`).

**Classes** : `admin/src/Controller/UsersController.php`,
`admin/src/Model/{Users,User}Model.php`, `admin/src/Table/CbuserTable.php`,
`admin/src/Service/PermissionService.php`,
`admin/layouts/form/permissions_tab.php` (point d'entrée).

**AJAX** : `UsersController::isAjaxCall()`/`respondAjax()` existent
(`:343-…`) — **Comportement déduit** : au moins une action de liste supporte
une variante AJAX, non identifiée précisément laquelle.

**Cas limites** : utilisateur n'ayant jamais interagi avec une vue
nécessitant un suivi → absence de ligne `#__contentbuilderng_users`, traitée
comme « jamais vérifié / 0 enregistrement » par `PermissionService`, sans
ligne créée tant qu'aucune action admin ou soumission front ne la
matérialise.

---

## 15. Titlesets ({cbstats})

**Nom** : écrans « Titlesets » (liste) et « Titleset » (édition) —
configuration du plugin de contenu `contentbuilderng_cbstats`
(fonctionnalité 27, balise `{cbstats}`).

**Objectif** : gérer des jeux de libellés/couleurs/config réutilisables par
la balise `{cbstats}` — **stockés en fichiers INI sur disque**, pas en base
(cohérent avec l'absence de table dédiée dans `03-data-model.md`).

**Acteurs** : Administrateur `core.manage` (`assertAuthorized()`, en tête de
chaque tâche). **Préconditions** : édition/suppression → le fichier doit
exister dans le répertoire « custom » (`source=custom`).

**Flux nominal — création/édition** :
1. `TitlesetsModel::getItems()` liste les fichiers (deux sources : intégrés
   au composant et « custom », déduit du préfixe `source:filename`).
2. `TitlesetController::save()`/`apply()` (`:27-35`) → `saveAndRedirect()`
   (`:269-289`) → `CbStatsTitleSetManagerService::save($data)` (`:165-212`) :
   valide (`validate()`), écrit un fichier `.ini` dans le répertoire
   « custom » approprié (`CbStatsTitleSetService::CUSTOM_DIRECTORY` pour un
   jeu de titres, `CbStatsConfigService::CUSTOM_DIRECTORY` pour une
   configuration), crée le répertoire si besoin (`mkdir(0755, true)` +
   `index.html` de protection). Échec de validation
   (`\InvalidArgumentException`) → état conservé en session
   (`com_contentbuilderng.titleset.data`) pour repeupler le formulaire.
3. **`save2copy()`** (`:37-55`) : duplique en un nouveau fichier
   (`saveCopy()`), redirige vers l'édition du nouveau.
4. **`validateFile()`** (`:238-252`) : valide sans sauvegarder.

**Flux alternatifs** :
- **`deleteSelected()`** (`:57-76`) : suppression en masse, uniquement
  `source=custom` (les jeux intégrés sont filtrés silencieusement).
- **`exportSelected()`** (`:78-132`) : téléchargement direct si un seul
  fichier (`text/plain`), sinon ZIP en mémoire (`\ZipArchive`) avec
  déduplication de noms (préfixe `source-` si collision).
- **`importFiles()`** (`:134-186`) : upload multi-fichiers (≤1 Mio chacun),
  validation individuelle (nom, contenu, taille, doublon **au sein du même
  lot** détecté **avant** toute écriture — `batch_duplicate`), option
  « écraser » (`titleset_overwrite`). Erreurs : `invalid_filename`,
  `invalid_contents`, `already_exists`, `upload_invalid`, `batch_duplicate`,
  `directory_failed`, `replace_failed`, `read_failed`/`write_failed`.
- **`deleteFile()`** (`:254-262`) : suppression unitaire depuis l'écran
  d'édition.

**Gestion des erreurs** : toutes les tâches encapsulent le service dans
`try/catch(\Throwable)`, journalisation Joomla dédiée pour l'import,
conservation de la saisie en session.

**Permissions/ACL** : `core.manage`, vérifié explicitement à chaque tâche.

**Effets de bord** : écriture directe sur le système de fichiers du serveur
— nécessite des droits d'écriture (`directory_failed`/`write_failed` sinon).

**Tables** : aucune — fonctionnalité entièrement fichier.

**Classes** : `admin/src/Controller/TitlesetController.php`,
`admin/src/Model/{Titleset,Titlesets}Model.php`,
`admin/src/Service/CbStatsTitleSetManagerService.php`.

**AJAX** : aucun — tâches `titleset.*` en soumission de formulaire classique,
y compris l'export (téléchargement direct, `Content-Disposition: attachment`).

**Messages** : `COM_CONTENTBUILDERNG_TITLESETS_SAVED`, `_COPY_SAVED`,
`_N_DELETED`, `_EXPORT_SELECT`, `_EXPORT_FAILED`, `_N_IMPORTED`,
`_IMPORT_ERROR_{FILENAME,CONTENTS,EXISTS,DIRECTORY,REPLACE,WRITE}`,
`_IMPORT_INVALID`, `_IMPORT_DUPLICATE`, `_IMPORT_FAILED`, `_VALID`,
`_ERROR_CONFIG_VALUE`, `_DELETED`, `_SAVE_FAILED_WRITE`.

**Cas limites** : import d'un lot avec deux fichiers de même nom normalisé
(insensible casse) → rejeté globalement (`batch_duplicate`) avant toute
écriture, même si `titleset_overwrite=1` ; suppression d'un identifiant non
`custom` via `deleteSelected()` → silencieusement ignoré.

---

# D. Frontend — affichage et navigation

## Mécanismes transversaux (préambule aux fonctionnalités 16-25)

Ces mécanismes ne sont pas des « fonctionnalités » au sens strict mais
conditionnent le comportement de toutes les vues front ; documentés une
seule fois ici.

**Dispatch/routage** : `site/src/Dispatcher/Dispatcher.php::dispatch()`
(`:32-244`) — réinitialise les paramètres de menu `cb_*` sur l'`Input` sauf
un sous-ensemble surchargeable par requête (`REQUEST_OVERRIDABLE_MENU_PARAMS`),
résout l'item de menu actif et mémorise en session le dernier `layout`
choisi par item de menu, injecte les paramètres d'un menu CBNG (`form_id`,
`record_id`, `cb_*`, mode « nouvelle liste »), route par défaut vers
`publicforms` (fonctionnalité 18) si ni `id` ni `storage_id` n'est présent,
détermine le contrôleur final (`export`/`verify` → contrôleur du même nom ;
`view=details` ou `view=latest` → `details` ; `cb_controller=edit` → `edit` ;
`cb_controller=publicforms` sans `id` → `publicforms` ; `task=xxx.yyy` déjà
qualifié **écrase** tout le reste). Voir fonctionnalité 35 pour le second
fichier `Dispatcher.php` (code mort).

**Mode « storage direct » (`storage_id=`)** : `ListController`,
`DetailsController`, `EditController` détectent
`$isDirectStorageMode = $storageId > 0 && getInt('id', 0) <= 0`. Il n'existe
pas nécessairement de ligne `forms` : le contrôleur appelle
`DirectStorageFormProvisioningService::resolveOrCreateFormId($storageId)`
qui **réutilise** la vue existante si déjà auto-provisionnée, ou en **crée
une à la volée**. Deux profils de permissions par défaut selon l'origine :
une vue provisionnée par une action admin délibérée (Storage Wizard) reçoit
des droits lecture/écriture « raisonnables » ; une vue auto-provisionnée par
une requête front anonyme reste **lecture seule pour Invité et n'accorde
rien d'autre** (« puisque personne ne l'a relue », commentaire du code).

**Prévisualisation admin signée (HMAC)** : détail cryptographique déjà
documenté dans `07-security.md §1.3.2`. `ListController::enqueueUnpublishedPreviewNotice()`
(`:557-576`) affiche **une fois par lien de preview** un avertissement « cette
vue n'est pas publiée ».

**Intégration `{CBList}` — mode « liste embarquée » (`cblist_embed`)** :
point de jonction avec le plugin `contentbuilderng_cblist` (fonctionnalité 26).
Paramètres de requête additionnels : `cblist_embed=<contexte>` (active le
mode), `cblist_fields=` (restreint les colonnes,
`EmbeddedListFieldFilterService`), `cblist_actions=` (restreint les
contrôles interactifs, vocabulaire fermé
`search|state|publish|language|new|edit|delete|export|rating|detail|print`
ou `none`, `EmbeddedListActionFilterService.php:22-131`), `cblist_sort=`/
`cblist_dir=`, `cblist_limit=` (≤5000, `ListModel::getEmbeddedResultLimit()`),
`cblist_hide_pagination=`, `cblist_title=`/`cblist_title_set=`,
`cblist_value_output=`. **Garantie « jamais plus de droits »** (documentée
explicitement dans le code) : ce mécanisme ne fait que **restreindre** ce
que l'ACL/la configuration de vue autorise déjà —
`ListController::assertConstrainedAction()`, `EditController::assertConstrainedAction()`,
`DetailsController::display()` appellent tous
`EmbeddedListActionFilterService::isRequestAllowed()` **avant** le contrôle
ACL normal, et lèvent HTTP 403 (`COM_CONTENTBUILDERNG_MENU_ACTION_DISABLED`)
si l'action est explicitement exclue — jamais l'inverse.

**Thème visuel** : chaque vue résout un plugin de thème
(fonctionnalité 33) via `MenuThemeHelper::resolve()` puis
`PreviewThemeHelper::apply()`, avec repli sur `'thoth'` si le plugin résolu
est indisponible.

**Panneau de debug front** : chaque `HtmlView` (List/Details/Edit) mesure
son temps de rendu et expose des indicateurs `debug_*` lus depuis `forms`,
actif uniquement si `debug_mode=1`.

## 16. Affichage liste (`list`)

**Nom** : liste des enregistrements d'une vue ContentBuilder NG.

**Objectif** : afficher, filtrer, trier et paginer les enregistrements d'une
source (storage interne ou BreezingForms) exposés par une vue publiée.

**Acteurs** : visiteur anonyme, utilisateur enregistré, administrateur en
prévisualisation. **Préconditions** : vue publiée (`forms.published=1`) sauf
preview admin signée ; permission `listaccess` accordée
(`07-security.md §1.3`).

**Déclencheur** : `GET index.php?option=com_contentbuilderng&view=list&id=<formId>`
(ou `storage_id=` mode direct, ou clic menu Joomla configuré).

**Flux nominal** :
1. `Dispatcher` route vers `ListController::display()`.
2. Résolution `formId` (URL > menu actif > mode storage direct) et
   `recordId` éventuel (`:366-418`).
3. `isValidAdminPreviewRequest()` : signature HMAC valide → mode preview
   (`cb_preview_ok=1`) ; sinon `PermissionService::checkPermissions('listaccess')`
   (403 sinon).
4. `parent::display()` instancie `ListModel` (constructeur : résout
   filtre/tri/pagination depuis la requête ou l'état de session, réinitialise
   si changement d'écran/vue, `:150-248`).
5. `ListModel::getData()` (`:671-1291`) : charge `forms`, résout la source
   (`FormSourceFactory::getForm()`), calcule les colonnes visibles
   (`list_include`), le filtre plein-texte, le filtre « externe » (session,
   `allow_external_filter`), les filtres par plage/correspondance multiple
   (`@range`/`@match`), le tri (y compris `Rand` avec rafraîchissement
   périodique `rand_date_update`/`rand_update`), puis
   `$form->getListRecords(...)` (implémentation `admin/src/types/*`) pour la
   page de résultats + `getListRecordsTotal()`.
6. `List/HtmlView::display()` (`:69-250`) assemble ~50 propriétés (colonnes,
   états, langue, pagination, thème, debug).
7. `site/tmpl/list/default.php` (ou `listcard.php`/`listcompact.php`/
   `listtiles.php` selon `layout=`, simples `require_once default.php` avec
   `$cbListTemplateVariant` positionné) rend le tableau/les cartes, la barre
   de filtre, les liens de tri Joomla natifs, le sélecteur « par page », la
   pagination (`site/layouts/contentbuilderng/list_pagination.php`).

**Flux alternatifs** :
- **Mode « storage direct »** : `ListModel::getDirectStorageListSubject()`
  (`:496-635`) construit un objet `$data` synthétique (pas de ligne `forms`
  réelle tant que non auto-provisionnée) avec thème imposé `thoth`,
  `show_id_column=1`, `edit_button`/`new_button=0` si `bytable=2`.
- **Liste embarquée `{CBList}`** : voir préambule ci-dessus.
- **Filtre externe déclenché par formulaire** (`contentbuilderng_filter_signal`,
  `cbListFilterKeywords`, `cbListFilterCalendarFrom/To`, `cb_filter[...]`,
  `cbListFilterArticleCategories`) : rejoué depuis la session tant que
  `allow_external_filter` est actif et que `filter_reset` n'a pas été
  demandé (`:790-869`).
- **Vue non liée à une source valide** : `$data->invalid_list_setup = true`,
  liste vide, message d'erreur côté gabarit (`:1281-1285`).

**Gestion des erreurs** : vue introuvable/dépubliée sans preview valide →
`COM_CONTENTBUILDERNG_FORM_NOT_FOUND` (404) ; setup de tri/filtre périmé →
`COM_CONTENTBUILDERNG_STALE_LIST_SETUP_RELOAD` (500, réinitialise le tri en
session avant de lever, `:1212-1215`) ; `listaccess` refusé → 403 sauf
preview ; lien de preview invalide/expiré → avertissement, retombe en mode
normal.

**Effets de bord** : rafraîchissement périodique de l'ordre aléatoire
(`records.rand_date`, `forms.rand_date_update`) si tri `Rand` actif et
fenêtre expirée ; écriture d'état utilisateur (filtre/tri/pagination) en
session Joomla, scopée par vue+layout+item de menu.

**Tables** : `forms`, `elements`, `records` (tri `Rand`), `list_records`/
`list_states` (état/couleur), table de storage physique ou `#__facileforms_*`.

**Classes** : `site/src/Controller/ListController.php`,
`site/src/Model/ListModel.php`, `site/src/View/List/HtmlView.php`,
`site/tmpl/list/{default,listcard,listcompact,listtiles}.php`,
`site/src/Service/{EmbeddedListFieldFilterService,EmbeddedListActionFilterService,EmbeddedListContextService,MenuDataFilterService}.php`,
`site/src/Helper/{MenuParamHelper,PublishedRecordVisibilityHelper}.php`,
`admin/src/Service/{ListSupportService,DirectStorageFormProvisioningService,PermissionService,TemplateRenderService}.php`.

**Plugins/événements** : `onContentPrepare` (intro_text, `:1265-1272`),
`onListViewCss`/`onListViewJavascript` (thème, fonctionnalité 33), jonction
`{CBList}` (fonctionnalité 26).

**JS** : `media/js/list-init.js` (675 lignes, chargé en dur par
`site/tmpl/list/default.php:526-532`, cache-busting par version) : tri de
colonnes, sélection multiple, actions groupées, coloration des états,
notation AJAX, bascule état/publication par ligne en AJAX (voir §16.1),
bouton « Réinitialiser les filtres ». `media/js/contentbuilderng.js` :
fallback notation + fermeture de fenêtre de preview.

**Configuration influente** : `forms.show_filter`, `show_records_per_page`,
`initial_list_limit`, `filter_exact_match`, `allow_external_filter`,
`initial_sort_order[123]`, `initial_order_dir[123]`, `list_state`/
`list_state_bulk`/`list_publish`/`list_language`/`list_author`/
`list_last_modification`, `select_column`, `button_bar_sticky`,
`show_preview_link` ; composant `default_list_limit`/`pagination_choices`.

**Messages** : `COM_CONTENTBUILDERNG_FORM_NOT_FOUND`, `_STALE_LIST_SETUP_RELOAD`,
`_PREVIEW_LINK_EXPIRED`, `_PREVIEW_UNPUBLISHED_NOTICE`,
`_PERMISSIONS_LISTACCESS_NOT_ALLOWED`, `_CBLIST_UNKNOWN_FIELD`/
`UNKNOWN_SORT_FIELD`/`INVALID_FIELDS`.

**Cas limites** : liste embarquée avec >5000 résultats demandés (plafonné) ;
tri sur colonne absente du `SELECT` (ignoré silencieusement) ; storage
direct en lecture seule (`bytable=2`, boutons Nouveau/Éditer masqués) ;
storage sans champ publié en mode preview.

### 16.1 Sous-flux AJAX — actions de liste (bascule état/publication par ligne)

- **Bascule d'état** (`<select data-cb-state-select>`) : `list-init.js:401-487`
  (`contentbuilderng_state_single()`) construit un `FormData` depuis
  `#adminForm`, force `task=edit.state`, `cb_ajax=1`, `list_state=<id>`,
  `cid[]=<recordId>`, `boxchecked=1`, `fetch(..., POST, X-Requested-With)`.
  Serveur : `EditController::state()` (`:387-429`) — jeton CSRF POST
  obligatoire, `assertConstrainedAction('state')`,
  `checkPermissionForAjax('state', …)`, `EditModel::change_list_states()`.
  Réponse JSON via `respondAjax()` si `cb_ajax=1` détecté.
- **Bascule publication** (`[data-cb-publish-toggle]`) : `list-init.js:553-604`
  fait un `fetch(href + '&cb_ajax=1', GET)`. Serveur :
  `EditController::publish()` (`:431-495`) — jeton vérifié via
  `checkToken('request', …)` (« per-row publish toggle is a GET link, token
  carried in query string »), `assertConstrainedAction('publish')`, garde
  ACL Joomla `core.edit.state` en mode storage direct,
  `checkPermissionForAjax('publish', …)`, `EditModel::change_list_publish()`.
- **Actions de masse** (`list.delete`/`list.state`/`list.publish`,
  soumission classique `#adminForm` par `Joomla.submitform()`, **pas** AJAX) :
  `ListController::delete()`/`state()`/`publish()` (`:88-346`) — jeton POST
  obligatoire, `armPermissionsForSelection()` (recalcule les permissions
  **pour la sélection courante**, garde anti-régression explicite,
  `:58-64`), délègue à `EditModel`.

**Contrat AJAX résumé** (référence : `06-api-contracts.md §4.2`) : URL
`task=edit.state` (POST, `FormData`) / `task=edit.publish` (GET, jeton en
query) ; paramètres clés `id`, `record_id`/`cid[]`, `list_state`, `cb_ajax=1`,
jeton CSRF Joomla classique ; détection AJAX `Input::getInt('cb_ajax', 0)` ;
réponse `{"success":bool,"message":"...","messages":[],"data":{"ok":bool}}`
(format `Joomla\CMS\Response\JsonResponse`) ; pas de code HTTP d'erreur
dédié (toujours 200, `success:false` en cas d'échec) ; CSRF distinct de
celui d'`ApiController` (fonctionnalité 24, qui accepte aussi l'en-tête
`X-CSRF-Token`).

---

## 17. Affichage détail (`details`)

**Objectif** : afficher un enregistrement unique selon le gabarit « détail »
configuré côté vue (moteur de templates à jetons `{champ}`/`{champ:value}`/
`{champ:label}`, `admin/src/Service/TemplateRenderService.php`).

**Acteurs/Préconditions/Déclencheur** : identiques à la liste, avec en plus
`record_id` obligatoire (ou résolution « dernier enregistrement » en mode
`cb_latest`/`view=latest`).

**Flux nominal** :
1. `DetailsController::__construct()` : résout `record_id` depuis le menu
   actif si absent (`:84-101`) ; cas spécial `view=latest` (`:103-173`) :
   charge la vue, ses éléments, cherche le dernier enregistrement (1
   résultat, tri desc) ; si aucun enregistrement, redirige vers
   `edit.display` si droit `new`, sinon message « ajoutez d'abord un
   enregistrement » + redirection accueil.
2. `display()` (`:180-262`) : résout `form_id`/`storage_id`/`record_id`,
   valide/active le mode preview admin, `checkPermissions('view', …)` sauf
   preview, `parent::display()`.
3. `DetailsModel::getData()` (`:312-685`) : charge `forms`, résout la source,
   calcule les colonnes « détail » (`detail_include=1`, filtrées par menu),
   applique le filtre de menu (`isRecordAllowedByMenuFilter()` — 404 si
   l'enregistrement demandé ne correspond pas au filtre imposé par le menu),
   charge l'enregistrement via `$form->getRecord()`, résout le titre (label
   du champ `title_field`, avec substitution spéciale en mode inscription :
   nom/username/e-mail réel de l'utilisateur Joomla plutôt que la valeur
   stockée), génère le gabarit rendu (`TemplateRenderService::getTemplate()`),
   enrichit avec métadonnées (auteur/dates), état de liste
   (`appendListStateData`, trait partagé avec `EditModel`) et notation
   (`appendRatingData`).
4. `Details/HtmlView::display()` (`:278-550`) : résout l'article Joomla lié
   (si `edit_by_type`, cas rare), construit un `Joomla\CMS\Table\Content`
   factice porteur du gabarit rendu pour **réutiliser le pipeline
   d'événements de contenu Joomla natif** (`onContentPrepare`/
   `onContentAfterTitle`/`onContentBeforeDisplay`/`onContentAfterDisplay`,
   avec injection d'un commentaire `system-pagebreak`), puis réécrit les
   liens internes marqués `contentbuilderng_slug_used` (générés par
   `TemplateRenderService` pour les liens `{champ:link}`) vers de vraies
   URLs `task=details.display&id=&record_id=`. Calcule l'enregistrement
   précédent/suivant (`resolveSiblingRecordIds()`, ré-exécute
   `ListModel::getData()` avec la même config de tri/filtre que l'écran
   liste actif).
5. `site/tmpl/details/default.php` rend le gabarit + barres d'action
   (retour, impression, export, notation) + panneau debug.

**Flux alternatifs** : mode storage direct → rendu minimal générique
(`buildDirectStorageDetailsTemplate()`, `:687-708`) ; impression
(`site/tmpl/details/print.php`, contrôlé par `print_button`) ; liaison
BreezingForms `{BreezingForms: …}` dans le gabarit détail (mécanisme
présent côté `Edit\HtmlView` uniquement d'après le grep effectué, pas
côté `Details\HtmlView`).

**Gestion des erreurs** : `COM_CONTENTBUILDERNG_FORM_NOT_FOUND`,
`_RECORD_NOT_FOUND` (introuvable, filtré par le menu, ou autre langue/
propriétaire selon config), `NotAllowed` 403 (`view` refusé).

**Effets de bord** : aucune écriture en base dans l'affichage standard
(lecture seule), hormis la notation (fonctionnalité 24.1, déclenchée
séparément via `ApiController`).

**Tables** : `forms`, `elements`, `records` (notation/état), `list_records`/
`list_states`, `#__content`/`#__assets` (si `edit_by_type`).

**Classes** : `site/src/Controller/DetailsController.php`,
`site/src/Model/DetailsModel.php`, `site/src/View/Details/HtmlView.php`,
`admin/src/Service/TemplateRenderService.php`, `admin/src/Helper/RatingHelper.php`.

**Plugins/événements** : pipeline article natif détourné, `onContentTemplateCss`/
`onContentTemplateJavascript` (thème).

**JS** : `media/js/contentbuilderng.js` (notation fallback),
`RatingHelper::getRating()` (HTML + JS `cbRate()` inline, fonctionnalité 24.1).

**Configuration influente** : `elements.detail_include`, `title_field`,
`print_button`, `show_back_button`/`cb_show_details_back_button` (menu),
`use_view_name_as_title`, `cb_filter_in_title`/`cb_prefix_in_title`.

**Cas limites** : enregistrement filtré par le menu mais existant en base →
404 volontaire (pas de fuite d'existence) ; navigation Précédent/Suivant
hors liste (accès direct par URL) → repli sur `resolveSiblingRecordIdsByRecordId()`
(tri par `record_id` seul).

---

## 18. Formulaires publics (`publicforms`)

**Objectif** : page d'accueil/annuaire listant les vues CBNG publiées
accessibles au visiteur, avec indicateurs de permission optionnels
(voir/créer/éditer) et filtrage par tags.

**Préconditions** : au moins une vue `published=1`. **Déclencheur** :
arrivée sur le composant sans `id`/`storage_id` avec `view=list` (route par
défaut du Dispatcher), ou menu Joomla `cb_controller=publicforms`.

**Flux nominal** :
1. `PublicformsController::display()` (`:20-27`) force `view=publicforms`.
2. `PublicformsModel::__construct()` (`:80-160`) : pagination Joomla
   standard, tri/état/tag depuis l'état utilisateur ou la requête, liste
   blanche d'ids de vues autorisées par le menu (CSV/tableau), 6 bascules
   d'affichage (`cb_show_permission_column`, `cb_show_permission_new_column`,
   `cb_show_permission_edit_column`, `cb_show_introtext`, `cb_show_tags`,
   `cb_show_id`).
3. `getData()` (`:274-284`) : `SELECT * FROM forms WHERE [id IN (…)] AND
   published=1 [AND tag LIKE …] ORDER BY ordering` (SQL en chaîne littérale,
   pas de Query Builder).
4. `getPermissions()` (`:241-256`) — si l'option colonnes de permission est
   active, recalcule `view`/`new`/`edit` **pour chaque vue listée** (une
   passe `PermissionService` par ligne — coût N requêtes, non mis en cache).
5. `Publicforms/HtmlView::display()` assemble + `getTags()` (filtre latéral)
   → `site/tmpl/publicforms/default.php` (321 lignes).

**Flux alternatifs** : aucun flux de mutation propre — annuaire en lecture
seule ; seul élément interactif : formulaire de filtre par tag (`method="get"`,
seul formulaire `GET` du composant identifié en `07-security.md §3`).

**Permissions/ACL** : pas de gate global sur cet écran lui-même — chaque
ligne affiche ses propres droits calculés via `PermissionService` si
l'option est active côté menu.

**Tables** : `forms`.

**Classes** : `site/src/Controller/PublicformsController.php`,
`site/src/Model/PublicformsModel.php`, `site/src/View/Publicforms/HtmlView.php`.

**Configuration influente** : paramètres de menu `forms` (liste blanche),
`cb_show_permission*_column`, `cb_show_introtext`, `cb_show_tags`, `cb_show_id`.

**Cas limites / Zone inconnue** : `buildOrderBy()` (`:168-183`) contient un
tri désactivé en commentaire — le tri effectif est **toujours** `ORDER BY
ordering`, quels que soient `filter_order`/`filter_order_Dir` lus et
stockés en état (jamais appliqués). Comportement volontaire (garde-fou
contre une colonne de tri non whitelistée injectée dans le SQL en chaîne
littérale) ou dette de code non nettoyée — non tranché.

---

## 19. Aide contextuelle (`cblisthelp`/`cbstatshelp`)

**Objectif** : pages d'aide autonomes documentant la syntaxe des balises de
contenu `{CBList}`/`{CBStats}` (fonctionnalités 26-27), atteignables depuis
les messages d'erreur du mode embarqué.

**Préconditions** : aucune — page publique, sans vue CBNG particulière.

**Déclencheur** : `task=cblisthelp.display[&help_lang=fr|en|de]` (ou
`cbstatshelp.display`), généré par `EmbeddedListHelpService::syntaxUrl()`/
`CbstatsHelpService::syntaxUrl()` ou lien manuel.

**Flux nominal** :
1. `Dispatcher` → `task=cblisthelp.display` → `CblisthelpController`.
2. `Cblisthelp/HtmlView::display()` (`:29-108`, `final`, `declare(strict_types=1)`) :
   résout la langue d'affichage (explicite via `help_lang` ou langue Joomla
   active), charge le fichier de langue **du plugin**
   `plg_content_contentbuilderng_cblist` (pas celui du composant), construit
   12 sections statiques, délègue au rendu Joomla standard.
3. `site/tmpl/cblisthelp/default.php` (46 lignes) rend un sommaire + sections.

**Flux alternatifs** : `Cbstatshelp/HtmlView` — mécanisme identique, 23
sections, charge `plg_content_contentbuilderng_cbstats`.

**Gestion des erreurs** : `help_lang` invalide → retombe silencieusement sur
la langue Joomla active.

**Permissions/ACL** : aucune — page publique.

**Classes** : `site/src/Controller/{Cblisthelp,Cbstatshelp}Controller.php`
(`final`, 21-22 lignes, déclarent seulement `$default_view`),
`site/src/View/{Cblisthelp,Cbstatshelp}/HtmlView.php`,
`site/src/Service/{EmbeddedListHelpService,CbstatsHelpService}.php`.

**Messages** : `PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_*`/`_CBSTATS_HELP_*`
(fichiers de langue du plugin).

---

# E. Soumission & édition front

## 20. Création/édition d'un enregistrement (`edit`)

C'est la fonctionnalité la plus complexe du périmètre front.

**Objectif** : afficher un formulaire d'édition/création et traiter sa
soumission (validation, upload, inscription optionnelle, vérification,
génération d'article Joomla, notifications e-mail).

**Correction terminologique** (voir §0.4 et fonctionnalité 38) : contrairement
à une hypothèse initiale, les 11 classes de `site/src/Field/*` **ne sont pas**
les types de champ de ce formulaire. Les vrais « types de champ » d'un
enregistrement (texte, upload, captcha, groupe, etc.) sont résolus côté
`EditModel::store()` par une correspondance `switch ($special_field['type'])`
sur la colonne `elements.type` (`:849-909`) ; leur définition/rendu vit dans
`admin/src/Elementtypes/*`/plugins `contentbuilderng_form_elements` (hors
périmètre de ce catalogue).

**Flux nominal — affichage (GET)** :
1. `EditController::__construct()` : calcule les permissions pour
   `(formId, recordId)` (`setPermissions()`), distingue nouvel enregistrement
   (`cbIsNew=1`) d'édition ; cas preview storage direct
   (`setStoragePreviewPermissions()`).
2. `display()` (`:664-742`) : résout `formId` (URL > menu > storage direct
   auto-provisionné), valide la preview,
   `checkPermissions('edit'|'new', …)` sauf preview, `parent::display()`.
3. `EditModel::getData()` (portion GET pure, `:372-766`, **Zone inconnue** :
   non entièrement relue ligne à ligne dans les brouillons sources au-delà
   de `store()` — construit le rendu du formulaire via
   `TemplateRenderService`/le moteur d'éditable template, partage la même
   résolution de source/vue que `store()`).
4. `Edit/HtmlView::display()` (`:745-859`) : résout le thème, calcule la
   navigation Précédent/Suivant (avec vérification fine des droits d'édition
   par enregistrement — `canNavigateToEditableRecord()`,
   `isOwnerEditNavigationEnabled()`), gère le cas `edit_by_type` (rendu via
   le pipeline article Joomla, avec support de shortcodes
   `{BreezingForms: <id ou nom>}` — `renderBreezingFormsShortcodes()`,
   `:526-632`, exécute littéralement le composant `com_breezingformsng` en
   `include` avec sauvegarde/restauration complète de
   `$_REQUEST`/`$_GET`/`$_POST`/`$GLOBALS`, mécanisme fragile mais isolé par
   `try/finally`).
5. `site/tmpl/edit/default.php` (1328 lignes) rend le formulaire (POST,
   jeton `HTMLHelper::_('form.token')`), les boutons Enregistrer/Appliquer/
   Supprimer, la notation, un script inline (confirmation de suppression +
   compatibilité `ff_setSelected` groupes façon BreezingForms) — **pas** de
   fichier JS dédié séparé pour ce gabarit.

**Flux nominal — soumission (POST, `save()`/`apply()`)** :
1. `save($apply=false)` (`:222-302`) : `assertConstrainedAction()`,
   `applyPreviewContextForAction()`, `checkPermissions('edit'|'new', …)` sauf
   preview, `isEditableTemplateConfigured()` (sinon erreur bloquante — pas
   de gabarit d'édition défini), puis **`EditModel::store()`**.
2. **`EditModel::store()`** (`:767-2293`) — vérifie le jeton CSRF
   (`checkToken('post')`), `PluginHelper::importPlugin('contentbuilderng_submit')`
   (import de **tout le groupe**, fonctionnalité 32), recharge `forms`, puis
   pour les vues « non `edit_by_type`» :
   - Liste des champs réellement éditables : élément `editable=1` **ET**
     publié **ET** non exclu par les restrictions de menu
     (`cb_menu_published_fields`/`cb_menu_edit_fields`, combinées à
     `{CBList fields=}`/mode « nouvelle liste »).
   - Classement par catégorie (`text`, `upload`, `captcha`, `textarea`,
     autres), repérage des champs spéciaux d'inscription
     (username/name/password[repeat]/email[repeat]) si `act_as_registration`.
   - **Captcha** : champ `captcha` présent et éditable → `\Securimage`
     (bibliothèque tierce vendorisée) valide la valeur postée ; échec →
     `cb_submission_failed=1` + `COM_CONTENTBUILDERNG_CAPTCHA_FAILED`.
   - **Mode inscription** (`act_as_registration`) : valide nom/e-mail (format
     + confirmation)/nom d'utilisateur (regex anti-injection basique
     `[<>"'%;()&]`)/mot de passe (confirmation), teste les conflits
     username/email (`userConflictExists()`, exclut l'utilisateur courant en
     ré-édition de son propre enregistrement).
   - **Boucle de validation par champ** (deux passes sur `names`) : lecture
     de la valeur postée (`raw`/`html`/`array` selon `allow_raw`/`allow_html`/
     champ groupé), reconversion de format de date si `transfer_format`
     configuré, exécution d'un éventuel `custom_validation_script` PHP
     stocké en base (`self::customValidate()`), puis `validateField()` →
     `FieldValidationService::validate()` (règles natives
     `notempty`/`equal`/`email`/`date_not_before`/`date_is_valid`, gatées par
     `enable_validations`) + validations externes déclarées par plugins
     `contentbuilderng_validation` (`onValidate`). Toute erreur →
     `cb_submission_failed=1` + message badgé par le libellé du champ.
   - **Upload de fichier** (champ `upload`, fonctionnalité 21).
   - **Contraintes de type storage** (après la boucle « métier ») : champ
     requis manquant (`getRequiredElementIds()`), longueur `varchar`
     dépassée, valeur non entière/décimale/booléenne/temporelle invalide.
     **Fait observé — édition partielle tolérée** : un champ requis absent
     du POST sur un enregistrement **existant** n'est **pas** traité comme
     vide (permet une soumission « sparse », ex. mode embarqué qui ne rend
     qu'un sous-ensemble de champs) — uniquement en **création** un champ
     requis manquant est rejeté.
   - Événement `onBeforeSubmit`. Si `cb_submission_failed`, mémorise les
     valeurs postées en session (`cb_failed_values`) pour repopuler le
     formulaire, et **retourne sans écrire en base**.
   - **Écriture** : `$data->form->saveRecord($record_id, $values)` (`admin/src/types/*`)
     → id retourné `$record_return`.
   - Inscription active et enregistrement sauvegardé : `register()` crée/met
     à jour le compte Joomla (`UserHelper::hashPassword()`, jamais de
     hachage maison — `07-security.md §2`), avec mode « bypass plugin »
     alternatif qui insère directement une ligne `verifications` porteuse
     d'une balise `{CBVerify plugin: …; verification-name: …; verify-view: …}`
     traitée par `onPrepareContent` du plugin de contenu `contentbuilderng_verify`
     — permet de déclencher une vérification/paiement **après** une
     inscription bypass, sans article Joomla réel (fonctionnalité 23).
   - Résolution de la langue SEF (`default_lang_code`/`default_lang_code_ignore`),
     `UPDATE records` (edited++, sef, lang_code).
   - **Génération d'article Joomla** si `create_articles` et au moins un
     champ présent : `ArticleService::createArticle()` (`:1994`) avec la
     permission `fullarticle` pour décider des réglages avancés d'article.
   - Trace l'inscription dans `registered_users` (upsert protégé par
     contrainte unique, doublon silencieusement ignoré).
   - Actions personnalisées par champ (`custom_action_script`), événement
     `onAfterSubmit` (fonctionnalité 32).
   - **E-mails de notification** (admin + soumetteur) si
     `email_notifications`/`email_update_notifications` : gabarits rendus
     par `TemplateRenderService::getEmailTemplate()` (jetons `{champ}` +
     jetons dynamiques `{RECORD_ID}`, `{USER_ID}`, `{USERNAME}`, `{IP}`,
     `{VIEW_NAME}`, `{VIEW_ID}`, …), pièces jointes optionnelles, corps
     HTML/texte selon `email_html`.
   - Retourne `$record_return` ou `false` si aucune ligne de données valide.
3. `EditController::save()` : succès sans échec de soumission → message
   `COM_CONTENTBUILDERNG_SAVED` + redirection (`return=` encodé si fourni) ;
   sinon force `$apply=true` (reste sur le formulaire) + type `error`,
   redirection avec état de liste/preview/mode embarqué préservés.

**Flux alternatifs** : `apply()` = `save(true)` ; suppression (fonctionnalité
22) ; changement d'état/publication/langue à l'unité (fonctionnalité 16.1) ;
mode storage direct (formId auto-résolu/créé) ; vue `edit_by_type` (rendu
via pipeline article Joomla).

**Gestion des erreurs** : gabarit d'édition non configuré → message
bloquant + redirection vers l'affichage. Captcha/validation/upload/
inscription échoués → `cb_submission_failed=1`, messages badgés, formulaire
réaffiché avec valeurs restaurées depuis la session. Inscription échouée
après écriture du record → **rollback applicatif**
(`clearDirtyRecordUserData()`) puis `Exception COM_CONTENTBUILDERNG_REGISTRATION_FAILED` —
**Zone inconnue** : rollback applicatif (suppression ciblée des données
utilisateur liées), **pas** une transaction SQL globale entourant tout
`store()` — un échec partiel entre l'écriture de l'enregistrement et
l'inscription laisse potentiellement un enregistrement CBNG sans compte
Joomla associé si l'exception n'est pas totalement rattrapée en amont.

**Données entrantes** : `id`/`storage_id`, `record_id`, `cb_<referenceId>`
(POST, une entrée par champ éditable), `cb_delete_<referenceId>`
(suppression de fichier uploadé), `cb_<uploadFieldId>` (fichiers, `$_FILES`),
jeton de formulaire, `return=` (base64), `backtolist`, `Form[...]`
(réglages d'article si `fullarticle`), `cb_category_id`, `cblist_*`.

**Données sortantes** : redirection HTTP (302) + message flash Joomla, ou
réponse JSON pour les actions `cb_ajax=1` (état/publication).

**Permissions/ACL** : `new`/`edit` (`PermissionService`), `core.edit.state`
Joomla natif en plus pour la publication en mode storage direct,
`fullarticle` pour les réglages avancés d'article.

**Effets de bord** : écriture `records`/`list_records`/`articles`/
`#__content`/`#__assets`/`#__users`/`registered_users`/`verifications`
(selon config) ; upload de fichier sur disque (avec `index.html` de
protection) ; suppression des anciens fichiers remplacés/supprimés ;
e-mails sortants (SMTP via `MailerFactoryInterface`) ; purge du cache
`com_content`/`com_contentbuilderng` (`cleanComponentCaches()`, trait
`OwnershipTrait`).

**Tables** : `forms`, `elements`, `records`, `list_records`, `list_states`,
`articles`, `registered_users`, `verifications`, `#__users` (compteurs),
storage physique ou BreezingForms, `#__content`, `#__assets`, `#__users`,
`#__languages`.

**Classes** : `site/src/Controller/EditController.php`,
`site/src/Model/EditModel.php` (+ traits
`site/src/Model/Edit/{ListStateAndRatingTrait,OwnershipTrait,PathHelpersTrait,VisibilityTrait}.php`),
`site/src/View/Edit/HtmlView.php`,
`admin/src/Service/{ArticleService,TemplateRenderService,FieldValidationService,PathService}.php`,
`admin/src/Helper/{ContentbuilderngHelper,StorageColumnTypeHelper,FormSourceFactory}.php`,
`site/src/Helper/DuplicateKeyViolationHelper.php`.

**Plugins/événements** : `contentbuilderng_submit` (import global,
fonctionnalité 32), `contentbuilderng_validation` (`onValidate`),
`contentbuilderng_form_elements` (`onAfterValidationSuccess`),
`contentbuilderng_verify` (contenu, `onPrepareContent`, bypass d'inscription,
fonctionnalité 23), `onBeforeSubmit`/`onAfterSubmit` (génériques CBNG),
`onEditableTemplateCss`/`onEditableTemplateJavascript` (thème), pipeline
article natif si `edit_by_type`.

**JS** : aucun fichier dédié — script inline minimal (`edit/default.php:502-…`).
Notation : voir fonctionnalité 24.1.

**Configuration influente** : `enable_validations` (composant, global),
`max_filesize`/`allowed_file_extensions` par champ upload,
`upload_directory`/`protect_upload_directory` (vue), `act_as_registration` +
6 champs de mapping, `registration_bypass_plugin`, `force_login`/`force_url`,
`create_articles`/`delete_articles`/`limited_article_options(_fe)`,
`email_notifications`/`email_update_notifications` + gabarits,
`verification_required_*` (gérés en amont par `PermissionService`).

**Messages** : `COM_CONTENTBUILDERNG_SAVED`, `_EDITABLE_TEMPLATE_NOT_SET`,
`_CAPTCHA_FAILED`, `_NAME_EMPTY`/`_USERNAME_EMPTY`/`_USERNAME_INVALID`/
`_USERNAME_NOT_AVAILABLE`/`_EMAIL_EMPTY`/`_EMAIL_INVALID`/`_EMAIL_MISMATCH`/
`_PASSWORD_EMPTY`/`_PASSWORD_MISMATCH`, `_FILESIZE_EXCEEDED`,
`_FILE_EXTENSION_NOT_ALLOWED`, `_UPLOAD_FAILED`, `_STORAGE_REQUIRED_VALUE`/
`_VARCHAR_LENGTH`/`_INTEGER_VALUE`/`_DECIMAL_VALUE`/`_BOOLEAN_VALUE`/
`_DATE_VALUE`/`_DATETIME_VALUE`, `_REGISTRATION_FAILED`, `_EMAIL_SEND_ERROR`.

**Cas limites** : champ absent du POST sur un gabarit d'édition obsolète
(jeton `{champ:value}` disparu) → valeur existante conservée plutôt
qu'effacée (`missingFromPost`, `:1096-1109`) ; fichier de même nom déjà
présent → renommage par hash aléatoire ; champ requis manquant en édition
partielle (sparse) → toléré ; inscription bypass sans e-mail vérifié →
dépend entièrement du plugin `contentbuilderng_verify` sélectionné
(fonctionnalité 23).

---

## 21. Upload de fichier

**Nom** : sous-flux d'`EditModel::store()` (`:1148-1358`, voir aussi
`07-security.md §7`).

**Objectif** : réceptionner, valider et stocker les fichiers soumis par un
champ `upload` d'un formulaire d'enregistrement.

**Flux nominal** : résolution du répertoire de destination par jetons
(`{champ:value}`, `{userid}`, `{username}`, `{name}`, `{date}`, `{time}`,
`{datetime}`, `{CBSite}`/`{cbsite}` → `JPATH_SITE`) via
`PathHelpersTrait::createPathByTokens()` ; test de taille max
(`max_filesize` du champ, `k`/`m`/`g`) ; test d'extension (liste blanche
configurée ou repli sur `DEFAULT_ALLOWED_UPLOAD_EXTENSIONS`, plus
`hasExecutableExtension()` — double-extension) ; assainissement du nom
(`File::makeSafe()`, remplacement espaces/points internes, troncature 100
caractères, déduplication par hash aléatoire) ; confinement au site
(`ContentbuilderngHelper::is_internal_path()`) ; création d'un `index.html`
de protection ; suppression des anciens fichiers remplacés/supprimés
(`cb_delete_<id>=1`, uniquement si le champ n'a pas de règle de validation
empêchant la suppression) ; script de validation personnalisé optionnel
puis validations standard.

**Classes** : `site/src/Model/EditModel.php` (trait `PathHelpersTrait`),
`admin/src/Helper/ContentbuilderngHelper.php`.

---

## 22. Suppression d'un enregistrement

**Nom** : sous-flux `EditController::delete()` (`:309-385`) +
`EditModel::delete()` (`:2575-2701`).

**Flux nominal** : `assertConstrainedAction('delete')`, jeton POST
obligatoire, contexte preview appliqué mais **jamais** de permission
`delete` accordée en mode preview (commentaire explicite, `:314-317`),
`checkPermissions('delete', …)`. `resolveDeleteOwnerRestriction()`
(`:2558-2573`) : si l'utilisateur n'a pas de droit de groupe `delete`,
restreint la suppression à ses **propres** enregistrements (id utilisateur,
ou `-1` — aucun enregistrement — si anonyme). `$data->form->delete($items, $formId, $ownerRestriction)`
(implémentation `admin/src/types/*`) fait le travail réel sur la source ; en
retour, `EditModel::delete()` purge `list_records`, `records`,
optionnellement les articles Joomla liés (`delete_articles`, avec
déclenchement propre des événements `onContentBeforeDelete`/
`onContentAfterDelete` et purge manuelle de `#__assets`/
`#__workflow_associations` — contournement du modèle article natif,
`03-data-model.md §9`), puis `articles`. Purge du cache en fin de traitement.

**Permissions/ACL** : `delete` (`PermissionService`), restriction owner si
pas de droit de groupe.

**Effets de bord** : `DELETE list_records`/`records`/`articles`, suppression
d'article Joomla optionnelle avec purge `#__assets`/
`#__workflow_associations`, purge de cache.

**Tables** : `records`, `list_records`, `articles`, `#__content`,
`#__assets`, `#__workflow_associations`.

**Classes** : `site/src/Controller/EditController.php`,
`site/src/Model/EditModel.php`.

---

## 23. Vérification / paiement (`verify`)

**Nom** : fonctionnalité fusionnée (source du brouillon site §6 + admin §13
+ plugins §1.7/§6) — gate une action (voir/créer/éditer) derrière une
confirmation externe (e-mail, paiement PayPal, « passe-plat ») avant de
débloquer `#__contentbuilderng_users.verified_{view,new,edit}`, et un volet
distinct d'activation manuelle de compte Joomla par un administrateur.

**Acteurs** : visiteur soumettant un formulaire gaté, PayPal (appel retour),
administrateur (activation manuelle de compte Joomla — réutilisation du
mécanisme natif `com_users`), rédacteur d'article (balise `{CBVerify}`).

**Préconditions** : `forms.verification_required_{view,new,edit}=1` (géré en
amont par `PermissionService::checkPermissions()`, **pas** par `VerifyModel`
lui-même) — la balise `{CBVerify …}` (plugin de contenu
`contentbuilderng_verify`) doit avoir été rendue dans un article visité au
préalable, posant en session la clé
`com_contentbuilderng.verify.<plugin><verification_name>`.

### 23.1 Rendu de la balise `{CBVerify}` (plugin de contenu `contentbuilderng_verify`)

À ne pas confondre avec le **groupe** de plugins `contentbuilderng_verify`
(§23.3) — même nom fonctionnel, type de plugin Joomla différent
(`plugins/contentbuilderng_verify/*`).

**Objectif** : remplacer `{CBVerify plugin:paypal;verification-name:...;
verify-view:15;...}` par un lien/bouton qui initie le parcours de
vérification/paiement, ou par le rendu personnalisé que le plugin choisit de
renvoyer via `onViewport`.

**Déclencheur** : `onContentPrepare`.

**Flux nominal** (`ContentbuilderngVerify.php:73-198`) :
1. `preg_match_all("/\{CBVerify([^}]*)\}/i", ...)`.
2. Parsing `clé:valeur;clé:valeur`, avec syntaxe multilingue **interne au
   plugin** (`getValueByLanguage()`, `:46-71`) : `fr___Texte français|en___English text`
   — sépare par `|`, chaque segment `lang___valeur`, sélectionne le segment
   dont `lang` correspond au paramètre GET `lang` courant, sinon le premier
   trouvé, sinon la valeur brute si aucun séparateur `___`. Options
   reconnues : `plugin`, `verification-name`, `verification-msg`, `image`,
   `image-width`, `image-height`, `desc`, `verify-view`, `verify-levels`
   (filtré à `new`/`edit`/`view`), `require-view`, `return-admin`,
   `return-site` ; toute autre clé collectée dans `plugin_options`
   (transmise telle quelle au plugin verify ciblé, ex. `amount`,
   `currency-code`, `tax`, `use-ipn` pour PayPal).
3. Si `plugin`/`verification_name`/`verify_view` manquants → message
   d'avertissement HTML codé en dur, **non traduit** (`:181`, écart aux
   conventions AGENTS.md — **Fait observé**, non corrigé, hors périmètre
   documentation).
4. Sinon : construit `plugin_settings` (query string encodée, `return-site`/
   `return-admin` en base64), la stocke en **session** sous la clé
   `com_contentbuilderng.verify.<plugin><verification_name>` (après
   suppression de toute valeur précédente — anti-fuite entre vérifications
   successives), construit le lien public
   `index.php?option=com_contentbuilderng&view=verify&plugin=...&verification_name=...&format=raw`.
5. `PluginHelper::importPlugin('contentbuilderng_verify', $plugin)` (import
   **ciblé**) puis `dispatch('onViewport', [$link, $plugin_settings])`.
6. Si le plugin ciblé renvoie un résultat non vide via `onViewport` : ce
   résultat **remplace** la balise. **Fait observé** : ni `paypal` ni
   `passthrough` n'exploitent réellement ce point d'extension aujourd'hui
   (`onViewport` retourne toujours `''` dans les deux implémentations) — le
   point d'extension existe mais n'a pas d'implémentation concrète.
7. Sinon (cas réel actuel) : rendu d'un lien `<a class="cb_verification_link">`
   avec image ou texte de description.

**Cas limites** : plusieurs `{CBVerify}` dans un même article → clés de
session distinctes par `plugin`/`verification-name`.

### 23.2 Traitement serveur (`VerifyController`/`VerifyModel`, site + admin)

**Déclencheur** : `index.php?option=com_contentbuilderng&view=verify&format=raw`
(GET, avec ou sans `verification_id`/`token`/`verify=1`).

**Flux nominal** :
1. `Dispatcher` route vers `VerifyController::display()`
   (`site/src/Controller/VerifyController.php:20-29`) — force `view=verify`,
   `format=raw`, délègue à `parent::display()`.
2. `VerifyController` instancie `VerifyModel` (`site/src/Model/VerifyModel.php` —
   classe **vide**, `extends CB\...\Administrator\Model\VerifyModel`) : **la
   totalité de la logique métier vit dans le constructeur du modèle admin**
   (`admin/src/Model/VerifyModel.php:109-467`), partagé entre client site et
   administrateur.
3. Le constructeur :
   - Résout la transaction (`verification_id` en query, ou la dernière
     configuration posée en session par `{CBVerify}`).
   - Décode la configuration `setup` (`parse_str`) : plugin, nom de
     vérification, vue à exiger (`require_view`), niveaux à débloquer
     (`verify_levels`), URLs de retour site/admin, message de succès
     personnalisé.
   - Si `require_view` configuré : recherche le dernier enregistrement de
     l'utilisateur dans cette vue, sinon **redirige vers `edit.display`** de
     cette vue.
   - Si ni `verify=1` ni `token=` (première visite) : **crée** la ligne
     `#__contentbuilderng_verifications`
     (`verification_hash = md5(uniqid()..mt_rand()..userId)` — CSPRNG non
     utilisé, `07-security.md §9`), stocke `verification_data` (jetons
     `{champ}` sérialisés depuis le dernier enregistrement soumis).
   - Importe `contentbuilderng_verify_<plugin>` (groupe
     `contentbuilderng_verify`, ex. `paypal`/`passthrough`), dispatch
     `onSetup` (le plugin valide sa propre config — échec →
     `Exception COM_CONTENTBUILDERNG_VERIFICATION_SETUP_FAILED`).
   - **Étape 1 (redirection vers le fournisseur)** : si `!verify=1`,
     construit l'URL de retour, dispatch `onForward` (le plugin décide de
     rediriger, ex. formulaire PayPal auto-soumis) — si le plugin retourne
     une URL, `redirect()`.
   - **Étape 2 (retour du fournisseur, `verify=1`)** : dispatch `onVerify`
     (validation côté serveur, ex. IPN/PDT PayPal ou simple retour
     passe-plat) ; succès → met à jour `#__contentbuilderng_users` (upsert
     `published=1` + `verified_{view,new,edit}`/`verification_date_{view,new,edit}`
     selon `verify_levels`), invalide le hash (`verification_hash=''`,
     `verification_date=NOW()`, concatène d'éventuelles données de retour du
     plugin), puis — si `token=` d'activation de compte Joomla présent —
     `activate($token)` (réutilise le flux natif `com_users`,
     `UserFactoryInterface::loadUserById()`, sans réimplémentation maison).
   - Message flash + redirection finale (`return-site`/`return-admin`
     décodés et validés `Uri::isInternal()` avant `redirect()`, ou repli
     `index.php`).

**Flux alternatifs** :
- **`verify_by_admin=1&token=`** : `VerifyModel::activate_by_admin(string $token)`
  (`admin/src/Model/VerifyModel.php:469-559`) — vérifie l'ACL `com_users`
  (**pas** `com_contentbuilderng` — **Fait notable**, `:474`), recherche
  l'utilisateur par `activation=token` (`#__users`, `block=1`,
  `lastvisitDate` nulle), le débloque (`block=0`, `activation=''`) via
  l'API Joomla `UserFactoryInterface`/`User::save()` (pas de SQL brut sur
  `#__users`), envoie l'e-mail de bienvenue Joomla standard (avec/sans mot
  de passe selon `com_users.params.sendpassword`), redirige vers
  `index.php?option=com_users`. Accessible aussi bien depuis un lien reçu
  par e-mail (notification admin de nouvelle inscription en attente) que
  depuis l'écran Utilisateurs/Vérifications (**Hypothèse** sur le
  déclencheur exact).
- **Vérification « bypass » déclenchée depuis une inscription CBNG**
  (fonctionnalité 20, `EditModel::store()`) : insertion directe d'une ligne
  `verifications` avec `verification_data = 'type=registration&'`, sans
  passer par le plugin de contenu `{CBVerify}` classique.

**Gestion des erreurs** : `COM_CONTENTBUILDERNG_VERIFICATION_SETUP_VIEW_UNAVAILABLE`
(vue `require_view` introuvable/dépubliée), `_SETUP_FAILED` (échec
`onSetup`), `_VERIFICATION_INVALID_ID` (config manquante/corrompue →
redirection accueil), `_VERIFICATION_FAILED`/`_NOT_EXECUTED` (échec
`onVerify` ou vérification jamais initiée) ; `COM_USERS_ACTIVATION_TOKEN_NOT_FOUND`,
`COM_USERS_REGISTRATION_ACTIVATION_SAVE_FAILED`,
`COM_USERS_REGISTRATION_ADMINACTIVATE_SUCCESS` (activation admin — clés
**Joomla core `com_users`**, pas CBNG — le composant réutilise le
vocabulaire natif plutôt que d'en définir un propre pour ce flux).

**Permissions/ACL** : aucune vérification ACL CBNG propre dans `VerifyModel`
(le gate `verification_required_*` est appliqué **en amont**, côté
`PermissionService::checkPermissions()`, `07-security.md §1.3`) ;
`activate_by_admin()` exige `core.create` sur `com_users` (Joomla natif).

**Effets de bord** : écriture/mise à jour `verifications` et `users` ; appel
réseau sortant éventuel vers un fournisseur tiers (PayPal, `10-dependencies.md §3`) ;
envoi d'e-mail natif Joomla si activation de compte ; déblocage direct d'un
compte utilisateur (activation admin).

**Tables** : `verifications`, `users`, `#__users`, `forms` (résolution
`require_view`).

**Classes** : `site/src/Controller/VerifyController.php`,
`site/src/Model/VerifyModel.php` (coquille vide),
`admin/src/Model/VerifyModel.php` (logique réelle, partagée),
`site/src/View/Verify/{HtmlView,RawView}.php` (quasi vides — tout se joue
dans le constructeur du modèle avant même le rendu),
`admin/src/View/Verify/RawView.php` (layout `raw`, réponse minimale, accès
par lien e-mail externe).

### 23.3 Groupe de plugins `contentbuilderng_verify` (`passthrough`, `paypal`)

Le protocole PayPal (paiement classique legacy, PDT/IPN,
`CURLOPT_SSL_VERIFYPEER => false`, repli `fsockopen` port 80) est déjà
détaillé dans `10-dependencies.md §3` et `07-security.md` (défaut de
sécurité TLS déjà signalé) — non redétaillé ici.

**Contrat commun** (`getSubscribedEvents()` identique, 4 événements) :
`onViewport`, `onSetup`, `onForward`, `onVerify`, orchestrés par
`VerifyModel` (§23.2) :
1. `dispatch('onSetup', [$this_page, $out])` (`VerifyModel.php:293`) —
   chaque plugin lit ses propres paramètres (ex. PayPal exige
   `plugin_options['amount']`, sinon message d'erreur bloquant,
   `Paypal.php:105-117`). Retour non vide = configuration invalide, flux
   arrêté (`passthrough` retourne toujours `''`, jamais bloquant).
2. `dispatch('onForward', …)` (`:311`) — le plugin peut **rediriger**
   (PayPal : formulaire HTML auto-soumis vers `cgi-bin/webscr`, `exit`
   immédiat, `Paypal.php:132-168`), ou renvoyer simplement l'URL de retour
   (`passthrough` **retourne `$return_url`** — `VerifyModel` redirige donc
   quand même vers cette URL, `:315-317` — aller-retour transparent, cohérent
   avec sa description « remplace le système de paiement par un simple
   enregistrement Joomla sans paiement »).
3. Au retour (`verify=1&verification_id=...`) : `dispatch('onVerify', …)`
   (`:323`) — PayPal choisit entre vérification synchrone (`_notify-synch`)
   ou IPN (`_notify-validate`) selon `use-ipn`/`paypal_ipn=true` ;
   `passthrough` retourne systématiquement un succès vide
   (`['msg'=>'','is_test'=>0,'data'=>[]]`, `Passthrough.php:92-103`) —
   vérification toujours réputée réussie.
4. `onViewport` (dispatché depuis §23.1, **pas** depuis `VerifyModel`) : les
   deux implémentations actuelles retournent systématiquement `''` — point
   d'extension existant mais inexploité.

**Cas limites** : `onForward` de PayPal fait un `exit` immédiat après le
formulaire auto-soumis — tout code appelant après ce point dans
`VerifyModel` ne s'exécute jamais pour PayPal (normal pour une redirection
externe), alors que `passthrough` rend la main normalement.

**Configuration influente** : paramètres de plugin PayPal
(`business`/`token`/`test`/`test_business`/`test_token`,
`10-dependencies.md §3.4`) ; `passthrough` sans paramètre observé.

**Zone inconnue** (site §13) : `verification_days_{view,new,edit}` —
colonnes existantes (`03-data-model.md`) mais usage non localisé dans le
constructeur de `VerifyModel` lu par les brouillons sources — probablement
une fenêtre de validité de vérification, à vérifier côté `PermissionService`.
**Zone inconnue** : accumulation non bornée de lignes `verifications` (pas
de purge observée, `03-data-model.md §19`).

**Classes** : `plugins/contentbuilderng_verify/paypal/src/Extension/Paypal.php:Paypal`,
`plugins/contentbuilderng_verify/passthrough/src/Extension/Passthrough.php:Passthrough`,
`plugins/content/contentbuilderng_verify/src/Extension/ContentbuilderngVerify.php:ContentbuilderngVerify`.

---

# F. API et export

## 24. API/AJAX interne générique (`ApiController`) et notation

Bien que non explicitement nommée dans les vues `site/tmpl/*` (pas de
gabarit — réponses JSON pures), cette fonctionnalité est centrale pour
`06-api-contracts.md`.

**Objectif** : exposer, via un seul contrôleur, (a) un mini-CRUD REST-like en
lecture/écriture sur les enregistrements d'une vue pour des intégrations
tierces (JS externe, autre site, app mobile), et (b) des actions AJAX
internes consommées par le rendu HTML lui-même (notation, valeurs uniques
pour listes déroulantes dépendantes, agrégats `{CBStats}`).

**Déclencheur** : `index.php?option=com_contentbuilderng&task=api.display&id=<formId>[&record_id=][&action=][...]`.

### Endpoints

| Méthode | `action=` | Paramètres | Comportement | Permissions requises |
|---|---|---|---|---|
| GET | *(aucun)*, `record_id` absent | `id`, `list[limit\|start]` (≤100/20 défaut) | Liste paginée : `{items:[{record_id, values:{...}}], pagination:{total,limit,start}}`, filtrée aux champs `api_allowed=1` | `api`, `view`, `listaccess` |
| GET | *(aucun)*, `record_id` présent | `id`, `record_id`, `verbose` (0/1) | Détail : `{record_id, form_id, fields:{nom: valeur}}` (ou `{reference_id,label,value}` si `verbose=1`), filtré `api_allowed` | `api`, `view` |
| PUT/PATCH/POST | *(aucun)*, `record_id` requis | corps JSON `{"fields":{...}}` **ou** POST `fields[...]` | Met à jour via `EditModel::store()` (réutilise tout le pipeline de validation/upload/e-mail — champs non `api_allowed` silencieusement ignorés), retourne `{message, record_id, detail:{...}}` | `api`, `edit` |
| GET | `get-unique-values` | `field_reference_id`, `where_field`, `where` | Valeurs distinctes d'un champ (listes déroulantes dépendantes), `{code,field_reference_id,msg:[...]}` — 403 si l'un des champs référencés n'est pas `api_allowed` | `api` |
| POST | `rating` | `id`, `record_id`, `rate` (1-5 selon `rating_slots`) | Notation d'un enregistrement (§24.1) | `rating` |
| GET | `stats` | `field`, `filter[field]`, `filter[value]` | Agrégat brut sur un champ (`StatsService`) | `stats` |
| GET | `cbstats` | `output` (json/table/pie/bar/histogram/line/radar/total/remaining/percentage/progress/distinct/sum/min/max/avg/view_name), `field`, `target`, `value`, `filter[...]`, `sort`, `dir`, `add`, `titles`, `titleset`, `groups`, `groupset`, `hide`, `limit` | Moteur complet derrière `{CBStats}` (fonctionnalité 27) — le calcul passe par ce même endpoint | `stats` |

**Fait observé — normalisation `record_id`**
(`ApiController::normalizeRequestedRecordId()`, `:1024-1069`) : accepte
indifféremment l'id « métier » (`records.record_id`, id dans la table
source) ou l'id technique de suivi CBNG (`records.id`) — retombe sur celui
fourni si aucune correspondance.

**Fait observé — CSRF spécifique** (`assertStateChangingRequestToken()`,
`:950-963`) : accepte le jeton soit en paramètre de formulaire, soit dans
l'en-tête `X-CSRF-Token` — comparé en temps constant (`hash_equals`) au
jeton de session — **avant** de retomber sur `Session::checkToken('post'|'get')`
natif. **Différent** du mécanisme des actions `edit.state`/`edit.publish`
(fonctionnalité 16.1), qui n'accepte que le jeton en paramètre de formulaire.

**Fait observé — filtrage par champ API** :
`ApiFieldPermissionService::getAllowedReferenceMap()` restreint
systématiquement les champs exposés en lecture **et** en écriture à ceux
marqués `elements.api_allowed=1` — indépendamment des droits d'affichage/
édition front classiques (double filtrage).

**Fait observé — sparse fieldsets** : `SparseFieldsetService::filter()`
applique, pour toute requête GET, un paramètre `fields[...]` similaire au
filtrage JSON:API (113 lignes, mécanique générique de projection de champs).

**Format de réponse standard** : `{"success": bool, "messages": [string], "data": mixed}`,
`Content-Type: application/json; charset=utf-8`,
`Cache-Control: private, no-store, max-age=0`, `X-Content-Type-Options: nosniff`.
Message d'erreur **générique** sauf si `isFormDebugEnabled($formId)` (debug
de la vue) et code 4xx — sinon `getPublicApiErrorMessage()` renvoie un
message générique par tranche de code (400/405, 401/403, autre) pour ne pas
fuiter de détail d'implémentation.

**JS** : aucun fichier `media/js/*` du périmètre analysé n'appelle
`task=api.display` avec `action=stats`/`cbstats`/`get-unique-values`/le CRUD
PUT/PATCH — usages qui sont donc, à ce stade, des **points d'intégration
externes** plutôt que consommés par le rendu HTML du composant lui-même.
**Seule exception** : `action=rating`, appelé depuis
`media/js/contentbuilderng.js:57-63` (`cbRate()`, fallback) et
`media/js/list-init.js:42-104` (`window.cbRate`, version « liste » avec
jeton CSRF dédié).

**Messages** : `COM_CONTENTBUILDERNG_FORM_NOT_FOUND`, `_API_RECORD_ID_REQUIRED`,
`_API_FIELDS_REQUIRED`, `_API_FIELD_NOT_ALLOWED`, `_API_ERROR_INVALID_REQUEST`/
`_ACCESS_DENIED`/`_RESOURCE_UNAVAILABLE`, famille `_API_CBSTATS_*`,
`_RATING_NOT_ALLOWED`, `_RATED_ALREADY`, `_THANK_YOU_FOR_RATING`.

**Cas limites** : méthode HTTP non gérée (`DELETE`, etc.) → `405 Unsupported
HTTP method` ; `record_id` fourni mais introuvable après normalisation →
conservé tel quel, laissant le 404 se produire plus loin.

**Classes** : `site/src/Controller/ApiController.php`,
`admin/src/Service/{ApiFieldPermissionService,StatsService}.php`,
`site/src/Service/SparseFieldsetService.php`.

### 24.1 Sous-flux — Notation d'un enregistrement (`action=rating`)

**Objectif** : incrémenter `rating_sum`/`rating_count` d'un enregistrement,
avec anti-doublon par IP (fenêtre glissante 1 jour) + session.

**Déclencheur** : clic sur une étoile générée par
`admin/src/Helper/RatingHelper::getRating()` (rendu HTML inline avec
`onclick="cbRate(...)"`, URL construite serveur :
`index.php?option=com_contentbuilderng&lang=<lang>&task=api.display&format=json&action=rating&id=<formId>&record_id=<recordId>&rate=<n>`,
`:189-234`). Aussi rendu par le plugin de contenu `{CBRating}`
(fonctionnalité — voir Section G, plugin `contentbuilderng_rating`) qui
partage le même helper de rendu.

**Flux nominal** : `ApiController::ratePayload()` (`:508-699`) —
`can('rating')` (`PermissionService`), méthode POST obligatoire,
`assertStateChangingRequestToken()`, résout la vue source, calcule la note
effective selon `rating_slots` (1 = « j'aime » binaire ; 2 = pouce haut/bas,
seuil `rate≥4`→1 sinon 0 ; 3/4/5 = échelle bornée), purge les entrées
`rating_cache` de plus d'un jour (`DATEDIFF(:now, date) >= 1`), teste le
doublon (table `rating_cache` **et** clé de session
`com_contentbuilderng.rating.rated<formId><recordId>`), puis dans une
**transaction** : insère `rating_cache`, met à jour
`records.rating_sum/count/lastip`, et — si un article Joomla est lié et
publié — synchronise `#__content_rating` (upsert) pour que le système de
notation natif Joomla (`com_content`) reste cohérent avec la note CBNG.
Répond `{code:0, msg:"Merci..."}` ou `{code:1, msg:"Déjà noté"}`.

**Gestion des erreurs** : `DuplicateKeyViolationHelper::isDuplicateKeyViolation()`
intercepte une violation de contrainte unique concurrente sur `rating_cache`
(course entre deux requêtes simultanées) et la traite comme « déjà noté »
plutôt que de laisser remonter une exception SQL.

**Tables** : `rating_cache`, `records`, `articles`, `#__content`,
`#__content_rating`.

**Classes** : `site/src/Controller/ApiController.php` (`ratePayload()`),
`admin/src/Helper/RatingHelper.php`, `site/src/Helper/DuplicateKeyViolationHelper.php`.

**Zone inconnue** (source plugins §9) : le détail exact du rendu HTML/JS de
`RatingHelper::getRating()` (au-delà du HTML inline exposé ici) n'est pas
tracé ligne à ligne dans les brouillons sources.

---

## 25. Export de données (XLSX)

**Objectif** : générer un classeur XLSX (PhpSpreadsheet) des enregistrements
visibles d'une vue, avec les mêmes filtres/tri que l'écran liste actif.

**Préconditions** : `forms.export_xls` contient au moins un élément
exportable (`elements.export_include=1`) ; vue publiée (sauf preview).

**Déclencheur** : `index.php?option=com_contentbuilderng&view=export&id=<formId>`
(généré depuis l'écran liste avec l'état de filtre/tri courant en query
string) ou accès direct.

**Flux nominal** :
1. `Dispatcher` → `controller=export` (règle explicite `view∈{export,verify}`)
   → `ExportController::display()` (`:22-35`) — valide une éventuelle
   preview admin signée, force `format=raw`, délègue.
2. `ExportModel::__construct()`/`getData()` (`:64-491`) — reprend l'état de
   filtre/tri/langue/état de publication depuis la session (partagé avec
   `ListModel` via les mêmes clés `formsd_filter*`), résout la vue,
   sélectionne les colonnes `export_include=1` (avec support des mêmes
   restrictions de menu/mode embarqué que la liste), interroge
   `$form->getListRecords(..., false)` (dernier argument `false` —
   **Comportement déduit** : désactive vraisemblablement une option de
   rendu HTML non pertinente pour un export brut, non vérifié précisément).
3. `Export/HtmlView`/`RawView` (quasi identiques, `RawView` utilisée en
   pratique via `format=raw` forcé) transmettent `$data` au gabarit.
4. `site/tmpl/export/default.php` (369 lignes) : construit un classeur
   PhpSpreadsheet (feuille unique, titre dérivé du nom de la vue, gelée en
   ligne 1, en-tête grisé/centré), colonnes réservées optionnelles (ID, état
   coloré, publication), colonnes de données typées
   (`SpreadsheetExportValueHelper::resolveColumnType()`/`prepareCellValue()`
   — dates/nombres/texte avec format Excel adapté), auto-largeur plafonnée à
   70 caractères, alignement gauche (texte)/droite (numérique), en-têtes
   HTTP de téléchargement forcé (`Content-Disposition: attachment`, nom
   construit par `ExportFilenameService::build()` — mode par défaut ou
   personnalisé via `cb_export_filename_mode`/`cb_export_filename` de menu),
   écrit directement sur `php://output` puis `exit`.

**Zone à signaler — renvoi croisé `07-security.md §1.3`** : accès **direct**
à `view=export&id=<id>` **ne rejoue pas** le contrôle `listaccess` que
`ListController::display()` applique pour la même donnée en liste —
`ExportController`/`ExportModel` ne consultent jamais `PermissionService`.
Seuls filtres appliqués : état publié (`PublishedRecordVisibilityHelper`) et
propriétaire (`own_only_fe`). Un lien d'export généré depuis l'écran liste
est protégé **transitivement** (la session a déjà été armée par le passage
préalable en liste), mais une URL d'export devinée/partagée directement ne
l'est pas forcément selon la configuration ACL de la vue.

**Cellule protégée contre l'injection de formule** (CSV/XLSX injection) :
`setCellValueExplicit(..., DataType::TYPE_STRING)` systématique pour les
en-têtes et le texte (`:136-139`).

**Gestion des erreurs** : `COM_CONTENTBUILDERNG_FORM_NOT_FOUND`,
`_NOT_EXPORTABLE_ERROR` (`export_xls` vide).

**Permissions/ACL** : aucune dans ce contrôleur/modèle (voir « Zone à
signaler » ci-dessus) — seulement les filtres de visibilité de données.

**Tables** : `forms`, `elements`, `list_records`/`list_states` (colonne état
colorée), `#__facileforms_elements`/`_forms` (si source BreezingForms),
storage physique.

**Classes** : `site/src/Controller/ExportController.php`,
`site/src/Model/ExportModel.php`, `site/src/View/Export/{HtmlView,RawView}.php`,
`site/src/Helper/SpreadsheetExportValueHelper.php`,
`site/src/Service/ExportFilenameService.php`,
`admin/src/Helper/VendorHelper.php` (autoload PhpSpreadsheet vendorisé).

**Configuration influente** : `forms.export_xls`, `export_id_column`,
`export_state_column`, `export_publish_column`, `elements.export_include`.

**Cas limites** : nom de feuille dérivé du titre de la vue avec caractères
interdits Excel (`[]:*?/\`) remplacés par un espace, tronqué à 31 caractères
(limite native Excel) ; nom de fichier final translittéré en ASCII pour
l'en-tête `filename=` avec variante `filename*=UTF-8''` (RFC 5987).

---

# G. Rendu de contenu — plugins de contenu

Préambule commun aux 7 plugins `plugins/content/*` : tous souscrivent
uniquement à `onContentPrepare`. Deux styles de code coexistent : **style
moderne** (`cblist`, `cbstats` — `declare(strict_types=1)`, services dédiés,
syntaxe stricte `nom=valeur`, validation cumulative) et **style hérité**
(`download`, `image_scale`, `permission_observer`, `rating`, plugin de
contenu `verify` — signature legacy avec adaptateur `EventInterface`,
syntaxe `{CBXxx clé:valeur;clé:valeur}`).

**Déclencheurs communs** : `com_content` (Joomla core) à l'affichage d'un
article ; `ListModel.php:1270` pour l'intro_text de la page liste (sans
`is_list`/`form`/`item`) ; `TemplateRenderService.php:690` avec
**`is_list=true`** et les 6e/7e arguments `$form`/`$recc` (l'enregistrement
courant) — chemin par lequel un **template de ligne de liste** (contenant
par exemple `{CBDownload field:...}`) est résolu pour chaque enregistrement
d'une liste, sans passer par un article Joomla ; `site/src/View/Details/HtmlView.php:359`,
`admin/src/View/Edit/HtmlView.php:144,517` pour les vues détail/édition CBNG
elles-mêmes.

## 26. `{CBList}` — liste embarquée

**Nom** : plugin de contenu `contentbuilderng_cblist`.

**Objectif** : remplacer une balise `{CBList id=... ...}` dans un contenu
Joomla par une iframe pointant vers `task=list.display` (fonctionnalité 16
en mode embarqué), ou par une valeur texte unique (`output=value`).

**Préconditions** : article contenant `{CBList` (test rapide `stripos`,
`:46`) ; requête côté site ; `option` de la requête courante ≠
`com_contentbuilderng` ; requête non déjà marquée `cblist_embed=content-plugin`
(anti-récursion, `:52-58`).

**Flux nominal** (`ContentbuilderngList.php:60-169`) :
1. `preg_replace_callback` avec `TagSyntaxService::TAG_PATTERN` (regex
   tolérante aux `}` à l'intérieur de valeurs entre guillemets) trouve
   chaque occurrence `{CBList ...}`.
2. `TagSyntaxService::parse()` normalise le balisage (retire `&nbsp;`,
   `strip_tags`, décode entités HTML, espace insécable, compresse les
   espaces) puis extrait les paires `clé=valeur`, mémorise si chaque valeur
   était citée (`quoted`).
3. `EmbedOptionsService::validationErrors()` valide **cumulativement**
   (toutes les erreurs collectées en une passe) : options inconnues,
   id/height/pagination/limit/offset/w numériques mais **cités**
   (invalides), bornes numériques, `output` (`list`|`value`), `card`/`w`,
   `layout`, `loading`, `fields` (100 sélecteurs max, 255 caractères, pas de
   caractères de contrôle), `labels`, `hide`, incompatibilités
   `output=value` avec `pagination`/`actions`/`labels`/`hide`/`layout`/
   `height`/`loading`/`card`/`w`, `offset` sans `output=value`, `actions`
   (vocabulaire exhaustif), `sort`/`dir`.
4. Erreurs → bloc `<div class="alert alert-warning">` listant chaque erreur,
   lien d'aide vers `EmbeddedListHelpService::syntaxUrl()` (fonctionnalité
   19). Aucune iframe produite.
5. Sinon `EmbedOptionsService::resolve()` calcule les options typées, puis
   `viewExists($id)` vérifie l'existence de la ligne `forms` (mise en cache
   statique par requête). Vue inexistante → même chemin d'erreur (`unknown_view`).
6. Construction de l'URL d'iframe : `task=list.display`, `tmpl=component`,
   `cblist_embed=content-plugin`, et selon les options : `cblist_title`/
   `cblist_title_set`, `cblist_hide_pagination` ou `list[limit]`,
   `cblist_limit`, `layout`, `cblist_fields`, `cblist_actions`,
   `cblist_sort`/`cblist_dir`.
7. Chargement des assets puis rendu :
   `<div class="cblist-embed"><iframe ...><noscript>...</noscript></div>`,
   id d'instance unique.
8. Si `card` renseigné : enveloppement par `ContentCardService::render()`.

**Flux alternatif `output=value`** (`:87-99`,
`EmbeddedListValueService::resolve()`) : insère directement la valeur texte
échappée d'un unique champ d'un enregistrement, en **réutilisant le
pipeline `ListModel` complet** (`createModel('List', 'Site', ...)`) avec
sauvegarde/restauration temporaire de l'état de la requête HTTP, ACL
`listaccess` vérifiée via `PermissionService`. Absence de résultat/valeur →
chaîne vide, aucune erreur.

**ACL** : déléguée entièrement à la vue liste réelle derrière l'iframe ;
pour `output=value`, vérifiée explicitement via
`PermissionService::authorizeFe('listaccess')`. `actions=` **intersecte**
toujours (ET logique) les permissions déjà accordées — jamais ne les étend
(`EmbeddedListActionFilterService::isAllowed()`, `:93-97`).

**Effets de bord** : aucun en base ; chargement d'assets Web Asset Manager.

**Tables** : lecture seule `forms` (existence de la vue) ; le reste passe
par le pipeline `ListModel` standard (fonctionnalité 16).

**Classes** :
`plugins/content/contentbuilderng_cblist/src/Extension/ContentbuilderngList.php:ContentbuilderngList`,
`plugins/content/contentbuilderng_cblist/src/Service/{TagSyntaxService,EmbedOptionsService}.php`,
`site/src/Service/{EmbeddedListFieldFilterService,EmbeddedListActionFilterService,EmbeddedListValueService,EmbeddedListHelpService,ContentCardService}.php`.

**Configuration influente** : aucun paramètre de plugin propre — tout vient
des attributs du tag et de la configuration de la vue ciblée.

**Messages** : famille `PLG_CONTENT_CONTENTBUILDERNG_CBLIST_*`.

**Cas limites** : plusieurs balises `{CBList}` dans un même contenu (chacune
traitée indépendamment, id d'iframe incrémental) ; anti-récursion stricte
(l'iframe elle-même ne réinterprète jamais `{CBList}`).

**Extension non documentée par `docs/specifications/cblist.md`** : suffixe
optionnel `|h1..h6`/`|remN` après le dernier `|` de `labels="title=..."`
utilisé comme titre de Card (`card=h1..h6|v1..v6`) —
`site/src/Service/ContentCardService.php:45-74` (`parseTitle()`), partagé
avec `{CBStats}` (fonctionnalité 27). **Lacune documentaire** signalée (pas
une contradiction : le code est plus permissif que ce que documente la
spécification pour cette sous-fonctionnalité). Hormis ce point, la synthèse
des brouillons sources conclut que `docs/specifications/cblist.md` est
**fidèle au code actuel** sur l'ensemble des points vérifiés ligne à ligne
(liste exhaustive des options, règles de syntaxe numérique stricte, règles
`fields`/`sort`, vocabulaire `actions`, sémantique `limit`/`pagination`/
`offset`/`output=value`, `labels`/`hide`, validation cumulative, aide
syntaxique, anti-récursion).

---

## 27. `{CBStats}` — statistiques

**Nom** : plugin de contenu `contentbuilderng_cbstats`.

**Objectif** : remplacer une balise `{CBStats id=... field=... output=...}`
par une statistique calculée sur une (ou plusieurs, via `idsum`) vue(s)
ContentBuilder NG : nombre total, agrégat numérique, tableau, JSON brut, ou
graphique (Chart.js). Gère aussi le balisage « Editorial Card »
(`<div class="cb-card-editorial">`) indépendant des balises `{CBStats}`.

**Préconditions** : `article->text` contient `{CBStats` ou un marqueur
Editorial Card (`EditorialCardService::containsMarker()`).

**Flux nominal** (`ContentbuilderngStats.php:75-107, 143-457`) :
1. Test rapide `stripos`/`containsMarker` — si aucun, retour immédiat.
2. Editorial Card présent → `EditorialCardService::transform($text)` réécrit
   le HTML et charge le style `com_contentbuilderng.cards`.
3. `{CBStats}` présent → `preg_replace_callback` avec
   `TagSyntaxService::TAG_PATTERN` (**moins tolérant que CBList** : pas de
   gestion des `}` à l'intérieur de valeurs citées).
4. `resolveConfigSyntax()` : option `config=<fichier.ini>` optionnelle —
   charge des valeurs par défaut depuis `CbStatsConfigService`, fusionnées
   **avant** les attributs explicites du tag (priorité aux attributs du
   tag), avec filtrage des clés non pertinentes pour l'`output` demandé.
5. Résolution des options : `source` (`view`|`manual`), `id`/`idsum`
   (mutuellement exclusifs, `IdSumService::resolveSourceIds()`, 2 à 5
   identifiants uniques), `output` (17 valeurs : `total`, `table`, `pie`,
   `bar`, `histogram`, `line`, `radar`, `json`, `sum`, `min`, `max`, `avg`,
   `remaining`, `percentage`, `progress`, `distinct`, `view_name`), `field`,
   filtre (`filter[field]`/`filter[value]` ou raccourci `field`+`value`),
   `add` (ajouts externes signés), `titles`, `titleset`/`groupset` (fichiers
   `.ini` réutilisables, fonctionnalité 15), `groups` (intervalles
   numériques inclusifs ou valeurs explicites), `labels`, `background`,
   `sort`/`dir`, `limit`, `hide`, `export=manual`, `target`, `value`, `debug`.
6. Validation via `StatsTagValidationService::validationErrors()` puis, au
   fil du traitement, une série d'exceptions typées (source invalide, id
   manquant, accès refusé par `canViewStats()`, output invalide, champ
   requis, filtre invalide, tri/direction invalides) — capturées en fin de
   méthode et transformées en messages de traduction.
7. `canViewStats($formId)` (`:805-826`) : ACL `stats` via
   `PermissionService`, front (`authorizeFe`) ou back (`authorize`) — **par
   vue**, répété pour chaque id d'un `idsum`.
8. Calcul via `StatsService::getStatsPayload()` (`site/src/Service/StatsService.php`)
   puis, pour `idsum`, fusion des payloads (`IdSumService::mergePayloads()`).
9. Rendu selon `output` : total scalaire, tableau HTML, JSON brut, ou
   graphique Chart.js avec canvas + payload JSON encodé en
   `data-cbstats-*`, exploité par `cbstats-{pie,bar,charts}.js` (Chart.js
   4.5.1 vendorisé, `10-dependencies.md §4.3`).
10. `card` renseigné → enveloppement `ContentCardService::render()` (mêmes
    suffixes de titre `|h1..h6`/`|remN`, fonctionnalité 26).
11. `export=manual` (avec `output` dans `table`/`pie`/`bar`) : ajout d'un
    second bloc « figé » `{CBStats source=manual ...}` avec bouton copier,
    via `ManualExportService`/`ManualValuesParser` — gèle des statistiques
    calculées en valeurs manuelles réutilisables ailleurs.

**Flux alternatif `source=manual`** : `values=` fournit directement des
paires label/valeur (échappement `\;`/`\=`/`\\`), traitées par le même
moteur de tri/limite/masquage que le mode `view`.

**Endpoint API partagé** : `action=stats`/`action=cbstats` de
`ApiController` (fonctionnalité 24) partage le même moteur de calcul —
résolu par recoupement avec `06-api-contracts.md §7` (point auparavant en
zone inconnue dans les brouillons sources).

**ACL** : `stats` par vue (front `authorizeFe('stats')`, back
`authorize('stats')`), vérifiée pour **chaque** id en cas d'`idsum`.

**Effets de bord** : aucun en base (lecture seule) ; chargement conditionnel
d'assets Chart.js/DataTable selon `output`.

**Classes** :
`plugins/content/contentbuilderng_cbstats/src/Extension/ContentbuilderngStats.php:ContentbuilderngStats`
et 11 services dédiés (`TagSyntaxService`, `DisplayOptionsService`,
`IdSumService`/`IdSumException`, `ManualExportService`/`ManualValuesParser`/
`ManualValuesException`, `PiePresentationService`, `StatsTagValidationService`,
`TableHeaderService`, `TotalPresentationService`, `CssDimensionService`) ;
services partagés du composant `site/src/Service/{StatsService,StatsFilterValueService,StatsHideOptionsService,CbstatsHelpService,CbStatsTitleSetService,CbStatsConfigService,EditorialCardService}.php`.

**Configuration influente** : fichiers `.ini` `titleset`/`groupset`/`config`
(répertoires `media/contentbuilderng/cbstats/...` et
`media/com_contentbuilderng/cbstats/...`), gérables depuis *ContentBuilder
NG › Titlesets* (fonctionnalité 15).

**Messages** : famille `PLG_CONTENT_CONTENTBUILDERNG_CBSTATS_*`.

**Cas limites** : radar 3–8 axes (`RADAR_MIN_AXES`/`RADAR_MAX_AXES`) ;
`groups=` rend le total « réel » indépendant de la somme des groupes
(chevauchements possibles) ; `hide=` interdit de masquer tous les éléments
(`ALL_HIDDEN`) ; sorties scalaires (`json`, `min`, `max`, `avg`) refusent
les options de présentation qui masqueraient leur résultat principal.

**Point d'attention documentaire** (pas une erreur de code) :
`plugins/content/contentbuilderng_cbstats/docs/` contient une spécification
développeur interne (`Gil_CBSTATS_SPECIFICATION.md`, `Gil_CBSTATS_PUBLIC_API.md`)
analogue à `docs/specifications/cblist.md` mais non déplacée sous
`docs/specifications/`. Confrontation point par point au code : **aucune
divergence significative constatée**.

---

## 28. `{CBDownload}`

**Nom** : plugin de contenu `contentbuilderng_download`.

**Objectif** : remplacer `{CBDownload field:X;info:true;...}` par un lien de
téléchargement pointant vers un champ « fichier » d'un enregistrement CBNG,
et servir le fichier lui-même quand ce lien est suivi.

**Préconditions** : `article->id` ou `article->cbrecord` défini ;
`{CBDownload...}` présent.

**Flux nominal** (`ContentbuilderngDownload.php:168-546`) :
1. Récupération de `article->id` depuis le commentaire
   `<!--(cbArticleId:N)-->` si absent (`:194-199`, injecté par le plugin
   système, fonctionnalité 34).
2. Si `is_list` : pseudo-`cbrecord` construit à partir de `$form`/`$item`
   transmis par `TemplateRenderService`.
3. Provisionnement défensif de `media/contentbuilderng/plugins/download/`
   avec `index.html` vides (anti-listing) — pattern identique à
   `image_scale`/`rating` (`:215-234`).
4. Résolution du formulaire/enregistrement source : par `article->id`
   (jointure `articles` ⋈ `forms`, `form.published=1`) ou via
   `article->cbrecord` (contexte liste). `FormSourceFactory::getForm()`
   centralise la résolution polymorphe.
5. **ACL** (sauf contexte liste, délégué à la liste) :
   `PermissionService::setPermissions()` puis `authorizeFe('view')`/
   `authorize('view')`. Refus → `die('No Access')` (téléchargement direct en
   cours) ou disparition silencieuse du bloc.
6. Parsing des options du tag (`field`, `info-style`, `box-style`, `align`,
   `info`, `hide-filename`, `hide-mime`, `hide-size`, `hide-downloads`).
7. Pour chaque fichier référencé (une ligne par fichier) :
   - **Contrôle de confinement de chemin** :
     `ContentbuilderngHelper::is_internal_path()` doit valider la valeur
     stockée avant tout `is_file()`/lecture — commentaire explicite
     (`:420-424`) : « le chemin vient des données stockées ; ne jamais
     toucher quoi que ce soit hors de la racine du site ». Correctif de
     sécurité déjà en place (pas un signalement à produire).
   - `contentbuilderng_download_file=sha1(field.path)` en requête → **sert le
     fichier** : compteur de hits anti-doublon par session
     (`incrementResourceHits`, table `resource_access`), en-têtes durcis
     (`Content-Type: application/octet-stream`, `X-Content-Type-Options: nosniff`,
     `Content-Disposition: attachment`, nom nettoyé), envoi par blocs de
     1 Mo, `$this->app->close()`.
   - Sinon : HTML d'affichage (lien + bloc info nom/type MIME/taille/
     compteur, chacun masquable).

**Effets de bord** : création de répertoires/`index.html` idempotente ;
écriture `resource_access` (compteur de hits) ; écriture en session
(`downloaded<type><elementId><fileId>`, une fois par session).

**Tables** : `resource_access` (lecture + écriture), `articles`/`forms`
(lecture).

**Classes** :
`plugins/content/contentbuilderng_download/src/Extension/ContentbuilderngDownload.php:ContentbuilderngDownload`,
`ContentbuilderngHelper::is_internal_path`, `FormSourceFactory`, `PermissionService`.

**Messages** : famille `COM_CONTENTBUILDERNG_PLUGIN_DOWNLOAD_*`.

**Cas limites** : champ « série » de fichiers (une ligne par fichier) → un
bloc par fichier ; fichier manquant/hors racine → silencieusement omis.

---

## 29. `{CBImageScale}`

**Nom** : plugin de contenu `contentbuilderng_image_scale`.

**Objectif** : remplacer `{CBImageScale field:X;width:150;height:100;...}`
par une balise `<img>` pointant vers une version redimensionnée (cache
disque) d'une image stockée dans un champ CBNG, avec lightbox JS native
optionnelle (`window.open`).

**Préconditions** : extension PHP GD chargée (sinon retour immédiat,
`:94-96`) ; `article->id`/`cbrecord` défini ; `{CBImageScale...}` présent.

**Flux nominal** (`ContentbuilderngImageScale.php:80-823`) :
1. Handler d'erreur/shutdown personnalisé installé au chargement du fichier
   (`:30-43`) : avale silencieusement les erreurs fatales GD pour éviter de
   casser le rendu de la page entière. Polyfill `exif_imagetype()` si absent.
2. Limite de temps d'exécution dynamique (moitié de `max_execution_time`,
   `:99-105`), utilisée pour interrompre la boucle de redimensionnement si
   elle dure trop longtemps (`:796-799`).
3. Paramètre de plugin `max_filesize` (Mo, défaut 4) borne la taille des
   fichiers sources traités.
4. Répertoires de travail provisionnés (`media/contentbuilderng/plugins/image_scale/`)
   + sous-répertoire **par formulaire** `.../cache/<form_id>/`, avec
   `.htaccess deny from all` posé/retiré dynamiquement selon
   `form.protect_upload_directory` (`:255-262`).
5. **ACL** `view`, mêmes règles que `{CBDownload}` (sauf contexte liste).
6. Options du tag : `width`, `height`, `original-width`/`original-height`
   (retaille aussi le fichier source en place), `field`, `background-color`,
   `folder` (cache alternatif), `alt`/`title` (valeur spéciale `USE-TITLE`
   — texte alternatif issu d'un autre champ du même enregistrement), `type`
   (`crop`|`simple`|autre=letterbox), `cache` (TTL secondes ou `none`),
   `global_cache` (purge périodique des fichiers `*_cbresized` plus vieux
   que ce TTL), `align`, `open` (lightbox JS), `default-image[-width/-height]`.
7. Contrôle de confinement de chemin identique à `{CBDownload}`
   (`:456-461`) avant tout `getimagesize()`.
8. Redimensionnement GD (`resize_image()`, 3 modes : crop-to-fit, letterbox,
   contain proportionnel) avec **garde mémoire** (calcul largeur×hauteur×
   bits×canaux/8 + marge 1.5×, comparaison à `memory_get_usage()`+
   `memory_limit`, `:602-628`) — insuffisant → image non générée, pas
   d'erreur fatale.
9. Cache disque nommé `<basename>_<w>x<h>_cbresized.<ext>` ; invalidation
   par âge (`cache=`) ou changement de dimensions demandées.
10. Deux modes de service direct de fichier : `contentbuilderng_display=1&contentbuilderng_field=sha1(...)`
    (version redimensionnée en cache) et
    `contentbuilderng_display_detail=1&contentbuilderng_detail_file=sha1(...)`
    (original, uniquement si `protect_upload_directory` actif).
11. Rendu `<img>` (`src` direct ou route de service selon protection),
    optionnellement enveloppé d'un lien `javascript:window.open(...)` si
    `open=true`.

**Effets de bord** : création de répertoires/cache par formulaire ;
écriture/suppression de `.htaccess` selon `protect_upload_directory` ;
écriture de fichiers image redimensionnés en cache disque ; purge
périodique (`global_cache`) ; peut **réécrire le fichier source** en place
si `original-width`/`original-height` diffère des dimensions actuelles.

**Tables** : mêmes lectures que `{CBDownload}`, aucune écriture DB propre.

**Classes** :
`plugins/content/contentbuilderng_image_scale/src/Extension/ContentbuilderngImageScale.php:ContentbuilderngImageScale`,
mêmes helpers que `{CBDownload}`.

**Configuration influente** : paramètre de plugin `max_filesize` (Mo).

**Messages** : aucune chaîne de traduction observée (HTML brut).

**Cas limites** : dimension demandée plafonnée à 16384px (anti-DoS,
`:481-487`) ; timeout doux mi-parcours sur un champ « série » avec beaucoup
de fichiers (`:796-799`, les images restantes ne sont simplement pas
rendues) ; PHP sans GD → plugin totalement inactif.

---

## 30. Permission Observer

**Nom** : plugin de contenu `contentbuilderng_permission_observer`.

**Objectif** : bloquer l'affichage d'un article Joomla lié à un
enregistrement ContentBuilder NG lorsque le visiteur n'a pas le droit
`view`, en remplaçant tout le texte de l'article par un message
d'interdiction (plutôt que de laisser le contenu s'afficher).

**Préconditions** : `article->id` défini.

**Flux nominal** (`ContentbuilderngPermissionObserver.php:32-102`) :
1. Résout `form_id`/`record_id`/`type`/flags de visibilité via jointure
   `articles` ⋈ `forms` (`form.published=1`).
2. **Garde explicite** : ne s'applique **pas** si la requête courante est
   déjà l'écran d'édition CBNG lui-même (`option=com_contentbuilderng&controller=edit`)
   — évite un blocage récursif de l'écran d'édition par son propre
   observateur.
3. Charge la langue du composant avec un chemin explicite (`JPATH_SITE`/
   `JPATH_ADMINISTRATOR . '/components/com_contentbuilderng'`) — commentaire
   expliquant que `Language::load('com_contentbuilderng')` sans base path
   échoue silencieusement (fichiers de langue « extension-scoped only »,
   `:76-83`), motif identique au plugin système (fonctionnalité 34).
4. Vue courante `article` (Joomla) : `PermissionService::checkPermissions('view', …)`
   (lève/redirige en cas de refus). Sinon : `authorizeFe('view')`/
   `authorize('view')` — refus → **remplace tout `article->text`** par
   `COM_CONTENTBUILDERNG_PERMISSIONS_VIEW_NOT_ALLOWED` (l'article entier,
   pas seulement une balise).

**Effets de bord** : aucun en base (lecture seule) ; peut interrompre le
rendu via `checkPermissions` sur la vue `article`.

**Classes** :
`plugins/content/contentbuilderng_permission_observer/src/Extension/ContentbuilderngPermissionObserver.php:ContentbuilderngPermissionObserver`,
`PermissionService`, `FormSourceFactory`.

**Messages** : `COM_CONTENTBUILDERNG_PERMISSIONS_VIEW_NOT_ALLOWED`.

**Cas limites** : article sans correspondance CBNG (pas de ligne `articles`,
ou formulaire dépublié) → jamais affecté, retour silencieux sans toucher au
texte.

---

# H. Workflow d'état de liste et extension de soumission

## 31. États de liste et routage d'action — `trash`/`untrash`

**Nom du groupe** : `contentbuilderng_listaction` (`plugins/contentbuilderng_listaction/{trash,untrash}`).

**Objectif du mécanisme** : permettre à un état de liste
(`list_states.action`) de déclencher un effet de bord PHP arbitraire quand
un enregistrement bascule vers cet état depuis l'écran liste (front ou
back) — typiquement synchroniser l'état `published`/`state` de l'article
Joomla lié.

**Correction (voir §0.3)** : les plugins `trash`/`untrash` **n'écrivent
jamais** `list_records.state_id` — cette colonne est écrite par `EditModel`
(le composant lui-même). Le rôle réel de ces plugins est de synchroniser un
**second système d'état, indépendant** : la colonne Joomla core
`#__content.state` de l'article lié (soft-delete/publication Joomla
« classique »), pas le système d'état de liste **propre** à CBNG. Autrement
dit : `list_states.action='trash'`/`'untrash'` est la **clé de routage** qui
sélectionne quel plugin s'exécute pour cet état de liste ; `list_records.state_id`
est mis à jour par le composant lui-même, quel que soit le plugin routé ; le
plugin routé, lui, agit sur `#__content.state` ; `onAfterArticleCreation`
(chez `trash` uniquement) referme la boucle en empêchant qu'une
resynchronisation ultérieure (`createMissingArticles()`, fonctionnalité 34)
ne recrée un article « publié » pour un enregistrement dont l'état de liste
courant est « trash ». Deux colonnes d'état, deux acteurs différents, reliés
par `list_states.action` — précision de `03-data-model.md §7-8`, pas une
contradiction.

**Déclencheur unique et précis** (`site/src/Model/EditModel.php:2727-2859`,
changement d'état de liste depuis les actions groupées de la vue liste) :
1. Lecture de `list_states` pour l'id d'état cible (`published=1`, bon
   `form_id`) → récupère `action`.
2. `PluginHelper::importPlugin('contentbuilderng_listaction', $res['action'])`
   — **import ciblé** du seul plugin dont le nom de dossier correspond
   exactement à la valeur de `action` (ex. `action='trash'` → importe
   `.../trash/`). Un état de liste dont `action` ne correspond à aucun
   plugin installé/activé n'a simplement aucun effet de bord.
3. `dispatch('onBeforeAction', [$form_id, $items])` — **avant** toute
   écriture de `list_records.state_id`.
4. Boucle d'upsert `list_records` (id, form_id, record_id, reference_id,
   state_id) pour chaque enregistrement, repli en cas de conflit de clé
   unique (`DuplicateKeyViolationHelper`).
5. `dispatch('onAfterAction', [$form_id, $items, $error])` — **après**
   l'écriture.

**Second déclencheur, indépendant** (`admin/src/Service/ArticleService.php:711-712`) :
`dispatch('onAfterArticleCreation', [$form_id, $record_id, $article])` — à
**chaque création ou mise à jour d'un article Joomla** généré/synchronisé
depuis un enregistrement CBNG, dispatché vers **tout le groupe**
`contentbuilderng_listaction` (pas d'import ciblé visible avant ce dispatch
dans `ArticleService.php` — **Zone inconnue** : point d'import exact non
localisé dans les brouillons sources, voir Contradictions en fin de document).

### 31.1 `trash`

**Objectif** : quand un enregistrement passe à un état dont `action='trash'`,
mettre l'article Joomla lié en **corbeille** (`state=-2`), et empêcher
qu'une recréation ultérieure ne le fasse ressortir.

**Flux nominal** (`Trash.php:44-142`) :
- `onBeforeAction` : `UPDATE #__content SET state=-2` en jointure sur
  `articles` (couple `form_id`+`record_id`) — agit directement sur la table
  Joomla core `#__content`, pas sur `list_records`/`list_states`. Message
  `COM_CONTENTBUILDERNG_TRASH_SUCCESSFULL`.
- `onAfterAction` : no-op.
- `onAfterArticleCreation` : si, pour le couple `form_id`/`record_id` reçu,
  l'état de liste courant a `list_states.action='trash'` (jointure
  `list_records` ⋈ `list_states`), **supprime** (`DELETE`, pas
  dépublication) l'article Joomla qui vient d'être (re)créé par
  `ArticleService` — garde-fou anti-résurrection.

**Effets de bord** : `UPDATE #__content.state=-2` ; `DELETE #__content`
(cas `onAfterArticleCreation`).

**Tables** : `#__content` (écriture), `articles` (lecture, jointure),
`list_records`/`list_states` (lecture, `onAfterArticleCreation`).

**Classes** : `plugins/contentbuilderng_listaction/trash/src/Extension/Trash.php:Trash`.

**Messages** : `COM_CONTENTBUILDERNG_TRASH_SUCCESSFULL`.

**Cas limites** : `record_ids` non numériques acceptés (cast `(string)`
uniquement, pas `(int)`, conforme au commentaire natif « `record_ids` may be
_non_numeric_ »).

### 31.2 `untrash`

**Objectif** : symétrique de `trash` — restaure l'état `#__content.state` de
l'article Joomla lié à la **valeur `published` de l'enregistrement CBNG
source** (`records.published`), plutôt qu'une valeur fixe.

**Flux nominal** (`Untrash.php:28-110`) :
- `onBeforeAction` : `UPDATE #__content c, records r, articles a SET
  c.state = r.published WHERE a.record_id = r.record_id AND a.form_id =
  <form_id> AND a.record_id = <record_id> AND c.id = a.article_id` —
  requête SQL brute multi-tables (syntaxe MySQL `UPDATE t1, t2, t3 SET ...
  WHERE ...`, cohérente avec la contrainte MySQL/MariaDB d'AGENTS.md).
  Message `COM_CONTENTBUILDERNG_UNTRASH_SUCCESSFULL`.
- `onAfterAction` : no-op.
- `onAfterArticleCreation` : **corps vide** — contrairement à `trash`,
  `untrash` n'a pas besoin de contre-mesure ici (la recréation normale d'un
  article restauré est le comportement désiré).

**Classes** : `plugins/contentbuilderng_listaction/untrash/src/Extension/Untrash.php:Untrash`.

**Messages** : `COM_CONTENTBUILDERNG_UNTRASH_SUCCESSFULL`.

---

## 32. Contrat de soumission — groupe `contentbuilderng_submit`

**Nom** : `plugins/contentbuilderng_submit/submit_sample` (1 plugin livré),
contrat `onBeforeSubmit`/`onAfterSubmit`.

**Objectif déclaré** (commentaire de fichier, `:12`) : « Plugin example. » —
squelette de référence sans logique métier, destiné à documenter par
l'exemple le contrat que tout plugin tiers du groupe
`contentbuilderng_submit` doit implémenter.

**Déclencheur** : soumission d'un enregistrement (`EditModel::store()`,
fonctionnalité 20).

**Contrat observé** (`SubmitSample.php:24-50`, croisé avec les 2 sites de
dispatch dans `EditModel.php`) :
1. `store()` (`:774`) appelle
   `PluginHelper::importPlugin('contentbuilderng_submit')` — **sans second
   argument** : importe **tous** les plugins activés du groupe, pour
   **chaque** formulaire (pas de filtrage par vue). C'est le seul groupe de
   plugins internes CBNG importé « en masse » plutôt que ciblé par une
   valeur de configuration (à la différence de `contentbuilderng_listaction`,
   ciblé par action, et `contentbuilderng_themes`, où chaque thème
   s'auto-filtre — fonctionnalité 33).
2. `onBeforeSubmit` (`:1649-1650`) dispatché **avant** la sauvegarde
   effective (`$data->form->saveRecord(...)`, `:1657`), arguments
   positionnels : `record_id` courant (0 si création), `$data->form`
   (interface `FormSourceFactory`), `$values` (tableau brut des valeurs
   soumises, **avant** nettoyage `cbGroupMark`). Le plugin ne renvoie rien
   d'exploité en tant que résultat de validation dans ce chemin : le
   blocage éventuel se fait en amont, via
   `$app->getInput()->set('cb_submission_failed', 1)` et
   `enqueueFieldValidationMessage()` (un plugin `onBeforeSubmit` **pourrait**
   en théorie appeler cette même API pour invalider la soumission, mais ce
   n'est ni fait ni testé par `submit_sample` — **Comportement déduit**).
3. `onAfterSubmit` (`:2044-2045`) dispatché **après** la sauvegarde,
   uniquement si `!$data->edit_by_type` (`:2028` — **Zone inconnue** : la
   signification exacte de `edit_by_type` n'a pas été creusée dans les
   brouillons sources), arguments : `$record_return` (identifiant sauvegardé),
   `$article_id` (int, 0 sinon), `$data->form`, `$cleanedValues` (valeurs
   soumises, **après** nettoyage `cbGroupMark`). S'exécute **avant** l'envoi
   des e-mails de notification et avant les `custom_action_script` par
   champ (`:2047-2051`). **Fait observé** : le retour de
   `dispatch('onAfterSubmit', ...)` (`$submit_after_result`) est **capturé
   mais jamais lu/exploité** plus loin dans la portion de `EditModel.php`
   observée par les brouillons sources (`:2045` suivi directement d'autres
   opérations sans branchement) — **Zone inconnue** : peut être exploité
   ailleurs dans le fichier (2870+ lignes, non entièrement lu), ou être un
   simple point d'observation sans effet de flux.

**Effets de bord** : aucun (`submit_sample` ne fait rien).

**Classes** :
`plugins/contentbuilderng_submit/submit_sample/src/Extension/SubmitSample.php:SubmitSample`,
déclencheur `site/src/Model/EditModel.php::store()`.

**Cas limites — risque documenté pour tout intégrateur** : parce que
l'import est **non ciblé**, un plugin tiers du groupe
`contentbuilderng_submit` mal écrit (boucle infinie, exception non gérée,
effet de bord non filtré par formulaire) impacterait **toutes** les
soumissions de **tous** les formulaires du site.

---

# I. Thèmes

## 33. Thèmes visuels (blank/dark/khepri/thoth)

**Nom du groupe** : `contentbuilderng_themes` (`blank`, `dark`, `khepri`, `thoth`).

**Objectif** : fournir, pour un « thème » nommé (associé à un formulaire via
`forms.theme_plugin`, ou choisi via un paramètre de menu Joomla résolu par
`MenuThemeHelper`/`PreviewThemeHelper`), le CSS/JS spécifique à injecter
dans le détail (`onContentTemplate*`), l'édition (`onEditableTemplate*`) et
la liste (`onListView*`) d'une vue CBNG, ainsi que le **gabarit HTML
d'exemple** (« Sample ») proposé dans l'éditeur admin pour démarrer un
template de détail/édition.

**Contrat commun** (`getSubscribedEvents()` identique dans les 4 fichiers,
8 événements) : `onContentTemplateJavascript`, `onEditableTemplateJavascript`,
`onListViewJavascript`, `onContentTemplateCss`, `onEditableTemplateCss`,
`onListViewCss`, `onContentTemplateSample`, `onEditableTemplateSample`.

**Mécanisme de filtrage — import de groupe + filtre applicatif** : à la
différence de `contentbuilderng_listaction`/`contentbuilderng_verify`
(import ciblé par nom exact), les sites d'appel importent parfois **un
seul** plugin nommé (`site/src/View/List/HtmlView.php:121`,
`admin/src/Service/TemplateSampleService.php:41-46` avec repli sur `thoth`
si l'import échoue/n'est pas activé) et parfois laissent chaque plugin
**s'auto-filtrer** via l'argument `theme` de l'événement comparé à sa propre
constante `THEME_NAME` : un thème n'ayant pas ce nom retourne immédiatement
sans pousser de résultat. Un événement dispatché **sans** argument `theme`
(chaîne vide) est accepté par **tous** les thèmes actifs simultanément —
**Comportement déduit** : ce cas ne semble pas exploité dans les sites de
dispatch observés (tous passent systématiquement `['theme' => $themePlugin]`),
mais reste un chemin de code valide.

**Déclencheurs précis** :
- `onContentTemplateCss`/`onContentTemplateJavascript` :
  `site/src/View/Details/HtmlView.php:474-484` (fonctionnalité 17).
- `onEditableTemplateCss`/`onEditableTemplateJavascript` :
  `admin/src/View/Edit/HtmlView.php:455-461` (aperçu back-office) **et**
  `site/src/View/Edit/HtmlView.php:825-834` (fonctionnalité 20).
- `onListViewCss`/`onListViewJavascript` :
  `site/src/View/List/HtmlView.php:163-169` (fonctionnalité 16).
- `onContentTemplateSample`/`onEditableTemplateSample` :
  `admin/src/Service/TemplateSampleService.php:48-52,149-150` (bouton
  « insérer un modèle d'exemple » de l'écran de vue admin).
- **Chargement effectif CSS/JS inline pour un article publié** :
  `plugins/system/contentbuilderng_system/src/Extension/ContentbuilderngSystem.php::onAfterDispatch:373-394`
  (fonctionnalité 34) — dispatch pour **chaque thème distinct** trouvé parmi
  les articles rendus sur la page (marqueurs `<!--(cbArticleId:N)-->`),
  injection en `<style>`/`<script>` inline via le Web Asset Manager.

**Flux nominal type, événement CSS** (identique dans les 4 plugins) :
1. `acceptsThemeEvent($event)` → sinon `return` sans rien pousser.
2. Lecture du fichier CSS statique du thème (`css/content.css` ou
   `list.css`) via `file_get_contents()`, retournée comme **chaîne CSS
   brute** (poussée dans `$event->getArgument('result')`, tableau
   cumulatif — plusieurs thèmes actifs simultanément produisent un tableau à
   plusieurs entrées, `implode('')` côté appelant).
3. `onEditableTemplateCss` délègue simplement à `onContentTemplateCss` dans
   `blank`/`dark`/`khepri`/`thoth` (même CSS pour détail et édition).

**Flux nominal type, Sample** : génère une table HTML `{champ:label}`/
`{champ:value}` (détail) ou `{champ:label}`/`{champ:item}` (édition) pour
**chaque élément publié** du formulaire, dans l'ordre
`TemplateFieldOrderHelper::getOrderedNames()`, avec des enveloppes
HTML/CSS différentes par thème (purement visuelle, même logique de données).

**Différence architecturale notable — `thoth` seul plugin « Web Asset
Manager natif »** : `thoth` **n'inline pas** son CSS en chaîne : il
enregistre/active un **asset Joomla natif**
(`getWebAssetManager()->registerStyle(...)`) et retourne une chaîne **vide**
pour l'événement CSS. `blank`/`dark`/`khepri` retournent le **contenu
texte** du fichier CSS (injecté en `<style>` inline par l'appelant).
**Comportement déduit** : `thoth` est le thème le plus aligné sur les
conventions Joomla natives (AGENTS.md : « Prefer native Joomla 6 admin
patterns »), cohérent avec son rôle de thème de **repli par défaut**
(`'thoth'` codé en dur comme fallback, `TemplateSampleService.php:33,42`,
`site/src/View/List/HtmlView.php:122`).

**Effets de bord** : aucune écriture en base ; enregistrement/activation
d'assets Web (uniquement `thoth`) ; injection de `<style>`/`<script>`
inline (les 3 autres thèmes).

**Classes** :
`plugins/contentbuilderng_themes/{blank,dark,khepri,thoth}/src/Extension/{Blank,Dark,Khepri,Thoth}.php`,
`TemplateFieldOrderHelper`, `RuntimeContextHelper` (Thoth uniquement).

**Sélection du thème vs skill `cbng-dark-mode`** : ce skill du dépôt décrit
un objectif d'audit/retouche CSS frontend transverse (contraste,
`data-bs-theme="dark"`, variables Bootstrap 5, probablement les feuilles
`media/css/` du composant lui-même) — pas le mécanisme de sélection de
thème CBNG par plugin, qui est un mécanisme de **contenu par vue**, choisi
par formulaire/menu, pas un mode d'affichage global clair/sombre du site.
**Comportement déduit** : les deux notions sont **orthogonales** dans le
code lu — le plugin `dark` n'a pas été confirmé comme spécifiquement lié à
`prefers-color-scheme`/`data-bs-theme` (son CSS n'a pas été lu ligne à ligne
dans les brouillons sources). **Zone inconnue** : le contenu exact de
`plugins/contentbuilderng_themes/dark/css/*.css`.

**Configuration influente** : `forms.theme_plugin` (par formulaire),
paramètre de menu Joomla résolu par `MenuThemeHelper::resolve()`/
`PreviewThemeHelper::apply()`.

**Cas limites** : import d'un thème inconnu/désactivé → repli systématique
sur `thoth` codé en dur ; un article affichant des enregistrements de
plusieurs formulaires à thèmes différents sur la même page → le plugin
système boucle sur tous les thèmes distincts détectés et concatène leurs
CSS/JS inline.

---

# J. Plugin système

## 34. Plugin système `contentbuilderng_system`

**Nom** : `plugins/system/contentbuilderng_system`.

**Objectif** : plugin système transverse — synchronisation enregistrements↔
articles Joomla, gestion des groupes utilisateurs automatiques, fenêtre de
publication programmée, désactivation ciblée du cache Joomla, chargement des
thèmes CSS/JS pour les articles publiés (fonctionnalité 33), blocage de la
création d'article Joomla natif quand configuré, redirection transparente
`com_content` → édition CBNG. Les **paramètres** de ce plugin sont déjà
documentés dans `08-configuration.md §14` — cette fiche décrit le
**comportement**.

**Événements souscrits** : `onAfterDispatch`, `onAfterInitialise`
(alias `onAfterInitialize`), `onAfterRoute`, `onBeforeRender`.

**Garde commune — `isSyncMutationRequest()`** (`:53-92`) : détermine si la
requête courante est une **mutation** (pas un simple affichage), en
combinant `option` (`com_contentbuilderng`/`com_content`/`com_breezingforms`
uniquement), `task` (préfixes `edit.`/`details.` pour CBNG, ou motif
générique `(save|apply|publish|unpublish|archive|trash|delete|remove|batch)$`
pour Joomla/BreezingForms), et un traitement spécial BreezingForms
(`ff_task` non vide, `confirmStripe`, ou toute requête POST). Protège
`onAfterRoute`/`onAfterInitialise` de faire un travail de synchronisation
coûteux sur de simples requêtes d'affichage.

**`onBeforeRender`** (`:153-209`) :
1. Si `nocache=1` (défaut) : restaure la valeur de cache mise de côté par
   `onAfterRoute` pour les requêtes `com_content`.
2. `keepContentbuilderAdminMenuOpenInOptions()` : force l'expansion de la
   branche de menu latéral « ContentBuilder NG » si la requête admin
   courante est `com_contentbuilderng` ou l'écran `com_config` du composant
   — confort UX admin uniquement.

**`onAfterDispatch`** (`:211-437`) :
1. `bootstrapContentbuilder()` : charge défensivement 4 classes Helper par
   `require_once` direct si `class_exists()` échoue (`PackedDataHelper`,
   `Logger`, `ContentbuilderngHelper`, `FormSourceFactory`) — permet au
   plugin de fonctionner même si le composant n'a pas encore été « démarré »
   par Joomla sur cette requête. Retour anticipé si le composant est absent.
2. **Gestion des groupes automatiques** (`is_auto_groups`) : pour
   `com_kunena`/`com_contentbuilderng` uniquement, ajoute les utilisateurs
   vérifiés (`users.verified_view=1`, `verification_required_view=1` côté
   vue) aux groupes Joomla configurés (`#__user_usergroup_map`), avec purge
   de session Kunena si détecté installé (`is_dir()`) ; **retire** des
   groupes automatiques les utilisateurs sans aucune vérification valide
   (`HAVING SUM(verified_view)=0`). Dépendance conditionnelle à `com_kunena`
   (extension tierce), détection par existence de répertoire plutôt que
   `ComponentHelper::isEnabled()` (cohérent avec `10-dependencies.md §5.5`).
3. **Chargement des thèmes CSS/JS pour les articles rendus** : détecte les
   marqueurs `<!--(cbArticleId:N)-->`, résout `forms.theme_plugin` par
   article, importe et dispatch les événements de thème
   (fonctionnalité 33), injecte en `<style>`/`<script>` inline.
4. **Blocage de la création d'article Joomla natif**
   (`disable_new_articles`) : si actif et requête ciblant la création/édition
   d'un article `com_content` natif, redirige vers `index.php` avec message
   d'erreur — empêche de contourner CBNG en créant un article Joomla « brut ».
5. **Redirection transparente `com_content` édition → CBNG édition** : si la
   requête tente d'éditer un article Joomla natif (`task=edit`/`layout=edit`)
   qui est en réalité lié à un enregistrement CBNG, redirige automatiquement
   vers `task=edit.display` avec les bons `id`/`record_id`.

**`onAfterRoute`** (`:439-554`) :
1. Mutation → synchronise les enregistrements pour **chaque couple
   `(type, reference_id)` distinct** de formulaire publié
   (`FormSourceFactory::getForm(...)->synchRecords()`) puis
   `createMissingArticles()`.
2. Mutation → applique la fenêtre de publication programmée
   (`is_future`/`publish_up`/`publish_down` sur `records`, comparaison à
   `now()`) — republie/dépublie selon les bornes.
3. Préserve la cible d'URL de déconnexion Joomla (`return=` base64) quand
   elle pointe vers `com_contentbuilderng`, en retirant `view=` pour éviter
   un retour vers une vue potentiellement invalide après logout.
4. Requêtes `com_content` avec `nocache=1` : désactive temporairement le
   cache Joomla (restauré par `onBeforeRender`).
5. Mutation → **deux `UPDATE` multi-tables bruts MySQL** (`UPDATE t1, t2,
   t3, t4 SET ... WHERE ...`) qui dépublient (`records.published=0`) les
   enregistrements `act_as_registration` dont l'utilisateur Joomla lié est
   **bloqué** (`#__users.block=1`), et inversement les republient
   (`records.published=forms.auto_publish`) quand débloqué — logique
   dupliquée symétriquement dans `onAfterInitialise` mais sur
   `#__content.state` au lieu de `records.published`.

**`onAfterInitialise`/`onAfterInitialize`** (`:556-625`) :
1. Actif uniquement côté site (`isClient('site')`) et uniquement si mutation.
2. Deux `UPDATE` multi-tables symétriques agissant directement sur
   **`#__content.state`** (l'article Joomla) des enregistrements
   `act_as_registration` dont l'auteur Joomla est bloqué/débloqué — **Fait
   observé** : redondance apparente avec les deux `UPDATE` d'`onAfterRoute`
   (qui touchent `records.published`) ; **Comportement déduit** : les deux
   paires ciblent des colonnes d'état différentes (enregistrement CBNG vs
   article Joomla lié), donc deux surfaces d'état à synchroniser
   séparément, cohérent avec la distinction « deux systèmes d'état »
   confirmée en fonctionnalité 31.
3. `createMissingArticles()`.

**`createMissingArticles()`** (méthode privée, `:627-738`) :
1. Requête de sélection des enregistrements à (re)synchroniser en article :
   pour chaque formulaire `published=1 AND create_articles=1`, sélectionne
   les enregistrements dont l'article existant est périmé
   (`form.last_update > article.last_update OR records.last_update >
   article.last_update`, article Joomla dans un état `0`/`1`), ou dont
   aucun article n'existe encore — limité à `limit_per_turn` (paramètre de
   plugin, défaut 50) lignes par exécution, pour étaler la charge.
2. Pour chaque ligne : résout le formulaire, les labels/éléments publiés, le
   record complet, appelle `ArticleService::createArticle(...)` — c'est ce
   point d'entrée qui déclenche, en aval, `onAfterArticleCreation`
   (fonctionnalité 31).
3. Met à jour `articles.last_update` après création réussie.

**Permissions/ACL** : aucune vérification propre — ce plugin agit en dehors
du cycle de permission normal (synchronisation système).

**Effets de bord** : écritures `#__user_usergroup_map`, `#__kunena_sessions`
(conditionnel), `records.published`, `#__content.state`, `articles.last_update`,
création d'articles, redirections HTTP, injection CSS/JS inline,
désactivation temporaire du cache Joomla.

**Tables** : `#__user_usergroup_map`, `#__kunena_sessions`, `users`, `forms`,
`records`, `registered_users`, `#__users`, `#__content`, `articles`, `elements`.

**Classes** :
`plugins/system/contentbuilderng_system/src/Extension/ContentbuilderngSystem.php:ContentbuilderngSystem`,
`FormSourceFactory`, `ArticleService`.

**Configuration influente** : `nocache`, `is_auto_groups`, `auto_groups`,
`auto_groups_limit_views`, `disable_new_articles`, `limit_per_turn`
(`08-configuration.md §14`).

**Messages** : `COM_CONTENTBUILDERNG_PERMISSIONS_NEW_NOT_ALLOWED`.

**Cas limites** : `limit_per_turn` borne le nombre d'articles synchronisés/
créés par requête — un grand volume de changements en attente s'étale sur
plusieurs requêtes HTTP successives. **Zone inconnue** : aucune tâche
planifiée Joomla (`com_scheduler`) référencée dans les brouillons sources —
la synchronisation semble purement « à la demande », déclenchée par le
trafic réel du site.

---

# K. Anomalies structurelles et code potentiellement mort

## 35. `site/src/Controller/Dispatcher.php` — code mort probable

**Fait observé — anomalie de dépôt** : il existe **deux** fichiers nommés
`Dispatcher.php` sous `site/src/` :
- `site/src/Dispatcher/Dispatcher.php` — namespace `...Site\Dispatcher`,
  classe `Dispatcher extends ComponentDispatcher` (245 lignes). C'est le
  **vrai** dispatcher PSR-4 du composant côté site (Joomla résout
  `Dispatcher\Dispatcher` pour `com_contentbuilderng` côté site). Toute la
  logique de routage décrite en préambule de la Section D en provient.
- `site/src/Controller/Dispatcher.php` — namespace `...Site\Controller`
  (dossier des **contrôleurs**), classe `Dispatcher extends
  ComponentDispatcher` elle aussi, mais avec un corps **beaucoup plus
  simple** et manifestement plus ancien (juste `view=list && task='' →
  task=list.display`).

**Fait observé** : `grep` exhaustif ne montre **aucune** référence à
`CB\Component\Contentbuilderng\Site\Controller\Dispatcher` ailleurs dans le
code (`admin/`, `site/`, `plugins/`) — ni instanciation, ni `extends`, ni
`use`. Joomla 6 PSR-4 résout les contrôleurs de tâche par convention
`<Tâche>Controller` (`ListController`, `EditController`, etc.), jamais par
une classe nommée `Dispatcher` placée dans `Controller/`.

**Comportement déduit** : ce fichier est du code mort — vraisemblablement un
reliquat d'une réorganisation antérieure (le vrai dispatcher a été déplacé
vers son propre dossier `Dispatcher/`, l'ancienne copie n'a pas été
supprimée de `Controller/`). Il ne casse rien (jamais chargé), mais alourdit
la surface de code à auditer/maintenir ; son historique Git ne montre qu'un
commit générique de bump de dépendances CI (`49ff303`), ce qui ne permet pas
de dater précisément son abandon.

**Zone inconnue** : à confirmer avec Gilles s'il s'agit d'un oubli de
nettoyage à corriger (suppression du fichier) ou d'un filet de sécurité
volontaire — le code lu ne permet pas de trancher, et ce catalogue n'a pas
vocation à le modifier (mission strictement documentaire).

---

## 36. `view=edit` admin — classe `EditModel` admin introuvable

**Fait observé** : `admin/src/View/Storage/HtmlView.php:426` construit un
lien `index.php?option=com_contentbuilderng&view=edit&storage_id=…` (bouton
« Preview »/édition directe d'un enregistrement depuis l'onglet Data d'un
storage, fonctionnalité 6). `admin/src/View/Edit/HtmlView.php` (507 lignes)
importe `use CB\Component\Contentbuilderng\Administrator\Model\EditModel;`
et appelle `$this->getModel()` sans argument (`:269-270`), ce qui — par
convention MVC Joomla — résout la classe
`CB\Component\Contentbuilderng\Administrator\Model\EditModel`.

**Fait observé** : une recherche exhaustive (`grep -rn "class EditModel"`)
sur `admin/` et `site/` ne trouve **qu'une seule** classe `EditModel`, dans
`site/src/Model/EditModel.php` (le modèle **front** de soumission/édition,
fonctionnalité 20) — **aucune classe `EditModel` n'existe dans
`admin/src/Model/`**. Aucune configuration de `MVCFactory` personnalisée
n'a été trouvée dans `admin/services/provider.php` redirigeant cette
résolution vers une autre classe.

**Comportement déduit** : sauf mécanisme de résolution MVC non identifié
dans les brouillons sources, l'appel `$this->getModel()` sur cet écran
devrait échouer à instancier un modèle (`Model not found`/exception Joomla
standard), ce qui rendrait le lien « Preview » de l'onglet Data
**non fonctionnel en l'état actuel du code**.

**Zone inconnue — à vérifier en priorité par test manuel Joomla** : non
confirmé par une exécution réelle (hors périmètre documentation statique de
cette mission) ; il est possible qu'un mécanisme Joomla de repli, un
événement d'extension, ou un fichier non exploré fournisse la classe
manquante. **Si confirmé, il s'agit d'une régression/fonctionnalité cassée**
à signaler distinctement de toute hypothèse habituelle, du fait de la force
des indices (import explicite d'une classe absente + `getModel()` sans
argument ni fallback visible).

---

## 37. `MenuService::createBackendMenuItem15/16/3` — code mort probable

**Fait observé** : `admin/src/Service/MenuService.php::createBackendMenuItem15()`/
`createBackendMenuItem16()`/`createBackendMenuItem3()` (suffixes numériques
évoquant Joomla 1.5/1.6/3) coexistent avec `createBackendMenuItem()`
générique (utilisé par la fonctionnalité 2 — menu admin & navigation).

**Hypothèse forte** : code legacy hérité de versions antérieures à la règle
AGENTS.md « Joomla 6 only / no backward compatibility », probable dette
technique à vérifier/purger. Aucun appelant de ces 3 méthodes versionnées
n'a été identifié dans le périmètre exploré par les brouillons sources — à
confirmer par une recherche exhaustive avant toute suppression (hors
périmètre de cette mission documentaire).

---

## 38. Correction terminologique — `site/src/Field/*`

**Fait observé** : contrairement à une hypothèse initiale de mission, les
11 classes de `site/src/Field/*` **ne sont pas** les types de champ du
formulaire d'édition d'un enregistrement (fonctionnalité 20). Ce sont des
**types de champ JForm personnalisés Joomla** utilisés dans l'écran
d'édition d'un **élément de menu** pointant vers ce composant — rendus côté
**back-office** de Joomla (Menus → Éditer un élément) — même si la classe
PHP réside dans `site/src/Field` pour des raisons d'autoload PSR-4 propres
au composant.

| Fichier | `type` JForm | Rôle observé |
|---|---|---|
| `CategoriesField.php` | `Categories` | Sélecteur de catégorie `#__categories` (filtre ACL `core.create`) pour le paramètre de menu de catégorie par défaut. |
| `FormsField.php` | `Forms` | Sélecteur de vue CBNG (`forms` publiées) pour `form_id` du menu, avec injection JS des valeurs par défaut par vue (`menu-options.js`). |
| `MultiformsField.php` | `Multiforms` | Variante multi-sélection (menu « formulaires publics » — liste de vues autorisées, fonctionnalité 18). |
| `MenucategoryField.php` | *(voir fichier)* | Sélecteur de catégorie pour un menu « nouvelle liste ». |
| `MenuformsField.php` | `Menuforms` | Sélecteur de vue pour le menu « nouvelle liste » custom. |
| `MenuinheritField.php` | `Menuinherit` | Bascule « hériter de la vue » pour un réglage d'affichage de menu. |
| `MenulistbuilderField.php` | `Menulistbuilder` | Constructeur visuel des colonnes/actions visibles pour un menu « nouvelle liste ». |
| `MenunumberField.php` | `Menunumber` | Champ numérique de menu (ex. limite de pagination du menu). |
| `MenuoverrideresetField.php` | `Menuoverridereset` | Bouton de réinitialisation d'une surcharge de menu vers la valeur héritée de la vue. |
| `MenuthemeField.php` | `Menutheme` | Sélecteur de thème CBNG pour le menu. |
| `CbmenuresetField.php` | `Cbmenureset` | Réinitialisation générique d'un paramètre de menu CBNG. |

**Comportement déduit** : les vrais « types de champ » d'un enregistrement
(texte, upload, captcha, groupe, etc.) sont résolus côté `EditModel::store()`
par un `switch` sur `elements.type` (`site/src/Model/EditModel.php:849-909`) ;
leur définition/rendu vit dans `admin/src/Elementtypes/*`/plugins
`contentbuilderng_form_elements`, hors périmètre de ce catalogue.

**Point d'attention pour tout instruction future** : la formulation « types
de champs `site/src/Field/*` » repose sur une hypothèse incorrecte sur le
rôle de ce dossier — corrigée ici.

---

# Contradictions et zones d'incertitude non tranchées

Ces points n'ont pas pu être tranchés par la présente fusion — ils sont
consignés ici pour investigation ultérieure (relecture de code plus
profonde ou test manuel en environnement Joomla réel) :

1. **Degré de duplication `DatatableService` ↔ `StorageModel`** (fonctionnalité 9) :
   `DatatableService::createForStorage()`/`syncColumnsFromFields()` semblent
   proches de `StorageModel::syncStorageDataTableOrBytable()`
   (fonctionnalité 6), mais les deux n'ont pas été recoupées ligne à ligne
   dans les brouillons sources (`DatatableService.php`, 749 lignes, non lu
   intégralement). Reste à déterminer si c'est une vraie duplication de
   logique ou une délégation interne de l'un vers l'autre.
2. **Point d'import du groupe `contentbuilderng_listaction` avant
   `onAfterArticleCreation`** (fonctionnalité 31) : dispatché depuis
   `ArticleService.php:711-712` vers **tout le groupe**, sans import ciblé
   visible juste avant dans ce fichier — soit le groupe entier est déjà
   importé en amont dans le flux de création d'article, soit seuls les
   plugins déjà importés pour d'autres raisons dans la requête courante
   répondent. Non localisé avec certitude.
3. **Atomicité de `ConfigImportService::applyPayload()`** (fonctionnalité 5) :
   aucun rollback transactionnel global identifié pour un import en cours
   d'application (`ConfigImportService.php`, 937 lignes, non lu
   intégralement) — en cas d'échec à mi-chemin, l'état partiellement importé
   n'a pas été confirmé comme atomique ou non.
4. **Branche `DELETE FROM articles` sans `WHERE`** dans
   `FormModel::deleteByIds()` (fonctionnalité 10) lorsque la liste des
   `form_id` restants est vide — comportement plausible d'après
   `03-data-model.md §19` mais non confirmé à 100 % par relecture complète
   dans aucun des trois brouillons sources.
5. **Rollback applicatif de `EditModel::store()`** en cas d'échec
   d'inscription après écriture du record (fonctionnalité 20) : pas de
   transaction SQL globale identifiée entourant tout `store()`, seulement
   un rollback ciblé (`clearDirtyRecordUserData()`) — un état partiel
   (enregistrement CBNG sans compte Joomla associé) n'est pas totalement
   exclu sans relecture plus poussée de `admin/src/types/*`.
6. **`PublicformsModel::buildOrderBy()`** (fonctionnalité 18) : tri effectif
   figé à `ORDER BY ordering`, indépendamment de l'état `filter_order`/
   `filter_order_Dir` calculé et stocké — garde-fou SQL volontaire (colonne
   de tri non whitelistée en chaîne littérale) ou dette de code non
   nettoyée, non tranché.
7. **Export sans gate `listaccess`** (fonctionnalité 25, déjà signalé
   `07-security.md §1.3`) : confirmé par lecture statique, non revérifié en
   conditions réelles (pas d'environnement Joomla exécutable disponible
   pour la rétro-analyse).
8. **`verification_days_{view,new,edit}`** (fonctionnalité 23) : colonnes
   existantes (`03-data-model.md`) mais usage non localisé dans le
   constructeur de `VerifyModel` lu par les brouillons sources — à vérifier
   plus finement côté `PermissionService`.
9. **Portion GET pure de `EditModel::getData()`** (`:372-766`, affichage du
   formulaire avant toute soumission, fonctionnalité 20) : non relue ligne à
   ligne dans les brouillons sources faute de budget d'analyse — le flux de
   soumission (`store()`, la partie la plus complexe et la plus à risque) a
   été priorisé.
10. **Table de vérification écrite par `VerifyModel`** (fonctionnalité 23) :
    nom de table exact confirmé comme `#__contentbuilderng_verifications`/
    `#__contentbuilderng_users` par recoupement avec `03-data-model.md`,
    mais le corps complet de `VerifyModel` (au-delà de `:109-559`) n'a pas
    été relu intégralement dans les brouillons sources.
11. **`$submit_after_result` non exploité plus loin** dans `EditModel.php`
    (fonctionnalité 32) : au-delà de la portion lue par les brouillons
    sources (~2870 lignes au total, zones `1600-2100`/`2700-2870`
    intégralement lues), possibilité que le retour d'`onAfterSubmit` soit
    consommé ailleurs dans le fichier — non exclu.
12. **Contenu CSS exact des 4 thèmes** (fonctionnalité 33), en particulier
    si `dark` implémente réellement `prefers-color-scheme`/
    `data-bs-theme="dark"` : non lu ligne à ligne dans les brouillons
    sources, seule l'architecture PHP de chargement a été vérifiée.
13. **`site/src/Controller/Dispatcher.php`** (fonctionnalité 35) : code mort
    probable, à confirmer avec Gilles avant toute suppression — hors
    périmètre de cette mission documentaire.
14. **`view=edit` admin / classe `EditModel` admin** (fonctionnalité 36) :
    anomalie de forte probabilité mais non confirmée par exécution réelle —
    priorité de vérification manuelle la plus élevée de ce document, étant
    donné qu'elle affecte potentiellement un bouton visible en production.

