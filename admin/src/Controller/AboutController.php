<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\Controller;

\defined('_JEXEC') or die('Restricted access');

use CB\Component\Contentbuilderng\Administrator\Helper\DatabaseAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Audit\DebugModeAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Audit\GeneratedArticleCategoryAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;
use CB\Component\Contentbuilderng\Administrator\Helper\Audit\StaleInstallerTempAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Audit\UploadDirectoryProtectionAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use CB\Component\Contentbuilderng\Administrator\Model\StorageModel;
use CB\Component\Contentbuilderng\Administrator\Service\ConfigExportService;
use CB\Component\Contentbuilderng\Administrator\Service\ConfigImportService;
use CB\Component\Contentbuilderng\Administrator\Service\DatatableService;
use CB\Component\Contentbuilderng\Administrator\Service\FormSupportService;
use CB\Component\Contentbuilderng\Administrator\Service\RepairWorkflowService;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

use CB\Component\Contentbuilderng\Administrator\Controller\Traits\ComponentAccessTrait;
use CB\Component\Contentbuilderng\Administrator\Controller\Traits\SafeErrorMessageTrait;

final class AboutController extends BaseController
{
    use ComponentAccessTrait;
    use SafeErrorMessageTrait;

    protected $default_view = 'about';

    private const CONFIG_TRANSFER_SELECTION_STATE_KEY = 'com_contentbuilderng.configtransfer.selection';
    private const ABOUT_LOG_FILES = ['com_contentbuilderng.log'];
    private const ABOUT_LOG_TAIL_BYTES = 262144;
    private const DIRECT_AUDIT_REPAIR_ISSUES = [
        'duplicate_indexes',
        'historical_tables',
        'historical_menu_entries',
        'table_encoding',
        'packed_data',
        'audit_columns',
        'form_audit_columns',
        'plugin_duplicates',
        'bf_field_sync',
        'element_reference_consistency',
        'content_record_duplicates',
        'bf_content_record_orphans',
        'stale_language_files',
    ];

    private function getApp(): AdministratorApplication
    {
        $app = $this->app;

        if (!$app instanceof AdministratorApplication) {
            throw new \RuntimeException('Unexpected application instance');
        }

        return $app;
    }

    private function getCurrentUserId(): int
    {
        return (int) ($this->getApp()->getIdentity()->id ?? 0);
    }

    private function getComponent(): ContentbuilderngComponent
    {
        $component = $this->getApp()->bootComponent('com_contentbuilderng');

        if (!$component instanceof ContentbuilderngComponent) {
            throw new \RuntimeException('Unexpected component instance');
        }

        return $component;
    }

    private function getRepairWorkflowService(): RepairWorkflowService
    {
        $container = $this->getComponent()->getContainer();

        return new RepairWorkflowService(
            $this->getApp(),
            $container->get(DatabaseInterface::class),
            $container->get(FormSupportService::class)
        );
    }

    private function getConfigExportService(): ConfigExportService
    {
        $container = $this->getComponent()->getContainer();

        return new ConfigExportService($container->get(DatabaseInterface::class));
    }

    private function getConfigImportService(): ConfigImportService
    {
        $container = $this->getComponent()->getContainer();

        return new ConfigImportService($container->get(DatabaseInterface::class), $this->getApp());
    }

    private function getAuthorizedApplication(): AdministratorApplication
    {
        $app = $this->getApp();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_contentbuilderng')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return $app;
    }

    private function getJoomlaLocalDateTime(): string
    {
        $app = $this->getApp();
        $offset = (string) $app->get('offset', 'UTC');

        try {
            $timezone = new \DateTimeZone($offset !== '' ? $offset : 'UTC');
        } catch (\Throwable) {
            $timezone = new \DateTimeZone('UTC');
        }

        return (new \DateTimeImmutable('now', $timezone))->format('Y-m-d H:i:s');
    }

    private function isAjaxCall(): bool
    {
        return $this->input->getInt('cb_ajax', 0) === 1;
    }

    private function respondAjax(bool $success, string $message): void
    {
        echo new JsonResponse(['ok' => $success], $message, !$success);
        $this->getApp()->close();
    }

    // -------------------------------------------------------------------------
    // Audit
    // -------------------------------------------------------------------------

