# 06 — Contrats API (routes, AJAX/API, événements, import/export)

> Rétro-analyse (reverse engineering) de `com_contentbuilderng` — Joomla 6 /
> PHP 8.3+ / MySQL-MariaDB. Documentation seule : aucun fichier de code n'a
> été modifié pour produire ce document.
>
> Méthode de qualification des affirmations :
> - **Fait observé** : lu directement dans le code, avec référence `fichier:ligne`.
> - **Comportement déduit** : logique assemblée à partir de plusieurs faits observés.
> - **Hypothèse** : interprétation plausible non totalement vérifiée dans le code lu.
> - **Zone inconnue** : point non tranché, incohérence potentielle, ou dette technique
>   à signaler.
>
> Ce document est le catalogue **externe** du composant : tout point d'entrée
> qu'un intégrateur, un développeur de plugin tiers ou un script d'échange de
> données peut appeler ou implémenter — routes Joomla standard, endpoints
> AJAX/API, événements internes exposés comme points d'extension, formats de
> fichiers échangés. Il ne redétaille pas l'ACL/CSRF déjà couvert par
> `07-security.md` (référencé section par section) ni le schéma de données
> déjà couvert par `03-data-model.md` ni les réglages système déjà couverts
> par `08-configuration.md`.
>
> Sources croisées : lecture directe de `site/src/Controller/ApiController.php`,
> `site/src/Controller/EditController.php`, `site/src/Controller/ListController.php`,
> `admin/src/Controller/{StorageController,FormController,AboutController,
> ConfigtransferController,DatatableController}.php`,
> `admin/src/Service/{ApiPermissionRequirementService,ConfigExportService}.php`,
> `admin/src/Dto/CsvImportOptions.php` ; complétée par les brouillons
> d'analyse fonctionnelle `draft-site-features-flows.md`,
> `draft-admin-features-flows.md`, `draft-plugins-features-flows.md`
> (scratchpad, hors dépôt) pour le contexte des parcours utilisateurs, chaque
> affirmation reprise d'un brouillon étant revérifiée ou requalifiée ici
> lorsque la lecture directe du code l'a permis.

## Sommaire

