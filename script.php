<?php

/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * script.php (Installer Script)
 * - Single-file, clean & hardened, Joomla 6 style (works Joomla 5+)
 * - Keeps ALL legacy handling in best-effort mode
 * - Legacy plugins: DISABLE ONLY (no uninstall), with optional best-effort folder cleanup
 */

defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\Folder;
use CB\Component\Contentbuilderng\Administrator\Service\InstallerService;
use CB\Component\Contentbuilderng\Administrator\Service\MigrationService;
use CB\Component\Contentbuilderng\Administrator\Service\PluginInstallerService;
use CB\Component\Contentbuilderng\Administrator\Service\SchemaService;

$serviceBasePath = is_dir(__DIR__ . '/administrator/src/Service')
    ? __DIR__ . '/administrator/src/Service'
    : __DIR__ . '/src/Service';

require_once $serviceBasePath . '/InstallerService.php';
require_once $serviceBasePath . '/MigrationService.php';
require_once $serviceBasePath . '/PluginInstallerService.php';
require_once $serviceBasePath . '/SchemaService.php';

/**
 * Installer Script class for com_contentbuilderng
 *
 * Notes:
 * - Joomla does not require extending a base class; method signatures are what matters.
 * - Methods used by Joomla: preflight, install, update, uninstall, postflight
 */
class com_contentbuilderngInstallerScript
{
    // ---------------------------------------------------------------------
    // Configuration
    // ---------------------------------------------------------------------
    protected string $extension = 'com_contentbuilderng';
    protected string $minimumPhp = '8.1';
    protected string $minimumJoomla = '5.0';

    private const SHARED_LOG_FILE = 'com_contentbuilderng.log';
    private const SHARED_LOG_KEEP_FILES = 10;

    /** Legacy table renames (without prefix) => target (without prefix) */
    private const LEGACY_TABLE_RENAMES = [
        'contentbuilder_articles'            => 'contentbuilderng_articles',
        'contentbuilder_ng_articles'         => 'contentbuilderng_articles',
        'contentbuilder_elements'            => 'contentbuilderng_elements',
        'contentbuilder_ng_elements'         => 'contentbuilderng_elements',
        'contentbuilder_forms'               => 'contentbuilderng_forms',
        'contentbuilder_ng_forms'            => 'contentbuilderng_forms',
        'contentbuilder_list_records'        => 'contentbuilderng_list_records',
        'contentbuilder_ng_list_records'     => 'contentbuilderng_list_records',
        'contentbuilder_list_states'         => 'contentbuilderng_list_states',
        'contentbuilder_ng_list_states'      => 'contentbuilderng_list_states',
        'contentbuilder_rating_cache'        => 'contentbuilderng_rating_cache',
        'contentbuilder_ng_rating_cache'     => 'contentbuilderng_rating_cache',
        'contentbuilder_records'             => 'contentbuilderng_records',
        'contentbuilder_ng_records'          => 'contentbuilderng_records',
        'contentbuilder_registered_users'    => 'contentbuilderng_registered_users',
        'contentbuilder_ng_registered_users' => 'contentbuilderng_registered_users',
        'contentbuilder_resource_access'     => 'contentbuilderng_resource_access',
        'contentbuilder_ng_resource_access'  => 'contentbuilderng_resource_access',
        'contentbuilder_storage_fields'      => 'contentbuilderng_storage_fields',
        'contentbuilder_ng_storage_fields'   => 'contentbuilderng_storage_fields',
        'contentbuilder_storages'            => 'contentbuilderng_storages',
        'contentbuilder_ng_storages'         => 'contentbuilderng_storages',
        'contentbuilder_users'               => 'contentbuilderng_users',
        'contentbuilder_ng_users'            => 'contentbuilderng_users',
        'contentbuilder_verifications'       => 'contentbuilderng_verifications',
        'contentbuilder_ng_verifications'    => 'contentbuilderng_verifications',
    ];

    // ---------------------------------------------------------------------
    // Runtime state
    // ---------------------------------------------------------------------
    private bool $criticalFailureDetected = false;
    private array $criticalFailureMessages = [];
    private float $installStartedAt = 0.0;
    private array $updateHighlights = [];
    private array $libraryUpdateHighlights = [];
    private array $preUpdatePhpLibraries = [];
    private ?string $incomingPackageSourceRoot = null;
    private InstallerService $installerService;
    private MigrationService $migrationService;
    private PluginInstallerService $pluginInstallerService;
    private SchemaService $schemaService;

