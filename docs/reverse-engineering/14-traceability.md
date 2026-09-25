# 14 — Table de traçabilité

## Méthodologie

Pour chaque fonctionnalité principale cataloguée dans `04-features.md`
(numérotation identique), une ou plusieurs lignes indiquent où retrouver son
comportement dans le dépôt : composant (`admin`/`site`/`plugin:<nom>`),
classe, méthode(s) clé(s), fichier. Les lignes couvrent les points d'entrée
et les méthodes structurantes identifiées dans les trois brouillons sources
et consolidées dans `04-features.md` et `05-user-flows.md` — pas
nécessairement chaque cas limite ou méthode privée secondaire (voir ces deux
documents pour le détail complet, ACL, données, messages).

Convention `Fichier` : chemin relatif à la racine du dépôt. Quand la ligne
exacte n'a pas été confirmée dans les brouillons sources, la colonne
Méthode reste au niveau de la classe/du rôle fonctionnel plutôt que
d'inventer un numéro de ligne.

---

## A. Installation, cycle de vie & administration système

| Fonctionnalité | Composant | Classe | Méthode | Fichier |
|---|---|---|---|---|
| 1. Installation/MAJ/désinstallation | admin | `com_contentbuilderngInstallerScript` | `__construct()`, `preflight()`, `install()`, `update()`, `postflight()`, `uninstall()` | `script.php` |
| 1. Installation/MAJ/désinstallation | admin | `InstallerService` | `ensurePluginsInstalled()`, `activatePlugins()`, `updateMenuLinks()` | `admin/src/Service/InstallerService.php` |
| 1. Installation/MAJ/désinstallation | admin | `MigrationService` | (migrations versionnées) | `admin/src/Service/MigrationService.php` |
| 1. Installation/MAJ/désinstallation | admin | `PluginInstallerService` | (installation des 17 plugins) | `admin/src/Service/PluginInstallerService.php` |
| 1. Installation/MAJ/désinstallation | admin | `SchemaService` | `ensureUniqueConstraints()`, `updateDateColumns()`, `normalizeExternalStorageModes()` | `admin/src/Service/SchemaService.php` |
| 1. Installation/MAJ/désinstallation | admin | `PackedDataMigrationHelper` | `migratePackedPayloadsToModernFormat()` | `admin/src/Helper/PackedDataMigrationHelper.php` |
| 1. Installation/MAJ/désinstallation | admin | — | 13 `DROP TABLE` | `admin/sql/uninstall.sql` |
| 2. Menu admin & navigation | admin | `InstallerService` | `ensureAdminMenuRootNodeExists()`, `ensureAdministrationMainMenuEntry()`, `ensureSubmenuQuickTasks()` | `admin/src/Service/InstallerService.php:421-814` |
| 2. Menu admin & navigation | admin | `MenuService` | `createBackendMenuItem()` (voir aussi #37) | `admin/src/Service/MenuService.php` |
| 3. Écran d'accueil / `view=edit` | admin | `Contentbuilderng\HtmlView` | `display()` | `admin/src/View/Contentbuilderng/HtmlView.php:20-59` |
| 3. Écran d'accueil / `view=edit` | admin | `Edit\HtmlView` | `display()` (classe modèle manquante, voir #36) | `admin/src/View/Edit/HtmlView.php` |
| 4. About/Audit/Repair/Journal | admin | `AboutController` | `runAudit()`, `repairAuditIssue()`, `showLog()`, `exportConfiguration()`, `importConfiguration()` | `admin/src/Controller/AboutController.php` |
| 4. About/Audit/Repair/Journal | admin | `DatabaseAuditHelper` | `run()` | `admin/src/Helper/DatabaseAuditHelper.php` |
| 4. About/Audit/Repair/Journal | admin | `Audit\*Helper` (×15) | `run()`, `repair()` | `admin/src/Helper/Audit/*.php` |
| 4. About/Audit/Repair/Journal | admin | `RepairWorkflowService` | `createWorkflowState()`, `executeStep()`, `advanceToNextPendingStep()`, `logAuditReport()` | `admin/src/Service/RepairWorkflowService.php` |
| 5. Config Transfer | admin | `ConfigtransferController` | `export()`, `import()` | `admin/src/Controller/ConfigtransferController.php` |
| 5. Config Transfer | admin | `AboutController` | `exportConfiguration()` (`:832-908`), `importConfiguration()` (`:914-1007`), `rememberConfigTransferSelection()` | `admin/src/Controller/AboutController.php` |
| 5. Config Transfer | admin | `ConfigExportService` | `resolveEffectiveSections()`, `buildPayload()`, `buildSummary()` | `admin/src/Service/ConfigExportService.php` |
| 5. Config Transfer | admin | `ConfigImportService` | `filterPayload()`, `applyPayload()` | `admin/src/Service/ConfigImportService.php` |
| 5. Config Transfer | admin | `Configtransfer\HtmlView` | `display()`, `hydrateSelectionState()` | `admin/src/View/Configtransfer/HtmlView.php` |

## B. Storages & données

| Fonctionnalité | Composant | Classe | Méthode | Fichier |
|---|---|---|---|---|
| 6. Gestion des Storages | admin | `StoragesController` | `display()`, `copy()` | `admin/src/Controller/StoragesController.php` |
| 6. Gestion des Storages | admin | `StorageController` | `edit()`, `save()` (`:157-549`), `addfield()`, `previewHeaders()`, `checkExistingTableColumns()`, `addindex()`/`deleteindex()`, `deleteRecord()`, `ajax_addfield()`, `ajax_update_field_type()`, `ajax_update_field_required()`, `ajax_update_field_title()` | `admin/src/Controller/StorageController.php` |
| 6. Gestion des Storages | admin | `StorageModel` | `ensureDataTable()` (`:628-637`), `syncStorageDataTableOrBytable()` (`:779-1011`), `ensureSystemFieldMetadata()` (`:647-761`), `storeCsv()` (`:1277-1382`), `csvFileToTable()` (`:1619-1818`), `extractHeaderColumnsFromUpload()`, `addIndexFromRequest()`/`deleteIndex()`, `getPhysicalIndexes()` (`:374-428`), `delete()` (`:1212-1274`), `normalizeStorageName()` | `admin/src/Model/StorageModel.php` |
| 6. Gestion des Storages | admin | `StoragesModel` | `getListQuery()` (`:116-180`) | `admin/src/Model/StoragesModel.php` |
| 6. Gestion des Storages | admin | `StorageColumnTypeHelper` | `sqlDefinition()`, `enforceRequired()` | `admin/src/Helper/StorageColumnTypeHelper.php` |
| 6. Gestion des Storages | admin | `StorageSystemFieldHelper` | `definitions()` | `admin/src/Helper/StorageSystemFieldHelper.php` |
| 6. Gestion des Storages | admin | `com_contentbuilderng` (type) | `delete()` (lignes physiques) | `admin/src/types/com_contentbuilderng.php` |
| 7. Storage Wizard | admin | `StoragewizardController` | `begin()`, `chooseStorageMode()`, `selectExistingStorage()`, `chooseCreationMode()`, `saveStorageDetails()`, `chooseInitializationMode()`, `confirmFields()`, `createForm()`, `confirmForm()`, `createMenu()`, `skipMenu()`, `finish()`, `back()`, `backSubstep()`, `start()` | `admin/src/Controller/StoragewizardController.php` |
| 7. Storage Wizard | admin | `StorageWizardService` | `createStorage()` (`:439-529`), `findExistingMenuItemId()` (`:736-749`), `resolveParentMenutype()` | `admin/src/Service/StorageWizardService.php` |
| 7. Storage Wizard | admin | `DirectStorageFormProvisioningService` | `resolveOrCreateFormId()` | `admin/src/Service/DirectStorageFormProvisioningService.php` |
| 8. Storage Fields | admin | `StoragefieldController` | `add()` (`:65-118`) | `admin/src/Controller/StoragefieldController.php` |
| 8. Storage Fields | admin | `StorageFieldService` | `addField()` (`:18-117`) | `admin/src/Service/StorageFieldService.php` |
| 8. Storage Fields | admin | `StoragefieldsModel` | `delete()` (`:329-421`) | `admin/src/Model/StoragefieldsModel.php` |
| 8. Storage Fields | admin | `StorageModel` | `addFieldFromRequest()` (`:229-364`), `syncEditedFields()`/`syncEditedFieldsFromRequest()` (`:1017-…`) | `admin/src/Model/StorageModel.php` |
| 9. Datatable | admin | `DatatableController` | `create()` (`:60-104`), `sync()` (`:106-148`) | `admin/src/Controller/DatatableController.php` |
| 9. Datatable | admin | `DatatableService` | `createForStorage()`, `syncColumnsFromFields()`, `getLastSyncWarnings()` | `admin/src/Service/DatatableService.php` |

## C. Formulaires & vues (Forms)

| Fonctionnalité | Composant | Classe | Méthode | Fichier |
|---|---|---|---|---|
| 10. Gestion des Forms | admin | `FormController` | `add()` (`:135-146`), `edit()` (`:110-129`), `save()` (`:173-183`), `save2new()`, `saveorder()`, `element_flag()`, `save_labels()`, `debug_on()`/`debug_off()`, `getRedirectToItemAppend()` (`:185-228`), `repairThemePlugin()`/`repairEditableTemplate()`/`repairDetailsTemplate()`/`repairTemplates()`/`repairEditableFieldItem()`, `ajax_add_bf_system_field()`/`ajax_remove_bf_system_field()` | `admin/src/Controller/FormController.php` |
| 10. Gestion des Forms | admin | `FormsController` | `copy()`, `delete()` (`:139-171`), `debug_on()`/`debug_off()` | `admin/src/Controller/FormsController.php` |
| 10. Gestion des Forms | admin | `FormModel` | `save()` (`Table::store()` + `buildDefaultListStates()` `:109-131`), `deleteByIds()`, `setListEditable()`/`setListListInclude()`/`setListSearchInclude()`/`setListNotLinkable()` (`:348-488`), `saveElementListSettings()` (`:149-207`) | `admin/src/Model/FormModel.php` |
| 10. Gestion des Forms | admin | `FormSupportService` | (synchronisation éléments↔source) | `admin/src/Service/FormSupportService.php` |
| 11. Article Settings | admin | (layout) | validation JS `create_articles`/`default_category`, `cbResetArticleOptions()` | `admin/layouts/form/article_tab.php:50-64,262-424` |
| 11. Article Settings | admin | `FormModel` | `save()` (colonnes « Intégration Articles ») | `admin/src/Model/FormModel.php` |
| 11. Article Settings | admin/site | `ArticleService` | `createArticle()` (`:1994` dans `EditModel::store()`) | `admin/src/Service/ArticleService.php` |
| 12. Elements et Element Options | admin | `ElementoptionsController` | `display()` (`:78-85`), `save()` (`:87-121`), `resyncLockedTemplatesAfterSave()` (`:49-76`) | `admin/src/Controller/ElementoptionsController.php` |
| 12. Elements et Element Options | admin | `ElementoptionsModel` | `store()` | `admin/src/Model/ElementoptionsModel.php` |
| 12. Elements et Element Options | admin | `FormSupportService` | `resyncLockedTemplates()` | `admin/src/Service/FormSupportService.php` |
| 13. List States | admin | `FormModel` | `save()` (`:1536-1599`), `buildDefaultListStates()` (`:109-131`) | `admin/src/Model/FormModel.php` |
| 14. Utilisateurs & permissions | admin | `UsersController` | `verified_view()`/`not_verified_view()`/`verified_new()`/`not_verified_new()`/`verified_edit()`/`not_verified_edit()` (`:96-233`), `publish()`/`unpublish()`, `edit()`/`apply()`/`save()` | `admin/src/Controller/UsersController.php` |
| 14. Utilisateurs & permissions | admin | `UserModel` | `setListVerifiedView()` etc. (`:110-224`), `ensureContentbuilderngUserRow()` (`:62-85`), `getData()`/`store()` | `admin/src/Model/UserModel.php` |
| 14. Utilisateurs & permissions | admin | `UsersModel` | `setPublished()`/`setUnpublished()` (`:175-251`) | `admin/src/Model/UsersModel.php` |
| 14. Utilisateurs & permissions | admin/site | `PermissionService` | `setPermissions()` (`:225-322`), `checkPermissions()`, `authorize()`, `authorizeFe()` | `admin/src/Service/PermissionService.php` |
| 15. Titlesets | admin | `TitlesetController` | `save()`/`apply()` (`:27-35`), `saveAndRedirect()` (`:269-289`), `save2copy()` (`:37-55`), `validateFile()` (`:238-252`), `deleteSelected()` (`:57-76`), `exportSelected()` (`:78-132`), `importFiles()` (`:134-186`), `deleteFile()` (`:254-262`) | `admin/src/Controller/TitlesetController.php` |
| 15. Titlesets | admin | `CbStatsTitleSetManagerService` | `save()` (`:165-212`), `saveCopy()`, `validate()` | `admin/src/Service/CbStatsTitleSetManagerService.php` |

## D. Frontend — affichage et navigation

| Fonctionnalité | Composant | Classe | Méthode | Fichier |
|---|---|---|---|---|
| Dispatch (préambule D) | site | `Dispatcher` | `dispatch()` (`:32-244`) | `site/src/Dispatcher/Dispatcher.php` |
| 16. Affichage liste | site | `ListController` | `display()`, `delete()`/`state()`/`publish()` (`:88-346`), `assertConstrainedAction()` (`:348-359`), `enqueueUnpublishedPreviewNotice()` (`:557-576`), `armPermissionsForSelection()` | `site/src/Controller/ListController.php` |
| 16. Affichage liste | site | `ListModel` | `__construct()` (`:150-248`), `getData()` (`:671-1291`), `getDirectStorageListSubject()` (`:496-635`), `getEmbeddedResultLimit()`, `getPaginationStateKeyPrefix()` | `site/src/Model/ListModel.php` |
| 16. Affichage liste | site | `List\HtmlView` | `display()` (`:69-250`) | `site/src/View/List/HtmlView.php` |
| 16. Affichage liste | site | `EmbeddedListFieldFilterService` / `EmbeddedListActionFilterService` | `isEmbeddedRequest()`, `matchFieldSelectors()`/`matchSelectors()`/`parseSelectors()`, `isRequestAllowed()`, `isAllowed()` (`:93-97`) | `site/src/Service/EmbeddedList{Field,Action}FilterService.php` |
| 17. Affichage détail | site | `DetailsController` | `__construct()` (`:84-173`), `display()` (`:180-262`) | `site/src/Controller/DetailsController.php` |
| 17. Affichage détail | site | `DetailsModel` | `getData()` (`:312-685`), `isRecordAllowedByMenuFilter()`, `buildDirectStorageDetailsTemplate()` (`:687-708`) | `site/src/Model/DetailsModel.php` |
| 17. Affichage détail | site | `Details\HtmlView` | `display()` (`:278-550`), `resolveSiblingRecordIds()` | `site/src/View/Details/HtmlView.php` |
| 17. Affichage détail | admin | `TemplateRenderService` | `getTemplate()` | `admin/src/Service/TemplateRenderService.php` |
| 18. Formulaires publics | site | `PublicformsController` | `display()` (`:20-27`) | `site/src/Controller/PublicformsController.php` |
| 18. Formulaires publics | site | `PublicformsModel` | `__construct()` (`:80-160`), `getData()` (`:274-284`), `getPermissions()` (`:241-256`), `buildOrderBy()` (`:168-183`) | `site/src/Model/PublicformsModel.php` |
| 19. Aide contextuelle | site | `CblisthelpController` / `CbstatshelpController` | (`$default_view`) | `site/src/Controller/{Cblisthelp,Cbstatshelp}Controller.php` |
| 19. Aide contextuelle | site | `Cblisthelp\HtmlView` / `Cbstatshelp\HtmlView` | `display()` (`:29-108`) | `site/src/View/{Cblisthelp,Cbstatshelp}/HtmlView.php` |
| 19. Aide contextuelle | site | `EmbeddedListHelpService` / `CbstatsHelpService` | `syntaxUrl()` | `site/src/Service/{EmbeddedListHelpService,CbstatsHelpService}.php` |

## E. Soumission & édition front

| Fonctionnalité | Composant | Classe | Méthode | Fichier |
|---|---|---|---|---|
| 20. Création/édition d'un enregistrement | site | `EditController` | `__construct()`, `display()` (`:664-742`), `save()`/`apply()` (`:222-302`) | `site/src/Controller/EditController.php` |
| 20. Création/édition d'un enregistrement | site | `EditModel` | `getData()` (`:372-766`), `store()` (`:767-2293`), `register()` (`:2295-2557`), `customValidate()`, `validateField()` | `site/src/Model/EditModel.php` |
| 20. Création/édition d'un enregistrement | site | `Edit\HtmlView` | `display()` (`:745-859`), `canNavigateToEditableRecord()`, `isOwnerEditNavigationEnabled()`, `renderBreezingFormsShortcodes()` (`:526-632`) | `site/src/View/Edit/HtmlView.php` |
| 20. Création/édition d'un enregistrement | admin | `FieldValidationService` | `validate()` | `admin/src/Service/FieldValidationService.php` |
| 21. Upload de fichier | site | `EditModel` (trait `PathHelpersTrait`) | `createPathByTokens()`, résolution taille/extension/confinement (`:1148-1358`) | `site/src/Model/EditModel.php`, `site/src/Model/Edit/PathHelpersTrait.php` |
| 21. Upload de fichier | admin | `ContentbuilderngHelper` | `is_internal_path()` | `admin/src/Helper/ContentbuilderngHelper.php` |
| 22. Suppression d'un enregistrement | site | `EditController` | `delete()` (`:309-385`) | `site/src/Controller/EditController.php` |
| 22. Suppression d'un enregistrement | site | `EditModel` | `delete()` (`:2575-2701`), `resolveDeleteOwnerRestriction()` (`:2558-2573`) | `site/src/Model/EditModel.php` |
| 23. Vérification/paiement | plugin:content-verify | `ContentbuilderngVerify` | `onContentPrepare()`, `getValueByLanguage()` (`:46-71`) | `plugins/content/contentbuilderng_verify/src/Extension/ContentbuilderngVerify.php:73-198` |
| 23. Vérification/paiement | site | `VerifyController` | `display()` (`:20-29`) | `site/src/Controller/VerifyController.php` |
| 23. Vérification/paiement | site | `VerifyModel` | (coquille vide) | `site/src/Model/VerifyModel.php` |
| 23. Vérification/paiement | admin | `VerifyModel` | `__construct()` (`:109-467`), `activate()` (`:561-…`), `activate_by_admin()` (`:469-559`) | `admin/src/Model/VerifyModel.php` |
| 23. Vérification/paiement | plugin:verify-paypal | `Paypal` | `onSetup()` (`:105-117`), `onForward()` (`:132-168`), `onVerify()`, `onViewport()` (`:83-90`) | `plugins/contentbuilderng_verify/paypal/src/Extension/Paypal.php` |
| 23. Vérification/paiement | plugin:verify-passthrough | `Passthrough` | `onSetup()`, `onForward()`, `onVerify()` (`:92-103`), `onViewport()` (`:46-53`) | `plugins/contentbuilderng_verify/passthrough/src/Extension/Passthrough.php` |

## F. API et export

| Fonctionnalité | Composant | Classe | Méthode | Fichier |
|---|---|---|---|---|
| 24. API/AJAX générique | site | `ApiController` | `display()`, `handleAction()`, `normalizeRequestedRecordId()` (`:1024-1069`), `assertStateChangingRequestToken()` (`:950-963`), `ratePayload()` (`:508-699`) | `site/src/Controller/ApiController.php` |
| 24. API/AJAX générique | admin | `ApiFieldPermissionService` | `getAllowedReferenceMap()` | `admin/src/Service/ApiFieldPermissionService.php` |
| 24. API/AJAX générique | site | `SparseFieldsetService` | `filter()` | `site/src/Service/SparseFieldsetService.php` |
| 24.1 Notation | admin | `RatingHelper` | `getRating()` (`:189-234`) | `admin/src/Helper/RatingHelper.php` |
| 24.1 Notation | site | `DuplicateKeyViolationHelper` | `isDuplicateKeyViolation()` | `site/src/Helper/DuplicateKeyViolationHelper.php` |
| 25. Export XLSX | site | `ExportController` | `display()` (`:22-35`) | `site/src/Controller/ExportController.php` |
| 25. Export XLSX | site | `ExportModel` | `__construct()`/`getData()` (`:64-491`) | `site/src/Model/ExportModel.php` |
| 25. Export XLSX | site | `Export\RawView` | (rendu `tmpl`) | `site/src/View/Export/RawView.php`, `site/tmpl/export/default.php` |
| 25. Export XLSX | site | `SpreadsheetExportValueHelper` / `ExportFilenameService` | `resolveColumnType()`/`prepareCellValue()`, `build()` | `site/src/Helper/SpreadsheetExportValueHelper.php`, `site/src/Service/ExportFilenameService.php` |

## G. Rendu de contenu — plugins de contenu

| Fonctionnalité | Composant | Classe | Méthode | Fichier |
|---|---|---|---|---|
| 26. `{CBList}` | plugin:content-cblist | `ContentbuilderngList` | `onContentPrepare()` (`:60-169`), `viewExists()` (`:268-282`) | `plugins/content/contentbuilderng_cblist/src/Extension/ContentbuilderngList.php` |
| 26. `{CBList}` | plugin:content-cblist | `TagSyntaxService` / `EmbedOptionsService` | `parse()`, `validationErrors()`, `resolve()` | `plugins/content/contentbuilderng_cblist/src/Service/{TagSyntaxService,EmbedOptionsService}.php` |
| 26. `{CBList}` | site | `EmbeddedListValueService` / `ContentCardService` | `resolve()`, `render()`, `parseTitle()` (`:45-74`) | `site/src/Service/{EmbeddedListValueService,ContentCardService}.php` |
| 27. `{CBStats}` | plugin:content-cbstats | `ContentbuilderngStats` | `onContentPrepare()` (`:75-107,143-457`), `canViewStats()` (`:805-826`) | `plugins/content/contentbuilderng_cbstats/src/Extension/ContentbuilderngStats.php` |
| 27. `{CBStats}` | plugin:content-cbstats | `IdSumService` / `ManualExportService` / `StatsTagValidationService` | `resolveSourceIds()`, `mergePayloads()` | `plugins/content/contentbuilderng_cbstats/src/Service/*.php` |
| 27. `{CBStats}` | site | `StatsService` / `CbStatsTitleSetService` / `CbStatsConfigService` / `EditorialCardService` | `getStatsPayload()`, `containsMarker()`, `transform()` | `site/src/Service/{StatsService,CbStatsTitleSetService,CbStatsConfigService,EditorialCardService}.php` |
| 28. `{CBDownload}` | plugin:content-download | `ContentbuilderngDownload` | `onContentPrepare()` (`:168-546`) | `plugins/content/contentbuilderng_download/src/Extension/ContentbuilderngDownload.php` |
| 29. `{CBImageScale}` | plugin:content-image_scale | `ContentbuilderngImageScale` | `onContentPrepare()` (`:80-823`), `resize_image()` (`:602-628`) | `plugins/content/contentbuilderng_image_scale/src/Extension/ContentbuilderngImageScale.php` |
| 30. Permission Observer | plugin:content-permission_observer | `ContentbuilderngPermissionObserver` | `onContentPrepare()` (`:32-102`) | `plugins/content/contentbuilderng_permission_observer/src/Extension/ContentbuilderngPermissionObserver.php` |

## H. Workflow d'état de liste et extension de soumission

| Fonctionnalité | Composant | Classe | Méthode | Fichier |
|---|---|---|---|---|
| 31. `trash`/`untrash` — routage | site | `EditModel` | `change_list_states()` (`:2727-2859`) | `site/src/Model/EditModel.php` |
| 31. `trash`/`untrash` — routage | admin | `ArticleService` | `createArticle()` (dispatch `onAfterArticleCreation`, `:711-712`) | `admin/src/Service/ArticleService.php` |
| 31.1 `trash` | plugin:listaction-trash | `Trash` | `onBeforeAction()`, `onAfterAction()`, `onAfterArticleCreation()` (`:44-142`) | `plugins/contentbuilderng_listaction/trash/src/Extension/Trash.php` |
| 31.2 `untrash` | plugin:listaction-untrash | `Untrash` | `onBeforeAction()`, `onAfterAction()`, `onAfterArticleCreation()` (`:28-110`) | `plugins/contentbuilderng_listaction/untrash/src/Extension/Untrash.php` |
| 32. Contrat `contentbuilderng_submit` | plugin:submit-submit_sample | `SubmitSample` | `onBeforeSubmit()`, `onAfterSubmit()` (`:24-50`) | `plugins/contentbuilderng_submit/submit_sample/src/Extension/SubmitSample.php` |
| 32. Contrat `contentbuilderng_submit` | site | `EditModel` | `store()` (dispatch `onBeforeSubmit` `:1649-1650`, `onAfterSubmit` `:2044-2045`) | `site/src/Model/EditModel.php` |

## I. Thèmes

| Fonctionnalité | Composant | Classe | Méthode | Fichier |
|---|---|---|---|---|
| 33. Thèmes visuels | plugin:themes-blank | `Blank` | `acceptsThemeEvent()`, `onContentTemplateCss()` etc. | `plugins/contentbuilderng_themes/blank/src/Extension/Blank.php` |
| 33. Thèmes visuels | plugin:themes-dark | `Dark` | idem | `plugins/contentbuilderng_themes/dark/src/Extension/Dark.php` |
| 33. Thèmes visuels | plugin:themes-khepri | `Khepri` | idem (`:133-147`) | `plugins/contentbuilderng_themes/khepri/src/Extension/Khepri.php` |
| 33. Thèmes visuels | plugin:themes-thoth | `Thoth` | idem (Web Asset Manager natif) | `plugins/contentbuilderng_themes/thoth/src/Extension/Thoth.php` |
| 33. Thèmes visuels | admin | `TemplateSampleService` | `getSample()` (`:41-52,149-150`) | `admin/src/Service/TemplateSampleService.php` |
| 33. Thèmes visuels | site/admin | `List\HtmlView` / `Details\HtmlView` / `Edit\HtmlView` | dispatch `onListView*`/`onContentTemplate*`/`onEditableTemplate*` | `site/src/View/{List,Details,Edit}/HtmlView.php`, `admin/src/View/Edit/HtmlView.php` |

## J. Plugin système

| Fonctionnalité | Composant | Classe | Méthode | Fichier |
|---|---|---|---|---|
| 34. Plugin système | plugin:system | `ContentbuilderngSystem` | `onAfterDispatch()` (`:211-437`), `onAfterRoute()` (`:439-554`), `onAfterInitialise()`/`onAfterInitialize()` (`:556-625`), `onBeforeRender()` (`:153-209`), `isSyncMutationRequest()` (`:53-92`), `createMissingArticles()` (`:627-738`), `bootstrapContentbuilder()` | `plugins/system/contentbuilderng_system/src/Extension/ContentbuilderngSystem.php` |

## K. Anomalies structurelles et code potentiellement mort

| Fonctionnalité | Composant | Classe | Méthode | Fichier |
|---|---|---|---|---|
| 35. Dispatcher mort | site | `Dispatcher` (code mort, non chargé) | — | `site/src/Controller/Dispatcher.php` |
| 36. `view=edit` admin cassé | admin | `Edit\HtmlView` (importe `Administrator\Model\EditModel`, classe absente) | `getModel()` (`:269-270`) | `admin/src/View/Edit/HtmlView.php` |
| 36. `view=edit` admin cassé | admin | `Storage\HtmlView` (génère le lien « Preview ») | — | `admin/src/View/Storage/HtmlView.php:426` |
| 37. `MenuService` legacy | admin | `MenuService` | `createBackendMenuItem15()`/`createBackendMenuItem16()`/`createBackendMenuItem3()` (aucun appelant identifié) | `admin/src/Service/MenuService.php` |
| 38. `site/src/Field/*` — champs JForm de menu | admin (rendu back-office via menu Joomla) | `FormsField`, `MultiformsField`, `MenucategoryField`, `MenuformsField`, `MenuinheritField`, `MenulistbuilderField`, `MenunumberField`, `MenuoverrideresetField`, `MenuthemeField`, `CbmenuresetField`, `CategoriesField` | (11 classes JForm `type=`) | `site/src/Field/*.php` |

---

## Notes de couverture

- Les colonnes/tables associées à chaque fonctionnalité (`#__contentbuilderng_*`)
  ne sont pas répétées ici : voir `03-data-model.md` et la colonne « Tables »
  de chaque fiche `04-features.md`.
- Les endpoints AJAX/API (URL, méthode HTTP, paramètres, format de réponse)
  sont détaillés dans `06-api-contracts.md` — cette table donne uniquement
  la classe/méthode qui les traite.
- Les paramètres de plugin (`#__extensions.params`) sont dans
  `08-configuration.md` — non repris ici, seulement les classes qui les
  consomment.
