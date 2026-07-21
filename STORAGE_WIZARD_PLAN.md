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

### 🚧 À faire

- [ ] **Recherche préalable** (avant de coder le wizard) :
  - [ ] Vérifier s'il existe déjà un import CSV pour les storages dans le
        composant (sinon c'est un développement à part entière).
  - [ ] Vérifier s'il existe déjà un mécanisme de session/état multi-écrans
        réutilisable ailleurs dans l'admin du composant.
- [ ] **Squelette du wizard** : nouvelle vue admin `view=storagewizard`
      (contrôleur + modèle + template), stepper à 5 étapes, état de
      progression persisté en session.
- [ ] **Étape 1 — Storage** : réutiliser `StorageModel` /
      `StorageController::save()` existants, encapsulés dans le stepper.
- [ ] **Étape 2 — Import CSV** : selon résultat de la recherche préalable.
- [ ] **Étape 3 — Champs** : réutiliser l'écran Storagefield existant.
- [ ] **Étape 4 — Formulaire** : soit `FormModel::save()` avec
      `type=com_contentbuilderng` / `reference_id=storageId` pré-rempli, soit
      s'appuyer sur `DirectStorageFormProvisioningService` (déjà en place,
      cf. chantier "direct storage mode" ci-dessous) pour générer le
      formulaire automatiquement.
- [ ] **Étape 5 — Menu** : création d'un item de menu Joomla via
      `Joomla\CMS\Table\Menu` / `MenusModel`, pointé sur
      `task=list.display&storage_id=X` (ou `id=<formId>` une fois le
      formulaire créé).

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