    // ---------------------------------------------------------------------
    // Lifecycle
    // ---------------------------------------------------------------------
    public function __construct()
    {
        $this->installStartedAt = microtime(true);
        $dbProvider = fn(): DatabaseInterface => $this->db();
        $logger = fn(string $message, int $priority = Log::INFO) => $this->log($message, $priority);
        $safeRunner = fn(callable $fn, mixed $fallback = null): mixed => $this->safe($fn, $fallback);
        $cachePurger = fn(string $context) => $this->purgeCaches($context);
        $updateHighlighter = fn(string $message) => $this->addUpdateHighlight($message);

        $this->installerService = new InstallerService($dbProvider, $logger, $safeRunner, $cachePurger);
        $this->migrationService = new MigrationService($dbProvider, $logger, $safeRunner, self::LEGACY_TABLE_RENAMES);
        $this->pluginInstallerService = new PluginInstallerService($dbProvider, $logger, $safeRunner, $cachePurger, $updateHighlighter);
        $this->schemaService = new SchemaService($dbProvider, $logger, $safeRunner);
        $this->loadComponentLanguage();
        $this->bootLogger();
        $this->writeInstallLogEntry('[INFO] ---------------------------------------------------------', Log::INFO);
        $this->log('[OK] <strong>ContentBuilder NG</strong> installer script booted.', Log::INFO);

        $this->log('[INFO] Joomla version: <strong>' . htmlspecialchars(defined('JVERSION') ? JVERSION : 'unknown', ENT_QUOTES, 'UTF-8') . '</strong>.', Log::INFO);
        $this->log('[INFO] PHP version: ' . PHP_VERSION . '.', Log::INFO);

        $detected = 'unknown';
        try {
            $detected = $this->getCurrentInstalledVersion();
        } catch (\Throwable) {
            $detected = 'unknown';
        }
        $this->log('[INFO] Detected installed version: <strong>' . htmlspecialchars((string) $detected, ENT_QUOTES, 'UTF-8') . '</strong>.', Log::INFO);

        $this->logDatabaseRuntimeInfo();
        $this->log('[INFO] User agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'CLI') . '.', Log::INFO);
        $this->writeInstallLogEntry('[INFO] ===================================================================', Log::INFO);
    }

    // ---------------------------------------------------------------------
    // Joomla callbacks
    // ---------------------------------------------------------------------
    public function preflight($type, $parent): bool
    {
        $this->resetCriticalFailures();

        try {
            if (!$this->checkRequirements()) {
                return false;
            }

            $type = strtolower((string) $type);
            $incomingVersion = $this->getIncomingPackageVersion($parent);
            $currentVersion  = $this->safe(fn() => $this->getCurrentInstalledVersion(), '0.0.0');

            $this->log(
                '[OK] Preflight: action <strong>' . htmlspecialchars(strtoupper($type), ENT_QUOTES, 'UTF-8') . '</strong>'
                    . ' | <strong>' . htmlspecialchars((string) $currentVersion, ENT_QUOTES, 'UTF-8') . '</strong>.'
                    . ' -> <strong>' . htmlspecialchars((string) $incomingVersion, ENT_QUOTES, 'UTF-8') . '</strong>',
                Log::INFO
            );

            if ($type !== 'uninstall') {
                $context = 'preflight:' . $type;

                if ($type === 'update') {
                    $this->captureUpdatePackageSnapshot();
                }

                // Disable legacy plugins first, especially system/contentbuilder_system
                $this->disableLegacyPluginsInPriorityOrder($context);

                // Best-effort: remove the legacy system plugin folder early (if present)
                $this->removeLegacySystemPluginFolderEarly($context);

                // Report collisions (legacy+target tables both exist)
                $this->reportLegacyTableCollisions();

                // Ensure admin root exists (older DBs can be broken)
                $this->ensureAdminMenuRootNodeExists();

                // Rename legacy tables (best-effort)
                $this->renameLegacyTables();

                // Migrate legacy component extension rows (com_contentbuilder / com_contentbuilder_ng) to NG if safe
                $this->migrateLegacyContentbuilderName('contentbuilder');
                $this->migrateLegacyContentbuilderName('com_contentbuilder');
                $this->migrateLegacyContentbuilderName('com_contentbuilder_ng');
            }
        } catch (\Throwable $e) {
            $this->log('[ERROR] Preflight aborted: ' . $e->getMessage(), Log::ERROR);
            return false;
        }

        return !$this->hasCriticalFailure();
    }

    public function install(?InstallerAdapter $parent): bool
    {
        $this->resetCriticalFailures();

        // Keep your original “hard warning” behavior
        if (!version_compare(PHP_VERSION, '8.1', '>=')) {
            Factory::getApplication()->enqueueMessage(
                'WARNING: YOU ARE RUNNING PHP VERSION "' . PHP_VERSION . '". ContentBuilder NG WON\'T WORK WITH THIS VERSION. PLEASE UPGRADE TO AT LEAST PHP 8.1.',
                'error'
            );
        }

        try {
            return $this->installAndUpdate($parent, 'install') && !$this->hasCriticalFailure();
        } catch (\Throwable $e) {
            $this->log('[ERROR] Install aborted: ' . $e->getMessage(), Log::ERROR);
            return false;
        }
    }

    public function update(?InstallerAdapter $parent): bool
    {
        $this->resetCriticalFailures();

        if (!version_compare(PHP_VERSION, '8.1', '>=')) {
            Factory::getApplication()->enqueueMessage(
                'WARNING: YOU ARE RUNNING PHP VERSION "' . PHP_VERSION . '". ContentBuilder NG WON\'T WORK WITH THIS VERSION. PLEASE UPGRADE TO AT LEAST PHP 8.1.',
                'error'
            );
        }

        try {
            return $this->installAndUpdate($parent, 'update') && !$this->hasCriticalFailure();
        } catch (\Throwable $e) {
            $this->log('[ERROR] Update aborted: ' . $e->getMessage(), Log::ERROR);
            return false;
        }
    }

    public function uninstall(?InstallerAdapter $parent): bool
    {
        $this->resetCriticalFailures();
        $this->log('[INFO] Uninstall of ContentBuilder NG.', Log::INFO);

        try {
            $db = $this->db();

            // Remove admin menu entries referencing component (best-effort)
            try {
                $conditions = array_merge(
                    $this->buildMenuLinkOptionWhereClauses($db, 'contentbuilder'),
                    $this->buildMenuLinkOptionWhereClauses($db, 'com_contentbuilder'),
                    $this->buildMenuLinkOptionWhereClauses($db, 'com_contentbuilderng'),
                    $this->buildMenuLinkOptionWhereClauses($db, 'com_contentbuilder_ng'),
                );

                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__menu'))
                        ->where('(' . implode(' OR ', $conditions) . ')')
                );
                $db->execute();

                $this->log('[OK] Component menu entries removed (best-effort).');
            } catch (\Throwable $e) {
                $this->log('[WARNING] Failed to remove component menu entries: ' . $e->getMessage(), Log::WARNING);
            }

            // IMPORTANT: keep uninstall conservative (no destructive operations)
            // Your legacy plugins logic is not relevant here; uninstall removes only current component-related extras.

            // Ensure admin root exists (avoid corrupted menu trees after uninstall on broken DBs)
            $this->ensureAdminMenuRootNodeExists();
        } catch (\Throwable $e) {
            $this->log('[ERROR] Uninstall aborted: ' . $e->getMessage(), Log::ERROR);
            return false;
        }

        return !$this->hasCriticalFailure();
    }

