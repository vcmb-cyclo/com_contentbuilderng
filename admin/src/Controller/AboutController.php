<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Controller;

\defined('_JEXEC') or die('Restricted access');

use CB\Component\Contentbuilderng\Administrator\Helper\DatabaseAuditHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;
use CB\Component\Contentbuilderng\Administrator\Service\ConfigExportService;
use CB\Component\Contentbuilderng\Administrator\Service\ConfigImportService;
use CB\Component\Contentbuilderng\Administrator\Service\RepairWorkflowService;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

final class AboutController extends BaseController
{
    protected $default_view = 'about';

    private const CONFIG_TRANSFER_SELECTION_STATE_KEY = 'com_contentbuilderng.configtransfer.selection';
    private const ABOUT_LOG_FILES = ['com_contentbuilderng.log'];
    private const ABOUT_LOG_TAIL_BYTES = 262144;

    private function getApp(): AdministratorApplication
    {
        /** @var AdministratorApplication $app */
        $app = Factory::getApplication();
        return $app;
    }

    private function getCurrentUserId(): int
    {
        return (int) ($this->getApp()->getIdentity()->id ?? 0);
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
        $offset = is_object($app) && method_exists($app, 'get') ? (string) $app->get('offset', 'UTC') : 'UTC';

        try {
            $timezone = new \DateTimeZone($offset !== '' ? $offset : 'UTC');
        } catch (\Throwable) {
            $timezone = new \DateTimeZone('UTC');
        }

        return (new \DateTimeImmutable('now', $timezone))->format('Y-m-d H:i:s');
    }

    // -------------------------------------------------------------------------
    // Repair workflow
    // -------------------------------------------------------------------------

    public function migratePackedData(): void
    {
        $this->startRepairWorkflow();
    }

    public function startRepairWorkflow(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();
        $service = new RepairWorkflowService();
        $workflow = $service->createWorkflowState();
        $app->setUserState(RepairWorkflowService::WORKFLOW_STATE_KEY, $workflow);

        Logger::info('DB repair workflow started', [
            'steps' => count((array) ($workflow['steps'] ?? [])),
        ]);

        $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_STARTED'), 'message');
        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about&repair_workflow=1', false));
    }

