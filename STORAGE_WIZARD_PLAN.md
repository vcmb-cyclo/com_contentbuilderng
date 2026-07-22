# Chantier : Assistant de création de Storage + réorganisation toolbar Storages

Suivi de l'implémentation demandée le 2026-07-21. Ce document est mis à jour au
fil de l'avancement ; cocher les cases au fur et à mesure.

## Objectif

1. Dans l'écran admin **Storages**, déplacer le bouton "Supprimer" dans le
   dropdown "Actions" existant (qui contient déjà Publier/Dépublier).
2. Ajouter un nouveau bouton **Assistant** qui déroule un wizard guidé
   (step-by-step, état sauvegardé entre les étapes) pour créer de bout en
   bout :
   1. un Storage,
   2. un import CSV (ou l'ajout manuel de champs),
   3. les champs du storage,
   4. un formulaire ContentBuilderNG pour consulter les enregistrements,
   5. un item de menu Joomla pointant vers ce formulaire.

## Décisions actées avec l'utilisateur

- Le dropdown "Actions" regroupe **toutes** les actions en masse (pas
  seulement Supprimer) : Publier / Dépublier / Supprimer.
- L'Assistant est un **vrai wizard** (pas un simple enchaînement de
  redirections vers les écrans existants) : navigation précédent/suivant,
  état persisté entre les étapes.
- L'étape "menu" **crée réellement** un item de menu Joomla (via
  `com_menus`), plutôt que de renvoyer l'utilisateur vers l'écran natif de
  création de menu.

## État d'avancement

### ✅ Fait

- [x] Toolbar Storages : bouton "Supprimer" déplacé dans le dropdown
      "Actions" (`admin/src/View/Storages/HtmlView.php::addToolbar()`).
- [x] Toolbar Storage (édition, sous-liste des champs) : bouton "Supprimer
      champ" déplacé dans le dropdown "Actions"
      (`admin/src/View/Storage/HtmlView.php`) — **et réparé** : le task
      `storage.listDelete` n'existait dans aucun contrôleur (bouton mort).
      Ajout de `StorageController::listDelete()` +
      `StoragefieldsModel::delete()` : supprime les lignes
      `#__contentbuilderng_storage_fields` sélectionnées (hors champs
      système), et `DROP COLUMN` la colonne physique correspondante pour les
      storages gérés (`bytable=0`) — les tables externes (`bytable=1`) ne
      sont jamais altérées, seule la métadonnée CB est retirée.
- [x] Toolbar Forms (`view=forms`) : bouton "Supprimer" (déjà fonctionnel,
      `forms.delete`) déplacé dans le dropdown "Actions"
      (`admin/src/View/Forms/HtmlView.php`).
