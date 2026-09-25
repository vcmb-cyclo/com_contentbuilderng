# 05 — Parcours utilisateur (flux de bout en bout)

## 0. Méthodologie

Ce document extrait et organise les « Parcours utilisateur » (Phase 4 de la
mission de rétro-analyse) identifiés dans `04-features.md`, regroupés par
grande catégorie fonctionnelle plutôt que par écran. Pour chaque parcours :
une liste **précise et ordonnée** des fichiers/classes/méthodes traversés,
du déclencheur (clic, requête HTTP, callback Joomla) jusqu'à l'effet observable
final (page rendue, redirection, réponse JSON, fichier téléchargé).

Convention de référence : `Fichier.php:méthode()` quand la ligne exacte est
connue depuis `04-features.md`, sinon `Fichier.php::Classe`. Chaque parcours
renvoie à la fiche de `04-features.md` qui porte le détail Phase 3 complet
(ACL, données entrantes/sortantes, messages, cas limites) — ce document ne
republie pas ce détail, il se concentre sur la **séquence d'exécution**.

Qualification reprise de `04-features.md` : **Fait observé** / **Comportement
déduit** / **Hypothèse** / **Zone inconnue**, appliquée aux étapes dont
l'enchaînement exact n'a pas été confirmé ligne à ligne.

Ce document complète `11-execution-flows.md` (qui présente 6 diagrammes
de flux) sans le dupliquer : `11-execution-flows.md` illustre visuellement
un sous-ensemble de scénarios majeurs, `05-user-flows.md` couvre
**l'ensemble** des catégories de la Phase 4 (installation, configuration,
administration, création, modification, suppression, publication, affichage
frontend, recherche, filtrage, soumission de formulaires, validation,
traitement AJAX, import/export, permissions, authentification, intégrations
externes) sous forme de listes de traçabilité fichier/classe/méthode.

## Sommaire