1. [Vue d'ensemble — mécanismes communs](#1-vue-densemble--mécanismes-communs)
2. [Routes admin (`index.php?option=com_contentbuilderng&task=<controller>.<task>`)](#2-routes-admin)
3. [Routes site](#3-routes-site)
4. [Endpoints AJAX/API détaillés](#4-endpoints-ajaxapi-détaillés)
   - 4.1 [`ApiController` — `task=api.display` (API REST-like)](#41-apicontroller--taskapidisplay-api-rest-like)
   - 4.2 [Bascule état/publication en liste (`edit.state` / `edit.publish`)](#42-bascule-étatpublication-en-liste)
   - 4.3 [Édition inline des champs de Storage (admin)](#43-édition-inline-des-champs-de-storage-admin)
   - 4.4 [Champs système BreezingForms (admin)](#44-champs-système-breezingforms-admin)
   - 4.5 [Réparations en un clic — Audit (`about.repair*`)](#45-réparations-en-un-clic--audit-aboutrepair)
5. [Événements/hooks Joomla exposés comme points d'extension](#5-événementshooks-joomla-exposés-comme-points-dextension)
6. [Formats d'import/export](#6-formats-dimportexport)
7. [Zone d'incertitude tranchée — `action=cbstats`](#7-zone-dincertitude-tranchée--actioncbstats)
8. [Synthèse et zones d'incertitude restantes](#8-synthèse-et-zones-dincertitude-restantes)

---

## 1. Vue d'ensemble — mécanismes communs

**Fait observé** : le composant n'utilise **aucun** endpoint `com_ajax`
(`index.php?option=com_ajax&...`) — recherche exhaustive du littéral
`com_ajax` sur tout le dépôt (`*.php`, `*.js`), aucune occurrence. Toute
interaction asynchrone passe par des tâches (`task=`) de contrôleurs
`com_contentbuilderng` standard, distinguées d'une soumission de formulaire
classique par un paramètre `cb_ajax=1` détecté côté serveur — un patron
répété à l'identique dans plusieurs contrôleurs indépendants (front et
admin) :

```php
private function isAjaxCall(): bool
{
    return (bool) $this->input->getInt('cb_ajax', 0);
}

private function respondAjax(bool $success, string $message = ''): void
{
    echo new JsonResponse(['ok' => $success], $message, !$success);
    $this->app->close(); // ou $this->siteApp->close() / $this->closeApp()
}
```
**Fait observé** — occurrences de ce patron : `site/src/Controller/EditController.php:653-662`,
`admin/src/Controller/AboutController.php:145-154`,
`admin/src/Controller/StorageController.php:1303-1318` (avec une variante
`respondAjaxData()` qui fusionne des données supplémentaires dans `data`).

**Format de réponse commun à ce premier patron** (`Joomla\CMS\Response\JsonResponse`
construit avec `(mixed $data, ?string $message, bool $error)`) :
```json
{"success": true, "message": "...", "messages": [], "data": {"ok": true}}
```
En échec : `"success": false`, `"messages": ["<message>"]`, `"data": {"ok": false}`
— **toujours en HTTP 200** pour ce patron (pas de code d'erreur HTTP dédié),
le succès/échec se lit dans le corps JSON.

**Second patron, indépendant** : `site/src/Controller/ApiController.php`
(§4.1) — enveloppe différente (`{"success","messages","data"}`, sans le
singulier `"message"`), codes HTTP d'erreur réels (400/401/403/404/405/500),
CSRF à deux voies (paramètre de formulaire **ou** en-tête `X-CSRF-Token`).
Les deux patrons **coexistent sans jamais se recouper** au niveau du code —
un même contrôleur n'implémente jamais les deux.

**Fait observé — CSRF** (détail déjà consolidé `07-security.md` §3, rappelé
ici par contrat) : tâches de mutation admin → `checkToken()` natif Joomla
(jeton en paramètre de formulaire, défaut `method='request'` sauf appel
explicite `checkToken('post')`) ; tâches de mutation site "formulaire
classique" → `checkToken('post', false)`/`checkToken('request', false)`
(retour booléen, levée manuelle `RuntimeException(..., 403)`) ;
`ApiController` → `assertStateChangingRequestToken()` (§4.1, seul endpoint
acceptant l'en-tête `X-CSRF-Token`).

**Fait observé — ACL** (détail déjà consolidé `07-security.md` §1) : routes
admin → ACL Joomla native `$user->authorise('core.<action>', 'com_contentbuilderng')` ;
routes site (hors `ApiController`) → moteur applicatif propre
`PermissionService::checkPermissions()`/`authorizeFe()` (permissions
"actions" par vue : `view`, `edit`, `listaccess`, `add`, `delete`, `rating`,
`stats`, `state`, `publish`) ; `ApiController` → les deux à la fois (moteur
applicatif via `PermissionService` **et**, en overlay, `ApiFieldPermissionService`
qui filtre chaque **champ** exposé selon `elements.api_allowed`).

---

## 2. Routes admin

Toutes les routes admin suivent le gabarit
`index.php?option=com_contentbuilderng&task=<controller>.<task>[&id=…]`
(back-office, session administrateur requise). Sauf mention contraire dans
la colonne Note, chaque tâche suit le patron ACL+CSRF standard Joomla déjà
détaillé `07-security.md` §1.2/§3 (ACL `core.manage`/`core.create`/`core.edit`/
`core.delete` selon l'action, `checkToken()` en tête de méthode) — non
redétaillé ligne à ligne ici, seules les **déviations** (endpoints AJAX,
absence de contrôle, comportement notable) sont signalées.

### 2.1 `StorageController` / `StoragesController` / `StoragefieldController` / `DatatableController`

| Tâche | Méthode HTTP | Fait observé (fichier:ligne) | Note |
|---|---|---|---|
| `storages.display` | GET | `admin/src/Controller/StoragesController.php:87` | Liste des storages |
| `storages.copy` | POST | `StoragesController.php:96` | **Zone inconnue** : comportement exact vis-à-vis de la table physique non tracé en détail (cf. brouillon admin §2) |
| `storage.edit`/`storage.cancel` | GET | `StorageController.php:131` / `:108` | Checkout Joomla standard |
| `storage.save`/`storage.apply` | POST (multipart si import CSV/XLSX) | `StorageController.php:157`, `:554` | Voir §6.2 (import CSV/XLSX) |
| `storage.previewHeaders` | **AJAX** POST | `StorageController.php:562-580` | Réponse `JsonResponse` brute (liste d'en-têtes), pas l'enveloppe `{ok}` — voir §4.3 |
| `storage.checkExistingTableColumns` | **AJAX** GET | `StorageController.php:588-622` | ACL `core.manage` vérifiée explicitement (`authorise()`), réponse `{known,missing}` |
| `storage.deleteRecord` | POST | `StorageController.php:633-725` | ACL `core.edit` ; refusé sur `bytable=2` |
| `storage.addfield` | POST | `StorageController.php:728-761` | Formulaire classique (non-AJAX), DDL `ALTER TABLE ADD` |
| `storage.addindex`/`storage.deleteindex` | POST | `StorageController.php:764`/`:794` | `ALTER TABLE ADD/DROP INDEX` SQL brut |
| `storage.delete` | POST | `StorageController.php:862` | `DROP TABLE` du storage interne — échec DDL remonté comme erreur, pas de transaction couvrant toute la méthode |
| `storage.add` | POST | `StorageController.php:939` | |
| `storage.orderup`/`orderdown`/`saveorder` | POST | `:945`/`:950`/`:955` | |
| `storage.listDelete` | POST | `:1030` | Suppression en masse depuis la liste |
| `storage.publish`/`unpublish`/`publishItem`/`unpublishItem` | POST | `:1080`–`:1095` | |
| `storage.ajax_addfield` | **AJAX** POST | `:1326-1350` | Voir §4.3 |
| `storage.ajax_update_field_type` | **AJAX** POST | `:1359-1438` | Voir §4.3 |
| `storage.ajax_update_field_required` | **AJAX** POST | `:1450-1526` | Voir §4.3 |
| `storage.ajax_update_field_title` | **AJAX** POST | `:1532-1559` | Voir §4.3 |
| `storagefield.add` | POST | `admin/src/Controller/StoragefieldController.php:65-118` | Soumission classique, **pas** d'`authorise()` séparé observé (porté par l'écran Storage parent) |
| `datatable.create` | POST | `admin/src/Controller/DatatableController.php:60-104` | `ComponentAccessTrait::execute()` exige `core.manage` ; `create()` vérifie le jeton CSRF, sans contrôle `core.edit` propre à cette opération DDL. |
| `datatable.sync` | POST | `DatatableController.php:106-148` | Même contrôle `core.manage` et jeton CSRF ; pas de contrôle `core.edit` propre à la synchronisation. |

### 2.2 `FormController` / `FormsController`

| Tâche | Méthode | Fait observé | Note |
|---|---|---|---|
| `form.edit`/`form.add`/`form.cancel` | GET | `admin/src/Controller/FormController.php:110/135/149` | Checkout Joomla standard |
| `form.save` | POST | `FormController.php:173-315` | Onglet Article inclus (§2.3) ; validation `default_category` **côté client uniquement** (**Zone inconnue** : pas de revalidation serveur confirmée) |
| `form.listorderup`/`listorderdown`/`saveorder` | POST | `:316`/`:333`/`:350` | Réordonnancement des éléments |
| `form.element_flag`/`element_publish` | POST | `:422`/`:434` | Toggles rapides (liste des éléments) |
| `form.save_labels` | POST | `:442-467` | Édition en masse des libellés |
| `form.formpublish`/`formunpublish` | POST | `:468`/`:473` | |
| `form.repairThemePlugin`/`repairEditableTemplate`/`repairDetailsTemplate`/`repairTemplates`/`repairEditableFieldItem` | POST | `:478`–`:694` | Réparations ciblées par vue (à distinguer de `about.repair*`, §4.5, qui sont globales) |
| `form.debug_on`/`debug_off` | POST | `:695`/`:700` | Active/désactive le mode debug **par vue** |
| `form.form_flag` | POST | `:705` | |
| `form.add_bf_system_field`/`remove_bf_system_field` | POST | `:849`/`:1121` | Variante formulaire classique |
| `form.ajax_add_bf_system_field` | **AJAX** POST | `:968-1064` | Voir §4.4 |
| `form.ajax_remove_bf_system_field` | **AJAX** POST | `:1086-1119` | Voir §4.4 |
| `forms.delete` | POST | `admin/src/Controller/FormsController.php:139` | |
| `forms.copy` | POST | `:176-220` | Duplication d'une vue |
| `forms.debug_on`/`debug_off` | POST | `:220`/`:225` | Mode debug en masse |

### 2.3 `ElementoptionsController`

| Tâche | Méthode | Fait observé | Note |
|---|---|---|---|
| `elementoptions.display` | GET | `admin/src/Controller/ElementoptionsController.php:78-85` | Modale `tmpl=component`, force `view=elementoptions` |
| `elementoptions.save` | POST | `:87-121` | Persiste l'élément puis `resyncLockedTemplatesAfterSave()` (régénère un template verrouillé, échec dégradé en avertissement) |

### 2.4 `StoragewizardController` — assistant guidé multi-étapes

**Fait observé** : aucun usage de `isAjaxCall()`/`JsonResponse` dans ce
contrôleur — c'est une machine à états **entièrement en redirections HTTP**
classiques, l'état courant étant conservé en session
(même patron que le Repair Workflow, §4.5), **pas** un contrat AJAX/JSON.

| Tâche | Méthode | Fait observé |
|---|---|---|
| `storagewizard.start`/`begin`/`back`/`backSubstep` | POST/GET | `admin/src/Controller/StoragewizardController.php:96/121/134/333` |
| `storagewizard.chooseStorageMode`/`selectExistingStorage`/`chooseCreationMode`/`chooseInitializationMode` | POST | `:151/180/220/254` |
| `storagewizard.saveStorageDetails`/`saveStorage` | POST | `:297/362` |
| `storagewizard.confirmFields`/`createForm`/`confirmForm` | POST | `:551/591/636` |
| `storagewizard.createMenu`/`skipMenu`/`finish` | POST | `:660/705/721` |

### 2.5 `TitlesetController` — voir aussi §6.4 (formats de fichiers)

| Tâche | Méthode | Fait observé | Note |
|---|---|---|---|
| `titleset.save`/`apply` | POST | `admin/src/Controller/TitlesetController.php:27-35` | `core.manage` vérifié explicitement à chaque tâche |
| `titleset.deleteSelected` | POST | `:57-76` | Uniquement `source=custom` |
| `titleset.exportSelected` | GET/POST | `:78-132` | Téléchargement direct (fichier unique) ou ZIP en mémoire |
| `titleset.importFiles` | **multipart** POST | `:134-186` | Voir §6.4 |
| `titleset.validateFile` | POST | `:238-252` | Valide sans sauvegarder |
| `titleset.deleteFile`/`cancel` | POST/GET | `:254`/`:264` | |

### 2.6 `AboutController` / `ConfigtransferController` — voir aussi §4.5 et §6.1

| Tâche | Méthode | Fait observé | Note |
|---|---|---|---|
| `configtransfer.export`/`import` | GET | `admin/src/Controller/ConfigtransferController.php:46`/`:51` | Routage seul, redirige vers `view=configtransfer&mode=…` |
| `configtransfer.back` | GET | `:41` | |
| `about.runAudit` | POST | `admin/src/Controller/AboutController.php:759-799` | Formulaire classique uniquement (pas d'AJAX) |
| `about.repairAuditIssue` | POST | `:160-206` | **AJAX** — voir §4.5 |
| `about.repair*` (12 tâches dédiées) | POST | `:208-758` | **AJAX** — voir §4.5 |
| `about.deleteStaleInstallerTemp` | POST | `:688-758` | **AJAX** — voir §4.5 |
| `about.showLog` | POST | `:805-826` | Formulaire classique |
| `about.exportConfiguration` | POST | `:832-908` | Voir §6.1 — **pas** `configtransfer.export` |
| `about.importConfiguration` | **multipart** POST | `:914-1007` | Voir §6.1 |

### 2.7 `UsersController`

| Tâche | Méthode | Fait observé |
|---|---|---|
| `users.verified_view`/`not_verified_view` | GET | `admin/src/Controller/UsersController.php:96`/`:119` |
| `users.verified_new`/`not_verified_new` | GET | `:142`/`:165` |
| `users.verified_edit`/`not_verified_edit`/`edit` | GET | `:188`/`:211`/`:234` |
| `users.apply`/`save`/`cancel` | POST/GET | `:244`/`:295`/`:322` |
| `users.publish`/`unpublish` | POST | `:249`/`:272` |
| `users.display` | GET | `:329` |

### 2.8 `DisplayController` (admin)

| Tâche | Méthode | Fait observé |
|---|---|---|
| (défaut, dispatch de vue) | GET | `admin/src/Controller/DisplayController.php:43` |

---

## 3. Routes site

Gabarit `index.php?option=com_contentbuilderng&task=<controller>.<task>[&id=…][&record_id=…]`,
accessible sans authentification sauf restriction ACL applicative
(`PermissionService`, `07-security.md` §1.3).

| Contrôleur | Tâche(s) | Méthode | Fait observé | Note |
|---|---|---|---|---|
| `DisplayController` | (dispatch, résolution `view=`) | GET | `site/src/Controller/DisplayController.php:21` | |
| `ListController` | `list.display` | GET | `:362-…` (`ListController.php`) | `listaccess` via `PermissionService`, sauf preview signée |
| `ListController` | `list.delete`/`list.state`/`list.publish` | POST | `:88`/`:183`/`:263` | Actions groupées, formulaire classique (`Joomla.submitform`), **pas** AJAX ; `armPermissionsForSelection()` recalcule les permissions pour la sélection courante |
| `EditController` | `edit.display` | GET | `:665-…` | Formulaire de soumission/édition |
| `EditController` | `edit.save`/`edit.apply` | POST | `:222`/`:304` | Délègue à `EditModel::store()` (voir `03-data-model.md`) ; dispatch `onBeforeSubmit`/`onAfterSubmit` (§5) |
| `EditController` | `edit.delete` | POST | `:309-…` | |
| `EditController` | `edit.state` | **AJAX** POST | `:387-429` | Voir §4.2 |
| `EditController` | `edit.publish` | **AJAX** GET | `:431-495` | Voir §4.2 |
| `EditController` | `edit.language` | POST | `:497-…` | |
| `DetailsController` | `details.display` | GET | `:180-…` | |
| `ExportController` | `export.display` | GET | `site/src/Controller/ExportController.php:22-35` | Voir §6.3 ; **pas** de gate `listaccess` sur accès direct (déjà signalé `07-security.md` §1.3) |
| `ApiController` | `api.display` | GET/POST/PUT/PATCH | `site/src/Controller/ApiController.php:94` | Voir §4.1 — seul contrôleur à router explicitement plusieurs méthodes HTTP sur une seule tâche |
| `VerifyController` | `verify.display` | GET/POST | `site/src/Controller/VerifyController.php:21-…` | Orchestre `onSetup`/`onForward`/`onVerify` (§5) |
| `PublicformsController` | `publicforms.display` | GET | `site/src/Controller/PublicformsController.php:20-…` | Tri figé `ORDER BY ordering` — **Zone inconnue** (relayée du brouillon site §7) : indépendant de `filter_order` calculé, intention vs. dette non tranchée |
| `CblisthelpController` | (défaut) | GET | `site/src/Controller/CblisthelpController.php` | Aide contextuelle statique `{CBList}` |
| `CbstatshelpController` | (défaut) | GET | `site/src/Controller/CbstatshelpController.php` | Aide contextuelle statique `{CBStats}` |

**Zone inconnue relayée** (brouillon site §1.1, non re-vérifiée ligne à ligne
ici) : il existerait un second fichier `Dispatcher` non branché dans
`site/src/Dispatcher/` — **code mort probable**, sans impact sur les
contrats de ce document puisque non atteignable en requête réelle ; à
confirmer avec Gilles, hors périmètre de nettoyage de cette mission
documentaire.

---

## 4. Endpoints AJAX/API détaillés

### 4.1 `ApiController` — `task=api.display` (API REST-like)

**URL de base** : `index.php?option=com_contentbuilderng&task=api.display&id=<formId>&format=json[&record_id=…][&action=…]`
(`format=json` recommandé mais non contrôlé par le contrôleur lui-même —
Joomla applique son mécanisme de format de vue standard en amont).

**Authentification** : aucune session requise par défaut (accessible à un
visiteur anonyme) — l'accès effectif est entièrement gouverné par les
permissions applicatives ci-dessous (`PermissionService`), potentiellement
restreintes à des groupes authentifiés selon la configuration de la vue.

**En-têtes de réponse constants** (`ApiController.php:1126-1133`) :
`Content-Type: application/json; charset=utf-8`, `Cache-Control: private,
no-store, max-age=0`, `Pragma: no-cache`, `X-Content-Type-Options: nosniff`,
`Vary: Authorization, Cookie`.

**Enveloppe de réponse** : succès `{"success":true,"messages":[],"data":<payload>}` ;
erreur `{"success":false,"messages":["<message>"],"data":null}` avec code
HTTP réel (`http_response_code($code)` si `code>=400`). Message d'erreur
**générique** par tranche de code (`getPublicApiErrorMessage()`,
`:1135-1142` : 400/405 → `COM_CONTENTBUILDERNG_API_ERROR_INVALID_REQUEST`,
401/403 → `_API_ERROR_ACCESS_DENIED`, autre → `_API_ERROR_RESOURCE_UNAVAILABLE`)
**sauf** si `4xx` **et** `StatsService::isFormDebugEnabled($formId)` (mode
debug activé sur la vue) : le message d'exception réel est alors exposé
(`:1110-1115`).

**Résolution d'identifiant d'enregistrement** (`normalizeRequestedRecordId()`,
`:1024-1069`) : accepte indifféremment l'id "métier" (`records.record_id`,
id dans la table source) ou l'id technique de suivi CBNG (`records.id`) —
retombe sur la valeur fournie telle quelle si aucune correspondance n'est
trouvée.

**Prévisualisation admin signée** : `isValidAdminPreviewRequest()`
(`:1147-…`) accepte les mêmes paramètres HMAC que `list`/`details`/`edit`/`export`
(`cb_preview`, `cb_preview_until`, `cb_preview_sig`, `cb_preview_actor_id`,
`cb_preview_actor_name`, `cb_preview_user_id` — voir `07-security.md` §1.3.2).

#### Permissions requises par méthode/action — **Fait observé, lu directement dans le code**

`admin/src/Service/ApiPermissionRequirementService.php::getRequiredPermissions(string $method, string $action, int $recordId): array` :

| Condition | Permissions requises (`PermissionService::authorize()`/`authorizeFe()`) |
|---|---|
| `action ∈ {stats, cbstats}` | `['stats']` uniquement — **pas** `api` |
| `action = 'get-unique-values'` | `['api', 'listaccess']` |
| `action = 'rating'` | `['api', 'rating']` (+ re-vérification interne `can('rating')` dans `ratePayload()`, `:510`) |
| autre `action` non vide | `['api']` |
| `GET`, sans `action`, `record_id>0` | `['api', 'view']` |
| `GET`, sans `action`, `record_id=0` (liste) | `['api', 'view', 'listaccess']` |
| `PUT`/`PATCH`/`POST`, sans `action` | `['api', 'edit']` |
| autre méthode | `['api']` (→ tombera de toute façon en 405) |

**Correction vs. brouillon site §9** : le brouillon indiquait des permissions
« `api`, `view`, `listaccess` » pour la liste GET (correct) mais omettait que
`get-unique-values` exige **aussi** `listaccess` (pas seulement `api`), et
que `stats`/`cbstats` exigent **uniquement** `stats` (pas `api`) — précision
apportée ici par lecture directe de `ApiPermissionRequirementService.php`.

#### Sous-endpoints (paramètre `action=`)

| Méthode | `action=` | Paramètres GET/POST | Corps requête | Réponse (`data`) | Effets de bord | Erreurs possibles |
|---|---|---|---|---|---|---|
| GET | *(aucun)*, `record_id` absent (0) | `id` (int, requis), `list[limit\|start]` (`limit` ≤ 100, défaut 20 ; `start` ≥ 0) | — | `{items:[{record_id:int, values:{<nom_champ>:valeur}}], pagination:{total,limit,start}}` — champs filtrés à `elements.api_allowed=1` | Aucun (lecture) | 404 `FORM_NOT_FOUND` ; 403 permission |
| GET | *(aucun)*, `record_id>0` | `id`, `record_id`, `verbose` (bool, 0/1) | — | `{record_id, form_id, fields:{<nom>:valeur}}` (ou `{<nom>:{reference_id,label,value}}` si `verbose=1`) | Aucun | 404 `RECORD_NOT_FOUND` ; 403 |
| PUT/PATCH/POST | *(aucun)*, `record_id` requis (>0) | `id`, `record_id` | JSON `{"fields":{"<nom_ou_ref>":<valeur>,...}}` (Content-Type `application/json`) **ou** POST `fields[...]` (form-encoded) | `{message, record_id, detail:{...}}` (relit le détail via `getDetailPayload()`) | **Écriture** — délègue à `EditModel::store()` (pipeline complet : validation, upload, e-mails, articles — voir `03-data-model.md`) ; champs non `api_allowed` **silencieusement ignorés** | 400 `RECORD_ID_REQUIRED`/`FIELDS_REQUIRED`/`ERROR_INVALID_REQUEST` (JSON malformé) ; 403 `FIELD_NOT_ALLOWED` (aucun champ autorisé transmis) ; 403 `PERMISSIONS_EDIT_NOT_ALLOWED` ; 403 `JINVALID_TOKEN` (CSRF) ; 500 `ERROR` (échec `store()`) |
| GET | `get-unique-values` | `id`, `field_reference_id`, `where_field` (optionnel), `where` (optionnel) | — | `{code:0, field_reference_id, msg:[<valeurs distinctes>]}` (max 100) | Aucun | 404 `FORM_ERROR` ; 403 `FIELD_NOT_ALLOWED` si `field_reference_id` **ou** `where_field` n'est pas `api_allowed` |
| POST | `rating` | `id`, `record_id`, `rate` (int, plage selon `rating_slots`) | — (form POST) | `{code:0\|1, msg:"..."}` | **Écriture transactionnelle** — `INSERT #__contentbuilderng_rating_cache`, `UPDATE records.rating_sum/count/lastip`, upsert `#__content_rating` si article Joomla lié publié | 403 `RATING_NOT_ALLOWED` ; 403 `JINVALID_TOKEN` si méthode ≠ POST ou CSRF invalide ; 404 `FORM_ERROR` ; `code:1` (pas une erreur HTTP) si déjà noté |
| GET | `stats` | `id`, `field`, `filter[field]`, `filter[value]` | — | Agrégat brut (`StatsService::getStatsPayload()`) | Aucun | 403 permission `stats` |
| GET | `cbstats` | `id`, `output` (json\|table\|pie\|bar\|histogram\|line\|radar\|total\|remaining\|percentage\|progress\|distinct\|sum\|min\|max\|avg\|view_name), `field`, `target` (requis pour `remaining`/`progress`, motif numérique strict), `value` (requis pour `percentage`), `filter[field]`/`filter[value]`, `sort` (`none`\|`title`\|`value`), `dir` (`asc`\|`desc`), `add`, `titles`, `titleset`, `groups`, `groupset`, `hide`, `limit` (≤100) | — | Selon `output` : total scalaire, `{total,items:[...]}` (sorties listées), ou objet via `StatsService::resolveCbstatsOutput()`. Réponse **brute** (`sendRawJson()`, pas l'enveloppe `{success,messages,data}`) pour `output ∈ {json,table,pie,bar,histogram,line,radar}` ; enveloppée pour les sorties scalaires | Aucun (lecture, résolution de fichiers `.ini` `titleset`/`groupset` sur disque, voir §7) | Famille complète `COM_CONTENTBUILDERNG_API_CBSTATS_*` (400, validation fine par paramètre) |

**Filtrage par champ (double filtrage)** : `ApiFieldPermissionService::getAllowedReferenceMap()`
(`admin/src/Service/ApiFieldPermissionService.php`) restreint systématiquement
les champs exposés en lecture **et** en écriture à ceux marqués
`elements.api_allowed=1`, **indépendamment** des droits d'affichage/édition
front classiques (`view`/`edit`) — un champ visible en front peut être
invisible via l'API si `api_allowed=0`, et réciproquement un champ non
affiché en front peut être exposé via l'API si `api_allowed=1`.

**Sparse fieldsets** : `SparseFieldsetService::filter()`
(`site/src/Controller/ApiController.php:453-462`, `site/src/Service/SparseFieldsetService.php`)
applique, pour toute requête `GET` (y compris les sous-actions), un
paramètre `fields[...]` de projection de champs (à la manière de
`fields[<ressource>]=<liste>` JSON:API) sur la réponse déjà filtrée par
`api_allowed`.

**CSRF** (`assertStateChangingRequestToken()`, `:950-963`) — mécanisme
**propre à `ApiController`**, distinct de tous les autres contrôleurs :
accepte le jeton soit en paramètre de formulaire (session standard), soit
dans l'en-tête `X-CSRF-Token` (comparé en temps constant via `hash_equals()`
au jeton de session `Session::getFormToken()`, puis réinjecté en paramètre
POST pour satisfaire la vérification Joomla native), avant de retomber sur
`Session::checkToken('post')`/`checkToken('get')`. Appelé pour `rating`
(`:518`) et pour les mutations PUT/PATCH/POST du CRUD (`:173`).

**Exemple de requête PATCH** (mise à jour d'un enregistrement, champ nommé) :
```http
PATCH /index.php?option=com_contentbuilderng&task=api.display&id=15&record_id=42&format=json HTTP/1.1
Content-Type: application/json
X-CSRF-Token: <jeton de session>

{"fields": {"Nom": "Dupont", "Email": "dupont@example.org"}}
```
Réponse (200) :
```json
{"success": true, "messages": [], "data": {"message": "Saved", "record_id": 42, "detail": {"record_id": 42, "form_id": 15, "fields": {"Nom": "Dupont", "Email": "dupont@example.org"}}}}
```

**JavaScript interne consommant cet endpoint** — **Fait observé** : aucun
fichier `media/js/*` n'appelle `task=api.display` avec `action=stats`/`cbstats`/
`get-unique-values` ou le CRUD PUT/PATCH — ces usages sont donc, à ce stade,
des **points d'intégration externes** (JS tiers, application mobile, autre
site). Seule exception : `action=rating`, appelé depuis
`media/js/contentbuilderng.js:57-63` (`cbRate()`, fallback) et
`media/js/list-init.js:42-104` (`window.cbRate`, version liste).

---

### 4.2 Bascule état/publication en liste

Sous-flux de l'écran liste (site), déjà présenté `03-data-model.md`/brouillon
site §3.1 — reformulé ici en contrat.

| Élément | `edit.state` | `edit.publish` |
|---|---|---|
| URL | `index.php?option=com_contentbuilderng&task=edit.state` | `index.php?option=com_contentbuilderng&task=edit.publish` |
| Méthode | POST (`FormData`, `X-Requested-With: XMLHttpRequest`) | GET (jeton **dans la query string**, commentaire explicite `EditController.php:434-436` : "per-row publish toggle is a GET link") |
| Détection AJAX | `Input::getInt('cb_ajax', 0)` (`isAjaxCall()`, `:653-656`) | idem |
| Paramètres | `id`, `record_id`/`cid[]`, `list_state=<id d'état>`, `boxchecked=1`, `cb_ajax=1`, jeton CSRF (`<Session::getFormToken()>=1`) | `id` (ou `storage_id` en mode storage direct), `record_id`, `list_publish` (0/1 implicite), `cb_ajax=1`, jeton en query |
| Permission | `assertConstrainedAction('state')` puis `checkPermissionForAjax('state', ...)` (`PermissionService`) ; jamais contournable par preview admin (`applyPreviewContextForAction()` documenté comme ne l'autorisant pas, commentaire `:394-396`) | `assertConstrainedAction('publish')`, `checkPermissionForAjax('publish', ...)` ; **en mode storage direct**, exige **en plus** l'ACL Joomla `core.edit.state` sur `com_contentbuilderng` (`:452-461`) — les deux gates sont cumulatives, ni l'une ni l'autre seule ne suffit |
| CSRF | `checkToken('post', false)` | `checkToken('request', false)` — jeton accepté en query string |
| Réponse succès (`cb_ajax=1`) | `{"success":true,"message":"<N états changés>","messages":[],"data":{"ok":true}}` | `{"success":true,"message":"Published"/"Unpublished","messages":[],"data":{"ok":true}}` |
| Réponse échec | Même enveloppe, `success:false`, `data.ok:false`, HTTP **200** (pas de code dédié) — sauf cas non-AJAX qui redirige avec message flash | idem |
| Sans `cb_ajax=1` | Redirection HTTP classique vers `list.display` avec message flash Joomla | idem |
| Effet de bord | `EditModel::change_list_states()` — upsert `#__contentbuilderng_list_records`, dispatch `onBeforeAction`/`onAfterAction` (§5) | `EditModel::change_list_publish()` — écrit `records.published` |

**Comportement déduit** : ce sous-flux est distinct de l'API REST-like
`ApiController` — réutilise les contrôleurs `Edit`/`List` **standard** avec
un simple indicateur `cb_ajax=1` côté serveur pour choisir entre réponse
JSON et redirection HTTP, plutôt qu'un contrôleur d'API dédié.

---

### 4.3 Édition inline des champs de Storage (admin)

Grille de champs de l'onglet « Storage » (`admin/tmpl/storage/default.php`) —
toutes ces tâches partagent le patron `respondAjax()`/`respondAjaxData()`
(§1) et exigent `checkToken()` + `assertStorageEditAccess()`
(`authorise('core.edit', 'com_contentbuilderng')`, `StorageController.php:78-83`).

| Tâche | Méthode | Paramètres | Effet de bord (DDL) | Réponse `data` | Erreurs |
|---|---|---|---|---|---|
| `storage.ajax_addfield` | POST | `jform[id]` ou `id`, champs du formulaire d'ajout de champ (nom, titre, type, taille, requis) | `ALTER TABLE ... ADD` puis `INSERT storage_fields` (rollback applicatif `DROP COLUMN` si l'`INSERT` échoue après un `ALTER` réussi) | `{ok:bool}` | `COM_CONTENTBUILDERNG_FIELD_ADD_FAILED` |
| `storage.ajax_update_field_type` | POST | `id` (storage), `field_id`, `sql_type`, `field_size` | `ALTER TABLE ... MODIFY` — **réservé** aux storages internes (`bytable=0`) **sans aucune ligne existante** si le type change réellement (`COUNT(*)` vérifié avant l'`ALTER`) ; refusé pour un champ système | `{ok:true, sql_type_definition:"<définition SQL>"}` | `STORAGE_SQL_TYPE_CREATE_ONLY_HINT` (table non vide ou `bytable>0`), `STORAGE_SYSTEM_FIELD_SELECT_REQUIRED` |
| `storage.ajax_update_field_required` | POST | `id`, `field_id`, `required` (bool) | `ALTER TABLE ... MODIFY` — sûr même sur table peuplée (comble d'abord les `NULL` via `StorageColumnTypeHelper::enforceRequired()`), métadonnées persistées **après** succès du DDL | `{ok:true}` | `STORAGE_REQUIRED_FOREIGN_TABLE_HINT` (`bytable>0`), `STORAGE_SYSTEM_FIELD_SELECT_REQUIRED` |
| `storage.ajax_update_field_title` | POST | `id`, `field_id`, `title` | `UPDATE storage_fields.title` (pas de colonne physique impactée) | `{ok:true}` | Aucune erreur métier dédiée observée (juste `JERROR_NO_ITEMS_SELECTED` si ids absents) |
| `storage.previewHeaders` | POST (`multipart/form-data`) | `csv_file` (upload), `csv_delimiter`, `csv_repair_encoding` | Aucun (lecture seule du fichier temporaire) | Réponse `JsonResponse` **brute** — tableau des en-têtes détectés, **pas** l'enveloppe `{ok}` (`StorageController.php:578`) | Réponse vide (`[]`) si aucun fichier |
| `storage.checkExistingTableColumns` | GET | `table` (nom logique) | Aucun (lecture `information_schema` via `getTableColumns()`) | `{known:bool, missing:[<colonnes>]}` (enveloppe brute) | Aucune (dégrade silencieusement en `{known:false,missing:[]}` sur exception) |

---

### 4.4 Champs système BreezingForms (admin)

| Tâche | Méthode | Paramètres | Effet de bord | Réponse `data` | Erreurs |
|---|---|---|---|---|---|
| `form.ajax_add_bf_system_field` | **AJAX** POST | `id` (form), `reference_id` (négatif, identifiant de champ système BreezingForms) | `INSERT #__contentbuilderng_elements` (type `text`, `editable=1`, `published=0`, `api_allowed=0`) | `{ok:true, element_id:<int>}` | `BF_SYSTEM_FIELD_SELECT_REQUIRED`, `BF_SYSTEM_FIELD_BF_ONLY` (vue non BreezingForms), `BF_SYSTEM_FIELD_ALREADY_ADDED` |
| `form.ajax_remove_bf_system_field` | **AJAX** POST | `id`, `element_id` | `DELETE #__contentbuilderng_elements` (condition `reference_id < 0`) + `reorder()` | `{ok:true}` | `JERROR_NO_ITEMS_SELECTED` |

Les deux tâches exigent `checkToken()` seul (pas d'`authorise()` séparé
observé dans ces deux méthodes — porté par l'écran Formulaire parent, cf.
`07-security.md` §1.2 pour l'ACL consolidée du contrôleur `Form`).

---

### 4.5 Réparations en un clic — Audit (`about.repair*`)

**Correction vs. brouillon admin §14** : le brouillon indiquait que seule
`about.repairAuditIssue` supportait explicitement l'AJAX. **Fait observé**
(lecture directe, recherche de `isAjaxCall()` dans tout `AboutController.php`) —
**13 tâches** au total supportent `cb_ajax=1`, pas une seule :
`repairAuditIssue`, `repairDebugMode`, `repairFormDetailsTemplate`,
`repairFormEditableTemplate`, `repairFormTemplates`, `repairFormThemePlugin`,
`repairFormUnknownMarker`, `repairGeneratedArticleCategories`,
`repairLegacyStorageIndexes`, `repairMissingStorageTable`,
`repairUploadDirectoryProtection`, `deleteStaleInstallerTemp`. À l'inverse,
`runAudit`, `showLog`, `exportConfiguration`, `importConfiguration`
**n'ont pas** ce support (formulaire classique uniquement).

**Contrat commun aux 13 tâches** :

| Élément | Valeur |
|---|---|
| URL | `index.php?option=com_contentbuilderng&task=about.<repair*>` |
| Méthode | POST |
| Authentification/ACL | `$user->authorise('core.manage', 'com_contentbuilderng')` vérifié en tête de tâche |
| CSRF | `checkToken()` natif Joomla |
| Paramètre AJAX | `cb_ajax=1` |
| Paramètres spécifiques | `repair_issue` (cmd, whitelisté — `DIRECT_AUDIT_REPAIR_ISSUES`) pour `repairAuditIssue` ; sans paramètre métier pour les tâches dédiées (chacune répare un point fixe) |
| Réponse succès | `{"success":true,"message":"...","messages":[],"data":{"ok":true}}` |
| Réponse échec | Même enveloppe, `success:false`, `data.ok:false` |
| Effets de bord | Selon le helper de réparation ciblé (`admin/src/Helper/Audit/*Helper::repair()` / `DatabaseRepairHelper`) — potentiellement `ALTER TABLE`/`DELETE`/renommages, mêmes risques que les DDL manuels de Storage (§2.1), sans sauvegarde automatique préalable |
| Sans `cb_ajax=1` | Redirection HTTP classique vers `view=about` avec message flash |

---

## 5. Événements/hooks Joomla exposés comme points d'extension

**Fait observé** : les 17 plugins livrés sont tous des extensions Joomla 6
namespaced (PSR-4, `services/provider.php` + `SubscriberInterface`) — aucune
classe procédurale legacy. Deux familles d'événements : Joomla core
(`onContentPrepare`, `onAfterDispatch`, `onAfterInitialise`, `onAfterRoute`,
`onBeforeRender`) et événements **internes ContentBuilder NG**, dispatchés
par le composant lui-même via `$app->getDispatcher()->dispatch(...)` après
`PluginHelper::importPlugin(<groupe>[, <plugin>])`. Cette section documente
les événements internes comme des **contrats** pour tout développeur de
plugin tiers — signature exacte (arguments positionnels reçus, dans l'ordre
du tableau passé à `dispatch()`) et usage du retour.

### 5.1 Groupe `contentbuilderng_submit` — `onBeforeSubmit` / `onAfterSubmit`

**Import** : `PluginHelper::importPlugin('contentbuilderng_submit')`
**sans second argument** (`site/src/Model/EditModel.php:774`) — **tous** les
plugins activés du groupe reçoivent l'événement, pour **chaque** soumission
de **chaque** vue ContentBuilder NG (aucun filtrage par formulaire). C'est
le seul groupe de plugins internes importé "en masse" plutôt que ciblé —
un plugin tiers mal écrit dans ce groupe impacte donc toutes les
soumissions du site.

**`onBeforeSubmit`** — dispatché `EditModel.php:1649-1650`, **avant**
`$data->form->saveRecord(...)` (`:1657`) :

| Argument | Type | Contenu |
|---|---|---|
| `arg0` | `int\|string` | `record_id` courant (0 en création) |
| `arg1` | objet | `$data->form` — instance résolue par `FormSourceFactory` |
| `arg2` | `array` | `$values` — valeurs brutes soumises, **avant** nettoyage `cbGroupMark` |

Retour : **non exploité** dans le chemin observé. Un plugin souhaitant
bloquer la soumission ne peut pas le faire via une valeur de retour ; le
mécanisme de blocage existant (`$app->getInput()->set('cb_submission_failed', 1)`
+ `enqueueFieldValidationMessage()`) est positionné **par `EditModel` lui-même
avant** ce dispatch — **Comportement déduit, non confirmé par du code
d'exemple** : un plugin `onBeforeSubmit` pourrait en théorie appeler cette
même API sur l'input pour invalider la soumission, mais aucun plugin livré
(`submit_sample`) ne le fait.

**`onAfterSubmit`** — dispatché `EditModel.php:2044-2045`, **uniquement si
`!$data->edit_by_type`** (**Zone inconnue** : signification exacte de
`edit_by_type` non creusée), après la sauvegarde, **avant** l'envoi des
e-mails de notification (`:2053+`) et l'exécution des
`custom_action_script` par champ (`:2047-2051`) :

| Argument | Type | Contenu |
|---|---|---|
| `arg0` | `int\|string` | `$record_return` — id de l'enregistrement sauvegardé |
| `arg1` | `int` | `$article_id` — article Joomla généré/lié (0 sinon) |
| `arg2` | objet | `$data->form` |
| `arg3` | `array` | `$cleanedValues` — valeurs soumises, **après** nettoyage `cbGroupMark` |

Retour : capturé (`$submit_after_result`) mais **jamais lu/exploité** plus
loin dans le code observé (**Zone inconnue** : `EditModel.php` fait 2870+
lignes, non entièrement relu — le retour pourrait être exploité ailleurs,
ou être un simple point d'observation sans effet de flux).

**Plugin de référence** : `plugins/contentbuilderng_submit/submit_sample`
(`src/Extension/SubmitSample.php:24-50`) — squelette vide, ne produit
aucune erreur ni effet de bord, sert uniquement à documenter le contrat par
l'exemple.

### 5.2 Groupe `contentbuilderng_verify` — `onSetup` / `onForward` / `onVerify` / `onViewport`

**Import** : **ciblé**, un seul plugin nommé par la balise
`{CBVerify plugin:<nom>}` — `PluginHelper::importPlugin('contentbuilderng_verify', $plugin)`.
Orchestrateur : `admin/src/Model/VerifyModel.php` (classe du composant, pas
un plugin — nécessaire pour comprendre le contrat).

| Événement | Dispatché par | Arguments | Retour attendu |
|---|---|---|---|
| `onSetup` | `VerifyModel.php:293` | `[$this_page, $out]` (état de page/sortie interne à `VerifyModel`) | Chaîne **non vide** = message d'erreur de configuration, **bloque** le flux (ex. PayPal exige `plugin_options['amount']`) ; chaîne vide = configuration valide, le flux continue |
| `onForward` | `VerifyModel.php:311` — seulement si `onSetup` n'a rien retourné **et** pas encore de `verify=1` en requête | `[$this_page, $out]` | Le plugin peut soit **rediriger lui-même** et `exit` immédiatement (PayPal : formulaire HTML auto-soumis vers la passerelle externe, `Paypal.php:132-168`), soit **retourner une URL** — `VerifyModel` redirige alors vers cette URL (`passthrough` retourne `$return_url`, `:315-317`) |
| `onVerify` | `VerifyModel.php:323` — au retour du visiteur (`verify=1&verification_id=...`) | `[$this_page, $out]` | Tableau de succès `{msg:string, is_test:0\|1, data:array}` (`Passthrough.php:92-103`, toujours réputé réussi) ou échec (déclenche `COM_CONTENTBUILDERNG_VERIFICATION_FAILED` — **Hypothèse** : sentinelle exacte de l'échec — `false`/tableau vide — non tracée en détail ici) |
| `onViewport` | Dispatché par le **plugin de contenu** `contentbuilderng_verify` (rendu de la balise `{CBVerify}`), **pas** par `VerifyModel` | Non détaillé dans les brouillons sources (**Zone inconnue**) | Chaîne HTML (personnalisation du rendu de la balise) — les deux implémentations livrées (`Paypal`, `Passthrough`) retournent systématiquement `''` : point d'extension défini mais inexploité par les plugins livrés |

**Plugins de référence** : `plugins/contentbuilderng_verify/{passthrough,paypal}/src/Extension/{Passthrough,Paypal}.php`
— protocole PayPal (sécurité TLS, PDT/IPN) déjà détaillé `10-dependencies.md` §3
et `07-security.md`, non redétaillé ici.

### 5.3 Groupe `contentbuilderng_listaction` — `onBeforeAction` / `onAfterAction` / `onAfterArticleCreation`

**Import** : **ciblé** par la colonne `list_states.action` de l'état de
liste concerné — `PluginHelper::importPlugin('contentbuilderng_listaction', $res['action'])`
(`site/src/Model/EditModel.php:2727-2859`). Un état de liste dont `action`
ne correspond à aucun plugin installé/activé n'a simplement aucun effet.

| Événement | Dispatché par | Arguments | Retour attendu |
|---|---|---|---|
| `onBeforeAction` | `EditModel.php` (dans la plage `:2727-2859`), **avant** toute écriture de `list_records.state_id` | `[$form_id, $items]` (`$items` = enregistrements/ids concernés par le changement d'état) | Non exploité comme résultat de validation dans le code observé — effets de bord uniquement (ex. `Trash` : `UPDATE #__content SET state=-2`) |
| `onAfterAction` | Après la boucle d'upsert `#__contentbuilderng_list_records` | `[$form_id, $items, $error]` | Non exploité (les deux plugins livrés retournent un no-op) |
| `onAfterArticleCreation` | `admin/src/Service/ArticleService.php:711-712`, à **chaque** création/mise à jour d'un article Joomla généré depuis un enregistrement CBNG — dispatché à **tout le groupe** (**Zone inconnue** : point d'import exact non localisé pour cet événement précis) | `[$form_id, $record_id, $article]` | Non exploité comme résultat — effet de bord uniquement (ex. `Trash` : `DELETE` de l'article qui vient d'être recréé si l'état de liste courant du couple `form_id`/`record_id` a `action='trash'`, empêchant la resynchronisation automatique du plugin système de le faire réapparaître) |

**Plugins de référence** : `plugins/contentbuilderng_listaction/{trash,untrash}/src/Extension/{Trash,Untrash}.php`.
Précision par rapport à l'hypothèse non confirmée de `03-data-model.md`
§7/§8 : `list_records.state_id` est écrit par le **composant** (`EditModel`),
pas par les plugins routés — ces derniers synchronisent un **second**
système d'état indépendant, `#__content.state` (article Joomla lié).

### 5.4 Groupe `contentbuilderng_themes` — 8 événements de rendu

`onContentTemplateCss`, `onContentTemplateJavascript`, `onEditableTemplateCss`,
`onEditableTemplateJavascript`, `onListViewCss`, `onListViewJavascript`,
`onContentTemplateSample`, `onEditableTemplateSample` — identiques dans les
4 plugins `blank`/`dark`/`khepri`/`thoth`.

**Argument commun** : `theme` (string) — nom du plugin/thème ciblé.
Dispatché soit avec import ciblé d'un seul plugin (ex.
`site/src/View/List/HtmlView.php:121`, repli codé en dur sur `'thoth'` si
le thème configuré est inconnu/inactif), soit avec import de groupe où
**chaque plugin s'auto-filtre** via
`$event->getArgument('theme') === self::THEME_NAME` (méthode privée
`acceptsThemeEvent()`, identique dans les 4 fichiers). Un événement
dispatché **sans** argument `theme` (chaîne vide) serait accepté par **tous**
les thèmes actifs simultanément — chemin de code valide mais non exploité
par les sites de dispatch observés.

**Retour attendu** : chaîne CSS/JS brute (événements `*Css`/`*Javascript`,
poussée dans un tableau cumulatif `result`, concaténé par l'appelant —
`thoth` fait exception et retourne une chaîne **vide** en enregistrant un
asset Joomla natif via le Web Asset Manager à la place) ; chaîne HTML
(événements `*Sample`, gabarit d'exemple `{champ:label}`/`{champ:value|item}`
inséré dans l'éditeur de template admin).

**Sites de dispatch** : `site/src/View/Details/HtmlView.php:474-484`
(`onContentTemplate*`), `admin/src/View/Edit/HtmlView.php:455-461` +
`site/src/View/Edit/HtmlView.php:825-834` (`onEditableTemplate*`),
`site/src/View/List/HtmlView.php:163-169` (`onListView*`),
`admin/src/Service/TemplateSampleService.php:48-52,149-150` (`*Sample`),
`plugins/system/contentbuilderng_system/.../ContentbuilderngSystem.php:373-394`
(`onContentTemplateCss`/`Javascript`, injection inline pour un article
publié contenant des enregistrements CBNG, une fois par thème distinct
détecté sur la page).

### 5.5 `onContentPrepare` (Joomla core, groupe `content`)

Utilisé par les 7 plugins de contenu (`cblist`, `cbstats`, `download`,
`image_scale`, `permission_observer`, `rating`, `verify`). Deux conventions
de signature coexistent : legacy positionnelle
`($context='', $article=null, $params=null, $limitstart=0, $is_list=false, $form=null, $item=null)`
(5 plugins, avec adaptateur en tête de méthode qui détecte
`Joomla\Event\EventInterface` et ré-extrait ces arguments) vs. moderne
(`cblist`, `cbstats`, dispatch objet `EventInterface` direct).

**Sites de dispatch pertinents pour un intégrateur tiers** (au-delà de
`com_content` core, hors dépôt) :
- `site/src/Model/ListModel.php:1270` — texte d'introduction de l'écran
  liste (`is_list`/`form`/`item` non fournis).
- `admin/src/Service/TemplateRenderService.php:690` — rendu d'un **template
  de ligne de liste** CBNG (`is_list=true`, `$form`, `$recc`=enregistrement
  courant) : c'est le chemin par lequel `{CBDownload}`, `{CBImageScale}`,
  `{CBRating}`, etc. embarqués dans un template de ligne de liste sont
  résolus **par enregistrement**, sans passer par un article Joomla.
- `site/src/View/Details/HtmlView.php:359`, `admin/src/View/Edit/HtmlView.php:144,517`
  — rendu du "content template"/"editable template" des vues détail/édition
  CBNG elles-mêmes.

Retour : `$article->text` modifié (remplacement des balises `{CBXxx...}`
par le HTML rendu) — contrat Joomla standard `onContentPrepare`.

---

## 6. Formats d'import/export

### 6.1 Config Transfer — export/import JSON (`cbng-config-export-v1`)

**Fait observé**, lecture directe de `admin/src/Service/ConfigExportService.php`.

**Sections exportables** (`EXPORT_SECTIONS`, `:30-39`) : `component_params`,
`forms` (table `#__contentbuilderng_forms`), `elements`, `list_states`,
`storages`, `storage_fields`, `resource_access`, `storage_content` (lignes
de données physiques des storages, optionnel). Sections **racines**
sélectionnables directement : `forms`, `storages` (`ROOT_SECTIONS`).
Sections **dépendantes**, ajoutées automatiquement si leur section racine
est sélectionnée : `elements`/`list_states`/`resource_access` (si `forms`),
`storage_fields` (si `storages`) — `FORM_DEPENDENT_SECTIONS`/`STORAGE_DEPENDENT_SECTIONS`.

**Structure du fichier JSON exporté** (`buildPayload()`, `:63-131`) :
```json
{
  "meta": {
    "generated_at": "2026-09-24 10:00:00",
    "generated_by": 42,
    "component": "com_contentbuilderng",
    "format": "cbng-config-export-v1"
  },
  "sections": ["forms", "storages"],
  "filters": {
    "form_ids": [15],
    "storage_ids": [3],
    "include_storage_content": 0
  },
  "data": {
    "forms": {"type": "table", "table": "#__contentbuilderng_forms", "row_count": 1, "rows": [{"id": 15, "...": "..."}]},
    "elements": {"type": "table", "table": "#__contentbuilderng_elements", "row_count": 8, "rows": [...]},
    "component_params": {"type": "component_params", "params": {"...": "..."}},
    "storage_content": {"type": "storage_content", "...": "..."}
  }
}
```

**Déclencheur** : `task=about.exportConfiguration` (POST, **pas**
`configtransfer.export` qui ne fait que router l'affichage) — téléchargement
direct, `Content-Disposition: attachment`, nom
`contentbuilderng-config-YYYYMMDD-His.json`. Requiert `core.manage`.

**Import** : `task=about.importConfiguration` (POST **multipart**, fichier
`cb_config_import_file`). Format vérifié **souplement** : `meta.format`
absent → toléré ; présent avec une valeur **différente** de
`cbng-config-export-v1` → rejeté. `ConfigImportService::filterPayload()`
restreint le contenu aux sections cochées **et** aux noms explicitement
sélectionnés (jamais « tout ou rien » au niveau fichier). Deux modes
(`applyPayload($payload, $sections, $importMode)`) :
- **`MODE_MERGE`** (défaut) : lignes existantes mises à jour par `id` sans
  purge préalable.
- **`MODE_REPLACE`** : pour les `form_id`/`storage_id` explicitement ciblés,
  suppression préalable des lignes correspondantes puis réinsertion —
  destructif si le fichier importé est incomplet.

**Zone inconnue** (relayée du brouillon admin §11, non revérifiée en
détail ici — `ConfigImportService.php` fait 937 lignes) : aucun rollback
transactionnel global identifié pour l'import ; l'atomicité d'un échec en
cours d'`applyPayload()` n'est pas confirmée.

### 6.2 Import CSV/XLSX d'un Storage

**Fait observé**, lecture directe de `admin/src/Model/StorageModel.php` et
`admin/src/Dto/CsvImportOptions.php`.

**Déclencheur** : `task=storage.save`/`storage.apply` (POST **multipart**),
fichier `csv_file` — extensions supportées `csv`, `xlsx`, `xls`
(`COM_CONTENTBUILDERNG_STORAGE_IMPORT_UNSUPPORTED_FORMAT` sinon). Réservé
aux storages internes (`bytable=0` — `COM_CONTENTBUILDERNG_CANNOT_USE_CSV_WITH_FOREIGN_TABLE`
sinon). Un `xlsx`/`xls` est **converti en CSV** avant traitement
(`convertSpreadsheetFileToCsv()`, PhpSpreadsheet vendorisé).

**Options** (`CsvImportOptions::fromPostData()`, lues indifféremment sous
`jform[...]` ou en POST direct) :

| Paramètre | Type | Défaut | Rôle |
|---|---|---|---|
| `csv_delimiter` | string | `,` | Délimiteur de colonnes |
| `csv_repair_encoding` | string | `''` | Réparation d'encodage (mojibake) avant lecture |
| `csv_import_columns[]` | array (index ou nom) | toutes | Colonnes retenues pour créer des `storage_fields` |
| `csv_import_data` | bool | `true` | Importer les lignes de données (pas seulement la structure des colonnes) |
| `csv_drop_records` | bool | `false` | **Vider** la table physique + les lignes `records`/`list_records`/`articles`/articles Joomla liés **avant** import (`TRUNCATE` + `DELETE` ciblé + jointure `DELETE a.*, c.* FROM ... INNER JOIN` MySQL/MariaDB brute) |
| `csv_published` | int | `0` | État de publication par défaut des enregistrements importés |

**Endpoint de prévisualisation d'en-têtes** : `task=storage.previewHeaders`
(POST multipart, §4.3) — retourne la liste des en-têtes détectés **avant**
soumission définitive, pour permettre le choix de `csv_import_columns[]`.

**Résultat** : une `storage_field` par colonne retenue, lignes insérées par
lots dans la table physique **et** lignes `#__contentbuilderng_records`
correspondantes créées ; résumé d'import (`getLastImportSummary()` : lignes
importées/lues, colonnes, lignes vides ignorées, durée, compteurs de
suppression si `csv_drop_records`).

### 6.3 Export de données — XLSX (PhpSpreadsheet)

Déjà largement détaillé côté "parcours utilisateur" par le brouillon site
§10 (revérifié cohérent avec `07-security.md` §1.3 pour la partie ACL, non
redétaillé ici) — repris ici sous l'angle **format de fichier** :

**Déclencheur** : `GET index.php?option=com_contentbuilderng&view=export&id=<formId>`.
**Format** : classeur `.xlsx` (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`,
plusieurs en-têtes `Content-Type` redondants pour compatibilité anciens
navigateurs) — une feuille unique, en-tête ligne 1 figée, colonnes issues de
`forms.export_xls` filtrées à `elements.export_include=1`. Colonnes
réservées optionnelles : id, état (couleur), publication
(`export_id_column`/`export_state_column`/`export_publish_column`).
Colonnes de données typées (`SpreadsheetExportValueHelper::resolveColumnType()`/`prepareCellValue()`).
**Durcissement anti-injection de formule** : `setCellValueExplicit(...,
DataType::TYPE_STRING)` systématique pour en-têtes et texte (protection
contre l'injection CSV/XLSX via `=`/`+`/`-`/`@`). Nom de fichier construit
par `ExportFilenameService::build()` (mode par défaut ou personnalisé via
paramètres de menu `cb_export_filename_mode`/`cb_export_filename`), avec
repli translittéré ASCII (`filename=`) et variante `filename*=UTF-8''`
(RFC 5987).

### 6.4 Titlesets — fichiers `.ini` (`{CBStats}`)

**Fait observé**, lecture directe des sections concernées du brouillon
admin §10 (lignes/classes citées non revérifiées ligne à ligne ici, hors
budget de cette mission, mais cohérentes avec l'absence de table dédiée
confirmée `03-data-model.md`).

**Stockage** : fichiers `.ini` sur disque (**pas** en base de données),
deux répertoires « custom » distincts gérés par
`CbStatsTitleSetManagerService` : un pour les jeux de titres
(`CbStatsTitleSetService::CUSTOM_DIRECTORY`), un pour les configurations
réutilisables (`CbStatsConfigService::CUSTOM_DIRECTORY`) — chacun protégé
par un `index.html` vide généré à la création du répertoire.

**Export** : `task=titleset.exportSelected` (GET/POST) — téléchargement
direct `text/plain` si un seul fichier sélectionné, sinon archive **ZIP**
en mémoire (`\ZipArchive`, fichier temporaire nettoyé après lecture) avec
déduplication de noms (préfixe `source-` en cas de collision entre un jeu
« intégré » au composant et un jeu « custom » de même nom).

**Import** : `task=titleset.importFiles` (POST **multipart**,
`titleset_files[]`, jusqu'à 1 MiB par fichier) — validation individuelle
(nom, contenu, taille) **et** détection de doublon **au sein du même lot**
**avant toute écriture** (`batch_duplicate`, rejeté même avec l'option
`titleset_overwrite=1`). Erreurs mappées à des clés de langue précises :
`invalid_filename`, `invalid_contents`, `already_exists`, `upload_invalid`,
`batch_duplicate`, `directory_failed`, `replace_failed`, `read_failed`,
`write_failed`.

---

## 7. Zone d'incertitude tranchée — `action=cbstats`

Le brouillon plugins (`draft-plugins-features-flows.md` §1.2) relayait
l'incertitude suivante : *« §17 de `Gil_CBSTATS_PUBLIC_API.md` mentionne un
endpoint API (`task=api.display&format=json&action=cbstats`) partageant le
même moteur — Zone inconnue : ce document ne trace pas ce contrôleur API
(hors du plugin, potentiellement dans le composant). »*

**Tranché par lecture directe** (`site/src/Controller/ApiController.php:94-197`) :

- `action=cbstats` est routé dans **`site/src/Controller/ApiController.php::display()`**,
  au tout début de la méthode (`:134-145`), **avant** l'appel à
  `handleAction()` — c'est un branchement **spécial**, distinct du `match`
  générique (`:189-197`) qui gère `get-unique-values`/`rating`/`stats`. En
  d'autres termes, **quatre** sous-actions existent dans `ApiController`
  (`get-unique-values`, `rating`, `stats`, `cbstats`), mais seules les trois
  premières partagent le dispatcheur `handleAction()` — `cbstats` a son
  propre chemin de code (`getCbstatsOutput()`/`getCbstatsPayload()`,
  `:212-411`) car sa sortie peut être une réponse JSON **brute** (`sendRawJson()`,
  sorties `json`/`table`/`pie`/`bar`/`histogram`/`line`/`radar`) plutôt que
  l'enveloppe `{success,messages,data}` standard.
- **Permission requise** : `['stats']` uniquement
  (`ApiPermissionRequirementService::getRequiredPermissions()` traite
  `stats` et `cbstats` de façon identique, `return ['stats']` pour les deux
  — `admin/src/Service/ApiPermissionRequirementService.php:19-21`).
- **Moteur partagé confirmé avec le plugin de contenu** : `getCbstatsPayload()`
  résout `titleset`/`groupset` via **la même classe**
  `CbStatsTitleSetService(JPATH_SITE)` que le plugin `contentbuilderng_cbstats`
  (`ApiController.php:314,342`), et calcule via **le même**
  `StatsService::getStatsPayload()`/`normalizeFieldStats()`/`resolveCbstatsOutput()`
  que la balise `{CBStats}`. Les 17 valeurs de `output=` acceptées sont
  **identiques** à celles du plugin (`getCbstatsOutput()`, liste
  `:215-219`, comparée à `ContentbuilderngStats.php:179-182`).
- **Conclusion** : l'endpoint `task=api.display&action=cbstats` **est bien**
  un contrôleur du composant (`site/`), pas un contrôleur du plugin — le
  plugin de contenu `{CBStats}` et cet endpoint API partagent le même moteur
  de calcul (`StatsService`) et les mêmes fichiers `.ini` de configuration
  (`titleset`/`groupset`/`config`), mais restent **deux points d'entrée
  distincts** : la balise `{CBStats}` est résolue côté serveur lors du rendu
  d'un article (`onContentPrepare`, §5.5), tandis que `action=cbstats` est
  un endpoint HTTP directement appelable par un client externe (JS tiers,
  application mobile) sans passer par le rendu d'un article Joomla.

---

## 8. Synthèse et zones d'incertitude restantes

**Points tranchés par ce document** (via lecture directe du code, en
complément ou en correction des brouillons sources) :

1. Résolution complète de la zone d'incertitude « `action=cbstats` »
   relayée par l'agent plugins — §7.
2. Permissions exactes par méthode/action de `ApiController`
   (`ApiPermissionRequirementService`) — le brouillon site simplifiait
   `get-unique-values` (manquait `listaccess`) et `stats`/`cbstats`
   (indiquait potentiellement `api`, alors que seul `stats` est requis) —
   §4.1.
3. Support AJAX réel de `AboutController` : 13 tâches `repair*` supportent
   `cb_ajax=1`, pas seulement `repairAuditIssue` comme indiqué dans le
   brouillon admin §14 — §4.5.
4. Confirmation exhaustive : **aucun** usage de `com_ajax` dans tout le
   dépôt — tous les endpoints asynchrones passent par des tâches
   `com_contentbuilderng` standard avec indicateur `cb_ajax=1` — §1.
5. Structure exacte du fichier JSON `cbng-config-export-v1` (Config
   Transfer) — lue directement dans `ConfigExportService::buildPayload()` —
   §6.1.

**Zones d'incertitude restantes** (relayées des brouillons sources, non
revérifiées ligne à ligne dans le cadre de cette mission documentaire —
budget de lecture concentré sur les endpoints/contrats eux-mêmes) :

1. **`DatatableController::create()`/`sync()`** — le trait
   `ComponentAccessTrait` impose `core.manage` avant chaque tâche, en plus
   du jeton CSRF (§2.1). Aucun contrôle spécifique `core.edit` n'est présent
   pour ces opérations DDL ; vérifier si `core.manage` seul correspond à
   la politique ACL souhaitée pour la création et la synchronisation.
2. **`onAfterArticleCreation`** (§5.3) — point d'import exact non localisé
   avec certitude (groupe entier vs. plugins déjà importés pour d'autres
   raisons dans la requête courante).
3. **Retour de `onAfterSubmit`** (§5.1) — capturé (`$submit_after_result`)
   mais jamais lu dans le code observé ; peut être exploité ailleurs dans
   `EditModel.php` (2870+ lignes, non entièrement relu) ou être un simple
   point d'observation.
4. **`onViewport`** (§5.2) — signature exacte des arguments non tracée
   (les deux plugins livrés retournent systématiquement `''`, donc le
   contrat n'a jamais été exercé en pratique par le code fourni).
5. **`ConfigImportService::applyPayload()`** (§6.1) — atomicité d'un échec
   en cours d'import non confirmée (fichier de 937 lignes, non lu
   intégralement).
6. **Validation serveur de `default_category`** (onglet Article, formulaire
   admin) — seule une validation JS a été identifiée ; une revalidation
   serveur dans `FormModel::save()` n'a pas été confirmée (relayé du
   brouillon admin §7, hors périmètre strict "contrats API" mais pertinent
   pour la robustesse du contrat `form.save`).
7. **`StoragesController::copy()`** — comportement exact vis-à-vis de la
   table physique et des champs non tracé en détail (relayé du brouillon
   admin §2).