    public function postflight($type, $parent): void
    {
        $this->resetCriticalFailures();
        $type = strtolower((string) $type);
        $context = 'postflight:' . $type;

        try {
            if ($type !== 'uninstall') {
                // Keep legacy plugins disabled (priority order)
                $this->disableLegacyPluginsInPriorityOrder($context);

                // Best-effort: delete legacy system plugin folder first if present
                $this->removeLegacySystemPluginFolderEarly($context);
                // Cleanup old directories/files (best-effort)
                $this->removeOldDirectories();
                $this->removeObsoleteFiles();
                $this->removeObsoleteLanguageFiles();

                // Ensure media templates / upload dir
                $this->ensureMediaListTemplateInstalled();
                $this->ensureUploadDirectoryExists();

                // DB migrations / hardening
                $this->updateDateColumns();
                $this->ensureFormsDisplayColumns();
                $this->ensureElementsLinkableDefault();

                // Normalize menu links and titles
                $this->updateMenuLinks('contentbuilder', 'com_contentbuilderng');
                $this->updateMenuLinks('com_contentbuilder', 'com_contentbuilderng');
                $this->updateMenuLinks('com_contentbuilder_ng', 'com_contentbuilderng');

                // Install / update plugins shipped in package
                $source = $this->resolveInstallSourcePath($parent);
                $this->incomingPackageSourceRoot = $source;
                if ($source && is_dir($source)) {
                    $this->log('[INFO] Plugin install source resolved: ' . $source, Log::INFO);
                } else {
                    $this->log('[WARNING] Plugin install source not resolved; missing plugins may not be installable in this run.', Log::WARNING);
                }

                // Refresh plugins on update (keeps manifest_cache aligned)
                $this->ensurePluginsInstalled($source, $type === 'update');
                $this->activatePlugins();
            }

            if ($type === 'update') {
                // Remove unsupported theme plugins (these are NG themes; ok to uninstall)
                $this->removeDeprecatedThemePlugins();

                // Normalize stored theme references to joomla6 when legacy/unsupported
                $this->normalizeFormThemePlugins();

                // Normalize legacy "type" fields pointing to component
                $this->normalizeLegacyComponentTypes();

                // Remove legacy components rows safely (no uninstall hooks)
                $this->removeLegacyComponent('contentbuilder');
                $this->removeLegacyComponent('com_contentbuilder');
                $this->removeLegacyComponent('com_contentbuilder_ng');

                // Legacy plugins: DISABLE ONLY (explicit), but allow best-effort folder cleanup
                $this->removeLegacyPluginsDisableOnly();
                $this->removeLegacyPluginFoldersBestEffort();

                // Deduplicate plugins (and component) extension rows pointing to contentbuilder*
                $this->deduplicateTargetPluginExtensions();

                // Remove old CB Menus.
                $this->removeLegacyAdminMenuBranchByAlias('contentbuilder');
            }

            if ($type !== 'uninstall') {
                // Always keep component extension rows deduped
                $this->deduplicateTargetComponentExtensions();

                // Fix past bad replacements option=com_contentbuilder_ng_ng
                $this->normalizeBrokenTargetMenuLinks();

                // Ensure admin main entry exists for NG
                $this->ensureAdministrationMainMenuEntry();

                // Ensure Joomla quicktasks (+) for submenu entries
                $this->ensureSubmenuQuickTasks();

                // Normalize legacy menu title keys (COM_CONTENTBUILDER -> COM_CONTENTBUILDERNG)
                $this->repairLegacyMenuTitleKeys();
            }
            
            // Normalize storages ordering (your original behavior, update only)
            if ($type === 'update') {
                $this->normalizeStoragesOrdering();
            }

            if ($type !== 'uninstall') {
                $this->reportUpdatedPackageAssets($type);
            }

            // Final cache purge (autoload + caches + opcache)
            $this->purgeCaches($context . ':final');

            if ($this->hasCriticalFailure()) {
                $summary = $this->getCriticalFailureSummary();
                $this->log('[ERROR] Postflight completed with critical failures: ' . $summary, Log::ERROR);
                throw new \RuntimeException('ContentBuilder NG postflight failed: ' . $summary);
            }

            $timezoneName = $this->resolveJoomlaTimezoneName();
            $finishedAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone($timezoneName))
                ->format('Y-m-d H:i:s');
            $durationSeconds = max(0.0, microtime(true) - $this->installStartedAt);

            // Reload after installation so newly deployed language files are available for the final message.
            $this->loadComponentLanguage();

            $actionLabel = match ($type) {
                'install' => 'installation',
                'update' => 'update',
                'uninstall' => 'uninstallation',
                default => $type,
            };

            $finishedMessage = '[OK] ContentBuilder NG ' . $actionLabel . ' finished. ' . $finishedAt
                . ' ' . $timezoneName
                . '. Duration: ' . number_format($durationSeconds, 2, '.', '') . 's.';

            if ($type === 'uninstall') {
                $this->log($finishedMessage, Log::INFO);
            } else {
                $auditUrl = Route::_('index.php?option=com_contentbuilderng&view=about#cb-audit-section', false);
                $auditLink = '<a href="' . htmlspecialchars($auditUrl, ENT_QUOTES, 'UTF-8') . '">'
                    . '<strong>' . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_AUDIT'), ENT_QUOTES, 'UTF-8') . '</strong>'
                    . '</a>';
                $auditReminder = Text::sprintf(
                    'COM_CONTENTBUILDERNG_INSTALLATION_AUDIT_REMINDER',
                    $auditLink
                );

                $this->log($finishedMessage, Log::INFO, $finishedMessage . '<br>' . $auditReminder);
            }
        } catch (\Throwable $e) {
            // In installer scripts, throwing aborts installer; log & rethrow for visibility
            $this->log('[ERROR] Postflight aborted: ' . $e->getMessage(), Log::ERROR);
            throw $e;
        }
    }

    // ---------------------------------------------------------------------
    // Main install/update logic
    // ---------------------------------------------------------------------
    private function installAndUpdate(InstallerAdapter $parent, string $mode): bool
    {
        try {
            $this->ensureUploadDirectoryExists();

            // You can add more “always-run” install/update steps here if needed.
            // We keep most heavy work in postflight for a stable installation state.

            return !$this->hasCriticalFailure();
        } catch (\Throwable $e) {
            $this->log('[ERROR] installAndUpdate aborted: ' . $e->getMessage(), Log::ERROR);
            return false;
        }
    }

    // ---------------------------------------------------------------------
    // Requirements
    // ---------------------------------------------------------------------
    private function checkRequirements(): bool
    {
        if (!version_compare(PHP_VERSION, $this->minimumPhp, '>=')) {
            $this->log('[ERROR] PHP ' . PHP_VERSION . ' is too old. Requires >= ' . $this->minimumPhp, Log::ERROR);
            return false;
        }

        if (defined('JVERSION') && !version_compare(JVERSION, $this->minimumJoomla, '>=')) {
            $this->log('[ERROR] Joomla ' . JVERSION . ' is too old. Requires >= ' . $this->minimumJoomla, Log::ERROR);
            return false;
        }

        return true;
    }

    // ---------------------------------------------------------------------
    // Logging
    // ---------------------------------------------------------------------
    private function bootLogger(): void
    {
        $logPath = '';
        try {
            $app = Factory::getApplication();
            if (is_object($app) && method_exists($app, 'get')) {
                $logPath = (string) $app->get('log_path', '');
            }
        } catch (\Throwable) {
            $logPath = '';
        }

        if ($logPath === '') {
            $logPath = JPATH_ROOT . '/logs';
        }

        if (!is_dir($logPath)) {
            Folder::create($logPath);
        }

        $this->applyJoomlaTimezoneForLogging();
        $this->rotateSharedLogIfNeeded($logPath);

    }

    private function applyJoomlaTimezoneForLogging(): void
    {
        try {
            $timezone = new DateTimeZone($this->resolveJoomlaTimezoneName());
            date_default_timezone_set($timezone->getName());
        } catch (\Throwable) {
            date_default_timezone_set('UTC');
        }
    }

    private function log(string $message, int $priority = Log::INFO, ?string $displayMessage = null): void
    {
        if ($priority === Log::ERROR) {
            $this->criticalFailureDetected = true;
            $normalized = trim(strip_tags($message));
            if ($normalized !== '' && !in_array($normalized, $this->criticalFailureMessages, true)) {
                $this->criticalFailureMessages[] = $normalized;
            }
        }

        $this->writeInstallLogEntry($message, $priority);

        try {
            $app = Factory::getApplication();
            $type = match ($priority) {
                Log::ERROR   => 'error',
                Log::WARNING => 'warning',
                default      => 'message',
            };
            $app->enqueueMessage(
                $this->formatInstallMessageForDisplay($displayMessage ?? $message),
                $type
            );
        } catch (\Throwable) {
            // ignore enqueue failures
        }
    }

    private function loadComponentLanguage(): void
    {
        try {
            Factory::getApplication()->getLanguage()->load('com_contentbuilderng', JPATH_ADMINISTRATOR);
        } catch (\Throwable) {
            // Installer output falls back to raw keys if language loading fails.
        }
    }

    private function writeInstallLogEntry(string $message, int $priority = Log::INFO): void
    {
        $previousTz = date_default_timezone_get();
        $targetTz   = $this->resolveJoomlaTimezoneName();
        $switched   = false;

        if ($previousTz !== $targetTz) {
            $switched = (bool) @date_default_timezone_set($targetTz);
        }

        try {
            $now = new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get()));
            $line = sprintf(
                "%s %s %s %s\n",
                $now->format('Y-m-d'),
                $now->format('H:i:s'),
                $this->priorityToString($priority),
                $message
            );
            @file_put_contents($this->resolveSharedLogPath(), $line, FILE_APPEND | LOCK_EX);
        } finally {
            if ($switched) {
                @date_default_timezone_set($previousTz);
            }
        }
    }

    private function formatInstallMessageForDisplay(string $message): string
    {
        return str_replace(
            ['[OK]', '[INFO]', '[WARNING]', '[ERROR]'],
            [
                '<span style="color:#198754;font-weight:700;" aria-hidden="true">&#10003;</span>',
                '<span style="color:#0d6efd;font-weight:700;" aria-hidden="true">&#9432;</span>',
                '<span style="color:#fd7e14;font-weight:700;" aria-hidden="true">&#9888;</span>',
                '<span style="color:#dc3545;font-weight:700;" aria-hidden="true">&#10060;</span>',
            ],
            $message
        );
    }

    private function priorityToString(int $priority): string
    {
        return match ($priority) {
            Log::EMERGENCY => 'EMERGENCY',
            Log::ALERT => 'ALERT',
            Log::CRITICAL => 'CRITICAL',
            Log::ERROR => 'ERROR',
            Log::WARNING => 'WARNING',
            Log::NOTICE => 'NOTICE',
            Log::INFO => 'INFO',
            Log::DEBUG => 'DEBUG',
            default => 'INFO',
        };
    }

    private function rotateSharedLogIfNeeded(string $logPath): void
    {
        $directory = rtrim($logPath, '/\\');
        $activeLog = $directory . '/' . self::SHARED_LOG_FILE;

        if (!is_file($activeLog)) {
            return;
        }

        $fileTimestamp = @filemtime($activeLog);
        if ($fileTimestamp === false) {
            return;
        }

        $timezone = new \DateTimeZone($this->resolveJoomlaTimezoneName());
        $today = (new \DateTimeImmutable('now', $timezone))->format('Y-m-d');
        $fileDate = (new \DateTimeImmutable('@' . $fileTimestamp))->setTimezone($timezone)->format('Y-m-d');

        if ($fileDate === $today) {
            return;
        }

        $archivePath = $directory . '/com_contentbuilderng-' . $fileDate . '.log';
        $archiveIndex = 1;

        while (is_file($archivePath)) {
            $archivePath = $directory . '/com_contentbuilderng-' . $fileDate . '-' . $archiveIndex . '.log';
            $archiveIndex++;
        }

        @rename($activeLog, $archivePath);
        $this->cleanupRotatedSharedLogs($directory);
    }

    private function resolveSharedLogPath(): string
    {
        $logPath = '';

        try {
            $app = Factory::getApplication();
            if (is_object($app) && method_exists($app, 'get')) {
                $logPath = (string) $app->get('log_path', '');
            }
        } catch (\Throwable) {
            $logPath = '';
        }

        if ($logPath === '') {
            $logPath = JPATH_ROOT . '/logs';
        }

        return rtrim($logPath, '/\\') . '/' . self::SHARED_LOG_FILE;
    }

    private function cleanupRotatedSharedLogs(string $directory): void
    {
        $rotatedFiles = glob($directory . '/com_contentbuilderng-*.log') ?: [];
        if (count($rotatedFiles) <= self::SHARED_LOG_KEEP_FILES) {
            return;
        }

        usort(
            $rotatedFiles,
            static fn(string $left, string $right): int => (@filemtime($right) ?: 0) <=> (@filemtime($left) ?: 0)
        );

        foreach (array_slice($rotatedFiles, self::SHARED_LOG_KEEP_FILES) as $staleFile) {
            @unlink($staleFile);
        }
    }

    private function resetCriticalFailures(): void
    {
        $this->criticalFailureDetected = false;
        $this->criticalFailureMessages = [];
    }

    private function hasCriticalFailure(): bool
    {
        return $this->criticalFailureDetected;
    }

    private function getCriticalFailureSummary(int $max = 3): string
    {
        if (empty($this->criticalFailureMessages)) {
            return 'unknown critical error';
        }
        $messages = array_slice($this->criticalFailureMessages, 0, max(1, $max));
        $summary = implode(' | ', $messages);
        $remaining = count($this->criticalFailureMessages) - count($messages);
        if ($remaining > 0) {
            $summary .= " | +{$remaining} more";
        }
        return $summary;
    }

    // ---------------------------------------------------------------------
    // DB helpers
    // ---------------------------------------------------------------------
    private function db(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }

    private function safe(callable $fn, $fallback = null)
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function ensureUploadDirectoryExists(): void
    {
        $this->installerService->ensureUploadDirectoryExists();
    }

    private function ensureMediaListTemplateInstalled(): void
    {
        $this->installerService->ensureMediaListTemplateInstalled();
    }

    private function removeOldDirectories(): void
    {
        $this->installerService->removeOldDirectories();
    }

    private function removeObsoleteFiles(): void
    {
        $this->installerService->removeObsoleteFiles();
    }

    private function removeObsoleteLanguageFiles(): void
    {
        $this->installerService->removeObsoleteLanguageFiles();
    }

    private function reportLegacyTableCollisions(): void
    {
        $this->migrationService->reportLegacyTableCollisions();
    }

    private function renameLegacyTables(): void
    {
        $this->migrationService->renameLegacyTables();
    }

    private function updateDateColumns(): void
    {
        $this->schemaService->updateDateColumns();
    }

    private function ensureFormsDisplayColumns(): void
    {
        $this->schemaService->ensureFormsDisplayColumns();
    }

    private function ensureElementsLinkableDefault(): void
    {
        $this->schemaService->ensureElementsLinkableDefault();
    }

    private function buildMenuLinkOptionWhereClauses(DatabaseInterface $db, string $option): array
    {
        return $this->migrationService->buildMenuLinkOptionWhereClauses($option);
    }

    private function updateMenuLinks(string $legacyElement, string $targetElement): void
    {
        $this->migrationService->updateMenuLinks($legacyElement, $targetElement);
    }

    private function normalizeBrokenTargetMenuLinks(): void
    {
        $this->migrationService->normalizeBrokenTargetMenuLinks();
    }

    private function ensureAdminMenuRootNodeExists(): void
    {
        $this->installerService->ensureAdminMenuRootNodeExists();
    }

    private function ensureAdministrationMainMenuEntry(): void
    {
        $this->installerService->ensureAdministrationMainMenuEntry();
    }

    private function ensureSubmenuQuickTasks(): void
    {
        $this->installerService->ensureSubmenuQuickTasks();
    }

    private function repairLegacyMenuTitleKeys(): void
    {
        $this->migrationService->repairLegacyMenuTitleKeys();
    }

    private function normalizeStoragesOrdering(): void
    {
        $this->schemaService->normalizeStoragesOrdering();
    }

    private function removeLegacyAdminMenuBranchByAlias(string $alias = 'contentbuilder'): void
    {
        $this->installerService->removeLegacyAdminMenuBranchByAlias($alias);
    }

    private function resolveInstallSourcePath($parent): ?string
    {
        return $this->pluginInstallerService->resolveInstallSourcePath($parent);
    }

    private function ensurePluginsInstalled(?string $source = null, bool $forceUpdate = false): void
    {
        $this->pluginInstallerService->ensurePluginsInstalled($source, $forceUpdate);
    }

    private function activatePlugins(): void
    {
        $this->pluginInstallerService->activatePlugins();
    }

    private function removeDeprecatedThemePlugins(): void
    {
        $this->pluginInstallerService->removeDeprecatedThemePlugins();
    }

    private function normalizeFormThemePlugins(): void
    {
        $this->pluginInstallerService->normalizeFormThemePlugins();
    }

    private function disableLegacyPluginsInPriorityOrder(string $context = 'update'): int
    {
        return $this->pluginInstallerService->disableLegacyPluginsInPriorityOrder($context);
    }

    private function removeLegacySystemPluginFolderEarly(string $context = 'postflight'): void
    {
        $this->pluginInstallerService->removeLegacySystemPluginFolderEarly($context);
    }

    private function removeLegacyPluginsDisableOnly(): void
    {
        $this->pluginInstallerService->removeLegacyPluginsDisableOnly();
    }

    private function removeLegacyPluginFoldersBestEffort(): void
    {
        $this->pluginInstallerService->removeLegacyPluginFoldersBestEffort();
    }

    private function deduplicateTargetPluginExtensions(): void
    {
        $this->pluginInstallerService->deduplicateTargetPluginExtensions();
    }

    private function captureUpdatePackageSnapshot(): void
    {
        $installedComponentRoot = JPATH_ADMINISTRATOR . '/components/com_contentbuilderng';
        $this->preUpdatePhpLibraries = $this->readComposerLockLibraries($installedComponentRoot . '/composer.lock');
    }

    private function readComposerLockLibraries(string $lockPath): array
    {
        if (!is_file($lockPath)) {
            return [];
        }

        try {
            $raw = file_get_contents($lockPath);
            if (!is_string($raw) || trim($raw) === '') {
                return [];
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return [];
            }

            $libraries = [];

            foreach (['packages', 'packages-dev'] as $section) {
                foreach ((array) ($decoded[$section] ?? []) as $package) {
                    if (!is_array($package)) {
                        continue;
                    }

                    $name = trim((string) ($package['name'] ?? ''));
                    $version = trim((string) ($package['version'] ?? ''));

                    if ($name === '' || $version === '') {
                        continue;
                    }

                    $libraries[$name] = $version;
                }
            }

            ksort($libraries);

            return $libraries;
        } catch (\Throwable) {
            return [];
        }
    }

    private function addUpdateHighlight(string $message): void
    {
        $message = trim($message);

        if ($message === '' || in_array($message, $this->updateHighlights, true)) {
            return;
        }

        $this->updateHighlights[] = $message;
    }

    private function addLibraryUpdateHighlight(string $message): void
    {
        $message = trim($message);

        if ($message === '' || in_array($message, $this->libraryUpdateHighlights, true)) {
            return;
        }

        $this->libraryUpdateHighlights[] = $message;
    }

    private function reportUpdatedPackageAssets(string $type): void
    {
        if ($type !== 'update') {
            return;
        }

        $packageRoot = $this->incomingPackageSourceRoot ?: __DIR__;
        $currentPhpLibraries = $this->readComposerLockLibraries(rtrim($packageRoot, '/\\') . '/administrator/composer.lock');
        if ($currentPhpLibraries === []) {
            $currentPhpLibraries = $this->readComposerLockLibraries(rtrim($packageRoot, '/\\') . '/composer.lock');
        }

        if ($currentPhpLibraries !== []) {
            foreach ($currentPhpLibraries as $name => $version) {
                $previousVersion = $this->preUpdatePhpLibraries[$name] ?? null;

                if ($previousVersion === null) {
                    $this->addLibraryUpdateHighlight("Bundled PHP library added: {$name} ({$version})");
                    continue;
                }

                if ((string) $previousVersion !== (string) $version) {
                    $this->addLibraryUpdateHighlight("Bundled PHP library updated: {$name} ({$previousVersion} -> {$version})");
                }
            }

            foreach ($this->preUpdatePhpLibraries as $name => $version) {
                if (!isset($currentPhpLibraries[$name])) {
                    $this->addLibraryUpdateHighlight("Bundled PHP library removed: {$name} ({$version})");
                }
            }
        } else {
            $this->log('[INFO] Bundled library diff skipped: incoming composer.lock not found in update package.', Log::INFO);
        }

        if ($this->updateHighlights === [] && $this->libraryUpdateHighlights === []) {
            $this->log('[INFO] No shipped plugin, script or bundled library change was detected during this update.', Log::INFO);
            return;
        }

        if ($this->updateHighlights !== []) {
            $this->log('[INFO] Visible update summary: shipped plugins/scripts modified during this update.', Log::INFO);
            foreach ($this->updateHighlights as $highlight) {
                $this->log('[UPDATED] ' . $highlight, Log::INFO);
            }
        }

        if ($this->libraryUpdateHighlights !== []) {
            $this->log('[INFO] Bundled library changes detected during this update.', Log::INFO);
            foreach ($this->libraryUpdateHighlights as $highlight) {
                $this->log('[INFO] ' . $highlight, Log::INFO);
            }
        }

        if ($this->updateHighlights !== []) {
            try {
                Factory::getApplication()->enqueueMessage(
                    '<strong>Updated shipped plugins/scripts:</strong><br>' . implode('<br>', array_map(
                        static fn(string $highlight): string => '- ' . htmlspecialchars($highlight, ENT_QUOTES, 'UTF-8'),
                        $this->updateHighlights
                    )),
                    'message'
                );
            } catch (\Throwable) {
                // Best-effort only; installer log already contains the details.
            }
        }
    }

    private function tableExists(string $tableNoPrefix): bool
    {
        $db = $this->db();
        $tables = $this->safe(fn() => $db->getTableList(), []);
        $expected = $db->getPrefix() . ltrim($tableNoPrefix, '#__');
        return is_array($tables) && in_array($expected, $tables, true);
    }

    // ---------------------------------------------------------------------
    // Runtime info / charset checks
    // ---------------------------------------------------------------------
    private function logDatabaseRuntimeInfo(): void
    {
        try {
            $db = $this->db();

            $type = '';
            if (method_exists($db, 'getServerType')) {
                $type = trim((string) $db->getServerType());
            }
            if ($type === '' && method_exists($db, 'getName')) {
                $type = trim((string) $db->getName());
            }
            if ($type === '') {
                $type = 'unknown';
            }

            $version = '';
            if (method_exists($db, 'getVersion')) {
                $version = trim((string) $db->getVersion());
            }
            if ($version === '') {
                $version = 'unknown';
            }

            $prefix = method_exists($db, 'getPrefix') ? trim((string) $db->getPrefix()) : '(none)';
            if ($prefix === '') {
                $prefix = '(none)';
            }

            $this->log('[INFO] Database: ' . $type . ' ' . $version . ' (prefix: ' . $prefix . ').', Log::INFO);

            // Session charset/collation (best-effort; MySQL/MariaDB)
            try {
                $db->setQuery('SELECT @@character_set_connection, @@collation_connection');
                $row = $db->loadRow();
                $cs = trim((string) ($row[0] ?? ''));
                $co = trim((string) ($row[1] ?? ''));
                if ($cs !== '' || $co !== '') {
                    $this->log('[INFO] Database session charset/collation: ' . ($cs ?: 'unknown') . ' / ' . ($co ?: 'unknown') . '.', Log::INFO);
                    if ($cs !== '' && stripos($cs, 'utf8mb4') === false) {
                        $this->log('[WARNING] Database session charset is not utf8mb4 (' . $cs . ').', Log::WARNING);
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        } catch (\Throwable $e) {
            $this->log('[WARNING] Could not resolve database runtime info: ' . $e->getMessage(), Log::WARNING);
        }
    }

    // ---------------------------------------------------------------------
    // Versions
    // ---------------------------------------------------------------------
    private function getCurrentInstalledVersion(): string
    {
        $db = $this->db();
        $query = $db->getQuery(true)
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote($this->extension));
        $db->setQuery($query);

        $manifest = (string) $db->loadResult();
        if ($manifest === '') {
            return '0.0.0';
        }

        $decoded = json_decode($manifest, true);
        if (!is_array($decoded)) {
            return '0.0.0';
        }

        return (string) ($decoded['version'] ?? '0.0.0');
    }

    private function getIncomingPackageVersion($parent): string
    {
        try {
            if (is_object($parent) && method_exists($parent, 'getManifest')) {
                $manifest = $parent->getManifest();
                if ($manifest instanceof \SimpleXMLElement) {
                    $v = trim((string) ($manifest->version ?? ''));
                    if ($v !== '') {
                        return $v;
                    }
                    $attr = trim((string) ($manifest['version'] ?? ''));
                    if ($attr !== '') {
                        return $attr;
                    }
                }
            }
        } catch (\Throwable) {
            // ignore
        }
        return 'unknown';
    }

    // ---------------------------------------------------------------------
    // Timezone
    // ---------------------------------------------------------------------
    private function resolveJoomlaTimezoneName(): string
    {
        $timezoneName = '';

        try {
            $app = Factory::getApplication();
            if (is_object($app) && method_exists($app, 'get')) {
                $timezoneName = trim((string) $app->get('offset', ''));
            }
        } catch (\Throwable) {
            $timezoneName = '';
        }

        if ($timezoneName === '') {
            $timezoneName = 'UTC';
        }

        if ($timezoneName === '') {
            $timezoneName = 'UTC';
        }

        try {
            new \DateTimeZone($timezoneName);
        } catch (\Throwable) {
            $timezoneName = 'UTC';
        }

        return $timezoneName;
    }

    // ---------------------------------------------------------------------
    // Cache purges (autoload + Joomla cache + opcache)
    // ---------------------------------------------------------------------
    private function purgeCaches(string $context = 'install'): void
    {
        // 1) Autoload cache
        $autoloadFiles = [
            JPATH_ADMINISTRATOR . '/cache/autoload_psr4.php',
            JPATH_ADMINISTRATOR . '/cache/autoload_classmap.php',
            JPATH_ADMINISTRATOR . '/cache/autoload_namespaces.php',
        ];

        $deleted = 0;
        foreach ($autoloadFiles as $file) {
            try {
                if (is_file($file) && @unlink($file)) {
                    $deleted++;
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        if ($deleted > 0) {
            $this->log("[OK] Autoload cache cleared ({$deleted} file(s)) during {$context}.");
        } else {
            $this->log("[INFO] No autoload cache file needed clearing during {$context}.", Log::INFO);
        }

        // 2) Joomla caches
        try {
            $cacheFactory = Factory::getContainer()->get(CacheControllerFactoryInterface::class);
            if (is_object($cacheFactory)) {
                $this->safe(function () use ($cacheFactory) {
                    $cacheFactory->createCacheController('callback', [
                        'defaultgroup' => '_system',
                        'cachebase' => JPATH_ROOT . '/cache',
                    ])->clean();
                });

                foreach (['com_installer', 'com_plugins', 'contentbuilder', 'com_contentbuilder', 'com_contentbuilderng'] as $group) {
                    $this->safe(function () use ($cacheFactory, $group) {
                        $cacheFactory->createCacheController('callback', [
                            'defaultgroup' => $group,
                            'cachebase' => JPATH_ROOT . '/cache',
                        ])->clean();
                    });
                }
            }

            $this->log("[OK] Joomla cache cleaned (best-effort) during {$context}.");
        } catch (\Throwable $e) {
            $this->log("[WARNING] Joomla cache cleanup failed during {$context}: " . $e->getMessage(), Log::WARNING);
        }

        // 3) OPcache reset
        try {
            if (function_exists('opcache_reset')) {
                @opcache_reset();
                $this->log("[OK] OPcache reset attempted during {$context}.", Log::INFO);
            }
        } catch (\Throwable) {
            // ignore
        }
    }

    private function normalizeLegacyComponentTypes(): void
    {
        $db = $this->db();
        $targetType = 'com_contentbuilderng';

        $legacyTypes = [
            'contentbuilder',
            'contentbuilderng',
            'com_contentbuilder',
            'com_contentbuilder_ng',
            'com_contentbuilderng',
            'COM_CONTENTBUILDER',
            'COM_CONTENTBUILDER_NG',
            'COM_CONTENTBUILDERNG',
        ];
        $quoted = array_map(static fn($t) => $db->quote($t), $legacyTypes);

        $tables = [
            '#__contentbuilderng_forms',
            '#__contentbuilderng_records',
            '#__contentbuilderng_articles',
            '#__contentbuilderng_resource_access',
        ];

        $totalUpdated = 0;

        foreach ($tables as $table) {
            // Ensure column exists
            try {
                $cols = $db->getTableColumns($table, false);
                $cols = is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
                if (!array_key_exists('type', $cols)) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            try {
                $q = $db->getQuery(true)
                    ->update($db->quoteName($table))
                    ->set($db->quoteName('type') . ' = ' . $db->quote($targetType))
                    ->where($db->quoteName('type') . ' IN (' . implode(',', $quoted) . ')');
                $db->setQuery($q);
                $db->execute();
                $updated = (int) $db->getAffectedRows();
                $totalUpdated += $updated;
                if ($updated > 0) {
                    $this->log("[OK] Normalized {$updated} legacy type row(s) in {$table}.");
                }
            } catch (\Throwable $e) {
                $this->log("[WARNING] Failed normalizing legacy types in {$table}: " . $e->getMessage(), Log::WARNING);
            }
        }

        if ($totalUpdated > 0) {
            $this->log("[OK] Legacy type normalization completed: {$totalUpdated} row(s) updated.");
        } else {
            $this->log('[INFO] No legacy ContentBuilder type value needed normalization.');
        }
    }

    // ---------------------------------------------------------------------
    // Legacy components migration / cleanup
    // ---------------------------------------------------------------------
    private function migrateLegacyContentbuilderName(string $legacyElement): void
    {
        $db = $this->db();
        $targetElement = 'com_contentbuilderng';

        // Find legacy component
        $legacyId = (int) $this->safe(function () use ($db, $legacyElement) {
            $q = $db->getQuery(true)
                ->select($db->quoteName('extension_id'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote($legacyElement))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('client_id') . ' = 1');
            $db->setQuery($q);
            return $db->loadResult();
        }, 0);

        if ($legacyId === 0) {
            return;
        }

        // If target exists, don't overwrite now (cleanup later)
        $targetId = (int) $this->safe(function () use ($db, $targetElement) {
            $q = $db->getQuery(true)
                ->select($db->quoteName('extension_id'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote($targetElement))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('client_id') . ' = 1');
            $db->setQuery($q);
            return $db->loadResult();
        }, 0);

        if ($targetId > 0) {
            $this->log("[INFO] Legacy component {$legacyElement} detected while {$targetElement} already exists; cleanup will run during postflight update.");
            return;
        }

        // Migrate extension row element/name
        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('element') . ' = ' . $db->quote($targetElement))
                    ->set($db->quoteName('name') . ' = ' . $db->quote($targetElement))
                    ->where($db->quoteName('extension_id') . ' = ' . (int) $legacyId)
            );
                $db->execute();

            $this->log("[OK] Migrated extension element from {$legacyElement} to {$targetElement}.");
        } catch (\Throwable $e) {
            $this->log("[ERROR] Failed to migrate legacy extension element: " . $e->getMessage(), Log::ERROR);
            return;
        }

        // Update asset name
        $this->safe(function () use ($db, $legacyElement, $targetElement) {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__assets'))
                    ->set($db->quoteName('name') . ' = ' . $db->quote($targetElement))
                    ->where($db->quoteName('name') . ' = ' . $db->quote($legacyElement))
            );
                $db->execute();
        });

        // Update menu links
        $this->updateMenuLinks($legacyElement, $targetElement);

        // Best-effort: normalize alias/title
        $this->safe(function () use ($db) {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__menu'))
                    ->set($db->quoteName('alias') . ' = ' . $db->quote('contentbuilderng'))
                    ->set($db->quoteName('path') . ' = ' . $db->quote('contentbuilderng'))
                    ->set($db->quoteName('title') . ' = ' . $db->quote('COM_CONTENTBUILDERNG'))
                    ->where($db->quoteName('alias') . ' = ' . $db->quote('contentbuilder'))
                    ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_contentbuilder%'))
            );
                $db->execute();
        });
    }

    private function removeLegacyComponent(string $legacyElement): void
    {
        $db = $this->db();
        $targetElement = 'com_contentbuilderng';

        $legacyId = (int) $this->safe(function () use ($db, $legacyElement) {
            $q = $db->getQuery(true)
                ->select($db->quoteName('extension_id'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote($legacyElement))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('client_id') . ' = 1');
            $db->setQuery($q);
            return $db->loadResult();
        }, 0);

        if ($legacyId === 0) {
            return;
        }

        $targetId = (int) $this->safe(function () use ($db, $targetElement) {
            $q = $db->getQuery(true)
                ->select($db->quoteName('extension_id'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote($targetElement))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('client_id') . ' = 1');
            $db->setQuery($q);
            return $db->loadResult();
        }, 0);

        if ($targetId === 0) {
            $this->log("[WARNING] Legacy component {$legacyElement} found but target component is missing; skipping removal.", Log::WARNING);
            return;
        }

        $this->log("[INFO] Legacy component {$legacyElement} detected (id {$legacyId}). Cleaning stale extension row (safe mode, no uninstall hooks).");

        // Keep admin links pointing to NG
        $this->updateMenuLinks($legacyElement, $targetElement);

        // Disable legacy component
        $this->safe(function () use ($db, $legacyId, $legacyElement) {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('enabled') . ' = 0')
                    ->where($db->quoteName('extension_id') . ' = ' . (int) $legacyId)
            );
                $db->execute();
            $this->log("[OK] Legacy component disabled: {$legacyElement} (id {$legacyId}).");
        });

        // Remap menu component_id
        $this->safe(function () use ($db, $legacyId, $targetId) {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__menu'))
                    ->set($db->quoteName('component_id') . ' = ' . (int) $targetId)
                    ->where($db->quoteName('component_id') . ' = ' . (int) $legacyId)
            );
                $db->execute();
        });

        // Clean related tables then delete legacy extension row (best-effort)
        foreach (['#__schemas', '#__update_sites_extensions'] as $table) {
            $this->safe(function () use ($db, $table, $legacyId) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName($table))
                        ->where($db->quoteName('extension_id') . ' = ' . (int) $legacyId)
                );
                $db->execute();
            });
        }

        $this->safe(function () use ($db, $legacyId, $legacyElement) {
            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__extensions'))
                    ->where($db->quoteName('extension_id') . ' = ' . (int) $legacyId)
            );
                $db->execute();
            $this->log("[OK] Legacy component extension row removed: {$legacyElement} (id {$legacyId}).");
        });

        $this->log('[INFO] Legacy component uninstall intentionally skipped to avoid destructive uninstall SQL/hooks.');
    }

    private function deduplicateTargetComponentExtensions(): void
    {
        $db = $this->db();
        $targetElement = 'com_contentbuilderng';

        $rows = $this->safe(function () use ($db, $targetElement) {
            $q = $db->getQuery(true)
                ->select($db->quoteName(['extension_id', 'enabled', 'manifest_cache']))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' = ' . $db->quote($targetElement))
                ->where($db->quoteName('client_id') . ' = 1')
                ->order($db->quoteName('extension_id') . ' DESC');
            $db->setQuery($q);
            return $db->loadAssocList() ?: [];
        }, []);

        if (count($rows) <= 1) {
            return;
        }

        $ids = array_values(array_unique(array_map(static fn($r) => (int) ($r['extension_id'] ?? 0), $rows)));
        $ids = array_values(array_filter($ids, static fn($id) => $id > 0));
        if (count($ids) <= 1) {
            return;
        }

        // Menu ref counts
        $menuRefs = array_fill_keys($ids, 0);
        $this->safe(function () use ($db, $ids, &$menuRefs) {
            $q = $db->getQuery(true)
                ->select([$db->quoteName('component_id'), 'COUNT(1) AS refs'])
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('client_id') . ' = 1')
                ->where($db->quoteName('component_id') . ' IN (' . implode(',', $ids) . ')')
                ->group($db->quoteName('component_id'));
            $db->setQuery($q);
            $refs = $db->loadAssocList() ?: [];
            foreach ($refs as $r) {
                $cid = (int) ($r['component_id'] ?? 0);
                if ($cid > 0) {
                    $menuRefs[$cid] = (int) ($r['refs'] ?? 0);
                }
            }
        });

        // Choose keep: enabled, has manifest, max menu refs, highest id
        $keepId = 0;
        $best = [-1, -1, -1, -1];

        foreach ($rows as $r) {
            $id = (int) ($r['extension_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $enabled = (int) ($r['enabled'] ?? 0);
            $hasManifest = trim((string) ($r['manifest_cache'] ?? '')) !== '' ? 1 : 0;
            $refs = (int) ($menuRefs[$id] ?? 0);

            $score = [$enabled, $hasManifest, $refs, $id];
            if ($keepId === 0 || $score > $best) {
                $best = $score;
                $keepId = $id;
            }
        }

        if ($keepId <= 0) {
            return;
        }

        $removeIds = array_values(array_filter($ids, static fn($id) => $id !== $keepId));
        if (empty($removeIds)) {
            return;
        }

        // Remap menu component_id
        $this->safe(function () use ($db, $keepId, $removeIds) {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__menu'))
                    ->set($db->quoteName('component_id') . ' = ' . (int) $keepId)
                    ->where($db->quoteName('component_id') . ' IN (' . implode(',', $removeIds) . ')')
            );
                $db->execute();
        });

        // Clean & delete duplicates
        foreach (['#__schemas', '#__update_sites_extensions'] as $table) {
            $this->safe(function () use ($db, $table, $removeIds) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName($table))
                        ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $removeIds) . ')')
                );
                $db->execute();
            });
        }

        $this->safe(function () use ($db, $removeIds, $targetElement, $keepId) {
            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__extensions'))
                    ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $removeIds) . ')')
            );
                $db->execute();
            $this->log("[OK] Deduplicated {$targetElement} component rows: kept extension_id {$keepId}, removed " . count($removeIds) . " duplicate(s).");
        });
    }

}