    public function executeRepairWorkflowStep(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();
        $service = new RepairWorkflowService();
        $workflow = $service->getWorkflowState($app);

        if ($workflow === []) {
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_MISSING'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about&repair_workflow=1', false));
            return;
        }

        $currentIndex = (int) ($workflow['current_step'] ?? 0);
        $steps = array_values((array) ($workflow['steps'] ?? []));
        $currentStep = $steps[$currentIndex] ?? null;
        $requestedStepId = trim((string) $this->input->post->get('repair_step', '', 'cmd'));
        $action = trim((string) $this->input->post->get('repair_action', '', 'cmd'));

        if (!is_array($currentStep) || $requestedStepId === '' || $requestedStepId !== (string) ($currentStep['id'] ?? '')) {
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_INVALID_STEP'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about&repair_workflow=1', false));
            return;
        }

        if ($action !== 'apply' && $action !== 'skip') {
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_INVALID_ACTION'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about&repair_workflow=1', false));
            return;
        }

        if ((string) ($currentStep['status'] ?? 'pending') !== 'pending') {
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_ALREADY_DONE'), 'message');
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about&repair_workflow=1', false));
            return;
        }

        try {
            if ($action === 'skip') {
                $result = [
                    'level' => 'message',
                    'summary' => Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_SKIPPED_SUMMARY'),
                    'lines' => [Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_SKIPPED_LOG')],
                ];
                $currentStep['status'] = 'skipped';
            } else {
                $result = $service->executeStep($requestedStepId);
                $currentStep['status'] = 'done';
            }
        } catch (\Throwable $e) {
            $result = [
                'level' => 'error',
                'summary' => Text::sprintf('COM_CONTENTBUILDERNG_PACKED_MIGRATION_FAILED', $e->getMessage()),
                'lines' => [],
            ];
            $currentStep['status'] = 'done';
        }

        $currentStep['decision'] = $action;
        $currentStep['result'] = $result;
        $currentStep['completed_at'] = $this->getJoomlaLocalDateTime();
        $steps[$currentIndex] = $currentStep;
        $workflow['steps'] = $steps;

        if ($action === 'skip' && $currentIndex < count($steps) - 1) {
            $workflow['current_step'] = $currentIndex + 1;
        }

        if (($workflow['current_step'] ?? $currentIndex) >= count($steps) - 1 && $currentIndex >= count($steps) - 1) {
            $workflow['completed'] = true;
        }

        if ($action === 'skip' && $currentIndex >= count($steps) - 1) {
            $workflow['completed'] = true;
        }

        $workflow['updated_at'] = $currentStep['completed_at'];
        $app->setUserState(RepairWorkflowService::WORKFLOW_STATE_KEY, $workflow);

        $service->logStepResult($requestedStepId, $action, $result);
        $this->setMessage((string) ($result['summary'] ?? ''), (string) ($result['level'] ?? 'message'));
        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about&repair_workflow=1', false));
    }

    public function nextRepairWorkflowStep(): void
    {
        $this->checkToken();

        $app = $this->getAuthorizedApplication();
        $service = new RepairWorkflowService();
        $workflow = $service->getWorkflowState($app);

        if ($workflow === []) {
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_MISSING'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about&repair_workflow=1', false));
            return;
        }

        $currentIndex = (int) ($workflow['current_step'] ?? 0);
        $steps = array_values((array) ($workflow['steps'] ?? []));
        $currentStep = $steps[$currentIndex] ?? null;

        if (!is_array($currentStep) || (string) ($currentStep['status'] ?? 'pending') === 'pending') {
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_COMPLETE_STEP_FIRST'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about&repair_workflow=1', false));
            return;
        }

        if ($currentIndex < count($steps) - 1) {
            $workflow['current_step'] = $currentIndex + 1;
            $workflow['updated_at'] = $this->getJoomlaLocalDateTime();
            $app->setUserState(RepairWorkflowService::WORKFLOW_STATE_KEY, $workflow);
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_NEXT_STEP'), 'message');
        } else {
            $workflow['completed'] = true;
            $workflow['updated_at'] = $this->getJoomlaLocalDateTime();
            $app->setUserState(RepairWorkflowService::WORKFLOW_STATE_KEY, $workflow);
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_FINISHED'), 'message');
        }

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&view=about&repair_workflow=1', false));
    }

    // -------------------------------------------------------------------------
    // Audit
    // -------------------------------------------------------------------------

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

            $issuesTotal = (int) ($report['summary']['issues_total'] ?? 0);
            $scannedTables = (int) ($report['scanned_tables'] ?? 0);
            $errorsCount = count((array) ($report['errors'] ?? []));

            (new RepairWorkflowService())->logAuditReport($report);

            Logger::info('Database audit completed', [
                'issues_total' => $issuesTotal,
                'scanned_tables' => $scannedTables,
                'errors' => $errorsCount,
            ]);

            if ($issuesTotal === 0 && $errorsCount === 0) {
                $this->setMessage(Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_SUMMARY_CLEAN', $scannedTables), 'message');
            } else {
                $message = Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_SUMMARY_ISSUES', $issuesTotal, $scannedTables);
                if ($errorsCount > 0) {
                    $message .= ' ' . Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_SUMMARY_PARTIAL', $errorsCount);
                }
                $this->setMessage($message, 'warning');
            }
        } catch (\Throwable $e) {
            $this->setMessage(Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_AUDIT_FAILED', $e->getMessage()), 'error');
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
            $this->setMessage(Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_LOG_LOAD_FAILED', $e->getMessage()), 'error');
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
            $service = new ConfigExportService();

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

            $app->setUserState('com_contentbuilderng.about.export', [
                'generated_at' => $this->getJoomlaLocalDateTime(),
                'summary' => $exportSummary,
            ]);

            $service->logReport($payload, $selectedSections, $selectedFormIds, $selectedStorageIds);

            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (!is_string($json) || $json === '') {
                throw new \RuntimeException('Failed to encode configuration export payload.');
            }

            $fileName = 'contentbuilderng-config-' . Factory::getDate()->format('Ymd-His') . '.json';

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
                    'details' => [(string) $e->getMessage()],
                ],
            ]);
            Logger::error('Configuration export failed', [
                'sections' => $selectedSections,
                'form_ids' => $selectedFormIds,
                'storage_ids' => $selectedStorageIds,
                'include_storage_content' => $includeStorageContent ? 1 : 0,
                'error' => $e->getMessage(),
            ]);
            $this->setMessage(Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_EXPORT_CONFIGURATION_FAILED', $e->getMessage()), 'error');
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
            $service = new ConfigImportService();

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

            $this->setMessage(Text::sprintf($messageKey, $tablesImported, $rowsImported), 'message');
        } catch (\Throwable $e) {
            $app->setUserState('com_contentbuilderng.about.import', [
                'generated_at' => $this->getJoomlaLocalDateTime(),
                'summary' => [
                    'status' => 'error',
                    'details' => [(string) $e->getMessage()],
                ],
            ]);
            Logger::error('Configuration import failed', [
                'mode' => $importMode,
                'sections' => $selectedSections,
                'error' => $e->getMessage(),
            ]);
            $this->setMessage(Text::sprintf('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION_FAILED', $e->getMessage()), 'error');
        }

        $this->setRedirect($this->buildConfigTransferRedirect('import'));
    }

    // -------------------------------------------------------------------------
    // Input helpers (access $this->input — must stay in controller)
    // -------------------------------------------------------------------------

    private function getSelectedConfigSections(): array
    {
        $selectedRaw = (array) $this->getApp()->input->get('cb_config_sections', [], 'array');
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
        $selectedRaw = (array) $this->getApp()->input->get($inputKey, [], 'array');
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
        $selectedRaw = (array) $this->getApp()->input->get($inputKey, [], 'array');
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
        $mode = strtolower((string) $this->getApp()->input->getCmd('cb_config_import_mode', ConfigImportService::MODE_MERGE));
        return in_array($mode, [ConfigImportService::MODE_MERGE, ConfigImportService::MODE_REPLACE], true)
            ? $mode
            : ConfigImportService::MODE_MERGE;
    }

    private function shouldExportStorageContent(): bool
    {
        return $this->getApp()->input->getInt('cb_export_storage_content', 0) === 1;
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
        $configuredPath = '';

        if (is_object($app) && method_exists($app, 'get')) {
            $configuredPath = trim((string) $app->get('log_path', ''));
        }

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