- [x] Message de confirmation de suppression détaillé (nombre d'éléments, ou
      nom de l'élément si un seul est sélectionné) sur les 3 écrans de
      suppression en masse du back-office (Storages, Storage/champs, Forms) :
      - `media/js/admin-ui.js` : interception générique (délégation d'événement)
        de tout bouton toolbar `confirm()` + `Joomla.submitbutton(...)`, sans
        wiring par écran — lit `data-cb-item-label` sur la ligne sélectionnée.
      - Nouvelles clés `COM_CONTENTBUILDERNG_CONFIRM_DELETE_ONE` (%s) /
        `_MANY` (%d), en-GB/fr-FR/de-DE, exposées via `Text::script()`.
      - `data-cb-item-label` ajouté aux lignes de `admin/tmpl/storages/default.php`,
        `admin/layouts/storage/storage_tab.php`, `admin/tmpl/forms/default.php`.

### ✅ Fait (suite) — squelette fonctionnel de l'Assistant

- [x] **Recherche préalable** :
  - Import CSV : **déjà fonctionnel** (upload → preview headers →
    création table+champs+lignes), voir
    `StorageController::previewHeaders()`/`save()` (lignes 243-340) et
    `StorageModel::extractHeaderColumnsFromUpload()`. Pas de redéveloppement
    nécessaire — le wizard s'appuie dessus en renvoyant vers l'écran Storage
    existant plutôt que de le dupliquer.
  - Pattern état multi-écrans : **existe déjà**, `RepairWorkflowService`
    (`admin/src/Service/RepairWorkflowService.php`) via
    `$app->getUserState()`/`setUserState()`. Repris à l'identique dans
    `StorageWizardService`.
- [x] **Squelette du wizard** — 4 étapes réelles (Storage / Champs /
      Formulaire / Menu ; l'étape "CSV ou champs manuels" du besoin initial
      est couverte par l'étape "Champs", qui renvoie vers l'écran Storage
      existant — CSV et ajout manuel y sont déjà tous les deux disponibles) :
  - `admin/src/Service/StorageWizardService.php` — état de session
    (`storage_id`/`form_id`/`menu_item_id`/`current_step`), miroir du
    pattern `RepairWorkflowService`.
  - `admin/src/Controller/StoragewizardController.php` — tâches
    `start`/`saveStorage`/`confirmFields`/`createForm`/`createMenu`/
    `skipMenu`/`finish`. Orchestrateur **fin** : ne réimplémente que ce qui
    n'existe pas ailleurs (création storage minimale, création menu) ;
    délègue la gestion des champs à l'écran Storage existant et la création
    du formulaire à `DirectStorageFormProvisioningService`.
  - `admin/src/View/Storagewizard/HtmlView.php` + `admin/tmpl/storagewizard/default.php`
    — stepper Bootstrap natif, un seul `#adminForm` (convention
    `Joomla.submitbutton()` du cœur), un template par étape.
  - Bouton toolbar "Assistant" sur l'écran Storages
    (`Storages/HtmlView.php::addToolbar()`).
  - Hook additif (uniquement si `?wizard=1`) sur l'écran Storage : bouton
    "Retour à l'assistant" (`Storage/HtmlView.php`) — n'affecte en rien le
    comportement normal de cet écran hors contexte wizard.
  - Étape "Menu" : création via `Joomla\Component\Menus\Administrator\Table\MenuTable`
    (obtenu via `MVCFactory::createTable('Menu', 'Administrator')`),
    `setLocation(1, 'last-child')` + `bind()`/`check()`/`store()`/`rebuildPath()`
    — pattern bas niveau standard Joomla pour insérer dans l'arbre imbriqué
    `#__menu`, plutôt que `ItemModel::save()` (qui suppose un POST jform
    complet, plus risqué à reproduire hors UI). Le menutype doit déjà
    exister (pas de création de menutype dans cette itération).
  - Toutes les chaînes UI ajoutées en en-GB/fr-FR/de-DE.

### 🚧 À faire / à vérifier

- [ ] **Test bout en bout sur instance Joomla réelle** — rien de ce qui
      précède n'a pu être exécuté dans cet environnement (pas d'instance
      Joomla vivante accessible depuis ce contexte). En particulier à
      valider en priorité :
  - la création de l'item de menu (`MenuTable`) ne corrompt pas l'arbre
    imbriqué `#__menu` — c'est le point le plus sensible de ce chantier ;
  - `StorageModel::save()` avec le tableau minimal (`id/name/title/published/ordering`)
    passe bien la validation du formulaire XML (`admin/forms/storage.xml`) ;
  - le retour "Retour à l'assistant" → étape Champs → "Suivant" fonctionne
    après un import CSV réel (pas seulement un ajout manuel de champ).
- [ ] Décider si un item de menu déjà existant devrait pouvoir être
      réutilisé/modifié plutôt que d'en créer un nouveau à chaque passage
      dans le wizard (actuellement : toujours création).
- [ ] Éventuellement : bouton "Précédent" pour revenir en arrière dans le
      stepper (actuellement wizard strictement linéaire, un `start` remet
      tout à zéro).

## Fichiers concernés (prévisionnel)

- `admin/src/View/Storages/HtmlView.php` — toolbar (fait).
- `admin/src/Controller/StoragewizardController.php` — nouveau.
- `admin/src/Model/StoragewizardModel.php` — nouveau.
- `admin/tmpl/storagewizard/*.php` — nouveau.
- `admin/src/Service/DirectStorageFormProvisioningService.php` — réutilisé
  pour l'étape formulaire (voir contexte ci-dessous).
- Intégration `com_menus` pour l'étape menu — à identifier précisément.

## Contexte lié : chantier "direct storage mode" (déjà en cours, PR #72 et suite)

Ce wizard vient s'appuyer sur des correctifs faits en parallèle pour que
l'édition/consultation directe d'un storage `bytable` (sans formulaire
CB classique préexistant) fonctionne de bout en bout :

- `DirectStorageFormProvisioningService` (admin/src/Service) : résout ou crée
  à la volée le `#__contentbuilderng_forms` lié à un storage, avec ses
  éléments, templates par défaut, permissions par défaut, `tag='Auto'`, et
  activation de la recherche sur les champs texte/date.
- Corrections apportées dans `EditController`, `DetailsController`,
  `ListController`, et les templates `edit/default.php` /
  `details/default.php` pour que le contexte "storage direct" et la
  signature de prévisualisation admin survivent à la navigation (sauvegarde,
  annulation, retour liste, retour admin).
- Fix générique dans `contentbuilderng_com_contentbuilderng::saveRecord()` :
  les champs `date`/`datetime` vides sont désormais enregistrés en `NULL`
  plutôt qu'en chaîne vide (évite l'erreur MySQL strict mode).

Ces deux chantiers sont liés : l'étape 4 de l'Assistant (création du
formulaire) peut s'appuyer directement sur `DirectStorageFormProvisioningService`
plutôt que de dupliquer sa logique.
