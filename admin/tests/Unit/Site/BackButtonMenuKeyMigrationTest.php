<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Site;

use PHPUnit\Framework\TestCase;

final class BackButtonMenuKeyMigrationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 4);
    }

    public function testMenuParamHelperNoLongerSupportsLegacyAliasParameter(): void
    {
        $source = $this->read('site/src/Helper/MenuParamHelper.php');

        self::assertStringNotContainsString('?string $legacyKey = null', $source);
        self::assertStringNotContainsString("get(\$legacyKey, null, 'raw')", $source);
        self::assertStringNotContainsString("getMenuParam(\$params, \$legacyKey, null)", $source);
        self::assertStringNotContainsString('return $params->get($key, $default);', $source);
    }

    public function testDispatcherOnlySeedsCanonicalBackButtonInputKey(): void
    {
        $source = $this->read('site/src/Dispatcher/Dispatcher.php');

        self::assertStringContainsString("'cb_show_details_back_button' => null", $source);
        self::assertStringNotContainsString("'show_back_button' => null", $source);
        self::assertStringNotContainsString("\$input->set('show_back_button'", $source);
        self::assertStringNotContainsString("getMenuParam(\$params, 'show_back_button', null)", $source);
    }

    public function testFrontendLayoutsUseCanonicalBackButtonMenuField(): void
    {
        foreach ([
            'site/tmpl/details/default.xml',
            'site/tmpl/edit/default.xml',
            'site/tmpl/latest/latest.xml',
        ] as $relativePath) {
            $source = $this->read($relativePath);
            self::assertStringContainsString('name="cb_show_details_back_button"', $source, $relativePath);
            self::assertStringNotContainsString('name="show_back_button"', $source, $relativePath);
        }
    }

    public function testInstallerMigratesLegacyMenuBackButtonParam(): void
    {
        $source = $this->read('script.php');

        self::assertStringContainsString('$this->migrateLegacyRootMenuParamsToSettings();', $source);
        self::assertStringContainsString('$this->migrateLegacyMenuBackButtonParams();', $source);
        self::assertStringContainsString("'cb_show_details_back_button'", $source);
        self::assertStringContainsString("'show_back_button'", $source);
    }

    public function testInstallerDefinesRootMenuKeysToMoveIntoSettings(): void
    {
        $source = $this->read('script.php');

        self::assertStringContainsString('private const LEGACY_ROOT_MENU_SETTING_KEYS = [', $source);
        self::assertStringContainsString("'form_id'", $source);
        self::assertStringContainsString("'cb_list_limit'", $source);
        self::assertStringContainsString("'cb_show_details_back_button'", $source);
        self::assertStringContainsString("'cb_show_permission_column'", $source);
    }

    public function testInstallerMigratesPackedPayloadsDuringUpdateFlow(): void
    {
        $source = $this->read('script.php');

        self::assertStringContainsString("require_once \$helperBasePath . '/PackedDataMigrationHelper.php';", $source);
        self::assertStringContainsString('$this->migratePackedPayloadsToModernFormat();', $source);
        self::assertStringContainsString('PackedDataMigrationHelper::migratePackedPayloadsStep($db)', $source);
        self::assertStringContainsString('$this->normalizeLegacyBreezingFormsTypes();', $source);
        self::assertStringContainsString("private function normalizeLegacyBreezingFormsTypes(): void", $source);
        self::assertStringContainsString("'com_breezingforms_ng'", $source);
        self::assertStringContainsString("'com_breezingformsng'", $source);
    }

    public function testPackedPayloadMigrationDoesNotReuseStrictRuntimeDecoder(): void
    {
        $source = $this->read('admin/src/Helper/PackedDataMigrationHelper.php');

        self::assertStringContainsString('decodePackedPayloadForMigration', $source);
        self::assertStringNotContainsString('PackedDataHelper::decodePackedData($raw, $sentinel, false)', $source);
        self::assertStringContainsString("@unserialize(\$decoded, ['allowed_classes' => ['stdClass']])", $source);
    }

    public function testMenuOptionsScriptNoLongerTargetsLegacyBackButtonField(): void
    {
        $source = $this->read('media/js/menu-options.js');

        self::assertStringContainsString("updateBooleanField('cb_show_details_back_button'", $source);
        self::assertStringNotContainsString("updateBooleanField('show_back_button'", $source);
        // Direct params targeting (#jform_params_<field>) is legacy; every
        // occurrence must go through the params.settings group instead.
        self::assertSame(
            substr_count($source, '#jform_params_'),
            substr_count($source, '#jform_params_settings_'),
            'menu-options.js must only target #jform_params_settings_* ids'
        );
        self::assertSame(
            substr_count($source, '[name="jform[params]['),
            substr_count($source, '[name="jform[params][settings]['),
            'menu-options.js must only target jform[params][settings] names'
        );
    }

    public function testMenuFieldsReadOnlyFromParamsSettings(): void
    {
        $formsField = $this->read('site/src/Field/FormsField.php');
        $filterField = $this->read('site/src/Field/CbfilterField.php');

        self::assertStringNotContainsString("getValue('form_id', 'params', 0)", $formsField);
        self::assertStringNotContainsString("get('params.form_id', 0)", $formsField);
        self::assertStringNotContainsString("getValue('form_id', 'params', 0)", $filterField);
        self::assertStringNotContainsString("get('params.form_id', 0)", $filterField);
        self::assertStringNotContainsString('#jform_params_form_id', $filterField);
        self::assertStringNotContainsString('[name=\\"jform[params][form_id]\\"]', $filterField);
    }

    public function testPhaseSevenRuntimeLotUsesLocalDatabaseAndMailerAccessors(): void
    {
        $apiController = $this->read('site/src/Controller/ApiController.php');
        $statsService = $this->read('site/src/Service/StatsService.php');
        $editModel = $this->read('site/src/Model/EditModel.php');

        self::assertStringContainsString('private function getDatabase(): DatabaseInterface', $apiController);
        self::assertStringContainsString('return $this->getComponent()->getContainer()->get(DatabaseInterface::class);', $apiController);
        self::assertSame(0, substr_count($apiController, 'Factory::getContainer()->get(DatabaseInterface::class)'));
        self::assertStringContainsString('private function getStatsService(): StatsService', $apiController);

        self::assertStringContainsString('public function __construct(private readonly DatabaseInterface $db)', $statsService);
        self::assertStringNotContainsString('private static function getDatabase(): DatabaseInterface', $statsService);
        self::assertStringNotContainsString('Factory::getContainer()->get(DatabaseInterface::class)', $statsService);
        self::assertStringContainsString('public function isFormDebugEnabled(int $formId): bool', $statsService);

        self::assertStringContainsString('private function getDatabase(): DatabaseInterface', $editModel);
        self::assertStringContainsString('private function createMailer()', $editModel);
        self::assertStringContainsString('return $this->getComponent()->getContainer()->get(DatabaseInterface::class);', $editModel);
        self::assertStringContainsString('return $this->getComponent()->getContainer()->get(MailerFactoryInterface::class)->createMailer();', $editModel);
        self::assertSame(0, substr_count($editModel, 'Factory::getContainer()->get(DatabaseInterface::class)'));
        self::assertSame(0, substr_count($editModel, 'Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer()'));
    }

    public function testSiteModelsNoLongerResolveApplicationViaFactory(): void
    {
        foreach ([
            'site/src/Model/EditModel.php',
            'site/src/Model/DetailsModel.php',
            'site/src/Model/ListModel.php',
            'site/src/Model/PublicformsModel.php',
            'site/src/Model/ExportModel.php',
        ] as $relativePath) {
            $source = $this->read($relativePath);
            self::assertStringNotContainsString('Factory::getApplication()', $source, $relativePath);
            self::assertStringContainsString('CMSApplicationInterface::class', $source, $relativePath);
        }
    }

    public function testSiteViewsUseLocalApplicationAndDatabaseAccessors(): void
    {
        $editView = $this->read('site/src/View/Edit/HtmlView.php');
        $detailsView = $this->read('site/src/View/Details/HtmlView.php');
        $listView = $this->read('site/src/View/List/HtmlView.php');

        self::assertStringContainsString('private function getApp(): SiteApplication', $editView);
        self::assertStringContainsString('private function getDatabase(): DatabaseInterface', $editView);
        self::assertStringNotContainsString('Factory::getApplication()', $editView);
        self::assertStringNotContainsString('Factory::getContainer()->get(DatabaseInterface::class)', $editView);

        self::assertStringContainsString('private function getApp(): SiteApplication', $detailsView);
        self::assertStringContainsString('private function getDatabase(): DatabaseInterface', $detailsView);
        self::assertStringNotContainsString('Factory::getApplication()', $detailsView);
        self::assertStringNotContainsString('Factory::getContainer()->get(DatabaseInterface::class)', $detailsView);

        self::assertStringContainsString('private function getApp(): SiteApplication', $listView);
        self::assertStringNotContainsString('Factory::getApplication()', $listView);
    }

    public function testSiteFieldsControllersAndMenuHelperNoLongerUseGlobalFactoryAccessors(): void
    {
        foreach ([
            'site/src/Field/FormsField.php',
            'site/src/Field/CbfilterField.php',
            'site/src/Field/CategoriesField.php',
            'site/src/Field/MultiformsField.php',
            'site/src/Field/CbmenuresetField.php',
            'site/src/Controller/ListController.php',
            'site/src/Controller/DetailsController.php',
            'site/src/Helper/MenuParamHelper.php',
        ] as $relativePath) {
            $source = $this->read($relativePath);
            self::assertStringNotContainsString('Factory::getApplication()', $source, $relativePath);
            self::assertStringNotContainsString('Factory::getContainer()->get(DatabaseInterface::class)', $source, $relativePath);
        }

        $menuParamHelper = $this->read('site/src/Helper/MenuParamHelper.php');
        self::assertStringContainsString('public static function hasExplicitListLimitRequest($app): bool', $menuParamHelper);
        self::assertStringContainsString('public static function resolvePageHeadingToggle($app, $value, ?int $globalValue = null, int $default = 1): int', $menuParamHelper);
    }

    public function testSiteTraitAndDebugHelperNoLongerUseGlobalFactoryAccessors(): void
    {
        $ownershipTrait = $this->read('site/src/Model/Edit/OwnershipTrait.php');
        $debugHelper = $this->read('site/src/Helper/DebugPermissionHelper.php');

        self::assertStringNotContainsString('Factory::getApplication()', $ownershipTrait);
        self::assertStringNotContainsString('Factory::getContainer()', $ownershipTrait);
        self::assertStringContainsString("\$this->app->bootComponent('com_contentbuilderng')", $ownershipTrait);
        self::assertStringContainsString("\$this->getComponent()->getContainer()->get(CacheControllerFactoryInterface::class)", $ownershipTrait);

        self::assertStringNotContainsString('Factory::getApplication()', $debugHelper);
        self::assertStringContainsString('PermissionService $permissionService,', $debugHelper);
        self::assertStringContainsString('$app,', $debugHelper);
        self::assertStringContainsString('private static function authorizeNewForForm(PermissionService $permissionService, $app, int $formId, bool $frontend): bool', $debugHelper);
    }

    public function testAdminEditViewUsesLocalApplicationAndDatabaseAccessors(): void
    {
        $source = $this->read('admin/src/View/Edit/HtmlView.php');

        self::assertStringContainsString('private function getApp(): CMSApplicationInterface', $source);
        self::assertStringContainsString('private function getDatabase(): DatabaseInterface', $source);
        self::assertStringContainsString('private function getDispatcher()', $source);
        self::assertStringNotContainsString('Factory::getApplication()', $source);
        self::assertStringNotContainsString('Factory::getContainer()', $source);
    }

    public function testAdminFormViewUsesLocalApplicationAndDatabaseAccessors(): void
    {
        $source = $this->read('admin/src/View/Form/HtmlView.php');

        self::assertStringContainsString('private function getApp(): CMSApplication', $source);
        self::assertStringContainsString('private function getComponent(): ContentbuilderngComponent', $source);
        self::assertStringContainsString('private function getDatabase(): DatabaseInterface', $source);
        self::assertStringNotContainsString('Factory::getApplication()', $source);
        self::assertStringNotContainsString('Factory::getContainer()', $source);
    }

    public function testAdminStorageModelUsesLocalApplicationAndDatabaseAccessors(): void
    {
        $source = $this->read('admin/src/Model/StorageModel.php');

        self::assertStringContainsString('private function getComponent(): ContentbuilderngComponent', $source);
        self::assertStringContainsString('private function getApp(): CMSApplication', $source);
        // Core BaseDatabaseModel::getDatabase() (injected by the MVCFactory) is
        // the sanctioned accessor; a local wrapper is not required.
        self::assertStringContainsString('$this->getDatabase()', $source);
        self::assertStringNotContainsString('Factory::getApplication()', $source);
        self::assertStringNotContainsString('Factory::getContainer()', $source);
    }

    public function testAdminStorageControllerUsesLocalApplicationAndDatabaseAccessors(): void
    {
        $source = $this->read('admin/src/Controller/StorageController.php');

        self::assertStringContainsString('private function getDatabase(): DatabaseInterface', $source);
        self::assertStringContainsString('return $this->getApp()->bootComponent(\'com_contentbuilderng\')->getContainer()->get(DatabaseInterface::class);', $source);
        self::assertStringNotContainsString('Factory::getApplication()', $source);
        self::assertStringNotContainsString('Factory::getContainer()', $source);
    }

    public function testAdminFormControllerUsesLocalApplicationAndDatabaseAccessors(): void
    {
        $source = $this->read('admin/src/Controller/FormController.php');

        self::assertStringContainsString('private function getDatabase(): DatabaseInterface', $source);
        self::assertStringContainsString('return $this->getApp()->bootComponent(\'com_contentbuilderng\')->getContainer()->get(DatabaseInterface::class);', $source);
        self::assertStringNotContainsString('Factory::getApplication()', $source);
        self::assertStringNotContainsString('Factory::getContainer()', $source);
    }

    public function testAdminVerifyModelUsesLocalApplicationMailerAndUserFactoryAccessors(): void
    {
        $source = $this->read('admin/src/Model/VerifyModel.php');

        self::assertStringContainsString('private function getComponent(): ContentbuilderngComponent', $source);
        self::assertStringContainsString('private function createMailer()', $source);
        self::assertStringContainsString('private function getUserFactory(): UserFactoryInterface', $source);
        self::assertStringContainsString('CMSApplicationInterface::class', $source);
        self::assertStringNotContainsString('Factory::getApplication()', $source);
        self::assertStringNotContainsString('Factory::getContainer()', $source);
    }

    public function testAdditionalAdminControllersNoLongerUseGlobalFactoryAccessors(): void
    {
        foreach ([
            'admin/src/Controller/ConfigtransferController.php',
            'admin/src/Controller/DisplayController.php',
            'admin/src/Controller/AboutController.php',
            'admin/src/Controller/DatatableController.php',
            'admin/src/Controller/StoragefieldController.php',
        ] as $relativePath) {
            $source = $this->read($relativePath);
            self::assertStringNotContainsString('Factory::getApplication()', $source, $relativePath);
            self::assertStringNotContainsString('Factory::getContainer()', $source, $relativePath);
        }

        $datatableController = $this->read('admin/src/Controller/DatatableController.php');
        self::assertStringContainsString('private function getDatatableService(): DatatableService', $datatableController);

        $storagefieldController = $this->read('admin/src/Controller/StoragefieldController.php');
        self::assertStringContainsString('private function getStorageFieldService(): StorageFieldService', $storagefieldController);
    }

    public function testAdditionalAdminModelsNoLongerUseGlobalFactoryAccessors(): void
    {
        foreach ([
            'admin/src/Model/FormsModel.php',
            'admin/src/Model/StoragesModel.php',
            'admin/src/Model/StoragefieldsModel.php',
            'admin/src/Model/UsersModel.php',
            'admin/src/Model/UserModel.php',
            'admin/src/Model/ElementsModel.php',
            'admin/src/Model/ElementoptionsModel.php',
        ] as $relativePath) {
            $source = $this->read($relativePath);
            self::assertStringNotContainsString('Factory::getApplication()', $source, $relativePath);
            self::assertStringNotContainsString('Factory::getContainer()', $source, $relativePath);
        }

        $formsModel = $this->read('admin/src/Model/FormsModel.php');
        self::assertStringContainsString('private function getApp(): CMSApplication', $formsModel);
        self::assertStringContainsString('private function getComponent(): ContentbuilderngComponent', $formsModel);

        $elementsModel = $this->read('admin/src/Model/ElementsModel.php');
        self::assertStringContainsString('private function getApp(): CMSApplication', $elementsModel);

        $elementoptionsModel = $this->read('admin/src/Model/ElementoptionsModel.php');
        self::assertStringContainsString('private function getComponent(): ContentbuilderngComponent', $elementoptionsModel);
        self::assertStringContainsString('$db = $this->getDatabase();', $elementoptionsModel);
    }

    public function testAdditionalAdminViewsNoLongerUseGlobalFactoryAccessors(): void
    {
        foreach ([
            'admin/src/View/About/HtmlView.php',
            'admin/src/View/Configtransfer/HtmlView.php',
            'admin/src/View/Storage/HtmlView.php',
            'admin/src/View/Storages/HtmlView.php',
            'admin/src/View/Forms/HtmlView.php',
            'admin/src/View/User/HtmlView.php',
            'admin/src/View/Users/HtmlView.php',
        ] as $relativePath) {
            $source = $this->read($relativePath);
            self::assertStringNotContainsString('Factory::getApplication()', $source, $relativePath);
            self::assertStringNotContainsString('Factory::getContainer()', $source, $relativePath);
        }

        $aboutView = $this->read('admin/src/View/About/HtmlView.php');
        self::assertStringContainsString('private function getDatabase(): DatabaseInterface', $aboutView);
        self::assertStringContainsString('private function getLanguage(): Language', $aboutView);

        $configtransferView = $this->read('admin/src/View/Configtransfer/HtmlView.php');
        self::assertStringContainsString('private function getDatabase(): DatabaseInterface', $configtransferView);

        $storageView = $this->read('admin/src/View/Storage/HtmlView.php');
        self::assertStringContainsString('private function getDatabase(): DatabaseInterface', $storageView);

        $formsView = $this->read('admin/src/View/Forms/HtmlView.php');
        self::assertStringContainsString('private function getApp(): CMSApplicationInterface', $formsView);
    }

    public function testAdminFormsControllerAndFormModelNoLongerUseGlobalFactoryAccessors(): void
    {
        $formsController = $this->read('admin/src/Controller/FormsController.php');
        self::assertStringContainsString('private function getDatabase(): DatabaseInterface', $formsController);
        self::assertStringNotContainsString('Factory::getApplication()', $formsController);
        self::assertStringNotContainsString('Factory::getContainer()', $formsController);

        $formModel = $this->read('admin/src/Model/FormModel.php');
        self::assertStringContainsString('private function getComponent(): ContentbuilderngComponent', $formModel);
        self::assertStringContainsString('private function getDispatcher()', $formModel);
        self::assertStringContainsString('CMSApplicationInterface::class', $formModel);
        self::assertStringNotContainsString('Factory::getApplication()', $formModel);
        self::assertStringNotContainsString('Factory::getContainer()', $formModel);

        $categoryField = $this->read('admin/src/Model/Category_fields/categoryeditcb.php');
        self::assertStringContainsString('private function getDatabase(): DatabaseInterface', $categoryField);
        self::assertStringNotContainsString('Factory::getApplication()', $categoryField);
        self::assertStringNotContainsString('Factory::getContainer()', $categoryField);
    }

    public function testTemplatePrepareAndApiFieldServicesUseInjectedDependencies(): void
    {
        $templatePrepareHelper = $this->read('admin/src/Helper/TemplatePrepareHelper.php');
        self::assertStringContainsString('CMSApplicationInterface $app', $templatePrepareHelper);
        self::assertStringNotContainsString('Factory::getApplication()', $templatePrepareHelper);

        $formResolverService = $this->read('admin/src/Service/FormResolverService.php');
        self::assertStringContainsString('private readonly CMSApplicationInterface $app', $formResolverService);
        self::assertStringNotContainsString('Factory::getApplication()', $formResolverService);

        $runtimeUtilityService = $this->read('admin/src/Service/RuntimeUtilityService.php');
        self::assertStringContainsString('private readonly CMSApplicationInterface $app', $runtimeUtilityService);
        self::assertStringNotContainsString('Factory::getApplication()', $runtimeUtilityService);

        $apiFieldPermissionService = $this->read('admin/src/Service/ApiFieldPermissionService.php');
        self::assertStringContainsString('private readonly DatabaseInterface $db', $apiFieldPermissionService);
        self::assertStringNotContainsString('Factory::getContainer()', $apiFieldPermissionService);

        $apiController = $this->read('site/src/Controller/ApiController.php');
        self::assertStringContainsString('private function getApiFieldPermissionService(): ApiFieldPermissionService', $apiController);

        $statsService = $this->read('site/src/Service/StatsService.php');
        self::assertStringContainsString('private function getApiFieldPermissionService(): ApiFieldPermissionService', $statsService);

        $articleService = $this->read('admin/src/Service/ArticleService.php');
        self::assertStringContainsString('private readonly DatabaseInterface $db', $articleService);
        self::assertStringContainsString('private readonly CacheControllerFactoryInterface $cacheControllerFactory', $articleService);
        self::assertStringNotContainsString('Factory::getApplication()', $articleService);
        self::assertStringNotContainsString('Factory::getContainer()', $articleService);

        $repairWorkflowService = $this->read('admin/src/Service/RepairWorkflowService.php');
        self::assertStringContainsString('private readonly AdministratorApplication $app', $repairWorkflowService);
        self::assertStringContainsString('private readonly DatabaseInterface $db', $repairWorkflowService);
        self::assertStringContainsString('private readonly FormSupportService $formSupportService', $repairWorkflowService);
        self::assertStringNotContainsString('Factory::getApplication()', $repairWorkflowService);
        self::assertStringNotContainsString('Factory::getContainer()', $repairWorkflowService);

        $aboutController = $this->read('admin/src/Controller/AboutController.php');
        self::assertStringContainsString('private function getRepairWorkflowService(): RepairWorkflowService', $aboutController);
        self::assertStringContainsString('private function getConfigExportService(): ConfigExportService', $aboutController);
        self::assertStringContainsString('private function getConfigImportService(): ConfigImportService', $aboutController);

        $configExportService = $this->read('admin/src/Service/ConfigExportService.php');
        self::assertStringContainsString('private readonly DatabaseInterface $db', $configExportService);
        self::assertStringNotContainsString('Factory::getContainer()', $configExportService);

        $configImportService = $this->read('admin/src/Service/ConfigImportService.php');
        self::assertStringContainsString('private readonly DatabaseInterface $db', $configImportService);
        self::assertStringContainsString('private readonly CMSApplicationInterface $app', $configImportService);
        self::assertStringNotContainsString('Factory::getApplication()', $configImportService);
        self::assertStringNotContainsString('Factory::getContainer()', $configImportService);
    }

    public function testAdminRuntimeContextAndTypesNoLongerUseFactoryGlobals(): void
    {
        $component = $this->read('admin/src/Extension/ContentbuilderngComponent.php');
        self::assertStringContainsString('RuntimeContextHelper::initialize($app, $db);', $component);
        self::assertStringNotContainsString('Factory::getApplication()', $component);

        foreach ([
            'admin/src/Helper/DatabaseAuditHelper.php',
            'admin/src/Helper/DatabaseRepairHelper.php',
            'admin/src/Helper/FormSourceFactory.php',
            'admin/src/Helper/Logger.php',
            'admin/src/Helper/PackedDataMigrationHelper.php',
            'admin/src/Helper/RatingHelper.php',
            'admin/src/types/com_contentbuilderng.php',
            'admin/src/types/com_breezingformsng.php',
        ] as $relativePath) {
            $source = $this->read($relativePath);
            self::assertStringNotContainsString('Factory::getApplication()', $source, $relativePath);
            self::assertStringNotContainsString('Factory::getContainer()', $source, $relativePath);
            self::assertStringContainsString('RuntimeContextHelper', $source, $relativePath);
        }

        // PluginInstallerService is fully closure-injected (db/logger/safe/cache
        // provided by script.php) and also runs during installation, before the
        // component boots — it must not depend on RuntimeContextHelper.
        $pluginInstallerService = $this->read('admin/src/Service/PluginInstallerService.php');
        self::assertStringNotContainsString('Factory::getApplication()', $pluginInstallerService);
        self::assertStringNotContainsString('Factory::getContainer()', $pluginInstallerService);

        $permissionService = $this->read('admin/src/Service/PermissionService.php');
        self::assertStringContainsString('public static function createFromRuntimeContext(): self', $permissionService);
        self::assertStringContainsString('private readonly CMSApplicationInterface $app', $permissionService);
        self::assertStringContainsString('private readonly DatabaseInterface $db', $permissionService);
        self::assertStringNotContainsString('Factory::getApplication()', $permissionService);
        self::assertStringNotContainsString('Factory::getContainer()', $permissionService);
    }

    public function testRuntimeNoLongerAcceptsLegacyBreezingFormsTypeAliases(): void
    {
        foreach ([
            'admin/src/Controller/FormController.php',
            'admin/src/Service/FormAuditService.php',
            'admin/src/Helper/Audit/BfFieldSyncAuditHelper.php',
            'admin/src/Service/FormSupportService.php',
            'admin/src/View/Edit/HtmlView.php',
            'site/src/View/Edit/HtmlView.php',
            'admin/tmpl/form/edit.php',
            'admin/tmpl/form/edit_footer_scripts.php',
            'site/tmpl/list/default.php',
        ] as $relativePath) {
            $source = $this->read($relativePath);
            self::assertStringNotContainsString('com_breezingforms_ng', $source, $relativePath);
            self::assertStringContainsString('com_breezingformsng', $source, $relativePath);
        }

        $formModel = $this->read('admin/src/Model/FormModel.php');
        self::assertStringContainsString("['com_breezingformsng']", $formModel);
        self::assertStringNotContainsString("['com_breezingforms']", $formModel);
    }

    public function testFrontendMenuXmlFilesNoLongerContainJoomla16CompatibilityComment(): void
    {
        foreach ([
            'site/tmpl/details/default.xml',
            'site/tmpl/details/print.xml',
            'site/tmpl/edit/default.xml',
            'site/tmpl/latest/latest.xml',
            'site/tmpl/list/default.xml',
            'site/tmpl/list/listone.xml',
            'site/tmpl/list/listtwo.xml',
            'site/tmpl/list/listthree.xml',
            'site/tmpl/publicforms/default.xml',
        ] as $relativePath) {
            $source = $this->read($relativePath);
            self::assertStringNotContainsString('Joomla! 1.6 compatibility', $source, $relativePath);
        }
    }

    private function read(string $relativePath): string
    {
        $contents = \file_get_contents($this->root . '/' . $relativePath);
        self::assertIsString($contents, 'Unable to read ' . $relativePath);

        return $contents;
    }
}