    public function repairAuditIssue(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();
        $issue = trim((string) $this->input->post->get('repair_issue', '', 'cmd'));

        try {
            if (!in_array($issue, self::DIRECT_AUDIT_REPAIR_ISSUES, true)) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_REPAIR_UNAVAILABLE'));
            }

            $service = $this->getRepairWorkflowService();
            $result = $service->executeStep($issue);

            $report = DatabaseAuditHelper::run();
            $app->setUserState('com_contentbuilderng.about.audit', $report);
            $service->logStepResult($issue, 'apply', $result);

            $message = trim((string) ($result['summary'] ?? ''));
            if ($message === '') {
                $message = Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_REPAIR_COMPLETED');
            }

            $level = strtolower((string) ($result['level'] ?? 'message'));
            if ($this->isAjaxCall()) {
                $this->respondAjax(!in_array($level, ['error', 'danger'], true), $message);
                return;
            }

            $this->setMessage($message, $level);
        } catch (\Throwable $e) {
            $message = Text::sprintf(
                'COM_CONTENTBUILDERNG_ABOUT_AUDIT_REPAIR_FAILED',
                $this->safeErrorMessage($e)
            );

            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $message);
                return;
            }

            $this->setMessage($message, 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    public function repairLegacyStorageIndexes(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();
        $storageId = $this->input->post->getInt('repair_storage_id', 0);

        try {
            if ($storageId <= 0) {
                throw new \RuntimeException(Text::_('JERROR_NO_ITEMS_SELECTED'));
            }

            $renamed = $this->getComponent()->getContainer()
                ->get(DatatableService::class)
                ->repairLegacyAuditIndexes($storageId);

            $report = DatabaseAuditHelper::run();
            $app->setUserState('com_contentbuilderng.about.audit', $report);
            $this->getRepairWorkflowService()->logAuditReport($report);

            $message = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_LEGACY_STORAGE_INDEXES_REPAIRED', $renamed);
            if ($this->isAjaxCall()) {
                $this->respondAjax(true, $message);
                return;
            }

            $this->setMessage($message, 'message');
        } catch (\Throwable $e) {
            $message = Text::sprintf(
                'COM_CONTENTBUILDERNG_ABOUT_AUDIT_LEGACY_STORAGE_INDEXES_REPAIR_FAILED',
                $this->safeErrorMessage($e)
            );
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $message);
                return;
            }

            $this->setMessage($message, 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    public function repairUploadDirectoryProtection(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();

        try {
            $summary = UploadDirectoryProtectionAuditHelper::repair(
                $this->getComponent()->getContainer()->get(DatabaseInterface::class)
            );

            $report = DatabaseAuditHelper::run();
            $app->setUserState('com_contentbuilderng.about.audit', $report);
            $this->getRepairWorkflowService()->logAuditReport($report);

            $message = Text::sprintf(
                'COM_CONTENTBUILDERNG_DB_REPAIR_UPLOAD_PROTECTION_SUMMARY',
                (int) ($summary['scanned'] ?? 0),
                (int) ($summary['repaired'] ?? 0),
                (int) ($summary['errors'] ?? 0)
            );

            if ($this->isAjaxCall()) {
                $this->respondAjax(true, $message);
                return;
            }

            $this->setMessage($message, (int) ($summary['errors'] ?? 0) > 0 ? 'warning' : 'message');
        } catch (\Throwable $e) {
            $message = Text::sprintf(
                'COM_CONTENTBUILDERNG_ABOUT_AUDIT_UPLOAD_PROTECTION_REPAIR_FAILED',
                $this->safeErrorMessage($e)
            );

            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $message);
                return;
            }

            $this->setMessage($message, 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    public function repairMissingStorageTable(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();
        $storageId = $this->input->post->getInt('repair_storage_id', 0);

        try {
            if ($storageId <= 0) {
                throw new \RuntimeException(Text::_('JERROR_NO_ITEMS_SELECTED'));
            }

            $model = $this->getModel('Storage', 'Administrator', ['ignore_request' => true]);

            if (!$model instanceof StorageModel) {
                throw new \RuntimeException('StorageModel not found');
            }

            $storage = $model->getItem($storageId);

            if (!$storage || (int) ($storage->bytable ?? 0) > 0 || trim((string) ($storage->name ?? '')) === '') {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE_TABLE_REPAIR_INVALID_STORAGE'));
            }

            $model->ensureDataTable($storageId);
            $this->getComponent()->getContainer()->get(DatatableService::class)->syncColumnsFromFields($storageId);

            $tableName = $this->getComponent()->getContainer()
                ->get(DatabaseInterface::class)
                ->getPrefix() . trim((string) $storage->name);

            $tableList = $this->getComponent()->getContainer()
                ->get(DatabaseInterface::class)
                ->getTableList();

            if (!in_array($tableName, $tableList, true)) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE_TABLE_REPAIR_NOT_CREATED'));
            }

            $report = DatabaseAuditHelper::run();
            $app->setUserState('com_contentbuilderng.about.audit', $report);
            $this->getRepairWorkflowService()->logAuditReport($report);

            $message = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE_TABLE_REPAIRED', $tableName);
            if ($this->isAjaxCall()) {
                $this->respondAjax(true, $message);
                return;
            }

            $this->setMessage($message, 'message');
        } catch (\Throwable $e) {
            $message = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STORAGE_TABLE_REPAIR_FAILED', $this->safeErrorMessage($e));
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $message);
                return;
            }

            $this->setMessage($message, 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    public function repairFormThemePlugin(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();
        $formId = $this->input->post->getInt('form_id', 0);

        try {
            if ($formId <= 0) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_AUDIT_THEME_REPAIR_INVALID'));
            }

            $db = $this->getComponent()->getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__contentbuilderng_forms'))
                ->where($db->quoteName('id') . ' = ' . $formId);
            $db->setQuery($query);

            if (!$db->loadResult()) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_AUDIT_THEME_REPAIR_INVALID'));
            }

            $update = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_forms'))
                ->set($db->quoteName('theme_plugin') . ' = ' . $db->quote('thoth'))
                ->set($db->quoteName('modified') . ' = ' . $db->quote((new Date())->toSql()))
                ->set($db->quoteName('modified_by') . ' = ' . $this->getCurrentUserId())
                ->where($db->quoteName('id') . ' = ' . $formId);
            $db->setQuery($update);
            $db->execute();

            $report = DatabaseAuditHelper::run();
            $app->setUserState('com_contentbuilderng.about.audit', $report);
            $this->getRepairWorkflowService()->logAuditReport($report);

            $message = Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_THEME_REPAIRED', 'Thoth');
            if ($this->isAjaxCall()) {
                $this->respondAjax(true, $message);
                return;
            }

            $this->setMessage($message, 'message');
        } catch (\Throwable $e) {
            $message = Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_THEME_REPAIR_FAILED', $this->safeErrorMessage($e));
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $message);
                return;
            }

            $this->setMessage($message, 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    public function repairFormEditableTemplate(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();
        $formId = $this->input->post->getInt('form_id', 0);

        try {
            $formSupportService = $this->getComponent()->getContainer()->get(FormSupportService::class);
            $formName = $formSupportService->regenerateEditableTemplate($formId, $this->getCurrentUserId());

            $report = DatabaseAuditHelper::run();
            $app->setUserState('com_contentbuilderng.about.audit', $report);
            $this->getRepairWorkflowService()->logAuditReport($report);

            $message = Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_EDITABLE_TEMPLATE_REPAIRED', $formName);
            if ($this->isAjaxCall()) {
                $this->respondAjax(true, $message);
                return;
            }

            $this->setMessage($message, 'message');
        } catch (\Throwable $e) {
            $message = Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_EDITABLE_TEMPLATE_REPAIR_FAILED', $this->safeErrorMessage($e));
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $message);
                return;
            }

            $this->setMessage($message, 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    public function repairFormDetailsTemplate(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();
        $formId = $this->input->post->getInt('form_id', 0);

        try {
            $formSupportService = $this->getComponent()->getContainer()->get(FormSupportService::class);
            $formName = $formSupportService->regenerateDetailsTemplate($formId, $this->getCurrentUserId());

            $report = DatabaseAuditHelper::run();
            $app->setUserState('com_contentbuilderng.about.audit', $report);
            $this->getRepairWorkflowService()->logAuditReport($report);

            $message = Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_DETAILS_TEMPLATE_REPAIRED', $formName);
            if ($this->isAjaxCall()) {
                $this->respondAjax(true, $message);
                return;
            }

            $this->setMessage($message, 'message');
        } catch (\Throwable $e) {
            $message = Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_DETAILS_TEMPLATE_REPAIR_FAILED', $this->safeErrorMessage($e));
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $message);
                return;
            }

            $this->setMessage($message, 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    public function repairFormTemplates(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();
        $formId = $this->input->post->getInt('form_id', 0);

        try {
            $formSupportService = $this->getComponent()->getContainer()->get(FormSupportService::class);
            $formName = $formSupportService->regenerateBothTemplates($formId, $this->getCurrentUserId());

            $report = DatabaseAuditHelper::run();
            $app->setUserState('com_contentbuilderng.about.audit', $report);
            $this->getRepairWorkflowService()->logAuditReport($report);

            $message = Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_TEMPLATES_REPAIRED', $formName);
            if ($this->isAjaxCall()) {
                $this->respondAjax(true, $message);
                return;
            }

            $this->setMessage($message, 'message');
        } catch (\Throwable $e) {
            $message = Text::sprintf('COM_CONTENTBUILDERNG_AUDIT_TEMPLATES_REPAIR_FAILED', $this->safeErrorMessage($e));
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $message);
                return;
            }

            $this->setMessage($message, 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    public function repairFormUnknownMarker(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();
        $formId = $this->input->post->getInt('form_id', 0);
        $markerName = $this->input->post->getString('marker_name', '');
        $templateType = $this->input->post->getCmd('template_type', '');

        try {
            $formSupportService = $this->getComponent()->getContainer()->get(FormSupportService::class);
            $formName = $formSupportService->removeUnknownTemplateMarker(
                $formId,
                $templateType,
                $markerName,
                $this->getCurrentUserId()
            );
            $templateLabel = Text::_($templateType === 'details'
                ? 'COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY'
                : 'COM_CONTENTBUILDERNG_TAB_EDIT_DISPLAY');

            $report = DatabaseAuditHelper::run();
            $app->setUserState('com_contentbuilderng.about.audit', $report);
            $this->getRepairWorkflowService()->logAuditReport($report);

            $message = Text::sprintf(
                'COM_CONTENTBUILDERNG_AUDIT_UNKNOWN_MARKER_REPAIRED',
                $markerName,
                $templateLabel
            );
            if ($this->isAjaxCall()) {
                $this->respondAjax(true, $message);
                return;
            }

            $this->setMessage($message, 'message');
        } catch (\Throwable $e) {
            $message = Text::sprintf(
                'COM_CONTENTBUILDERNG_AUDIT_UNKNOWN_MARKER_REPAIR_FAILED',
                $this->safeErrorMessage($e)
            );
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $message);
                return;
            }

            $this->setMessage($message, 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    public function repairGeneratedArticleCategories(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();
        $formIds = array_values(array_unique(array_filter(
            array_map('intval', (array) $this->input->post->get('form_ids', [], 'array')),
            static fn(int $formId): bool => $formId > 0
        )));

        try {
            if ($formIds === []) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_GENERATED_ARTICLE_CATEGORIES_NO_SELECTION'));
            }

            $summary = GeneratedArticleCategoryAuditHelper::repair(
                $this->getComponent()->getContainer()->get(DatabaseInterface::class),
                $formIds
            );

            $report = DatabaseAuditHelper::run();
            $app->setUserState('com_contentbuilderng.about.audit', $report);
            $this->getRepairWorkflowService()->logAuditReport($report);

            $message = Text::sprintf(
                'COM_CONTENTBUILDERNG_GENERATED_ARTICLE_CATEGORIES_REPAIR_SUMMARY',
                (int) ($summary['scanned'] ?? 0),
                (int) ($summary['issues'] ?? 0),
                (int) ($summary['repaired'] ?? 0),
                (int) ($summary['unchanged'] ?? 0),
                (int) ($summary['forms_updated'] ?? 0),
                (int) ($summary['articles_updated'] ?? 0),
                (int) ($summary['assets_updated'] ?? 0),
                (int) ($summary['workflows_updated'] ?? 0),
                (int) ($summary['errors'] ?? 0)
            );

            if ($this->isAjaxCall()) {
                $this->respondAjax(true, $message);
                return;
            }

            $this->setMessage($message, (int) ($summary['errors'] ?? 0) > 0 ? 'warning' : 'message');
        } catch (\Throwable $e) {
            $message = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_GENERATED_ARTICLE_CATEGORIES_REPAIR_FAILED', $this->safeErrorMessage($e));
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $message);
                return;
            }

            $this->setMessage($message, 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    public function repairDebugMode(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();

        // Accepts the per-row button (single form_id) and the bulk button
        // (checkbox selection). An empty selection is rejected rather than
        // silently disabling debug mode on every view at once.
        $formIds = array_map('intval', (array) $this->input->post->get('form_ids', [], 'array'));
        $singleFormId = (int) $this->input->post->getInt('form_id', 0);
        if ($singleFormId > 0) {
            $formIds[] = $singleFormId;
        }
        $formIds = array_values(array_unique(array_filter(
            $formIds,
            static fn(int $formId): bool => $formId > 0
        )));

        try {
            if ($formIds === []) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DEBUG_MODE_NO_SELECTION'));
            }

            $summary = DebugModeAuditHelper::repair(
                $this->getComponent()->getContainer()->get(DatabaseInterface::class),
                $formIds
            );

            $report = DatabaseAuditHelper::run();
            $app->setUserState('com_contentbuilderng.about.audit', $report);
            $this->getRepairWorkflowService()->logAuditReport($report);

            $message = Text::sprintf(
                'COM_CONTENTBUILDERNG_DEBUG_MODE_REPAIR_SUMMARY',
                (int) ($summary['scanned'] ?? 0),
                (int) ($summary['repaired'] ?? 0),
                (int) ($summary['unchanged'] ?? 0),
                (int) ($summary['errors'] ?? 0)
            );

            if ($this->isAjaxCall()) {
                $this->respondAjax(true, $message);
                return;
            }

            $this->setMessage($message, (int) ($summary['errors'] ?? 0) > 0 ? 'warning' : 'message');
        } catch (\Throwable $e) {
            $message = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_DEBUG_MODE_REPAIR_FAILED', $this->safeErrorMessage($e));
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $message);
                return;
            }

            $this->setMessage($message, 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    public function deleteStaleInstallerTemp(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();

        // Accepts both the per-row button (single path) and the bulk button
        // (checkbox selection). Each path is revalidated against the current
        // stale list by the helper before anything is removed from disk.
        $paths = array_map(
            static fn($path): string => trim((string) $path),
            (array) $this->input->post->get('stale_installer_temp_paths', [], 'array')
        );
        $singlePath = trim((string) $this->input->post->getString('stale_installer_temp_path', ''));
        if ($singlePath !== '') {
            $paths[] = $singlePath;
        }
        $paths = array_values(array_unique(array_filter($paths, static fn(string $path): bool => $path !== '')));

        try {
            if ($paths === []) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP_NOT_SELECTED'));
            }

            $deletedPaths = [];
            $failures = [];
            foreach ($paths as $path) {
                try {
                    $deletedPaths[] = StaleInstallerTempAuditHelper::delete($path);
                } catch (\Throwable $e) {
                    $failures[] = $path . ' (' . $this->safeErrorMessage($e) . ')';
                }
            }

            if ($deletedPaths === []) {
                throw new \RuntimeException(implode(' ; ', $failures));
            }

            $report = DatabaseAuditHelper::run();
            $app->setUserState('com_contentbuilderng.about.audit', $report);
            $this->getRepairWorkflowService()->logAuditReport($report);

            $message = count($deletedPaths) === 1
                ? Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP_DELETED', $deletedPaths[0])
                : Text::plural('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP_DELETED_N', count($deletedPaths));

            if ($failures !== []) {
                $message .= ' ' . Text::sprintf(
                    'COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP_DELETE_PARTIAL',
                    implode(' ; ', $failures)
                );
            }
            if ($this->isAjaxCall()) {
                $this->respondAjax(true, $message);
                return;
            }

            $this->setMessage($message, 'message');
        } catch (\Throwable $e) {
            $message = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_STALE_INSTALLER_TEMP_DELETE_FAILED', $this->safeErrorMessage($e));
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $message);
                return;
            }

            $this->setMessage($message, 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    public function runAudit(): void
    {
        $this->checkToken();

        $app = $this->getApp();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_contentbuilderng')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        try {
            $report = DatabaseAuditHelper::run();
            $app->setUserState('com_contentbuilderng.about.audit', $report);

            $auditErrorCount = (int) ($report['summary']['audit_errors'] ?? count((array) ($report['errors'] ?? [])));
            $auditWarningCount = (int) ($report['summary']['audit_warnings'] ?? 0);
            $issuesTotal = (int) ($report['summary']['issues_total'] ?? 0);
            $scannedTables = (int) ($report['scanned_tables'] ?? 0);

            $this->getRepairWorkflowService()->logAuditReport($report);

            Logger::info('Database audit completed', [
                'issues_total' => $issuesTotal,
                'scanned_tables' => $scannedTables,
                'errors' => $auditErrorCount,
                'warnings' => $auditWarningCount,
            ]);

            if ($auditErrorCount === 0 && $auditWarningCount === 0) {
                $this->setMessage(Text::plural('COM_CONTENTBUILDERNG_ABOUT_AUDIT_SUMMARY_CLEAN', $scannedTables), 'message');
            } else {
                $message = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_SUMMARY_COUNTS', $auditErrorCount, $auditWarningCount);
                $this->setMessage($message, $auditErrorCount > 0 ? 'error' : 'warning');
            }
        } catch (\Throwable $e) {
            $this->setMessage(Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_FAILED', $this->safeErrorMessage($e)), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    // -------------------------------------------------------------------------
    // Log viewer
    // -------------------------------------------------------------------------

    public function showLog(): void
    {
        $this->checkToken();

        $app = $this->getApp();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_contentbuilderng')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        try {
            $logReport = $this->readAboutLogReport();
            $app->setUserState('com_contentbuilderng.about.log', $logReport);
            $this->setMessage(Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_LOG_LOADED', (string) ($logReport['file'] ?? '')), 'message');
        } catch (\Throwable $e) {
            $app->setUserState('com_contentbuilderng.about.log', []);
            $this->setMessage(Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_LOG_LOAD_FAILED', $this->safeErrorMessage($e)), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about', false));
    }

    // -------------------------------------------------------------------------
    // Configuration export
    // -------------------------------------------------------------------------

    public function exportConfiguration(): void
    {
        $this->checkToken();

        $app = $this->getApp();
        $user = $app->getIdentity();
        $selectedSections = [];
        $selectedFormIds = [];
        $selectedStorageIds = [];
        $includeStorageContent = false;

        if (!$user->authorise('core.manage', 'com_contentbuilderng')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->rememberConfigTransferSelection();

        try {
            $service = $this->getConfigExportService();

            $selectedSections = $this->getSelectedConfigSections();
            if ($selectedSections === []) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIGURATION_SELECT_SECTION'));
            }

            $selectedFormIds = $this->getSelectedConfigFormIds();
            $selectedStorageIds = $this->getSelectedConfigStorageIds();
            $selectedSections = $service->resolveEffectiveSections($selectedSections, $selectedFormIds, $selectedStorageIds);

            if ($selectedSections === []) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIGURATION_SELECT_EXPORT_TARGET'));
            }

            $includeStorageContent = $this->shouldExportStorageContent();
            $payload = $service->buildPayload($selectedSections, $selectedFormIds, $selectedStorageIds, $includeStorageContent, $this->getCurrentUserId());
            $exportSummary = $service->buildSummary($payload, $selectedSections, $selectedFormIds, $selectedStorageIds, $includeStorageContent);
            $fileName = 'contentbuilderng-config-' . (new Date())->format('Ymd-His') . '.json';

            $app->setUserState('com_contentbuilderng.about.export', [
                'generated_at' => $this->getJoomlaLocalDateTime(),
                'summary' => array_merge($exportSummary, ['file_name' => $fileName]),
            ]);

            $service->logReport($payload, $selectedSections, $selectedFormIds, $selectedStorageIds, $fileName);

            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (!is_string($json)) {
                throw new \RuntimeException('Failed to encode configuration export payload.');
            }

            $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            $app->setHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"', true);
            $app->setHeader('Pragma', 'no-cache', true);
            $app->setHeader('Expires', '0', true);
            $app->sendHeaders();
            echo $json;
            $app->close();
        } catch (\Throwable $e) {
            $app->setUserState('com_contentbuilderng.about.export', [
                'generated_at' => $this->getJoomlaLocalDateTime(),
                'summary' => [
                    'status' => 'error',
                    'details' => [(string) $this->safeErrorMessage($e)],
                ],
            ]);
            Logger::error('Configuration export failed', [
                'sections' => $selectedSections,
                'form_ids' => $selectedFormIds,
                'storage_ids' => $selectedStorageIds,
                'include_storage_content' => $includeStorageContent ? 1 : 0,
                'error' => $this->safeErrorMessage($e),
            ]);
            $this->setMessage(Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_EXPORT_CONFIGURATION_FAILED', $this->safeErrorMessage($e)), 'error');
            $this->setRedirect($this->buildConfigTransferRedirect('export'));
        }
    }

    // -------------------------------------------------------------------------
    // Configuration import
    // -------------------------------------------------------------------------

    public function importConfiguration(): void
    {
        $this->checkToken();

        $app = $this->getApp();
        $user = $app->getIdentity();
        $selectedSections = [];
        $importMode = ConfigImportService::MODE_MERGE;

        if (!$user->authorise('core.manage', 'com_contentbuilderng')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->rememberConfigTransferSelection();

        try {
            $service = $this->getConfigImportService();

            $selectedSections = $this->getSelectedConfigSections();
            if ($selectedSections === []) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_CONFIGURATION_SELECT_SECTION'));
            }

            $importMode = $this->getImportMode();
            $upload = (array) $app->getInput()->files->get('cb_config_import_file', [], 'array');
            $tmpName = (string) ($upload['tmp_name'] ?? '');
            $errorCode = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($errorCode !== UPLOAD_ERR_OK || $tmpName === '' || !is_uploaded_file($tmpName)) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_SELECT_FILE'));
            }

            $raw = file_get_contents($tmpName);
            if (!is_string($raw) || trim($raw) === '') {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_INVALID'));
            }

            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_INVALID'));
            }

            $format = (string) ($payload['meta']['format'] ?? '');
            if ($format !== '' && $format !== 'cbng-config-export-v1') {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_INVALID'));
            }

            $payload = $service->filterPayload(
                $payload,
                $selectedSections,
                $this->getSelectedConfigImportNames('cb_config_import_form_names'),
                $this->getSelectedConfigImportNames('cb_config_import_storage_names'),
                $this->getSelectedConfigImportNames('cb_config_import_storage_content_names')
            );

            $summary = $service->applyPayload($payload, $selectedSections, $importMode);

            $app->setUserState('com_contentbuilderng.about.import', [
                'generated_at' => $this->getJoomlaLocalDateTime(),
                'summary' => $summary,
            ]);

            $service->logReport($summary, $selectedSections, $importMode);

            $rowsImported = (int) ($summary['rows'] ?? 0);
            $tablesImported = (int) ($summary['tables'] ?? 0);
            $messageKey = $rowsImported > 0
                ? 'COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_SUCCESS'
                : 'COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_NO_CHANGES';

            $this->setMessage(
                $rowsImported > 0
                    ? Text::sprintf($messageKey, $tablesImported, $rowsImported)
                    : Text::plural($messageKey, $tablesImported),
                'message'
            );
        } catch (\Throwable $e) {
            $app->setUserState('com_contentbuilderng.about.import', [
                'generated_at' => $this->getJoomlaLocalDateTime(),
                'summary' => [
                    'status' => 'error',
                    'details' => [(string) $this->safeErrorMessage($e)],
                ],
            ]);
            Logger::error('Configuration import failed', [
                'mode' => $importMode,
                'sections' => $selectedSections,
                'error' => $this->safeErrorMessage($e),
            ]);
            $this->setMessage(Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_FAILED', $this->safeErrorMessage($e)), 'error');
        }

        $this->setRedirect($this->buildConfigTransferRedirect('import'));
    }

    // -------------------------------------------------------------------------
    // Input helpers (access $this->input — must stay in controller)
    // -------------------------------------------------------------------------

    private function getSelectedConfigSections(): array
    {
        $selectedRaw = (array) $this->getApp()->getInput()->get('cb_config_sections', [], 'array');
        $selected = [];

        foreach ($selectedRaw as $sectionKey) {
            $key = strtolower((string) $sectionKey);
            $key = preg_replace('/[^a-z0-9_]/', '', $key) ?? '';
            if ($key !== '') {
                $selected[] = $key;
            }
        }

        $selected = array_values(array_unique($selected));
        return array_values(array_intersect($selected, ConfigExportService::ROOT_SECTIONS));
    }

    private function getSelectedConfigFormIds(): array
    {
        return $this->getSelectedNumericIds('cb_config_form_ids');
    }

    private function getSelectedConfigStorageIds(): array
    {
        return $this->getSelectedNumericIds('cb_config_storage_ids');
    }

    private function getSelectedConfigImportNames(string $inputKey): array
    {
        $selectedRaw = (array) $this->getApp()->getInput()->get($inputKey, [], 'array');
        $selected = [];

        foreach ($selectedRaw as $selectedName) {
            $name = trim((string) $selectedName);
            if ($name !== '') {
                $selected[] = $name;
            }
        }

        return array_values(array_unique($selected));
    }

    private function getSelectedNumericIds(string $inputKey): array
    {
        $selectedRaw = (array) $this->getApp()->getInput()->get($inputKey, [], 'array');
        $selected = [];

        foreach ($selectedRaw as $selectedId) {
            $id = (int) $selectedId;
            if ($id > 0) {
                $selected[] = $id;
            }
        }

        return array_values(array_unique($selected));
    }

    private function getImportMode(): string
    {
        $mode = strtolower((string) $this->getApp()->getInput()->getCmd('cb_config_import_mode', ConfigImportService::MODE_MERGE));
        return in_array($mode, [ConfigImportService::MODE_MERGE, ConfigImportService::MODE_REPLACE], true)
            ? $mode
            : ConfigImportService::MODE_MERGE;
    }

    private function shouldExportStorageContent(): bool
    {
        return $this->getApp()->getInput()->getInt('cb_export_storage_content', 0) === 1;
    }

    // -------------------------------------------------------------------------
    // Config transfer session state
    // -------------------------------------------------------------------------

    private function buildConfigTransferRedirect(string $fallbackMode = 'export'): string
    {
        $app = $this->getApp();
        $returnView = $app->getInput()->getCmd('return_view', '');
        $returnMode = $app->getInput()->getCmd('return_mode', $fallbackMode);
        $returnMode = in_array($returnMode, ['export', 'import'], true) ? $returnMode : $fallbackMode;

        if ($returnView === 'configtransfer') {
            return Route::_('index.php?option=com_contentbuilderng&view=configtransfer&mode=' . $returnMode, false);
        }

        return Route::_('index.php?option=com_contentbuilderng&view=about', false);
    }

    private function rememberConfigTransferSelection(): void
    {
        $app = $this->getApp();
        $postData = (array) $app->getInput()->post->getArray();
        $previous = (array) $app->getUserState(self::CONFIG_TRANSFER_SELECTION_STATE_KEY, []);

        $sections = (array) ($previous['sections'] ?? []);
        if (array_key_exists('cb_config_sections', $postData)) {
            $sections = $this->normalizeInputStringArray((array) ($postData['cb_config_sections'] ?? []));
        }

        $formIds = (array) ($previous['form_ids'] ?? []);
        if (array_key_exists('cb_config_form_ids', $postData)) {
            $formIds = $this->normalizeInputIntArray((array) ($postData['cb_config_form_ids'] ?? []));
        }

        $storageIds = (array) ($previous['storage_ids'] ?? []);
        if (array_key_exists('cb_config_storage_ids', $postData)) {
            $storageIds = $this->normalizeInputIntArray((array) ($postData['cb_config_storage_ids'] ?? []));
        }

        $includeStorageContent = (int) ($previous['include_storage_content'] ?? 0) === 1;
        if (array_key_exists('cb_export_storage_content', $postData)) {
            $includeStorageContent = (int) ($postData['cb_export_storage_content'] ?? 0) === 1;
        } elseif (((string) ($postData['task'] ?? '')) === 'about.exportConfiguration') {
            $includeStorageContent = false;
        }

        $app->setUserState(self::CONFIG_TRANSFER_SELECTION_STATE_KEY, [
            'touched' => 1,
            'sections' => $sections,
            'form_ids' => $formIds,
            'storage_ids' => $storageIds,
            'include_storage_content' => $includeStorageContent ? 1 : 0,
        ]);
    }

    private function normalizeInputStringArray(array $values): array
    {
        $clean = [];

        foreach ($values as $value) {
            $key = strtolower((string) $value);
            $key = preg_replace('/[^a-z0-9_]/', '', $key) ?? '';
            if ($key !== '') {
                $clean[] = $key;
            }
        }

        return array_values(array_unique($clean));
    }

    private function normalizeInputIntArray(array $values): array
    {
        $clean = [];

        foreach ($values as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $clean[] = $id;
            }
        }

        return array_values(array_unique($clean));
    }

    // -------------------------------------------------------------------------
    // Log reader
    // -------------------------------------------------------------------------

    private function readAboutLogReport(): array
    {
        $logDirectory = $this->resolveLogDirectory();
        $logFile = $this->resolveLogFile($logDirectory);

        if ($logFile === null) {
            throw new \RuntimeException(Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_LOG_NOT_FOUND', $logDirectory));
        }

        $size = is_file($logFile) ? (int) @filesize($logFile) : 0;
        $tailBytes = self::ABOUT_LOG_TAIL_BYTES;
        $truncated = false;
        $content = $this->readLogTail($logFile, $tailBytes, $truncated);

        return [
            'file' => basename($logFile),
            'path' => $logFile,
            'size' => max(0, $size),
            'content' => $content,
            'loaded_at' => $this->getJoomlaLocalDateTime(),
            'truncated' => $truncated ? 1 : 0,
            'tail_bytes' => $tailBytes,
        ];
    }

    private function resolveLogDirectory(): string
    {
        $app = $this->getApp();
        $configuredPath = trim((string) $app->get('log_path', ''));

        if ($configuredPath === '') {
            $configuredPath = JPATH_ROOT . '/logs';
        }

        return rtrim($configuredPath, '/\\');
    }

    private function resolveLogFile(string $logDirectory): ?string
    {
        foreach (self::ABOUT_LOG_FILES as $fileName) {
            $path = $logDirectory . '/' . $fileName;
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function readLogTail(string $logFile, int $tailBytes, bool &$truncated): string
    {
        $truncated = false;
        $size = (int) @filesize($logFile);
        $tailBytes = max(1, $tailBytes);
        $handle = @fopen($logFile, 'rb');

        if (!is_resource($handle)) {
            throw new \RuntimeException('Unable to open log file for reading.');
        }

        try {
            if ($size > $tailBytes) {
                $truncated = true;
                fseek($handle, -$tailBytes, SEEK_END);
            }
            $data = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }

        if (!is_string($data)) {
            throw new \RuntimeException('Unable to read log file content.');
        }

        if ($truncated) {
            $lineBreakPosition = strpos($data, "\n");
            if ($lineBreakPosition !== false) {
                $data = substr($data, $lineBreakPosition + 1);
            }
        }

        return trim($data);
    }
}