1. [Installation](#1-installation)
2. [Configuration](#2-configuration)
3. [Administration](#3-administration)
4. [Création](#4-création)
5. [Modification](#5-modification)
6. [Suppression](#6-suppression)
7. [Publication](#7-publication)
8. [Affichage frontend](#8-affichage-frontend)
9. [Recherche](#9-recherche)
10. [Filtrage](#10-filtrage)
11. [Soumission de formulaires](#11-soumission-de-formulaires)
12. [Validation](#12-validation)
13. [Traitement AJAX](#13-traitement-ajax)
14. [Import/export](#14-importexport)
15. [Permissions](#15-permissions)
16. [Authentification](#16-authentification)
17. [Intégrations externes](#17-intégrations-externes)
18. [Contradictions et zones d'incertitude non tranchées](#18-contradictions-et-zones-dincertitude-non-tranchées)

---

## 1. Installation

### 1.1 Installation neuve / mise à jour (référence `04-features.md` #1)

1. Super Administrateur dépose un ZIP dans *Extensions › Gérer › Installer*
   (ou clique « Mettre à jour »).
2. Joomla core invoque `script.php:com_contentbuilderngInstallerScript::__construct()`
   (`:152-183`) — instancie `InstallerService`, `MigrationService`,
   `PluginInstallerService`, `SchemaService`.
3. `com_contentbuilderngInstallerScript::preflight('install'|'update', $parent)`
   (`:188-258`) — purge langues obsolètes, désactive plugins legacy, renomme
   tables legacy, migre lignes `#__extensions` legacy.
4. Joomla core copie les fichiers du paquet sur disque.
5. `com_contentbuilderngInstallerScript::install()`/`update()` →
   `installAndUpdate($parent, $type)` (`:284-306,539-552`).
6. `com_contentbuilderngInstallerScript::postflight($type, $parent)` (`:350-534`) :
   1. Manifeste canonique.
   2. Plugins legacy désactivés.
   3. Nettoyage répertoires/fichiers/langues obsolètes, média/upload dir.
   4. `SchemaService::ensureUniqueConstraints()`, `updateDateColumns()`,
      `normalizeExternalStorageModes()`, colonnes manquantes une à une,
      `PackedDataMigrationHelper::migratePackedPayloadsToModernFormat()`.
   5. `InstallerService::updateMenuLinks()`.
   6. `PluginInstallerService::ensurePluginsInstalled($source, forceUpdate=true)`
      puis `activatePlugins()`.
   7. `removeCoreValidationPlugins()`.
   8. (branche `update`) retrait thèmes dépréciés, plugin Ping, dédoublonnage
      extensions, retrait ancienne branche de menu.
   9. Normalisation de l'ordre des storages.
   10. `verifyInstalledExtensionConsistency()`.
   11. Purge caches/opcache.
   12. `enqueueMessage()` avec durée + lien `view=about`.
7. Écran de résultat Joomla natif affiché.

### 1.2 Désinstallation (référence `04-features.md` #1)

1. Super Administrateur clique « Désinstaller » dans *Extensions › Gérer*.
2. Joomla core invoque `com_contentbuilderngInstallerScript::uninstall($parent)`
   (`:308-348`).
3. Suppression des entrées `#__menu` du composant (toutes variantes
   `option`), cohérence du nœud racine du menu admin conservée.
4. `admin/sql/uninstall.sql` exécuté par le noyau Joomla (13 `DROP TABLE`).
   **Fait observé** : aucune donnée métier au-delà de ce script n'est
   supprimée (tables de storage internes `#__<name>` et articles Joomla
   générés **non supprimés**).

---

## 2. Configuration

### 2.1 Export de configuration (référence `04-features.md` #5)

1. Administrateur : *ContentBuilder NG › À propos* → bouton « Config Transfer ».
2. `AboutController` → redirection `view=configtransfer&mode=export`.
3. `Configtransfer\HtmlView::display()` → `loadForms()`/`loadStorages()` →
   `hydrateSelectionState()` (session `com_contentbuilderng.configtransfer.selection`).
4. Administrateur coche sections/vues/storages, soumet.
5. `AboutController::exportConfiguration()` (`:832-908`, task
   `about.exportConfiguration`) : `rememberConfigTransferSelection()` →
   `ConfigExportService::resolveEffectiveSections()` →
   `ConfigExportService::buildPayload()` → `buildSummary()`.
6. Réponse HTTP directe (`Content-Disposition: attachment`,
   `contentbuilderng-config-YYYYMMDD-His.json`).

### 2.2 Import de configuration (référence `04-features.md` #5)

1. Administrateur : *À propos › Config Transfer* → onglet Import, sélectionne
   un fichier JSON + sections/noms + mode (`merge`/`replace`).
2. `AboutController::importConfiguration()` (`:914-1007`, task
   `about.importConfiguration`) : lecture upload `cb_config_import_file`,
   vérification `meta.format`.
3. `ConfigImportService::filterPayload()` → restreint aux sections/noms
   sélectionnés.
4. `ConfigImportService::applyPayload($payload, $sections, $importMode)` :
   - `MODE_MERGE` : `UPDATE`/`INSERT` par id, sans purge.
   - `MODE_REPLACE` : `DELETE` ciblé par `form_id`/`storage_id` puis
     réinsertion.
5. Message de résumé (`_IMPORT_CONFIGURATION_SUCCESS`/`_NO_CHANGES`) +
   redirection `view=configtransfer&mode=import`.

### 2.3 Réglages « Article » d'une vue (référence `04-features.md` #11)

1. Écran Formulaire → onglet « Article » (tab10) →
   `admin/layouts/form/article_tab.php` rendu par `admin/tmpl/form/edit.php`.
2. Saisie (`create_articles`, `default_category`, `default_access`, …) →
   validation JS conditionnelle (`article_tab.php:262-334`) si
   `create_articles=1`.
3. « Enregistrer »/« Appliquer » → `FormController::save()` →
   `parent::save()` (core) → `FormModel::save()` → colonnes `forms`
   persistées.
4. (Plus tard, côté site) soumission d'un enregistrement → `EditModel::store()`
   → `ArticleService::createArticle()` consomme ces réglages
   (voir §11 Soumission de formulaires).

### 2.4 Réglages de plugin (paramètres `#__extensions.params`)

**Comportement déduit** : Administrateur → *Système › Gérer › Plugins* →
édite un plugin CBNG (ex. `contentbuilderng_system`, `contentbuilderng_image_scale`,
plugin `paypal`) → formulaire de paramètres Joomla natif (XML du plugin) →
`PluginModel::save()` (core Joomla, hors périmètre du composant) → colonne
`#__extensions.params` mise à jour → lue au prochain dispatch d'événement
par le plugin concerné (voir `08-configuration.md` pour la liste exhaustive
des paramètres par plugin).

---

## 3. Administration

### 3.1 Navigation admin (référence `04-features.md` #2, #3)

1. Après installation/mise à jour, `InstallerService::ensureAdminMenuRootNodeExists()`,
   `ensureAdministrationMainMenuEntry()`, `ensureSubmenuQuickTasks()`
   (`:421-814`) garantissent la présence des entrées `#__menu` (client_id=1).
2. Administrateur clique un sous-menu (Storages, Forms, Titlesets, Users,
   About) ou un « quick task » (+) → route standard `option=com_contentbuilderng&view=<vue>`.
3. `view=contentbuilderng` (aucune vue précisée) → `Contentbuilderng\HtmlView::display()`
   (`:20-59`) → template vide (`admin/tmpl/contentbuilderng/default.php`) —
   **Fait observé** : aucun tableau de bord fonctionnel, page neutre.

### 3.2 Audit et réparation guidée (référence `04-features.md` #4)

1. *À propos* → bouton « Lancer l'audit » → `AboutController::runAudit()`
   (`:759-799`, task `about.runAudit`).
2. `DatabaseAuditHelper::run()` → boucle sur ~15 `admin/src/Helper/Audit/*Helper::run()`.
3. Résultat en session (`com_contentbuilderng.about.audit`), rendu
   `admin/tmpl/about/audit_report.php`.
4. Clic « Réparer » sur une ligne → `AboutController::repairAuditIssue()`
   (`:160-206`, whitelist `DIRECT_AUDIT_REPAIR_ISSUES`, AJAX ou page complète)
   → `RepairWorkflowService::executeStep($issue)` → helper `::repair()`
   ciblé → ré-audit → rapport rafraîchi.
5. (Alternative) Ouverture du flux pas-à-pas →
   `RepairWorkflowService::createWorkflowState()` (`:67-135`, checklist de
   17 étapes, état en session `com_contentbuilderng.about.repair_workflow`)
   → étapes déroulées une à une → `advanceToNextPendingStep()`.
6. *À propos* → « Journal » → `AboutController::showLog()` (`:805-826`) →
   `admin/tmpl/about/log.php`.

### 3.3 Gestion des storages, formulaires, éléments, titlesets

Voir catégories dédiées ci-dessous (§4 Création, §5 Modification, §6
Suppression, §14 Import/export) qui couvrent la totalité des flux CRUD
admin (Storages, Forms, Storage Fields, Elements/Element Options, List
States, Users, Titlesets, Config Transfer) sans redite ici.

---

## 4. Création

### 4.1 Création d'un storage — écran direct (référence `04-features.md` #6)

1. *ContentBuilder NG › Storages* → bouton « Nouveau » →
   `StorageController::edit()` (`:131-150`) → `admin/tmpl/storage/default.php`.
2. Saisie (`name`, `title`, `bytable`) + « Enregistrer » →
   `StorageController::save()` (`:157-549`) : `checkToken()` → garde-fou
   longueur de nom (`:180-201`) → garde-fou unicité (`:203-232`) →
   `parent::save()` (core Joomla, `Table::store()`).
3. `StorageModel::ensureDataTable($id, $isNew, $oldName)` (`:628-637`) →
   `syncStorageDataTableOrBytable()` (`:779-1011`) : `CREATE TABLE`
   (`bytable=0`) ou `ALTER TABLE` (`bytable=1`) ou vérification `id`
   (`bytable=2`) → `ensureSystemFieldMetadata()` (`:647-761`).
4. `StorageModel::syncEditedFieldsFromRequest($id)`.
5. Redirection `storages.display` avec message de succès.

### 4.2 Création guidée d'un storage — Storage Wizard (référence `04-features.md` #7)

1. *ContentBuilder NG › Storage Wizard* → `StoragewizardController::begin()`
   (`:134-145`).
2. `chooseStorageMode()` (`:151-173`) → `new` → `chooseCreationMode()`
   (`:220-248`) → `saveStorageDetails()` (`:297-327`, `prepareStorageInput()`) →
   `chooseInitializationMode()` (`:254-291`) → `createStorage()` (`:439-529`) :
   `StorageModel::save()` → `ensureDataTable()` (comme §4.1) → avance
   `STEP_FIELDS`.
3. Redirection vers l'écran Storage réel (`wizard=1`) → ajout de champs
   (voir §4.3) → retour à l'assistant → `confirmFields()` (`:551-584`,
   exige ≥1 champ publié).
4. `createForm()` (`:591-630`) → `DirectStorageFormProvisioningService::resolveOrCreateFormId($storageId, 'thoth', true)`
   → avance à `STEP_FORM`, reste sur l'étape pour personnalisation
   optionnelle (écran Formulaire réel, `wizard=1`) → `confirmForm()`
   (`:636-654`).
5. `createMenu()` (`:660-699`) → item de menu Joomla de site (réutilise un
   existant si même lien exact dans le même `menutype`,
   `findExistingMenuItemId()`) — ou `skipMenu()` (`:705-715`).
6. `finish()` (`:721-729`) → `StorageWizardService::reset()` → redirection
   `view=storages`.

### 4.3 Ajout d'un champ de storage (référence `04-features.md` #8)

1. Écran Storage, onglet Storage → formulaire « Ajouter un champ ».
2. `task=storage.addfield` → `StorageController::addfield()` (`:728-761`)
   → `StorageModel::addFieldFromRequest()` (`:229-364`) : normalisation du
   nom → unicité `(storage_id, name)` → `ALTER TABLE ... ADD` (colonne
   physique **avant** métadonnées) → `INSERT storage_fields` (compensation
   `DROP COLUMN` si l'`INSERT` échoue, `:341-360`).
3. **Ou** `task=storagefield.add` → `StoragefieldController::add()` →
   `StorageFieldService::addField()` (`:18-117`) — même séquence DDL.
4. Redirection avec message (`COM_CONTENTBUILDERNG_FIELD_ADDED`).

### 4.4 Création d'une vue (Form) (référence `04-features.md` #10)

1. *ContentBuilder NG › Vues* → « Nouveau » → `FormController::add()`
   (`:135-146`) → redirection `task=form.display&layout=edit&id=0`.
2. Saisie tab0 (`type`, `reference_id` résolus par `FormSourceFactory`) +
   autres onglets → « Enregistrer » → `FormController::save()` (`:173-183`)
   → `parent::save()` → `FormModel::save()` (`Table::store()`, `UPDATE`
   ciblés, `buildDefaultListStates()` si aucun état, `:109-131`).
3. Si `wizard=1` (venant du Storage Wizard) → redirection `view=storagewizard`
   (`getRedirectToItemAppend()`, `:185-228`) ; sinon `view=forms`.

### 4.5 Création/édition d'un enregistrement front — voir §11 Soumission de formulaires

### 4.6 Création d'un titleset (référence `04-features.md` #15)

1. *Titlesets* → « Nouveau » → `admin/tmpl/titleset/default.php`.
2. « Enregistrer »/« Appliquer » → `TitlesetController::save()`/`apply()`
   (`:27-35`) → `saveAndRedirect()` (`:269-289`) →
   `CbStatsTitleSetManagerService::save($data)` (`:165-212`) : `validate()`
   → écriture fichier `.ini` dans le répertoire « custom » + `index.html`.

---

## 5. Modification

### 5.1 Édition d'un storage existant — voir §4.1 (même contrôleur/modèle,
`StorageController::edit()`/`save()`, avec branches renommage `RENAME TABLE`,
`:806-824`, si `oldName` détecté).

### 5.2 Édition inline des champs d'un storage (référence `04-features.md` #8)

1. Écran Storage, grille de champs → édition de titre/type/requis en ligne.
2. **Sans rechargement** : `ajax_addfield()`, `ajax_update_field_type()`,
   `ajax_update_field_required()`, `ajax_update_field_title()`
   (`StorageController.php:1326-1560`) → réponses JSON (voir §13 Traitement AJAX).
3. **Avec rechargement** (sauvegarde groupée de l'écran Storage) :
   `StorageModel::syncEditedFieldsFromRequest($id)` →
   `syncEditedFields()` (`:1017-…`) — renomme les colonnes physiques si le
   nom change, synchronise titre/type/taille/requis.

### 5.3 Édition d'une vue (Form) — voir §4.4, même contrôleur `FormController::save()`.

### 5.4 Options d'un élément (référence `04-features.md` #12)

1. Écran Formulaire → liste Éléments → clic sur une ligne → modale
   `view=elementoptions&element_id=…` (`tmpl=component`) —
   `ElementoptionsController::display()` (`:78-85`).
2. Saisie + Enregistrer → `ElementoptionsController::save()` (`:87-121`) →
   `ElementoptionsModel::store()` → si succès :
   `resyncLockedTemplatesAfterSave()` (`:49-76`) →
   `FormSupportService::resyncLockedTemplates($formId, $userId)` si
   template verrouillé.
3. Fermeture/réouverture de la modale avec message.

### 5.5 Édition en masse des libellés d'éléments (référence `04-features.md` #12)

1. Liste des éléments → édition groupée des libellés → « Enregistrer ».
2. `FormController::save_labels()` → `FormModel::saveElementListSettings()`
   (`:149-207`, boucle d'`UPDATE` sur `label`, `wordwrap`, `order_type`,
   `ordering`, `item_wrapper`).

### 5.6 Édition d'un utilisateur de vue (référence `04-features.md` #14)

1. Écran Utilisateurs (`view=users&form_id=…`) → clic ligne →
   `UsersController::edit()`/`apply()`/`save()` → `UserModel::getData()`/`store()`
   (via `CbuserTable`).
2. Actions de liste en masse (`verified_view()`, etc., `:96-233`) →
   `UserModel::setListVerifiedView()` etc. — `ensureContentbuilderngUserRow()`
   (`:62-85`) crée la ligne à la volée si absente.

### 5.7 Modification d'un enregistrement front — voir §11 Soumission de formulaires (mode édition, `record_id` non nul).

---

## 6. Suppression

### 6.1 Suppression d'un storage (référence `04-features.md` #6, correction §0.2 de `04-features.md`)

1. Écran Storages (liste) → sélection + « Supprimer » →
   `StorageController::delete()` (`:862-921`) → `StorageModel::delete($cid)`
   (`:1212-1274`).
2. `delete()` supprime **uniquement** : les lignes `storage_fields` du
   storage, réordonne `StorageFields`, `$row->delete($pk)` (ligne
   `storages`), `DROP TABLE #__<name>` si `bytable=0`.
3. **Fait observé (correction)** : **aucune** requête ne touche
   `records`/`list_records`/`articles` dans cette méthode — le nettoyage
   cascade de ces trois tables n'existe que dans le flux de réimport CSV
   avec option « vider » (§14.1).

### 6.2 Suppression d'enregistrements depuis l'onglet Data d'un storage (référence `04-features.md` #6)

1. Onglet Data → sélection de lignes + « Supprimer ».
2. `StorageController::deleteRecord()` (`:633-725`) — refuse `bytable=2`.
3. `FormSourceFactory::getForm('com_contentbuilderng', $storageId)->delete()`
   (`admin/src/types/com_contentbuilderng.php`) — suppression physique des
   lignes + fichiers uploadés.
4. Nettoyage `list_records`/`records`/`articles` par `record_id` IN (…).

### 6.3 Suppression d'un champ de storage (référence `04-features.md` #8)

1. Liste des champs → sélection + « Supprimer ».
2. `StoragefieldsModel::delete(array $pks)` (`:329-421`) — refuse
   `bytable=2`, filtre les champs système protégés, `ALTER TABLE ... DROP COLUMN`
   pour chaque champ physiquement présent **avant** `DELETE storage_fields`.

### 6.4 Suppression d'une vue (Form) (référence `04-features.md` #10)

1. Liste des vues → sélection + « Supprimer » → `FormsController::delete()`
   (`:139-171`) → `FormModel::delete()` (core) → `deleteByIds()` — cascade
   documentée dans `03-data-model.md §4` (`elements`, `list_states`,
   `users`, potentiellement `articles`, voir Contradiction #4 de
   `04-features.md`).

### 6.5 Suppression d'un titleset (référence `04-features.md` #15)

1. Liste Titlesets → sélection + « Supprimer » (uniquement `source=custom`)
   → `TitlesetController::deleteSelected()` (`:57-76`).
2. **Ou** depuis l'écran d'édition → `deleteFile()` (`:254-262`).

### 6.6 Suppression d'un enregistrement front (référence `04-features.md` #22)

1. Écran Edit ou Liste → bouton « Supprimer » (confirmation JS inline) →
   POST `task=edit.delete`.
2. `EditController::delete()` (`:309-385`) : `assertConstrainedAction('delete')`,
   jeton POST, `checkPermissions('delete', …)` (jamais accordé en mode
   preview), `resolveDeleteOwnerRestriction()` (`:2558-2573`).
3. `$data->form->delete($items, $formId, $ownerRestriction)` (`admin/src/types/*`).
4. `EditModel::delete()` (`:2575-2701`) : `DELETE list_records`, `DELETE records`,
   optionnellement suppression de l'article Joomla lié (`delete_articles`,
   `onContentBeforeDelete`/`onContentAfterDelete`, purge `#__assets`/
   `#__workflow_associations`), `DELETE articles`.
5. Purge du cache (`cleanComponentCaches()`).

---

## 7. Publication

### 7.1 Publication/dépublication d'un storage ou d'une vue (masse)

1. Liste Storages/Forms → sélection + icône publier/dépublier →
   `StoragesController::publish()`/`unpublish()`
   (`storagesPublish()`) ou `FormsController::formpublish()`/`formunpublish()`
   (bascule rapide depuis la liste, sans repasser par le formulaire complet).

### 7.2 Publication/dépublication d'un enregistrement — unitaire, AJAX (référence `04-features.md` #16.1, #24)

1. Écran Liste (ou Edit) → icône ✓/✗ (`[data-cb-publish-toggle]`) → JS
   `list-init.js:553-604` (`contentbuilderngHandlePublishToggleClick()`) →
   `fetch(href + '&cb_ajax=1', GET)`.
2. `EditController::publish()` (`:431-495`) : `checkToken('request', …)`
   (jeton en query string) → `assertConstrainedAction('publish')` → garde
   ACL `core.edit.state` (mode storage direct) → `checkPermissionForAjax('publish', …)`
   → `EditModel::change_list_publish()`.
3. Réponse JSON `respondAjax()` si `cb_ajax=1` détecté.

### 7.3 Publication/dépublication en masse depuis la liste (référence `04-features.md` #16.1)

1. Liste → sélection multiple + action groupée « Publier »/« Dépublier »
   (`Joomla.submitform()`, formulaire classique, pas AJAX).
2. `ListController::publish()` (`:88-346`) — `checkToken('post', false)` →
   `armPermissionsForSelection()` (recalcule les permissions pour la
   sélection) → délègue à `EditModel`.

### 7.4 Bascule d'état de liste (workflow trash/untrash) (référence `04-features.md` #31)

1. Écran Liste → sélecteur d'état (`<select data-cb-state-select>`) →
   `list-init.js:401-487` (`contentbuilderng_state_single()`) →
   `fetch(..., POST, task=edit.state, cb_ajax=1)`.
2. `EditController::state()` (`:387-429`) → `EditModel::change_list_states()`
   (`site/src/Model/EditModel.php:2727-2859`) :
   1. Lecture `list_states` pour l'id cible → `action`.
   2. `PluginHelper::importPlugin('contentbuilderng_listaction', $action)`.
   3. `dispatch('onBeforeAction', [$form_id, $items])` — plugin routé
      (`Trash::onBeforeAction()` → `UPDATE #__content SET state=-2` ; ou
      `Untrash::onBeforeAction()` → `UPDATE #__content c, records r, articles a SET c.state=r.published ...`).
   4. Upsert `list_records` (id, form_id, record_id, reference_id, state_id)
      — **c'est ici**, pas dans le plugin, que `list_records.state_id` est écrit.
   5. `dispatch('onAfterAction', [$form_id, $items, $error])`.
3. Réponse JSON `respondAjax()`.
4. (Événement indépendant) À chaque (re)création d'article par
   `ArticleService::createArticle()` (§7.5) :
   `dispatch('onAfterArticleCreation', [$form_id, $record_id, $article])` →
   `Trash::onAfterArticleCreation()` supprime l'article si l'état de liste
   courant est `trash` (garde-fou anti-résurrection) ; `Untrash` : no-op.

### 7.5 Fenêtre de publication programmée (référence `04-features.md` #34)

1. Toute requête de mutation (`isSyncMutationRequest()`) côté
   `plugins/system/contentbuilderng_system`.
2. `ContentbuilderngSystem::onAfterRoute()` (`:439-554`) : synchronise
   `FormSourceFactory::getForm(...)->synchRecords()` par
   `(type, reference_id)` distinct → `createMissingArticles()` (`:627-738`,
   appelle `ArticleService::createArticle()` par lot de `limit_per_turn`).
3. `UPDATE` multi-tables : republie/dépublie `records.published` selon
   `publish_up`/`publish_down` comparés à `now()`, et selon le blocage
   Joomla de l'utilisateur (`act_as_registration`).
4. `ContentbuilderngSystem::onAfterInitialise()` (`:556-625`) : `UPDATE`
   symétriques sur `#__content.state` (article lié).

---

## 8. Affichage frontend

### 8.1 Affichage liste (référence `04-features.md` #16)

1. Clic menu/lien → `index.php?...&view=list&id=X` (ou `storage_id=`).
2. `Dispatcher::dispatch()` (`site/src/Dispatcher/Dispatcher.php:32-244`)
   résout `controller=list`, `task=list.display`.
3. `ListController::__construct()`+`display()` : résolution `formId`/
   `recordId` (`:366-418`) → `isValidAdminPreviewRequest()` ou
   `PermissionService::checkPermissions('listaccess')` (voir §15 Permissions).
4. `parent::display()` (Joomla MVC) → `ListModel::__construct()` (résolution
   état filtre/tri/pagination) → `ListModel::getData()` (`:671-1291`) →
   `FormSourceFactory::getForm()->getListRecords()`.
5. `List\HtmlView::display()` (`:69-250`) assemble les variables de gabarit.
6. `site/tmpl/list/default.php` (ou variante `layout=`) rend le HTML, charge
   `media/js/list-init.js`.

### 8.2 Affichage détail (référence `04-features.md` #17)

1. Lien liste/menu → `Dispatcher` → `DetailsController::__construct()`
   (résolution `record_id`, cas `view=latest`) →
   `checkPermissions('view', …)`.
2. `DetailsModel::getData()` (`:312-685`) → `FormSourceFactory::getForm()->getRecord()`
   + `TemplateRenderService::getTemplate()`.
3. `Details\HtmlView::display()` (`:278-550`) — pipeline `onContentPrepare`
   et réécriture de liens → `site/tmpl/details/default.php`.

### 8.3 Formulaires publics — annuaire (référence `04-features.md` #18)

1. Arrivée sans `id`/`storage_id` → `Dispatcher` (route par défaut, point 4
   de la résolution de contrôleur) → `PublicformsController::display()`.
2. `PublicformsModel::getData()` + `getPermissions()` (une passe
   `PermissionService` par ligne) + `getTags()`.
3. `Publicforms\HtmlView::display()` → `site/tmpl/publicforms/default.php`.

### 8.4 Aide contextuelle (référence `04-features.md` #19)

1. Message d'erreur de champ/tri inconnu en liste embarquée → lien « aide
   syntaxe » → `task=cblisthelp.display`.
2. `Dispatcher` → `CblisthelpController` → `Cblisthelp\HtmlView::display()`
   (charge la langue du plugin, construit 12 sections) →
   `site/tmpl/cblisthelp/default.php`.

### 8.5 Rendu d'une balise de contenu dans un article Joomla tiers (référence `04-features.md` #26-30)

1. Un article `com_content` (ou toute vue déclenchant `onContentPrepare`,
   y compris le rendu d'un template de liste/détail CBNG) contient
   `{CBList}`/`{CBStats}`/`{CBDownload}`/`{CBImageScale}`/`{CBRating}`/`{CBVerify}`.
2. Joomla core (ou `ListModel.php:1270`/`TemplateRenderService.php:690`/
   `Details\HtmlView.php:359`/`admin/src/View/Edit/HtmlView.php:144,517`)
   dispatch `onContentPrepare`.
3. Le plugin de contenu correspondant intercepte, parse le tag
   (`TagSyntaxService::parse()`), valide, résout la source via
   `FormSourceFactory`, vérifie l'ACL (`PermissionService`), réécrit
   `article->text`.
4. Pour `{CBList}` : construction d'une URL d'iframe pointant vers §8.1 en
   mode embarqué (`cblist_embed=content-plugin`).
5. Pour `{CBDownload}`/`{CBImageScale}` : au clic, une seconde requête GET
   avec un paramètre de service (`contentbuilderng_download_file=sha1(...)`
   ou `contentbuilderng_display=1&contentbuilderng_field=sha1(...)`) sert le
   fichier binaire directement (voir §17 Intégrations externes pour le
   détail de service de fichier).

---

## 9. Recherche

### 9.1 Recherche plein texte sur la liste (référence `04-features.md` #16)

1. Utilisateur saisit un terme dans la barre de filtre (affichée si
   `show_filter=1` et au moins un champ `search_include=1`).
2. Soumission → paramètre `filter` en requête (GET, formulaire `#adminForm`).
3. `ListModel::getData()` (`:671-1291`) construit la clause de recherche
   plein-texte sur les colonnes marquées `search_include`, combinée aux
   autres filtres actifs.
4. Résultat re-rendu, état de recherche persisté en session
   (`ListModel::getPaginationStateKeyPrefix()`).

### 9.2 Listes déroulantes dépendantes (API `get-unique-values`) (référence `04-features.md` #24, `09-business-rules.md §5.6`)

1. Formulaire d'édition (front) : sélection d'une valeur dans un champ
   parent (ex. « Pays ») déclenche un appel JS (hors périmètre PHP direct)
   vers `task=api.display&action=get-unique-values&field_reference_id=…&where_field=…&where=…`.
2. `ApiController::handleAction('get-unique-values', …)` → vérifie `api_allowed`
   sur les deux champs référencés (403 sinon) → requête de valeurs
   distinctes → `{code, field_reference_id, msg:[...]}`.
3. Le JS appelant (externe au périmètre documenté) peuple dynamiquement le
   champ enfant (ex. « Ville »).

---

## 10. Filtrage

### 10.1 Filtre externe déclenché par formulaire (référence `04-features.md` #16)

1. Un formulaire de filtre externe (article, module tiers, ou template CBNG)
   soumet `contentbuilderng_filter_signal`, `cbListFilterKeywords`,
   `cbListFilterCalendarFrom/To[...]`, `cb_filter[...]` (POST), ou
   `cbListFilterArticleCategories`.
2. `ListModel::getData()` (`:790-869`) rejoue ce filtre depuis la session
   tant que `allow_external_filter` est actif sur la vue et que
   `filter_reset` n'a pas été demandé.

### 10.2 Restriction par mode embarqué `{CBList}` (référence `04-features.md` #26, `09-business-rules.md §6.6`)

1. Le plugin de contenu `contentbuilderng_cblist` construit une URL avec
   `cblist_fields=`/`cblist_actions=`/`cblist_sort=`/`cblist_limit=`.
2. `List\HtmlView.php:89-105` (via `EmbeddedListFieldFilterService`) valide
   les sélecteurs de champs/tri contre les colonnes réellement disponibles
   → erreur `COM_CONTENTBUILDERNG_CBLIST_UNKNOWN_*` si invalide (lien vers
   §8.4 aide contextuelle).
3. `ListController::assertConstrainedAction()`/`EditController::assertConstrainedAction()`/
   `DetailsController::display()` appellent
   `EmbeddedListActionFilterService::isRequestAllowed()` **avant** le
   contrôle ACL normal — **restriction uniquement**, jamais d'extension de
   droits (voir §15 Permissions).

### 10.3 Filtre par tags — Formulaires publics (référence `04-features.md` #18)

1. Annuaire `publicforms` → formulaire GET de filtre par tag.
2. `PublicformsModel::getData()` (`:274-284`) ajoute `AND tag LIKE …` à la
   requête SQL littérale. **Zone inconnue** : le tri (`filter_order`) reste
   figé sur `ORDER BY ordering`, indépendamment du filtre de tri choisi
   (voir Contradiction #6 de `04-features.md`).

### 10.4 Filtre par storage lecture seule (`bytable=2`) (référence `04-features.md` #16)

1. Mode « storage direct », storage `bytable=2` → `ListModel::getDirectStorageListSubject()`
   (`:496-635`) force `edit_button`/`new_button=0` — les boutons de
   mutation sont masqués côté gabarit, pas seulement refusés côté serveur
   (cohérent avec `09-business-rules.md §6.8`).

---

## 11. Soumission de formulaires

### 11.1 Soumission complète d'un enregistrement (référence `04-features.md` #20 — flux le plus complexe du composant)

1. Visiteur ouvre le formulaire (`task=edit.display&id=&record_id=`) →
   `EditController::__construct()` (calcule les permissions
   `(formId, recordId)`) → `display()` (`:664-742`) →
   `checkPermissions('edit'|'new', …)` → `EditModel::getData()` (portion
   GET, `:372-766`) → `Edit\HtmlView::display()` (`:745-859`) →
   `site/tmpl/edit/default.php` (formulaire POST, jeton `form.token`).
2. Visiteur remplit et soumet (« Enregistrer » ou « Appliquer ») →
   `EditController::save($apply)` (`:222-302`) : `assertConstrainedAction()`
   → `checkPermissions('edit'|'new', …)` → `isEditableTemplateConfigured()`.
3. **`EditModel::store()`** (`:767-2293`) :
   1. `checkToken('post')`.
   2. `PluginHelper::importPlugin('contentbuilderng_submit')` (import de
      groupe, voir §17 pour le contrat `onBeforeSubmit`/`onAfterSubmit`).
   3. Résolution des champs éditables (`editable=1` + publié + non exclu
      par restriction de menu/mode embarqué).
   4. Captcha (`\Securimage`) si champ présent — voir §12 Validation.
   5. Mode inscription (`act_as_registration`) — validations nom/e-mail/
      username/mot de passe — voir §12 et §16 Authentification.
   6. Boucle de validation par champ →
      `FieldValidationService::validate()` + événement `onValidate`
      (`contentbuilderng_validation`) — voir §12.
   7. Upload de fichier (référence `04-features.md` #21) — voir §12.3.
   8. Contraintes de type storage (requis/longueur/type SQL).
   9. `onBeforeSubmit` (groupe `contentbuilderng_submit`).
   10. Si échec de soumission (`cb_submission_failed=1`) : valeurs mémorisées
       en session (`cb_failed_values`), **retour sans écriture**.
   11. **Écriture** : `$data->form->saveRecord($record_id, $values)`
       (`admin/src/types/*`).
   12. Inscription active → `EditModel::register()` (`:2295-2557`) : création/
       mise à jour du compte Joomla (`UserHelper::hashPassword()`), ou mode
       bypass → insertion `verifications` + balise `{CBVerify}` traitée par
       `onPrepareContent` (voir §16 Authentification, §17 Intégrations externes).
   13. `UPDATE records` (edited++, sef, lang_code).
   14. `create_articles=1` → `ArticleService::createArticle()` (`:1994`).
   15. `INSERT registered_users` (upsert protégé, doublon ignoré).
   16. `custom_action_script` par champ, `onAfterSubmit` (groupe
       `contentbuilderng_submit`) — voir §17.
   17. E-mails de notification (`TemplateRenderService::getEmailTemplate()`).
   18. Retour `$record_return`.
4. `EditController::save()` : succès → message `COM_CONTENTBUILDERNG_SAVED`
   + redirection ; échec → `$apply=true` forcé, formulaire réaffiché avec
   messages badgés par champ.

### 11.2 Application partielle depuis un mode embarqué (sparse submission)

1. Formulaire embarqué (`{CBList}`/mode « nouvelle liste ») ne rend qu'un
   sous-ensemble de champs éditables (`cb_menu_edit_fields`).
2. `EditModel::store()` — **Fait observé** : un champ requis absent du POST
   sur un enregistrement **existant** n'est pas traité comme vide (tolère
   la soumission partielle) ; uniquement en **création**, un champ requis
   manquant est rejeté.

### 11.3 Soumission via l'API REST-like (référence `04-features.md` #24)

1. Intégration externe → `PUT`/`PATCH`/`POST index.php?...&task=api.display&id=&record_id=`
   avec corps JSON `{"fields":{...}}`.
2. `ApiController::assertStateChangingRequestToken()` (jeton formulaire ou
   en-tête `X-CSRF-Token`) → `ApiFieldPermissionService::getAllowedReferenceMap()`
   (filtre `api_allowed`) → réutilise **le même** `EditModel::store()` que
   §11.1 (champs non `api_allowed` silencieusement ignorés) → réponse
   `{message, record_id, detail:{...}}`.

---

## 12. Validation

### 12.1 Validation standard par champ (référence `04-features.md` #20, `09-business-rules.md §3`)

Séquence par champ, dans `EditModel::store()` :
1. Lecture de la valeur postée (`raw`/`html`/`array` selon config du champ).
2. Reconversion de format de date si `transfer_format`.
3. `custom_validation_script` PHP éventuel (`self::customValidate()`).
4. `validateField()` → `FieldValidationService::validate()` — règles
   natives `notempty`/`equal`/`email`/`date_not_before`/`date_is_valid`,
   gatées globalement par `enable_validations` (composant).
5. Événement `onValidate` (plugins `contentbuilderng_validation`, externes).
6. Erreur → `cb_submission_failed=1` + `enqueueFieldValidationMessage()`
   (message badgé par le libellé du champ).
7. Après la boucle « métier » : contraintes de type storage (requis,
   longueur `varchar`, type SQL entier/décimal/booléen/temporel).

### 12.2 Validation du captcha (référence `04-features.md` #20)

1. Champ `captcha` présent et éditable dans `EditModel::store()`.
2. Instanciation `\Securimage` (bibliothèque tierce vendorisée,
   `10-dependencies.md §1.1`) → vérification de la valeur postée.
3. Échec → `cb_submission_failed=1` + `COM_CONTENTBUILDERNG_CAPTCHA_FAILED`.

### 12.3 Validation d'upload de fichier (référence `04-features.md` #21)

1. Champ `upload` dans `EditModel::store()` (`:1148-1358`).
2. Test de taille (`max_filesize`), test d'extension (liste blanche ou
   `DEFAULT_ALLOWED_UPLOAD_EXTENSIONS` + `hasExecutableExtension()`),
   confinement au site (`ContentbuilderngHelper::is_internal_path()`).
3. Échec → `COM_CONTENTBUILDERNG_FILESIZE_EXCEEDED`/
   `_FILE_EXTENSION_NOT_ALLOWED`/`_UPLOAD_FAILED`, `cb_submission_failed=1`.
4. Succès → `File::makeSafe()`, déduplication par hash aléatoire, écriture
   sur disque + `index.html` de protection, suppression des anciens
   fichiers remplacés (`cb_delete_<id>=1`).

### 12.4 Validation du mode inscription (référence `04-features.md` #20, `09-business-rules.md §5.5`, voir §16 Authentification)

1. `act_as_registration=1` → validation nom (non vide), e-mail (format +
   confirmation), nom d'utilisateur (regex anti-injection `[<>"'%;()&]`),
   mot de passe (confirmation).
2. `userConflictExists()` — test des conflits username/e-mail, exclusion de
   l'utilisateur courant en ré-édition de son propre enregistrement.
3. Échec → messages `COM_CONTENTBUILDERNG_NAME_EMPTY`/`_USERNAME_EMPTY`/
   `_USERNAME_INVALID`/`_USERNAME_NOT_AVAILABLE`/`_EMAIL_EMPTY`/
   `_EMAIL_INVALID`/`_EMAIL_MISMATCH`/`_PASSWORD_EMPTY`/`_PASSWORD_MISMATCH`.

### 12.5 Validation côté client — réglages Article (référence `04-features.md` #11)

1. Onglet Article, écran admin → si `create_articles=1`,
   `default_category` devient `required` en JS (`article_tab.php:262-334`).
2. **Zone inconnue** : aucune contrepartie serveur identifiée dans
   `FormController::save()`/`FormModel::save()` (voir Contradiction dans
   `04-features.md` #11).

---

## 13. Traitement AJAX

Référence détaillée : `06-api-contracts.md §4`. Cette section récapitule les
séquences d'exécution ; le contrat (paramètres, format de réponse) est dans
`04-features.md` #16.1 et #24, non répété ici.

### 13.1 Bascule d'état/publication par ligne (liste) — voir §7.2, §7.4.

### 13.2 Édition inline des champs de Storage (référence `04-features.md` #6, #8)

1. Grille de champs, écran Storage → modification de type/requis/titre en
   ligne (sans rechargement).
2. JS de `admin/tmpl/storage/default.php` → `fetch(task=storage.ajax_update_field_type|ajax_update_field_required|ajax_update_field_title, POST)`.
3. `StorageController::ajax_update_field_type()`/`ajax_update_field_required()`/
   `ajax_update_field_title()` (`:1326-1560`) → `StorageModel` (DDL ciblé si
   nécessaire) → `respondAjax()`/`respondAjaxData()` (JSON).
4. Ajout d'un champ sans rechargement : `ajax_addfield()` — même séquence
   DDL que §4.3, réponse JSON.

### 13.3 Prévisualisation d'en-têtes CSV/XLSX (référence `04-features.md` #6)

1. Sélection d'un fichier CSV/XLSX dans le formulaire d'import Storage.
2. `fetch(task=storage.previewHeaders, POST)` →
   `StorageController::previewHeaders()` (`:562-580`) →
   `StorageModel::extractHeaderColumnsFromUpload()` → liste de colonnes en
   JSON pour la sélection manuelle avant import réel (§14.1).

### 13.4 Vérification de colonnes manquantes (table existante) (référence `04-features.md` #6)

1. Sélection d'une table existante en mode `bytable=1`/`2`.
2. `fetch(task=storage.checkExistingTableColumns, GET)` →
   `StorageController::checkExistingTableColumns()` (`:588-622`,
   `authorise('core.manage', …)`) → compare colonnes système attendues aux
   colonnes réelles → avertissement `COM_CONTENTBUILDERNG_CUSTOM_STORAGE_MSG`
   si CBNG ajouterait une colonne.

### 13.5 Réparation d'un problème d'audit (référence `04-features.md` #4)

1. Écran Audit → clic « Réparer » sur une ligne.
2. `fetch(task=about.repairAuditIssue, isAjaxCall())` →
   `AboutController::repairAuditIssue()` (`:160-206`) →
   `RepairWorkflowService::executeStep($issue)` → helper `::repair()` →
   `respondAjax()`.

### 13.6 API REST-like générique — voir `04-features.md` #24 (tableau des endpoints) et §11.3/§9.2 ci-dessus pour les scénarios de soumission/valeurs dépendantes.

### 13.7 Notation (rating) (référence `04-features.md` #24.1)

1. Clic étoile (liste ou détail, rendu par `RatingHelper::getRating()`) →
   JS `cbRate(url, lastId)` → `fetch(task=api.display&action=rating, POST)`.
2. `ApiController::display()` → `handleAction('rating', …)` →
   `ratePayload()` (`:508-699`) : `can('rating')` →
   `assertStateChangingRequestToken()` → calcul de la note selon
   `rating_slots` → purge `rating_cache` >1 jour → test doublon (table +
   session) → **transaction** : `INSERT rating_cache`, `UPDATE records.rating_*`,
   upsert `#__content_rating` si article lié publié.
3. Réponse JSON `{code:0|1, msg:"..."}` → JS met à jour l'affichage sans
   recharger la page.

---

## 14. Import/export

### 14.1 Import CSV/XLSX d'un storage (référence `04-features.md` #6)

1. Écran Storage → onglet import → sélection fichier `csv_file`/`xlsx` →
   (optionnel) prévisualisation des en-têtes (§13.3) → sélection des
   colonnes à importer → « Enregistrer ».
2. `StorageController::save()` (`:390-549`, branche fichier détecté) :
   sauvegarde core du storage → `ensureDataTable()` (table créée **avant**
   l'import).
3. `StorageModel::storeCsv($file, $id)` (`:1277-1382`) : lecture d'en-tête →
   `store()` par colonne retenue (crée une `storage_field`) →
   `convertSpreadsheetFileToCsv()` si XLSX.
4. `csvFileToTable()` (`:1619-1818`) : insertion des lignes par lots dans
   la table physique **et** création des lignes `records` correspondantes.
5. Option « vider les enregistrements existants avant import »
   (`$options->dropRecords`) : `TRUNCATE` table physique → `DELETE records`
   ciblé → `DELETE a.*, c.* ... INNER JOIN #__content` (SQL brut,
   `:1700-1748`) purge `articles` **et** articles Joomla liés en une passe.
   **C'est ici** (pas à la suppression du storage, voir §6.1) que se trouve
   le nettoyage cascade.
6. Résumé (`getLastImportSummary()`) → message détaillé.

### 14.2 Export XLSX de données (référence `04-features.md` #25)

1. Écran Liste (état de filtre/tri en session) → clic « Export » →
   `view=export&id=X`.
2. `Dispatcher` → `ExportController::display()` (`:22-35`, valide preview,
   force `format=raw`).
3. `ExportModel::getData()` (`:64-491`) — réutilise l'état de session de la
   liste, sélectionne colonnes `export_include=1`.
4. `Export\RawView` → `site/tmpl/export/default.php` (369 lignes) —
   construit le classeur PhpSpreadsheet, écrit sur `php://output`, `exit`.
   **Zone à signaler** : aucun contrôle `listaccess` propre à ce contrôleur
   (voir §15 Permissions).

### 14.3 Export/import de titlesets (référence `04-features.md` #15)

1. **Export** : liste Titlesets → sélection + « Exporter » →
   `TitlesetController::exportSelected()` (`:78-132`) — téléchargement
   direct (1 fichier) ou ZIP en mémoire (`\ZipArchive`).
2. **Import** : formulaire d'upload multi-fichiers → `importFiles()`
   (`:134-186`) — validation individuelle + détection de doublon **au sein
   du lot avant toute écriture** → écriture effective (avec option
   `titleset_overwrite`).

### 14.4 Export/import de configuration — voir §2.1/§2.2.

---

## 15. Permissions

### 15.1 Contrôle d'accès applicatif front (`PermissionService`) (référence `04-features.md` #14, `07-security.md §1.3`)

1. Toute requête `List`/`Details`/`Edit`/`ApiController`/plugins de contenu
   (`{CBDownload}`, `{CBImageScale}`, `{CBRating}`, Permission Observer)
   appelle `PermissionService::setPermissions($formId, $recordId, …)` puis
   `authorizeFe('<droit>')` (front) ou `authorize('<droit>')` (back).
2. `PermissionService::setPermissions()` (`admin/src/Service/PermissionService.php:225-322`)
   joint `forms` + `#__contentbuilderng_users` (LEFT JOIN `form_id`,
   `userid`) — résout quotas (`limit_add`/`limit_edit`), gates de
   vérification (`verified_{view,new,edit}`).
3. Refus → `NotAllowed` HTTP 403 (ou disparition silencieuse d'un bloc,
   selon le contexte plugin de contenu).

### 15.2 Restriction du mode embarqué `{CBList}` — voir §10.2 (intersection uniquement, jamais d'extension de droits).

### 15.3 Preview admin signée (HMAC) (référence `04-features.md` #16, `07-security.md §1.3.2`)

1. Administrateur génère un lien de preview depuis l'écran Formulaire admin
   (signature HMAC, `until`, `sig`).
2. `ListController`/`DetailsController`/`EditController::isValidAdminPreviewRequest()`
   valide la signature → active `cb_preview_ok=1`, contourne
   `checkPermissions()` standard — **sauf** pour `delete`/`publish`/`state`,
   jamais accordés en mode preview (`09-business-rules.md §6.5`).
3. `ListController::enqueueUnpublishedPreviewNotice()` (`:557-576`) affiche
   un avertissement une fois par lien si la vue est dépubliée.

### 15.4 ACL Joomla native (back-office) (référence `07-security.md §1.2`)

1. `ComponentAccessTrait::execute()` impose `core.manage` sur
   `com_contentbuilderng` aux contrôleurs qui l'utilisent avant chaque tâche
   (`07-security.md` §1.2). `TitlesetController` applique ce contrôle dans
   ses méthodes.
2. Certaines tâches ajoutent un contrôle plus fin (`core.edit`,
   `core.edit.state`, etc.). `DatatableController::create()`/`sync()`
   vérifient le jeton CSRF et héritent du contrôle `core.manage`, mais
   ne vérifient pas `core.edit` pour ces opérations DDL (`04-features.md` #9).

### 15.5 Storage direct auto-provisionné — profils de droits (référence `04-features.md` #16, `09-business-rules.md §6.7`)

1. Requête `storage_id=` sans vue existante →
   `DirectStorageFormProvisioningService::resolveOrCreateFormId($storageId)`.
2. Vue provisionnée par une action admin délibérée (Storage Wizard) → droits
   lecture/écriture « raisonnables » immédiatement utilisables.
3. Vue auto-provisionnée par une requête front anonyme → **lecture seule
   pour Invité, rien d'autre** tant qu'un administrateur n'a pas relu/ajusté
   les droits.

---

## 16. Authentification

### 16.1 Inscription via un formulaire CBNG (`act_as_registration`) (référence `04-features.md` #20, `09-business-rules.md §5.5`)

1. Formulaire d'édition avec champs spéciaux mappés
   (username/name/password[repeat]/email[repeat]).
2. `EditModel::store()` — validations §12.4 → écriture de l'enregistrement
   (`saveRecord()`) → `EditModel::register()` (`:2295-2557`) : création/mise
   à jour du compte Joomla via `UserHelper::hashPassword()` (jamais de
   hachage maison).
3. **Mode bypass** (`registration_bypass_plugin`) : insertion directe d'une
   ligne `verifications` avec `verification_data='type=registration&'` +
   balise `{CBVerify plugin: …}` traitée par `onPrepareContent`
   (§16.2/§17) — permet de déclencher une vérification/paiement **après**
   une inscription bypass, sans article Joomla réel.
4. Échec après écriture du record → `clearDirtyRecordUserData()` (rollback
   applicatif ciblé, **pas** une transaction SQL globale — voir
   Contradiction #5 de `04-features.md`) → `Exception COM_CONTENTBUILDERNG_REGISTRATION_FAILED`.

### 16.2 Vérification/paiement gating une action (référence `04-features.md` #23)

1. Article contenant `{CBVerify …}` → plugin de contenu
   `contentbuilderng_verify` pose la config en session
   (`com_contentbuilderng.verify.<plugin><verification_name>`).
2. Visiteur clique le lien « Vérifier » → `Dispatcher` →
   `VerifyController::display()` → `VerifyModel::__construct()`
   (`admin/src/Model/VerifyModel.php:109-467`, partagé site/admin) :
   1. Résout la transaction, décode `setup`.
   2. `require_view` non satisfait → redirection `edit.display`.
   3. Première visite → `INSERT verifications` (`verification_hash`).
   4. `PluginHelper::importPlugin('contentbuilderng_verify', $plugin)` →
      `dispatch('onSetup', …)` → `dispatch('onForward', …)` — le plugin
      redirige vers le fournisseur (PayPal) ou renvoie l'URL de retour
      (`passthrough`).
3. Retour du fournisseur (`verify=1`) → nouvelle requête `view=verify` →
   `VerifyModel::__construct()` : `dispatch('onVerify', …)` → succès →
   upsert `#__contentbuilderng_users` (`verified_{view,new,edit}` +
   `verification_date_*`) → invalide le hash → si `token=` présent,
   `activate($token)` (réutilise `com_users`) → redirection finale
   (`return-site`/`return-admin`, validées `Uri::isInternal()`).

### 16.3 Activation manuelle d'un compte par un administrateur (référence `04-features.md` #23.2)

1. Administrateur reçoit/ouvre un lien
   `view=verify&layout=raw&task=…&token=…` (ou `verify_by_admin=1&token=`).
2. `Verify\RawView` → `VerifyModel::activate_by_admin($token)`
   (`admin/src/Model/VerifyModel.php:469-559`) : ACL `core.create` sur
   `com_users` (pas `com_contentbuilderng`) → recherche `#__users` par
   `activation=token`, `block=1`, `lastvisitDate` nulle → `block=0`,
   `activation=''` via `User::save()` → `PluginHelper::importPlugin('user')`
   → envoi e-mail de bienvenue Joomla natif (avec/sans mot de passe selon
   `com_users.params.sendpassword`) → redirection `index.php?option=com_users`.

### 16.4 Synchronisation authentification ↔ publication (référence `04-features.md` #34)

1. Utilisateur Joomla bloqué/débloqué (`#__users.block`) pour un
   enregistrement `act_as_registration`.
2. `ContentbuilderngSystem::onAfterRoute()`/`onAfterInitialise()` — `UPDATE`
   multi-tables synchronisent `records.published` et `#__content.state`
   selon l'état de blocage (voir §7.5).
3. `ContentbuilderngSystem::onAfterDispatch()` — gestion des groupes
   automatiques (`is_auto_groups`) : ajoute/retire l'utilisateur de groupes
   Joomla configurés selon `users.verified_view`.

---

## 17. Intégrations externes

### 17.1 Paiement PayPal (référence `04-features.md` #23.3, `10-dependencies.md §3`)

1. `VerifyModel::onSetup` → `Paypal::onSetup()` — lit `plugin_options['amount']`
   (requis).
2. `VerifyModel::onForward` → `Paypal::onForward()` — construit un
   formulaire HTML auto-soumis vers `cgi-bin/webscr` (PayPal), `exit`
   immédiat après vidage du buffer de sortie.
3. Visiteur redirigé vers PayPal, effectue le paiement, PayPal redirige
   vers l'URL de retour construite à l'étape `onSetup`.
4. Retour (`verify=1`) → `VerifyModel::onVerify` → `Paypal::onVerify()` —
   vérification synchrone (`_notify-synch`) ou IPN (`_notify-validate`)
   selon `use-ipn`/`paypal_ipn=true` — appel réseau sortant cURL/`fsockopen`
   (`CURLOPT_SSL_VERIFYPEER => false`, défaut de sécurité déjà signalé en
   `07-security.md`).
5. Succès → suite du flux §16.2 point 3.

### 17.2 Rendu `{CBList}` dans un article Joomla tiers (référence `04-features.md` #26)

Voir §8.5 pour la séquence complète (déclenchement `onContentPrepare` →
construction d'iframe → chargement de la vue liste embarquée).

### 17.3 Service de fichier protégé (`{CBDownload}`/`{CBImageScale}`) (référence `04-features.md` #28-29)

1. Lien/`<img>` généré par le plugin de contenu, avec paramètre de service
   (`contentbuilderng_download_file=sha1(...)` ou
   `contentbuilderng_display[_detail]=1&contentbuilderng_[detail_]field=sha1(...)`).
2. Visiteur clique/charge l'image → requête GET avec ce paramètre.
3. `ContentbuilderngDownload`/`ContentbuilderngImageScale::onContentPrepare()`
   détecte le paramètre de service → `ContentbuilderngHelper::is_internal_path()`
   (confinement) → `PermissionService` (ACL `view`, sauf contexte liste) →
   sert le fichier (en-têtes durcis, envoi par blocs) ou l'image
   redimensionnée en cache → `$this->app->close()`.

### 17.4 Composant BreezingForms embarqué (`edit_by_type`) (référence `04-features.md` #17, #20)

1. Gabarit détail/édition CBNG contient un shortcode `{BreezingForms: <id ou nom>}`.
2. `Edit\HtmlView::renderBreezingFormsShortcodes()` (`:526-632`) sauvegarde
   `$_REQUEST`/`$_GET`/`$_POST`/`$GLOBALS`, exécute littéralement le
   composant `com_breezingformsng` en `include`, restaure l'état global
   dans un bloc `try/finally` (mécanisme fragile mais isolé).

### 17.5 Chart.js (rendu `{CBStats}`) (référence `04-features.md` #27, `10-dependencies.md §4.3`)

1. `{CBStats output=pie|bar|histogram|line|radar}` → plugin
   `contentbuilderng_cbstats` calcule le payload
   (`StatsService::getStatsPayload()`) → encode en `data-cbstats-*` sur un
   `<canvas>`.
2. `cbstats-{pie,bar,charts}.js` (Chart.js 4.5.1 vendorisé) lit l'attribut
   `data-cbstats-*` côté client et construit le graphique — aucun appel
   réseau supplémentaire (données déjà embarquées côté serveur).

### 17.6 Kunena (groupes automatiques) (référence `04-features.md` #34)

1. `ContentbuilderngSystem::onAfterDispatch()` détecte `com_kunena` par
   existence de répertoire (`is_dir()`, pas `ComponentHelper::isEnabled()`).
2. Si présent : purge de session Kunena (`#__kunena_sessions`) lors de la
   gestion des groupes automatiques (`is_auto_groups`).

---

## 18. Contradictions et zones d'incertitude non tranchées

Les mêmes points que la liste consolidée de `04-features.md` (section
« Contradictions et zones d'incertitude non tranchées ») s'appliquent ici
dès qu'ils affectent la séquence d'exécution d'un parcours. Points
supplémentaires propres à la vue « parcours de bout en bout » :

1. **Ordre exact entre `onBeforeSubmit` et la résolution des champs
   spéciaux d'inscription** dans `EditModel::store()` (§11.1, §16.1) : les
   brouillons sources décrivent les deux comme proches dans la séquence
   mais n'ont pas confirmé un ordre strict ligne à ligne sur l'intégralité
   du fichier (2870+ lignes, non lu intégralement).
2. **Déclenchement exact de l'activation manuelle d'un compte** (§16.3) :
   **Hypothèse** sur le fait que ce lien provient toujours d'une
   notification e-mail à l'administrateur — le gabarit d'e-mail
   correspondant n'a pas été tracé dans les brouillons sources.
3. **Absence de tâche planifiée (`com_scheduler`) pour la synchronisation
   articles↔enregistrements** (§7.5) : la séquence documentée est purement
   « à la demande » (déclenchée par le trafic réel du site via
   `isSyncMutationRequest()`) — aucune preuve d'un mécanisme cron alternatif
   trouvée, mais son absence totale n'est pas formellement prouvée
   (recherche non exhaustive au-delà des fichiers lus par les brouillons
   sources).
