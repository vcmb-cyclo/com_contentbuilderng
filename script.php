<?php

/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   (C) 2024-2026
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * script.php (Installer Script)
 * - Single-file, clean & hardened, Joomla 6 style (works Joomla 5+)
 * - Keeps ALL legacy handling in best-effort mode
 * - Legacy plugins: DISABLE ONLY (no uninstall), with optional best-effort folder cleanup
 */

defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\File;
use Joomla\Registry\Registry;

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

    // ---------------------------------------------------------------------
    // Lifecycle
    // ---------------------------------------------------------------------
    public function __construct()
    {
        $this->installStartedAt = microtime(true);
        $this->bootLogger();
        $this->log('[INFO] ---------------------------------------------------------', Log::INFO);
        $this->log('[OK] ContentBuilder NG installer script booted.', Log::INFO);

        $this->log('[INFO] Joomla version: <strong>' . htmlspecialchars(defined('JVERSION') ? JVERSION : 'unknown', ENT_QUOTES, 'UTF-8') . '</strong>.', Log::INFO);
        $this->log('[INFO] PHP version: ' . PHP_VERSION . '.', Log::INFO);

        $detected = 'unknown';
        try {
            $detected = $this->getCurrentInstalledVersion();
        } catch (\Throwable) {
            $detected = 'unknown';
        }
        $this->log('[INFO] Detected installed version: ' . htmlspecialchars((string) $detected, ENT_QUOTES, 'UTF-8') . '.', Log::INFO);

        $this->logDatabaseRuntimeInfo();
        $this->log('[INFO] User agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'CLI') . '.', Log::INFO);
        $this->log('[INFO] ===================================================================', Log::INFO);
        $this->log('[INFO] ---------------------------------------------------------', Log::INFO);
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
                    . ' | package <strong>' . htmlspecialchars((string) $incomingVersion, ENT_QUOTES, 'UTF-8') . '</strong>'
                    . ' | installed <strong>' . htmlspecialchars((string) $currentVersion, ENT_QUOTES, 'UTF-8') . '</strong>.',
                Log::INFO
            );

            if ($type !== 'uninstall') {
                $context = 'preflight:' . $type;

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
                )->execute();

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
            }

            // Cleanup old directories/files (best-effort)
            $this->removeOldDirectories();
            $this->removeObsoleteFiles();

            // Ensure media templates / upload dir
            $this->ensureMediaListTemplateInstalled();
            $this->ensureUploadDirectoryExists();

            // DB migrations / hardening
            $this->updateDateColumns();
            $this->ensureFormsNewButtonColumn();
            $this->ensureElementsLinkableDefault();

            // Normalize menu links and titles
            $this->updateMenuLinks('contentbuilder', 'com_contentbuilderng');
            $this->updateMenuLinks('com_contentbuilder', 'com_contentbuilderng');
            $this->updateMenuLinks('com_contentbuilder_ng', 'com_contentbuilderng');

            // Install / update plugins shipped in package
            $source = $this->resolveInstallSourcePath($parent);
            if ($source && is_dir($source)) {
                $this->log('[INFO] Plugin install source resolved: ' . $source, Log::INFO);
            } else {
                $this->log('[WARNING] Plugin install source not resolved; missing plugins may not be installable in this run.', Log::WARNING);
            }

            // Refresh plugins on update (keeps manifest_cache aligned)
            $this->ensurePluginsInstalled($source, $type === 'update');
            $this->activatePlugins();

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
            
            // Normalize storages ordering (your original behavior, update only)
            if ($type === 'update') {
                $this->normalizeStoragesOrdering();
            }

            // Final cache purge (autoload + caches + opcache)
            $this->purgeCaches($context . ':final');

            if ($this->hasCriticalFailure()) {
                $summary = $this->getCriticalFailureSummary();
                $this->log('[ERROR] Postflight completed with critical failures: ' . $summary, Log::ERROR);
                throw new \RuntimeException('ContentBuilder NG postflight failed: ' . $summary);
            }

            $finishedAt = Factory::getDate('now', $this->resolveJoomlaTimezoneName())->format('Y-m-d H:i:s T');
            $durationSeconds = max(0.0, microtime(true) - $this->installStartedAt);

            $this->log(
                '[OK] ContentBuilder NG installation finished. ' . $finishedAt
                    . '. Duration: ' . number_format($durationSeconds, 2, '.', '') . 's.'
            );
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

        $this->rotateSharedLogIfNeeded($logPath);

        Log::addLogger(
            [
                'text_file'         => self::SHARED_LOG_FILE,
                'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}',
                'text_file_path'    => $logPath,
            ],
            Log::ALL,
            ['com_contentbuilderng.install']
        );
    }

    private function log(string $message, int $priority = Log::INFO): void
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
            $app->enqueueMessage($this->formatInstallMessageForDisplay($message), $type);
        } catch (\Throwable) {
            // ignore enqueue failures
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
            Log::add($message, $priority, 'com_contentbuilderng.install');
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
            $this->safe(function () {
                $cache = Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class);
                if (is_object($cache) && method_exists($cache, 'clean')) {
                    $cache->clean();
                }
            });

            $this->safe(function () {
                $app = Factory::getApplication();
                if (is_object($app) && method_exists($app, 'getCache')) {
                    $appCache = $app->getCache();
                    if (is_object($appCache) && method_exists($appCache, 'clean')) {
                        $appCache->clean();
                    }
                }
            });

            foreach (['_system', 'com_installer', 'com_plugins', 'contentbuilder', 'com_contentbuilder', 'com_contentbuilderng'] as $group) {
                $this->safe(function () use ($group) {
                    $groupCache = Factory::getCache($group);
                    if (is_object($groupCache) && method_exists($groupCache, 'clean')) {
                        $groupCache->clean();
                    }
                });
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


    // ---------------------------------------------------------------------
    // Filesystem
    // ---------------------------------------------------------------------
    private function ensureUploadDirectoryExists(): void
    {
        $uploadDir = JPATH_ROOT . '/media/com_contentbuilderng/upload';
        $parentDir = dirname($uploadDir);

        if (!is_dir($parentDir)) {
            if (!Folder::create($parentDir)) {
                $this->log('[WARNING] Could not create upload parent directory: ' . $parentDir, Log::WARNING);
                return;
            }
        }

        if (!is_dir($uploadDir)) {
            if (!Folder::create($uploadDir)) {
                $this->log('[WARNING] Could not create upload directory: ' . $uploadDir, Log::WARNING);
                return;
            }
        }

        $indexFile = $uploadDir . '/index.html';
        if (!is_file($indexFile)) {
            $this->safe(fn() => File::write($indexFile, ''));
        }
    }

    private function ensureMediaListTemplateInstalled(): void
    {
        $source = JPATH_SITE . '/components/com_contentbuilderng/tmpl/list/default.php';
        $target = JPATH_ROOT . '/media/com_contentbuilderng/images/list/tmpl/default.php';
        $targetDir = dirname($target);

        if (is_file($target)) {
            return;
        }

        if (!is_file($source)) {
            $this->log("[WARNING] Missing source list template {$source}; cannot install media list template.", Log::WARNING);
            return;
        }

        if (!is_dir($targetDir) && !Folder::create($targetDir)) {
            $this->log("[WARNING] Could not create media template directory {$targetDir}.", Log::WARNING);
            return;
        }

        if (!File::copy($source, $target)) {
            $this->log("[WARNING] Could not install media list template {$target}.", Log::WARNING);
            return;
        }

        $this->log('[OK] Installed missing media list template: images/list/tmpl/default.php.');
    }

    private function removeOldDirectories(): void
    {
        $paths = [
            JPATH_ADMINISTRATOR . '/components/contentbuilder/',
            JPATH_ADMINISTRATOR . '/components/com_contentbuilder/',
            JPATH_ADMINISTRATOR . '/components/com_contentbuilder_ng/',
            JPATH_SITE . '/components/contentbuilder/',
            JPATH_SITE . '/components/com_contentbuilder/',
            JPATH_SITE . '/components/com_contentbuilder_ng/',
            JPATH_ROOT . '/media/contentbuilder/',
            JPATH_SITE . '/media/com_contentbuilder/',
            JPATH_SITE . '/media/com_contentbuilder_ng/',
        ];

        foreach ($paths as $path) {
            try {
                if (is_dir($path)) {
                    if (Folder::delete($path)) {
                        $this->log("[OK] Old {$path} folder successfully deleted.");
                    } else {
                        $this->log("[WARNING] Failed to delete {$path} folder.", Log::WARNING);
                    }
                } elseif (is_file($path)) {
                    $this->log("[WARNING] Not a {$path} folder, but a file.", Log::WARNING);
                }
            } catch (\Throwable $e) {
                $this->log("[WARNING] Failed removing {$path}: " . $e->getMessage(), Log::WARNING);
            }
        }
    }

    private function removeObsoleteFiles(): void
    {
        $paths = [
            JPATH_ADMINISTRATOR . '/components/contentbuilder/classes/PHPExcel',
            JPATH_ADMINISTRATOR . '/components/com_contentbuilder/classes/PHPExcel',
            JPATH_ADMINISTRATOR . '/components/com_contentbuilder/classes/PHPExcel.php',
        ];

        foreach ($paths as $path) {
            try {
                if (is_file($path)) {
                    if (File::delete($path)) {
                        $this->log("[OK] Removed obsolete file {$path}.");
                    } else {
                        $this->log("[WARNING] Failed to remove obsolete file {$path}.", Log::WARNING);
                    }
                }
            } catch (\Throwable $e) {
                $this->log("[WARNING] Failed removing obsolete file {$path}: " . $e->getMessage(), Log::WARNING);
            }
        }
    }

    // ---------------------------------------------------------------------
    // Tables / schema migrations
    // ---------------------------------------------------------------------
    private function reportLegacyTableCollisions(): void
    {
        try {
            $db = $this->db();
            $prefix = $db->getPrefix();
            $existing = $db->getTableList();

            $collisionCount = 0;
            foreach (self::LEGACY_TABLE_RENAMES as $legacy => $target) {
                $legacyFull = $prefix . $legacy;
                $targetFull = $prefix . $target;

                if (!in_array($legacyFull, $existing, true) || !in_array($targetFull, $existing, true)) {
                    continue;
                }

                $legacyHasRows = false;
                $targetHasRows = false;

                try {
                    $db->setQuery('SELECT 1 FROM ' . $db->quoteName($legacyFull) . ' LIMIT 1');
                    $legacyHasRows = (bool) $db->loadResult();
                    $db->setQuery('SELECT 1 FROM ' . $db->quoteName($targetFull) . ' LIMIT 1');
                    $targetHasRows = (bool) $db->loadResult();
                } catch (\Throwable $e) {
                    $this->log('[WARNING] Could not inspect row presence for collision ' . $legacyFull . ' / ' . $targetFull . ': ' . $e->getMessage(), Log::WARNING);
                }

                $this->log(
                    '[WARNING] Table collision detected: '
                        . $legacyFull . ' (has rows: ' . ($legacyHasRows ? 'yes' : 'no') . ')'
                        . ' and '
                        . $targetFull . ' (has rows: ' . ($targetHasRows ? 'yes' : 'no') . ').',
                    Log::WARNING
                );
                $collisionCount++;
            }

            if ($collisionCount > 0) {
                $this->log('[WARNING] Legacy/NG table collision report: ' . $collisionCount . ' collision(s) detected before migration.', Log::WARNING);
            } else {
                $this->log('[OK] Legacy/NG table collision report: no collision detected.');
            }
        } catch (\Throwable $e) {
            $this->log('[WARNING] Could not build legacy/NG table collision report: ' . $e->getMessage(), Log::WARNING);
        }
    }

    private function renameLegacyTables(): void
    {
        $db = $this->db();
        $prefix = $db->getPrefix();
        $existing = $this->safe(fn() => $db->getTableList(), []);
        $renamed = 0;
        $skipped = 0;
        $missing = 0;

        foreach (self::LEGACY_TABLE_RENAMES as $legacy => $target) {
            $legacyFull = $prefix . $legacy;
            $targetFull = $prefix . $target;

            if (!in_array($legacyFull, $existing, true)) {
                $missing++;
                continue;
            }

            // If target exists: decide whether to drop empty legacy or empty target
            if (in_array($targetFull, $existing, true)) {
                $targetHasRows = false;
                $legacyHasRows = false;

                try {
                    $db->setQuery('SELECT 1 FROM ' . $db->quoteName($targetFull) . ' LIMIT 1');
                    $targetHasRows = (bool) $db->loadResult();
                    $db->setQuery('SELECT 1 FROM ' . $db->quoteName($legacyFull) . ' LIMIT 1');
                    $legacyHasRows = (bool) $db->loadResult();
                } catch (\Throwable $e) {
                    $this->log("[WARNING] Could not inspect {$targetFull}/{$legacyFull}: " . $e->getMessage(), Log::WARNING);
                    $skipped++;
                    continue;
                }

                if (!$legacyHasRows) {
                    // Legacy empty => drop legacy, keep target
                    try {
                        $db->setQuery('DROP TABLE ' . $db->quoteName($legacyFull))->execute();
                        $this->log("[WARNING] Legacy source table {$legacyFull} was empty and has been removed; keeping {$targetFull}.", Log::WARNING);
                        $existing = array_values(array_filter($existing, static fn($name) => $name !== $legacyFull));
                        $skipped++;
                        continue;
                    } catch (\Throwable $e) {
                        $this->log("[WARNING] Failed dropping empty legacy table {$legacyFull}: " . $e->getMessage(), Log::WARNING);
                        $skipped++;
                        continue;
                    }
                }

                if ($targetHasRows) {
                    // Both contain rows => skip rename
                    $this->log("[WARNING] Legacy table {$legacyFull} detected but {$targetFull} already contains data, skipping rename.", Log::WARNING);
                    $skipped++;
                    continue;
                }

                // Target empty + legacy has rows => drop target then rename legacy -> target
                try {
                    $db->setQuery('DROP TABLE ' . $db->quoteName($targetFull))->execute();
                    $this->log("[WARNING] Target table {$targetFull} existed but was empty; it has been replaced by legacy table {$legacyFull}.", Log::WARNING);
                    $existing = array_values(array_filter($existing, static fn($name) => $name !== $targetFull));
                } catch (\Throwable $e) {
                    $this->log("[WARNING] Failed dropping empty target table {$targetFull}: " . $e->getMessage(), Log::WARNING);
                    $skipped++;
                    continue;
                }
            }

            // Rename legacy -> target
            try {
                $db->setQuery("RENAME TABLE " . $db->quoteName($legacyFull) . " TO " . $db->quoteName($targetFull))->execute();
                $this->log("[OK] Renamed table {$legacyFull} to {$targetFull}.");
                $renamed++;
                $existing[] = $targetFull;
                $existing = array_values(array_filter($existing, static fn($name) => $name !== $legacyFull));
            } catch (\Throwable $e) {
                $this->log("[WARNING] Failed to rename table {$legacyFull}: " . $e->getMessage(), Log::WARNING);
            }
        }

        $total = count(self::LEGACY_TABLE_RENAMES);
        $this->log("[OK] Table migration summary: renamed {$renamed}, skipped {$skipped}, missing {$missing} of {$total}.");
    }

    private function updateDateColumns(): void
    {
        $db = $this->db();

        $queries = [
            // #__contentbuilderng_forms
            "ALTER TABLE `#__contentbuilderng_forms` MODIFY `created` DATETIME NULL DEFAULT CURRENT_TIMESTAMP",
            "UPDATE `#__contentbuilderng_forms` SET `created` = NULL WHERE `created` = '0000-00-00'",
            "ALTER TABLE `#__contentbuilderng_forms` MODIFY `modified` DATETIME NULL DEFAULT NULL",
            "UPDATE `#__contentbuilderng_forms` SET `modified` = NULL WHERE `modified` = '0000-00-00'",
            "ALTER TABLE `#__contentbuilderng_forms` MODIFY `last_update` DATETIME NULL DEFAULT NULL",
            "UPDATE `#__contentbuilderng_forms` SET `last_update` = NULL WHERE `last_update` = '0000-00-00'",
            "ALTER TABLE `#__contentbuilderng_forms` MODIFY `rand_date_update` DATETIME NULL DEFAULT NULL",
            "UPDATE `#__contentbuilderng_forms` SET `rand_date_update` = NULL WHERE `rand_date_update` = '0000-00-00'",

            // #__contentbuilderng_records
            "ALTER TABLE `#__contentbuilderng_records` MODIFY `publish_up` DATETIME NULL DEFAULT NULL",
            "UPDATE `#__contentbuilderng_records` SET `publish_up` = NULL WHERE `publish_up` = '0000-00-00'",
            "ALTER TABLE `#__contentbuilderng_records` MODIFY `publish_down` DATETIME NULL DEFAULT NULL",
            "UPDATE `#__contentbuilderng_records` SET `publish_down` = NULL WHERE `publish_down` = '0000-00-00'",
            "ALTER TABLE `#__contentbuilderng_records` MODIFY `last_update` DATETIME NULL DEFAULT NULL",
            "UPDATE `#__contentbuilderng_records` SET `last_update` = NULL WHERE `last_update` = '0000-00-00'",
            "ALTER TABLE `#__contentbuilderng_records` MODIFY `rand_date` DATETIME NULL DEFAULT NULL",
            "UPDATE `#__contentbuilderng_records` SET `rand_date` = NULL WHERE `rand_date` = '0000-00-00'",

            // #__contentbuilderng_articles
            "ALTER TABLE `#__contentbuilderng_articles` MODIFY `last_update` DATETIME NULL DEFAULT NULL",
            "UPDATE `#__contentbuilderng_articles` SET `last_update` = NULL WHERE `last_update` = '0000-00-00'",

            // #__contentbuilderng_users
            "ALTER TABLE `#__contentbuilderng_users` MODIFY `verification_date_view` DATETIME NULL DEFAULT NULL",
            "UPDATE `#__contentbuilderng_users` SET `verification_date_view` = NULL WHERE `verification_date_view` = '0000-00-00'",
            "ALTER TABLE `#__contentbuilderng_users` MODIFY `verification_date_new` DATETIME NULL DEFAULT NULL",
            "UPDATE `#__contentbuilderng_users` SET `verification_date_new` = NULL WHERE `verification_date_new` = '0000-00-00'",
            "ALTER TABLE `#__contentbuilderng_users` MODIFY `verification_date_edit` DATETIME NULL DEFAULT NULL",
            "UPDATE `#__contentbuilderng_users` SET `verification_date_edit` = NULL WHERE `verification_date_edit` = '0000-00-00'",

            // #__contentbuilderng_rating_cache
            "ALTER TABLE `#__contentbuilderng_rating_cache` MODIFY COLUMN `date` DATETIME NULL DEFAULT NULL",
            "UPDATE `#__contentbuilderng_rating_cache` SET `date` = NULL WHERE `date` = '0000-00-00'",

            // #__contentbuilderng_verifications
            "ALTER TABLE `#__contentbuilderng_verifications` MODIFY `start_date` DATETIME NULL DEFAULT NULL",
            "UPDATE `#__contentbuilderng_verifications` SET `start_date` = NULL WHERE `start_date` = '0000-00-00'",
            "ALTER TABLE `#__contentbuilderng_verifications` MODIFY `verification_date` DATETIME NULL DEFAULT NULL",
            "UPDATE `#__contentbuilderng_verifications` SET `verification_date` = NULL WHERE `verification_date` = '0000-00-00'",
        ];

        foreach ($queries as $sql) {
            try {
                $db->setQuery($sql)->execute();
            } catch (\Throwable $e) {
                // Silent if table/column not present or already OK, but keep trace
                $this->log('[WARNING] Could not alter date column: ' . $e->getMessage() . '.', Log::WARNING);
            }
        }

        // Migrate audit columns (storages + internal storage data tables)
        $this->migrateStoragesAuditColumns();
        $this->migrateInternalStorageDataTablesAuditColumns();

        $this->log('[OK] Date fields updated to support NULL correctly, if necessary.');
    }

    private function ensureFormsNewButtonColumn(): void
    {
        $db = $this->db();
        try {
            $cols = $db->getTableColumns('#__contentbuilderng_forms', false);
            if (!is_array($cols) || array_key_exists('new_button', $cols)) {
                return;
            }
        } catch (\Throwable $e) {
            $this->log('[WARNING] Could not inspect #__contentbuilderng_forms columns: ' . $e->getMessage(), Log::WARNING);
            return;
        }

        try {
            $db->setQuery(
                'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_forms')
                    . ' ADD COLUMN ' . $db->quoteName('new_button') . " TINYINT(1) NOT NULL DEFAULT '0'"
            )->execute();
            $this->log('[OK] Added #__contentbuilderng_forms.new_button column.');
        } catch (\Throwable $e) {
            $this->log('[WARNING] Failed adding #__contentbuilderng_forms.new_button column: ' . $e->getMessage(), Log::WARNING);
        }
    }

    private function ensureElementsLinkableDefault(): void
    {
        $db = $this->db();
        try {
            $cols = $db->getTableColumns('#__contentbuilderng_elements', false);
            if (!is_array($cols) || !array_key_exists('linkable', $cols)) {
                return;
            }
        } catch (\Throwable $e) {
            $this->log('[WARNING] Could not inspect #__contentbuilderng_elements columns: ' . $e->getMessage(), Log::WARNING);
            return;
        }

        try {
            $db->setQuery(
                'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_elements')
                    . ' MODIFY ' . $db->quoteName('linkable') . " TINYINT(1) NOT NULL DEFAULT '0'"
            )->execute();
            $this->log('[OK] Ensured #__contentbuilderng_elements.linkable default is 0.');
        } catch (\Throwable $e) {
            $this->log('[WARNING] Failed to set #__contentbuilderng_elements.linkable default: ' . $e->getMessage(), Log::WARNING);
        }
    }

    // ---------------------------------------------------------------------
    // Storages audit migrations
    // ---------------------------------------------------------------------
    private function storageAuditColumnDefinition(string $column): string
    {
        return match ($column) {
            'created'     => 'DATETIME NULL DEFAULT CURRENT_TIMESTAMP',
            'modified'    => 'DATETIME NULL DEFAULT NULL',
            'created_by'  => "VARCHAR(255) NOT NULL DEFAULT ''",
            'modified_by' => "VARCHAR(255) NOT NULL DEFAULT ''",
            default       => 'TEXT NULL',
        };
    }

    private function getStoragesTableColumnsLower(): array
    {
        $db = $this->db();
        try {
            $columns = $db->getTableColumns('#__contentbuilderng_storages', false);
            return array_change_key_case($columns ?: [], CASE_LOWER);
        } catch (\Throwable $e) {
            $this->log('[WARNING] Could not inspect #__contentbuilderng_storages columns: ' . $e->getMessage(), Log::WARNING);
            return [];
        }
    }

    private function migrateStoragesAuditColumns(): void
    {
        $db = $this->db();
        $columns = $this->getStoragesTableColumnsLower();
        if (empty($columns)) {
            return;
        }

        $legacyToStandard = [
            'last_update'  => 'modified',
            'last_updated' => 'modified',
            'createdby'    => 'created_by',
            'modifiedby'   => 'modified_by',
            'updated_by'   => 'modified_by',
        ];

        foreach ($legacyToStandard as $legacy => $target) {
            if (!array_key_exists($legacy, $columns)) {
                continue;
            }

            $targetDef = $this->storageAuditColumnDefinition($target);

            // If target does not exist => rename legacy -> target
            if (!array_key_exists($target, $columns)) {
                try {
                    $db->setQuery(
                        'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_storages')
                            . ' CHANGE ' . $db->quoteName($legacy) . ' ' . $db->quoteName($target) . ' ' . $targetDef
                    )->execute();
                    $this->log("[OK] Renamed storage audit column {$legacy} to {$target}.");
                    $columns = $this->getStoragesTableColumnsLower();
                    continue;
                } catch (\Throwable $e) {
                    $this->log("[WARNING] Failed renaming storage audit column {$legacy} to {$target}: " . $e->getMessage(), Log::WARNING);
                }
            }

            // Copy values if needed
            try {
                if ($target === 'modified' || $target === 'created') {
                    $db->setQuery(
                        'UPDATE ' . $db->quoteName('#__contentbuilderng_storages')
                            . ' SET ' . $db->quoteName($target) . ' = ' . $db->quoteName($legacy)
                            . ' WHERE (' . $db->quoteName($target) . ' IS NULL OR ' . $db->quoteName($target) . " IN ('0000-00-00', '0000-00-00 00:00:00'))"
                            . ' AND ' . $db->quoteName($legacy) . ' IS NOT NULL'
                            . ' AND ' . $db->quoteName($legacy) . " NOT IN ('0000-00-00', '0000-00-00 00:00:00')"
                    )->execute();
                } else {
                    $db->setQuery(
                        'UPDATE ' . $db->quoteName('#__contentbuilderng_storages')
                            . ' SET ' . $db->quoteName($target) . ' = ' . $db->quoteName($legacy)
                            . ' WHERE (' . $db->quoteName($target) . " = '' OR " . $db->quoteName($target) . ' IS NULL)'
                            . ' AND ' . $db->quoteName($legacy) . ' IS NOT NULL'
                            . ' AND ' . $db->quoteName($legacy) . " <> ''"
                    )->execute();
                }
            } catch (\Throwable $e) {
                $this->log("[WARNING] Failed copying data from {$legacy} to {$target}: " . $e->getMessage(), Log::WARNING);
            }

            // Drop legacy column
            if ($legacy !== $target) {
                try {
                    $db->setQuery(
                        'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_storages')
                            . ' DROP COLUMN ' . $db->quoteName($legacy)
                    )->execute();
                    $this->log("[OK] Removed legacy storage audit column {$legacy}.");
                    $columns = $this->getStoragesTableColumnsLower();
                } catch (\Throwable $e) {
                    $this->log("[WARNING] Failed removing legacy storage audit column {$legacy}: " . $e->getMessage(), Log::WARNING);
                }
            }
        }

        // Ensure required columns exist
        foreach (['created', 'modified', 'created_by', 'modified_by'] as $col) {
            if (array_key_exists($col, $columns)) {
                continue;
            }
            try {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_storages')
                        . ' ADD COLUMN ' . $db->quoteName($col) . ' ' . $this->storageAuditColumnDefinition($col)
                )->execute();
                $this->log("[OK] Added storage audit column {$col}.");
            } catch (\Throwable $e) {
                $this->log("[WARNING] Failed adding storage audit column {$col}: " . $e->getMessage(), Log::WARNING);
            }
        }

        // Normalize nulls
        $normalize = [
            "ALTER TABLE `#__contentbuilderng_storages` MODIFY `created` DATETIME NULL DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE `#__contentbuilderng_storages` MODIFY `modified` DATETIME NULL DEFAULT NULL",
            "ALTER TABLE `#__contentbuilderng_storages` MODIFY `created_by` VARCHAR(255) NOT NULL DEFAULT ''",
            "ALTER TABLE `#__contentbuilderng_storages` MODIFY `modified_by` VARCHAR(255) NOT NULL DEFAULT ''",
            "UPDATE `#__contentbuilderng_storages` SET `created` = NULL WHERE `created` IN ('0000-00-00', '0000-00-00 00:00:00')",
            "UPDATE `#__contentbuilderng_storages` SET `modified` = NULL WHERE `modified` IN ('0000-00-00', '0000-00-00 00:00:00')",
            "UPDATE `#__contentbuilderng_storages` SET `created_by` = '' WHERE `created_by` IS NULL",
            "UPDATE `#__contentbuilderng_storages` SET `modified_by` = '' WHERE `modified_by` IS NULL",
        ];
        foreach ($normalize as $sql) {
            $this->safe(fn() => $db->setQuery($sql)->execute());
        }
    }

    private function getIndexedColumns(DatabaseInterface $db, string $tableQN): array
    {
        $indexed = [];
        try {
            $db->setQuery('SHOW INDEX FROM ' . $tableQN);
            $rows = $db->loadAssocList() ?: [];
            foreach ($rows as $row) {
                $col = strtolower((string) ($row['Column_name'] ?? $row['column_name'] ?? ''));
                if ($col !== '') {
                    $indexed[$col] = true;
                }
            }
        } catch (\Throwable $e) {
            $this->log('[WARNING] Could not inspect existing indexes on ' . $tableQN . ': ' . $e->getMessage(), Log::WARNING);
        }
        return $indexed;
    }

    private function getTableIndexes(DatabaseInterface $db, string $tableQN): array
    {
        $indexMap = [];

        $db->setQuery('SHOW INDEX FROM ' . $tableQN);
        $rows = $db->loadAssocList() ?: [];

        foreach ($rows as $row) {
            $keyName = (string) ($row['Key_name'] ?? $row['key_name'] ?? '');
            if ($keyName === '') {
                continue;
            }

            $seq = (int) ($row['Seq_in_index'] ?? $row['seq_in_index'] ?? 0);
            if ($seq < 1) {
                $seq = count($indexMap[$keyName]['columns'] ?? []) + 1;
            }

            $col = strtolower((string) ($row['Column_name'] ?? $row['column_name'] ?? ''));
            if ($col === '') {
                $col = strtolower(trim((string) ($row['Expression'] ?? $row['expression'] ?? '')));
            }
            if ($col === '') {
                continue;
            }

            if (!isset($indexMap[$keyName])) {
                $indexMap[$keyName] = [
                    'non_unique' => (int) ($row['Non_unique'] ?? $row['non_unique'] ?? 1),
                    'index_type' => strtoupper((string) ($row['Index_type'] ?? $row['index_type'] ?? 'BTREE')),
                    'columns'    => [],
                ];
            }

            $indexMap[$keyName]['columns'][$seq] = [
                'name'      => $col,
                'sub_part'  => (string) ($row['Sub_part'] ?? $row['sub_part'] ?? ''),
                'collation' => strtoupper((string) ($row['Collation'] ?? $row['collation'] ?? 'A')),
            ];
        }

        foreach ($indexMap as &$def) {
            ksort($def['columns'], SORT_NUMERIC);
            $def['columns'] = array_values($def['columns']);
            $def['signature'] = $this->indexDefinitionSignature($def);
        }
        unset($def);

        return $indexMap;
    }

    private function indexDefinitionSignature(array $indexDefinition): string
    {
        $parts = [];
        foreach ((array) ($indexDefinition['columns'] ?? []) as $colDef) {
            $parts[] = implode(':', [
                (string) ($colDef['name'] ?? ''),
                (string) ($colDef['sub_part'] ?? ''),
                (string) ($colDef['collation'] ?? ''),
            ]);
        }

        return implode('|', [
            (string) ($indexDefinition['non_unique'] ?? 1),
            strtoupper((string) ($indexDefinition['index_type'] ?? 'BTREE')),
            implode(',', $parts),
        ]);
    }

    private function removeDuplicateIndexes(DatabaseInterface $db, string $tableQN, string $tableAlias, int $storageId = 0): int
    {
        $removed = 0;

        $tableIndexes = $this->safe(fn() => $this->getTableIndexes($db, $tableQN), []);
        if (!$tableIndexes) {
            return 0;
        }

        $sigs = [];
        foreach ($tableIndexes as $keyName => $def) {
            if (strtoupper((string) $keyName) === 'PRIMARY') {
                continue;
            }
            $sig = (string) ($def['signature'] ?? '');
            if ($sig !== '') {
                $sigs[$sig][] = (string) $keyName;
            }
        }

        foreach ($sigs as $names) {
            if (count($names) < 2) {
                continue;
            }
            usort($names, static fn($a, $b) => strcasecmp($a, $b));
            $kept = array_shift($names);

            foreach ($names as $dup) {
                try {
                    $db->setQuery(
                        'ALTER TABLE ' . $tableQN . ' DROP INDEX ' . $db->quoteName($dup)
                    )->execute();
                    $removed++;
                    $suffix = $storageId > 0 ? " (storage {$storageId})" : '';
                    $this->log("[OK] Removed duplicate index {$dup} on {$tableAlias}{$suffix}; kept {$kept}.");
                } catch (\Throwable $e) {
                    $suffix = $storageId > 0 ? " (storage {$storageId})" : '';
                    $this->log("[WARNING] Failed removing duplicate index {$dup} on {$tableAlias}{$suffix}: " . $e->getMessage(), Log::WARNING);
                }
            }
        }

        return $removed;
    }

    private function migrateInternalStorageDataTablesAuditColumns(): void
    {
        $db = $this->db();
        $now = Factory::getDate()->toSql();

        // Load storages where bytable = 0 (internal)
        $storages = $this->safe(function () use ($db) {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'name']))
                ->from($db->quoteName('#__contentbuilderng_storages'))
                ->where('(' . $db->quoteName('bytable') . ' = 0 OR ' . $db->quoteName('bytable') . ' IS NULL)')
                ->where($db->quoteName('name') . " <> ''");
            $db->setQuery($query);
            return $db->loadAssocList() ?: [];
        }, []);

        if (empty($storages)) {
            return;
        }

        $processed = 0;
        $updated = 0;
        $missingTables = 0;
        $duplicatesRemoved = 0;

        foreach ($storages as $storage) {
            $processed++;

            $storageId = (int) ($storage['id'] ?? 0);
            $name = strtolower(trim((string) ($storage['name'] ?? '')));
            if ($storageId < 1 || $name === '' || !preg_match('/^[a-z0-9_]+$/', $name)) {
                continue;
            }

            $tableAlias = '#__' . $name;
            $tableQN = $db->quoteName($tableAlias);

            // Inspect table columns
            try {
                $columns = $db->getTableColumns($tableAlias, false);
            } catch (\Throwable $e) {
                $msg = strtolower((string) $e->getMessage());
                if (strpos($msg, "doesn't exist") !== false || strpos($msg, 'does not exist') !== false) {
                    $this->log("[INFO] Data table {$tableAlias} (storage {$storageId}) is missing; skipping.", Log::INFO);
                } else {
                    $this->log("[WARNING] Could not inspect data table {$tableAlias} (storage {$storageId}): " . $e->getMessage(), Log::WARNING);
                }
                $missingTables++;
                continue;
            }

            if (empty($columns) || !is_array($columns)) {
                $missingTables++;
                continue;
            }

            $columnsLower = array_change_key_case($columns, CASE_LOWER);
            $tableChanged = false;

            // Remove duplicate indexes first
            $removedDup = $this->removeDuplicateIndexes($db, $tableQN, $tableAlias, $storageId);
            if ($removedDup > 0) {
                $duplicatesRemoved += $removedDup;
                $tableChanged = true;
            }

            $indexedColumns = $this->getIndexedColumns($db, $tableQN);

            $requiredColumns = [
                'id'               => 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY',
                'storage_id'        => 'INT NOT NULL DEFAULT ' . $storageId,
                'user_id'           => 'INT NOT NULL DEFAULT 0',
                'created'           => 'DATETIME NOT NULL DEFAULT ' . $db->quote($now),
                'created_by'        => "VARCHAR(255) NOT NULL DEFAULT ''",
                'modified_user_id'  => 'INT NOT NULL DEFAULT 0',
                'modified'          => 'DATETIME NULL DEFAULT NULL',
                'modified_by'       => "VARCHAR(255) NOT NULL DEFAULT ''",
            ];

            foreach ($requiredColumns as $col => $def) {
                if (array_key_exists($col, $columnsLower)) {
                    continue;
                }
                try {
                    $db->setQuery(
                        'ALTER TABLE ' . $tableQN
                            . ' ADD COLUMN ' . $db->quoteName($col) . ' ' . $def
                    )->execute();
                    $columnsLower[$col] = true;
                    $tableChanged = true;
                } catch (\Throwable $e) {
                    $this->log("[WARNING] Failed adding audit column {$col} on {$tableAlias} (storage {$storageId}): " . $e->getMessage(), Log::WARNING);
                }
            }

            // Normalize storage_id to correct ID
            if (array_key_exists('storage_id', $columnsLower)) {
                $this->safe(function () use ($db, $tableQN, $storageId) {
                    $db->setQuery(
                        'UPDATE ' . $tableQN
                            . ' SET ' . $db->quoteName('storage_id') . ' = ' . $storageId
                            . ' WHERE ' . $db->quoteName('storage_id') . ' IS NULL OR ' . $db->quoteName('storage_id') . ' = 0'
                    )->execute();
                });
            }

            // Normalize actor columns
            foreach (['created_by', 'modified_by'] as $actorColumn) {
                if (!array_key_exists($actorColumn, $columnsLower)) {
                    continue;
                }
                $this->safe(function () use ($db, $tableQN, $actorColumn) {
                    $db->setQuery(
                        'UPDATE ' . $tableQN
                            . ' SET ' . $db->quoteName($actorColumn) . " = ''"
                            . ' WHERE ' . $db->quoteName($actorColumn) . ' IS NULL'
                    )->execute();
                });
            }

            // Add indexes (best-effort)
            foreach (['storage_id', 'user_id', 'created', 'modified_user_id', 'modified'] as $indexColumn) {
                if (!array_key_exists($indexColumn, $columnsLower)) {
                    continue;
                }
                if (isset($indexedColumns[$indexColumn])) {
                    continue;
                }

                try {
                    $db->setQuery(
                        'ALTER TABLE ' . $tableQN
                            . ' ADD INDEX (' . $db->quoteName($indexColumn) . ')'
                    )->execute();
                    $indexedColumns[$indexColumn] = true;
                    $tableChanged = true;
                } catch (\Throwable $e) {
                    $m = strtolower((string) $e->getMessage());
                    if (strpos($m, 'too many keys') !== false) {
                        $this->log("[WARNING] Max index count reached on {$tableAlias} (storage {$storageId}); skipping remaining index additions.", Log::WARNING);
                        break;
                    }
                }
            }

            if ($tableChanged) {
                $updated++;
            }
        }

        $this->log("[OK] Internal storage audit migration complete. Processed: {$processed}, updated: {$updated}, missing tables: {$missingTables}, duplicate indexes removed: {$duplicatesRemoved}.");
    }

    // ---------------------------------------------------------------------
    // Plugins (NG)
    // ---------------------------------------------------------------------
    private function getPlugins(): array
    {
        $plugins = [];
        $plugins['contentbuilderng_verify'] = ['paypal', 'passthrough'];
        $plugins['contentbuilderng_validation'] = ['notempty', 'equal', 'email', 'date_not_before', 'date_is_valid'];
        $plugins['contentbuilderng_themes'] = ['khepri', 'blank', 'joomla6', 'dark'];
        $plugins['system'] = ['contentbuilderng_system'];
        $plugins['contentbuilderng_submit'] = ['submit_sample'];
        $plugins['contentbuilderng_listaction'] = ['trash', 'untrash'];
        $plugins['content'] = ['contentbuilderng_verify', 'contentbuilderng_permission_observer', 'contentbuilderng_image_scale', 'contentbuilderng_download', 'contentbuilderng_rating'];
        return $plugins;
    }

    private function resolveInstallSourcePath($parent): ?string
    {
        $candidates = [];
        $push = static function (array &$list, $path): void {
            $path = trim((string) $path);
            if ($path === '') {
                return;
            }
            if (is_file($path)) {
                $path = dirname($path);
            }
            $path = rtrim($path, '/\\');
            if ($path !== '' && !in_array($path, $list, true)) {
                $list[] = $path;
            }
        };

        if (is_object($parent) && method_exists($parent, 'getPath')) {
            $this->safe(function () use ($parent, &$push, &$candidates) {
                $push($candidates, $parent->getPath('source'));
                $push($candidates, $parent->getPath('manifest'));
            });
        }

        if (is_object($parent) && method_exists($parent, 'getParent')) {
            $this->safe(function () use ($parent, &$push, &$candidates) {
                $p = $parent->getParent();
                if (is_object($p) && method_exists($p, 'getPath')) {
                    $push($candidates, $p->getPath('source'));
                    $push($candidates, $p->getPath('manifest'));
                }
            });
        }

        $push($candidates, __DIR__);

        foreach ($candidates as $candidate) {
            if (is_dir($candidate . '/plugins')) {
                return $candidate;
            }
        }
        return $candidates[0] ?? null;
    }

    private function resolvePluginSourcePath(?string $source, string $folder, string $element): string
    {
        $source = $source ? rtrim($source, '/') : null;

        $candidates = [];
        if ($source) {
            $candidates[] = $source . '/plugins/' . $folder . '/' . $element;

            // legacy package layouts (best-effort)
            $legacy = $this->getLegacyPluginSourcePath($source, $folder, $element);
            if ($legacy) {
                $candidates[] = $legacy;
            }
        }

        // Fallback: already installed location
        $candidates[] = JPATH_ROOT . '/plugins/' . $folder . '/' . $element;

        foreach ($candidates as $p) {
            if (is_dir($p)) {
                return $p;
            }
        }

        return $candidates[0];
    }

    private function getLegacyPluginSourcePath(string $source, string $folder, string $element): ?string
    {
        if ($folder === 'system' && $element === 'contentbuilderng_system') {
            return $source . '/plugins/plg_system';
        }

        if ($folder === 'contentbuilderng_themes') {
            $candidates = [
                $source . '/plugins/contentbuilderng_themes/' . $element,
                $source . '/plugins/contentbuilder_themes_ng/' . $element,
                $source . '/plugins/contentbuilder_themes/' . $element,
            ];
            foreach ($candidates as $c) {
                if (is_dir($c)) {
                    return $c;
                }
            }
            return $candidates[0];
        }

        if ($folder === 'content' && str_starts_with($element, 'contentbuilderng_')) {
            $suffix = substr($element, strlen('contentbuilderng_'));
            return $source . '/plugins/plg_content_' . $suffix;
        }

        if (str_starts_with($folder, 'contentbuilderng_')) {
            $short = substr($folder, strlen('contentbuilderng_'));
            return $source . '/plugins/plg_' . $short . '_' . $element;
        }

        return null;
    }

    private function installPluginFromPath(string $path): bool
    {
        $installer = new Installer();
        $installer->setDatabase(Factory::getContainer()->get(DatabaseInterface::class));
        return (bool) $installer->install($path);
    }

    private function getPluginManifestVersion(string $path): ?string
    {
        $files = glob(rtrim($path, '/') . '/*.xml') ?: [];
        foreach ($files as $file) {
            try {
                $xml = simplexml_load_file($file);
                if (!$xml || $xml->getName() !== 'extension') {
                    continue;
                }
                $v = isset($xml->version) ? trim((string) $xml->version) : '';
                if ($v !== '') {
                    return $v;
                }
                $attr = isset($xml['version']) ? trim((string) $xml['version']) : '';
                if ($attr !== '') {
                    return $attr;
                }
            } catch (\Throwable) {
                continue;
            }
        }
        return null;
    }

    private function ensurePluginsInstalled(?string $source = null, bool $forceUpdate = false): void
    {
        $db = $this->db();
        $plugins = $this->getPlugins();

        $total = 0;
        if ($forceUpdate) {
            foreach ($plugins as $elements) {
                $total += count($elements);
            }
        }
        $i = 0;

        foreach ($plugins as $folder => $elements) {
            foreach ($elements as $element) {
                $query = $db->getQuery(true)
                    ->select($db->quoteName(['extension_id', 'manifest_cache']))
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder') . ' = ' . $db->quote($folder))
                    ->where($db->quoteName('element') . ' = ' . $db->quote($element));

                $db->setQuery($query);
                $row = $db->loadAssoc() ?: [];
                $id  = (int) ($row['extension_id'] ?? 0);

                $installedVersion = null;
                if (!empty($row['manifest_cache'])) {
                    $cache = json_decode((string) $row['manifest_cache'], true);
                    if (is_array($cache)) {
                        $installedVersion = $cache['version'] ?? null;
                    }
                }

                $path = $this->resolvePluginSourcePath($source, $folder, $element);
                if (!is_dir($path)) {
                    $this->log("[WARNING] Plugin folder not found: {$path}", Log::WARNING);
                    continue;
                }

                $rank = '';
                if ($forceUpdate) {
                    $i++;
                    $rank = $total > 0 ? " ({$i}/{$total})" : '';
                    $ok = $this->safe(fn() => $this->installPluginFromPath($path), false);
                    if ($ok) {
                        $this->log("[OK] Plugin refreshed{$rank}: {$folder}/{$element}");
                    } else {
                        $this->log("[ERROR] Plugin refresh failed{$rank}: {$folder}/{$element}", Log::ERROR);
                    }
                    continue;
                }

                if ($id > 0) {
                    // Update only if needed (or strings differ)
                    $manifestVersion = $this->getPluginManifestVersion($path);
                    $needsUpdate = !$installedVersion || !$manifestVersion || version_compare((string) $installedVersion, (string) $manifestVersion, '<') || ((string) $installedVersion !== (string) $manifestVersion);

                    if (!$needsUpdate) {
                        $this->log("[INFO] Plugin already installed: {$folder}/{$element} (version {$installedVersion})");
                        continue;
                    }

                    $ok = $this->safe(fn() => $this->installPluginFromPath($path), false);
                    if ($ok) {
                        $this->log("[OK] Plugin updated: {$folder}/{$element} (version {$installedVersion} -> {$manifestVersion})");
                    } else {
                        $this->log("[ERROR] Plugin update failed: {$folder}/{$element}", Log::ERROR);
                    }
                } else {
                    // Fresh install
                    $ok = $this->safe(fn() => $this->installPluginFromPath($path), false);
                    if ($ok) {
                        $this->log("[OK] Plugin installed: {$folder}/{$element}");
                    } else {
                        $this->log("[ERROR] Plugin install failed: {$folder}/{$element}", Log::ERROR);
                    }
                }
            }
        }
    }

    private function activatePlugins(): void
    {
        $db = $this->db();
        foreach ($this->getPlugins() as $folder => $elements) {
            foreach ($elements as $element) {
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('enabled') . ' = 1')
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder') . ' = ' . $db->quote($folder))
                    ->where($db->quoteName('element') . ' = ' . $db->quote($element));
                try {
                    $db->setQuery($query)->execute();
                    $this->log("[OK] Plugin enabled: {$folder}/{$element}");
                } catch (\Throwable $e) {
                    $this->log("[ERROR] Failed enabling {$folder}/{$element}: " . $e->getMessage(), Log::ERROR);
                }
            }
        }
    }

    // ---------------------------------------------------------------------
    // NG themes cleanup/normalization
    // ---------------------------------------------------------------------
    private function removeDeprecatedThemePlugins(): void
    {
        $db = $this->db();
        $installer = new Installer();
        $installer->setDatabase(Factory::getContainer()->get(DatabaseInterface::class));

        $supported = ['blank', 'joomla6', 'dark', 'khepri'];

        try {
            $query = $db->getQuery(true)
                ->select([$db->quoteName('extension_id'), $db->quoteName('element')])
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('contentbuilderng_themes'));
            $db->setQuery($query);
            $installed = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $this->log('[WARNING] Failed reading theme plugins: ' . $e->getMessage(), Log::WARNING);
            return;
        }

        foreach ($installed as $row) {
            $id = (int) ($row['extension_id'] ?? 0);
            $element = (string) ($row['element'] ?? '');
            if ($id < 1 || $element === '' || in_array($element, $supported, true)) {
                continue;
            }

            $this->log("[INFO] Removing unsupported theme plugin contentbuilderng_themes/{$element} (id {$id}).");
            $this->safe(fn() => $installer->uninstall('plugin', $id, 1));
        }
    }

    private function normalizeFormThemePlugins(): void
    {
        $db = $this->db();

        // Ensure column exists
        try {
            $columns = $db->getTableColumns('#__contentbuilderng_forms', false);
            if (!is_array($columns) || !array_key_exists('theme_plugin', $columns)) {
                $this->log('[INFO] Theme normalization skipped: #__contentbuilderng_forms.theme_plugin column not found.');
                return;
            }
        } catch (\Throwable $e) {
            $this->log('[WARNING] Could not inspect #__contentbuilderng_forms columns: ' . $e->getMessage(), Log::WARNING);
            return;
        }

        $supported = $this->getPlugins()['contentbuilderng_themes'] ?? ['joomla6'];
        if (!in_array('joomla6', $supported, true)) {
            $supported[] = 'joomla6';
        }

        // Migrate joomla3 -> joomla6
        $migratedLegacy = 0;
        $migratedUnsupported = 0;

        try {
            $q = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_forms'))
                ->set($db->quoteName('theme_plugin') . ' = ' . $db->quote('joomla6'))
                ->where($db->quoteName('theme_plugin') . ' = ' . $db->quote('joomla3'));
            $db->setQuery($q)->execute();
            $migratedLegacy = (int) $db->getAffectedRows();
        } catch (\Throwable $e) {
            $this->log('[WARNING] Failed migrating joomla3 theme references: ' . $e->getMessage(), Log::WARNING);
        }

        // Replace unsupported stored themes with joomla6
        try {
            $q = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('theme_plugin'))
                ->from($db->quoteName('#__contentbuilderng_forms'))
                ->where($db->quoteName('theme_plugin') . ' IS NOT NULL')
                ->where($db->quoteName('theme_plugin') . " <> ''");
            $db->setQuery($q);
            $stored = $db->loadColumn() ?: [];
            $unsupported = array_values(array_diff($stored, $supported));

            if (!empty($unsupported)) {
                $quoted = array_map(static fn($t) => $db->quote((string) $t), $unsupported);
                $q = $db->getQuery(true)
                    ->update($db->quoteName('#__contentbuilderng_forms'))
                    ->set($db->quoteName('theme_plugin') . ' = ' . $db->quote('joomla6'))
                    ->where($db->quoteName('theme_plugin') . ' IN (' . implode(',', $quoted) . ')');
                $db->setQuery($q)->execute();
                $migratedUnsupported = (int) $db->getAffectedRows();
            }
        } catch (\Throwable $e) {
            $this->log('[WARNING] Failed normalizing unsupported theme references: ' . $e->getMessage(), Log::WARNING);
        }

        if ($migratedLegacy > 0 || $migratedUnsupported > 0) {
            $this->log("[OK] Normalized form theme references to joomla6: {$migratedLegacy} legacy + {$migratedUnsupported} unsupported.");
        } else {
            $this->log('[INFO] No form theme references needed normalization.');
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
                $db->setQuery($q)->execute();
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
            )->execute();

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
            )->execute();
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
            )->execute();
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
            )->execute();
            $this->log("[OK] Legacy component disabled: {$legacyElement} (id {$legacyId}).");
        });

        // Remap menu component_id
        $this->safe(function () use ($db, $legacyId, $targetId) {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__menu'))
                    ->set($db->quoteName('component_id') . ' = ' . (int) $targetId)
                    ->where($db->quoteName('component_id') . ' = ' . (int) $legacyId)
            )->execute();
        });

        // Clean related tables then delete legacy extension row (best-effort)
        foreach (['#__schemas', '#__update_sites_extensions'] as $table) {
            $this->safe(function () use ($db, $table, $legacyId) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName($table))
                        ->where($db->quoteName('extension_id') . ' = ' . (int) $legacyId)
                )->execute();
            });
        }

        $this->safe(function () use ($db, $legacyId, $legacyElement) {
            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__extensions'))
                    ->where($db->quoteName('extension_id') . ' = ' . (int) $legacyId)
            )->execute();
            $this->log("[OK] Legacy component extension row removed: {$legacyElement} (id {$legacyId}).");
        });

        $this->log('[INFO] Legacy component uninstall intentionally skipped to avoid destructive uninstall SQL/hooks.');
    }

    // ---------------------------------------------------------------------
    // Legacy plugins: DISABLE ONLY + best-effort folder cleanup
    // ---------------------------------------------------------------------
    private function disableLegacySystemPluginFirst(string $context = 'update'): int
    {
        $db = $this->db();

        try {
            $q = $db->getQuery(true)
                ->select($db->quoteName(['extension_id', 'enabled']))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('contentbuilder_system'));

            $db->setQuery($q);
            $rows = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $this->log("[WARNING] Failed to detect legacy system plugin during {$context}: " . $e->getMessage(), Log::WARNING);
            return 0;
        }

        if (empty($rows)) {
            return 0;
        }

        $ids = [];
        $alreadyDisabled = 0;
        foreach ($rows as $r) {
            $id = (int) ($r['extension_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
            if ((int) ($r['enabled'] ?? 0) === 0) {
                $alreadyDisabled++;
            }
        }
        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            return 0;
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('enabled') . ' = 0')
                    ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $ids) . ')')
            )->execute();
        } catch (\Throwable $e) {
            $this->log("[WARNING] Failed disabling legacy system plugin during {$context}: " . $e->getMessage(), Log::WARNING);
            return 0;
        }

        $disabledNow = max(0, count($ids) - $alreadyDisabled);
        $this->log("[OK] Legacy plugin disabled first: system/contentbuilder_system ({$disabledNow} newly disabled, " . count($ids) . " total) during {$context}.");

        $this->purgeCaches($context . ':disableLegacySystemPluginFirst');

        return count($ids);
    }

    private function getLegacyContentbuilderPlugins(): array
    {
        $db = $this->db();
        $likeLegacy = $db->quote('contentbuilder%');
        $likeNg     = $db->quote('contentbuilderng%');

        $folderCond  = $db->quoteName('folder') . ' LIKE ' . $likeLegacy . ' AND ' . $db->quoteName('folder') . ' NOT LIKE ' . $likeNg;
        $elementCond = $db->quoteName('element') . ' LIKE ' . $likeLegacy . ' AND ' . $db->quoteName('element') . ' NOT LIKE ' . $likeNg;

        $q = $db->getQuery(true)
            ->select($db->quoteName(['extension_id', 'folder', 'element', 'enabled']))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where("(($folderCond) OR ($elementCond))");

        try {
            $db->setQuery($q);
            return $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $this->log('[WARNING] Failed to detect legacy ContentBuilder plugins: ' . $e->getMessage(), Log::WARNING);
            return [];
        }
    }

    private function disableLegacyContentbuilderPlugins(string $context = 'update', bool $excludeSystemPlugin = false): int
    {
        $db = $this->db();
        $rows = $this->getLegacyContentbuilderPlugins();

        if (empty($rows)) {
            return 0;
        }

        $ids = [];
        $alreadyDisabled = 0;

        foreach ($rows as $row) {
            $folder = strtolower((string) ($row['folder'] ?? ''));
            $element = strtolower((string) ($row['element'] ?? ''));

            if ($excludeSystemPlugin && $folder === 'system' && $element === 'contentbuilder_system') {
                continue;
            }

            $id = (int) ($row['extension_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
            if ((int) ($row['enabled'] ?? 0) === 0) {
                $alreadyDisabled++;
            }
        }

        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            return 0;
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('enabled') . ' = 0')
                    ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $ids) . ')')
            )->execute();
        } catch (\Throwable $e) {
            $this->log("[WARNING] Failed disabling legacy ContentBuilder plugins: " . $e->getMessage(), Log::WARNING);
            return 0;
        }

        $disabledNow = max(0, count($ids) - $alreadyDisabled);
        $this->log("[OK] Legacy ContentBuilder plugins disabled ({$disabledNow} newly disabled, " . count($ids) . " total) during {$context}.");

        return count($ids);
    }

    private function disableLegacyPluginsInPriorityOrder(string $context = 'update'): int
    {
        $disabled = 0;
        $disabled += $this->disableLegacySystemPluginFirst($context . ':first');
        $disabled += $this->disableLegacyContentbuilderPlugins($context . ':others', true);
        return $disabled;
    }

    private function removeLegacySystemPluginFolderEarly(string $context = 'postflight'): void
    {
        $path = JPATH_ROOT . '/plugins/system/contentbuilder_system';

        if (is_dir($path)) {
            if (Folder::delete($path)) {
                $this->log("[OK] Legacy system plugin folder deleted first during {$context}: {$path}");
                $this->purgeCaches($context . ':removeLegacySystemPluginFolderEarly');
            } else {
                $this->log("[WARNING] Failed deleting legacy system plugin folder during {$context}: {$path}", Log::WARNING);
            }
            return;
        }

        if (is_file($path)) {
            if (File::delete($path)) {
                $this->log("[OK] Legacy system plugin file deleted first during {$context}: {$path}");
                $this->purgeCaches($context . ':removeLegacySystemPluginFolderEarly');
            } else {
                $this->log("[WARNING] Failed deleting legacy system plugin file during {$context}: {$path}", Log::WARNING);
            }
        }
    }

    private function removeLegacyPluginsDisableOnly(): void
    {
        $disabled = $this->disableLegacyContentbuilderPlugins('postflight:disableOnly');
        if ($disabled > 0) {
            $this->log('[INFO] Legacy plugins are disabled (NOT uninstalled) to avoid destructive uninstall hooks.');
        }
    }

    private function removeLegacyPluginFoldersBestEffort(): void
    {
        $pluginRoot = JPATH_ROOT . '/plugins';
        if (!is_dir($pluginRoot)) {
            return;
        }

        $paths = [];

        // (A) Known legacy plugin folders derived from NG plugin map
        foreach ($this->getPlugins() as $folder => $elements) {
            foreach ($elements as $element) {
                [$legacyFolder, $legacyElement] = $this->mapToLegacyPlugin($folder, $element);
                if ($legacyFolder && $legacyElement) {
                    $paths[] = $pluginRoot . '/' . $legacyFolder . '/' . $legacyElement;
                }
            }
        }

        // (B) Catch-all: any folder/element starting with contentbuilder but not contentbuilderng
        $groupFolders = Folder::folders($pluginRoot, '.', false, true) ?: [];
        foreach ($groupFolders as $groupPath) {
            $groupName = strtolower(basename($groupPath));

            if (str_starts_with($groupName, 'contentbuilder') && !str_starts_with($groupName, 'contentbuilderng')) {
                $paths[] = $groupPath;
                continue;
            }

            if (!in_array($groupName, ['content', 'system'], true)) {
                continue;
            }

            $elements = Folder::folders($groupPath, '.', false, true) ?: [];
            foreach ($elements as $elementPath) {
                $elementName = strtolower(basename($elementPath));
                if (str_starts_with($elementName, 'contentbuilder') && !str_starts_with($elementName, 'contentbuilderng')) {
                    $paths[] = $elementPath;
                }
            }
        }

        $paths = array_values(array_unique(array_map(static fn($p) => rtrim((string) $p, '/\\'), $paths)));
        if (empty($paths)) {
            return;
        }

        // Delete deeper paths first
        usort($paths, static fn($a, $b) => strlen($b) <=> strlen($a));

        $deleted = 0;
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            try {
                if (Folder::delete($path)) {
                    $deleted++;
                    $this->log("[OK] Legacy plugin folder deleted (best-effort): {$path}");
                }
            } catch (\Throwable $e) {
                $this->log("[WARNING] Failed deleting legacy plugin folder {$path}: " . $e->getMessage(), Log::WARNING);
            }
        }

        if ($deleted > 0) {
            $this->purgeCaches('postflight:legacyPluginFoldersCleanup');
        }
    }

    private function mapToLegacyPlugin(string $folder, string $element): array
    {
        if ($folder === 'system' && $element === 'contentbuilderng_system') {
            return ['system', 'contentbuilder_system'];
        }

        if ($folder === 'content' && str_starts_with($element, 'contentbuilderng_')) {
            $suffix = substr($element, strlen('contentbuilderng_'));
            return ['content', 'contentbuilder_' . $suffix];
        }

        if (str_starts_with($folder, 'contentbuilderng_')) {
            $legacyFolder = 'contentbuilder_' . substr($folder, strlen('contentbuilderng_'));
            return [$legacyFolder, $element];
        }

        return [null, null];
    }

    // ---------------------------------------------------------------------
    // Deduplication (plugins + component)
    // ---------------------------------------------------------------------
    private function canonicalizePluginFolder(string $folder): string
    {
        $folder = strtolower(trim($folder));
        if ($folder === '') {
            return $folder;
        }

        if ($folder === 'contentbuilder_themes_ng' || $folder === 'contentbuilder_themes') {
            return 'contentbuilderng_themes';
        }

        if (str_starts_with($folder, 'contentbuilder_ng_')) {
            return 'contentbuilderng_' . substr($folder, strlen('contentbuilder_ng_'));
        }

        if (str_starts_with($folder, 'contentbuilder_')) {
            return 'contentbuilderng_' . substr($folder, strlen('contentbuilder_'));
        }

        return $folder;
    }

    private function canonicalizePluginElement(string $element): string
    {
        $element = strtolower(trim($element));
        if ($element === '') {
            return $element;
        }

        if (str_starts_with($element, 'contentbuilder_ng_')) {
            return 'contentbuilderng_' . substr($element, strlen('contentbuilder_ng_'));
        }

        if (str_starts_with($element, 'contentbuilder_')) {
            return 'contentbuilderng_' . substr($element, strlen('contentbuilder_'));
        }

        return $element;
    }

    private function deduplicateTargetPluginExtensions(): void
    {
        $db = $this->db();

        $rows = $this->safe(function () use ($db) {
            $q = $db->getQuery(true)
                ->select($db->quoteName(['extension_id', 'folder', 'element', 'enabled', 'manifest_cache']))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where(
                    '('
                        . $db->quoteName('folder') . ' LIKE ' . $db->quote('contentbuilder%')
                        . ' OR '
                        . $db->quoteName('element') . ' LIKE ' . $db->quote('contentbuilder%')
                        . ')'
                )
                ->order($db->quoteName('extension_id') . ' DESC');
            $db->setQuery($q);
            return $db->loadAssocList() ?: [];
        }, []);

        if (empty($rows)) {
            return;
        }

        $groups = [];
        foreach ($rows as $row) {
            $id = (int) ($row['extension_id'] ?? 0);
            $folder = strtolower(trim((string) ($row['folder'] ?? '')));
            $element = strtolower(trim((string) ($row['element'] ?? '')));
            if ($id <= 0 || $folder === '' || $element === '') {
                continue;
            }

            $canonFolder = $this->canonicalizePluginFolder($folder);
            $canonElement = $this->canonicalizePluginElement($element);
            $key = $canonFolder . '/' . $canonElement;

            $row['extension_id'] = $id;
            $row['folder'] = $folder;
            $row['element'] = $element;
            $row['canonical_folder'] = $canonFolder;
            $row['canonical_element'] = $canonElement;

            $groups[$key][] = $row;
        }

        $removedTotal = 0;
        $groupCount = 0;

        foreach ($groups as $key => $groupRows) {
            if (!is_array($groupRows) || count($groupRows) < 2) {
                continue;
            }

            [$canonFolder, $canonElement] = explode('/', $key, 2);

            // Choose best candidate to keep:
            // prefer canonical naming, then enabled, then has manifest_cache, then highest id
            $keepId = 0;
            $best = [-1, -1, -1, -1];

            foreach ($groupRows as $r) {
                $id = (int) ($r['extension_id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $isCanonical = ((string) ($r['folder'] ?? '') === $canonFolder && (string) ($r['element'] ?? '') === $canonElement) ? 1 : 0;
                $enabled = (int) ($r['enabled'] ?? 0);
                $hasManifest = trim((string) ($r['manifest_cache'] ?? '')) !== '' ? 1 : 0;

                $score = [$isCanonical, $enabled, $hasManifest, $id];
                if ($keepId === 0 || $score > $best) {
                    $best = $score;
                    $keepId = $id;
                }
            }

            if ($keepId <= 0) {
                continue;
            }

            $removeIds = [];
            foreach ($groupRows as $r) {
                $id = (int) ($r['extension_id'] ?? 0);
                if ($id > 0 && $id !== $keepId) {
                    $removeIds[] = $id;
                }
            }
            $removeIds = array_values(array_unique($removeIds));
            if (empty($removeIds)) {
                continue;
            }

            try {
                // Normalize kept row naming
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__extensions'))
                        ->set($db->quoteName('folder') . ' = ' . $db->quote($canonFolder))
                        ->set($db->quoteName('element') . ' = ' . $db->quote($canonElement))
                        ->where($db->quoteName('extension_id') . ' = ' . (int) $keepId)
                )->execute();

                // Clean related rows
                foreach (['#__schemas', '#__update_sites_extensions'] as $table) {
                    $db->setQuery(
                        $db->getQuery(true)
                            ->delete($db->quoteName($table))
                            ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $removeIds) . ')')
                    )->execute();
                }

                // Delete duplicates
                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__extensions'))
                        ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $removeIds) . ')')
                )->execute();

                $removedTotal += count($removeIds);
                $groupCount++;

                $this->log("[OK] Deduplicated plugin {$canonFolder}/{$canonElement}: kept {$keepId}, removed " . implode(',', $removeIds) . '.');
            } catch (\Throwable $e) {
                $this->log("[WARNING] Failed deduplicating plugin {$canonFolder}/{$canonElement}: " . $e->getMessage(), Log::WARNING);
            }
        }

        if ($groupCount > 0) {
            $this->log("[OK] Plugin deduplication completed: {$groupCount} group(s), {$removedTotal} duplicate row(s) removed.");
        }
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
            )->execute();
        });

        // Clean & delete duplicates
        foreach (['#__schemas', '#__update_sites_extensions'] as $table) {
            $this->safe(function () use ($db, $table, $removeIds) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName($table))
                        ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $removeIds) . ')')
                )->execute();
            });
        }

        $this->safe(function () use ($db, $removeIds, $targetElement, $keepId) {
            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__extensions'))
                    ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $removeIds) . ')')
            )->execute();
            $this->log("[OK] Deduplicated {$targetElement} component rows: kept extension_id {$keepId}, removed " . count($removeIds) . " duplicate(s).");
        });
    }

    // ---------------------------------------------------------------------
    // Menu fixes
    // ---------------------------------------------------------------------
    private function buildMenuLinkOptionWhereClauses(DatabaseInterface $db, string $option): array
    {
        $param = 'option=' . $option;
        return [
            $db->quoteName('link') . ' = ' . $db->quote('index.php?' . $param),
            $db->quoteName('link') . ' LIKE ' . $db->quote('index.php?' . $param . '&%'),
            $db->quoteName('link') . ' LIKE ' . $db->quote('%&' . $param),
            $db->quoteName('link') . ' LIKE ' . $db->quote('%&' . $param . '&%'),
        ];
    }

    private function updateMenuLinks(string $legacyElement, string $targetElement): void
    {
        $db = $this->db();
        $conditions = $this->buildMenuLinkOptionWhereClauses($db, $legacyElement);

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__menu'))
                    ->set(
                        $db->quoteName('link') . ' = REPLACE(' . $db->quoteName('link') . ', '
                            . $db->quote('option=' . $legacyElement) . ', ' . $db->quote('option=' . $targetElement) . ')'
                    )
                    ->where('(' . implode(' OR ', $conditions) . ')')
            )->execute();
            $this->log("[OK] Updated menu links to point to {$targetElement}.");
        } catch (\Throwable $e) {
            $this->log("[WARNING] Could not update menu links for legacy option {$legacyElement}: " . $e->getMessage(), Log::WARNING);
        }
    }

    private function normalizeBrokenTargetMenuLinks(): void
    {
        $db = $this->db();
        $passes = 0;
        $total = 0;

        while ($passes < 5) {
            $passes++;
            try {
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__menu'))
                        ->set(
                            $db->quoteName('link') . ' = REPLACE(' . $db->quoteName('link') . ', '
                                . $db->quote('option=com_contentbuilder_ng_ng') . ', ' . $db->quote('option=com_contentbuilderng') . ')'
                        )
                        ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_contentbuilder_ng_ng%'))
                )->execute();

                $affected = (int) $db->getAffectedRows();
                $total += $affected;

                if ($affected === 0) {
                    break;
                }
            } catch (\Throwable $e) {
                $this->log('[WARNING] Failed to normalize broken menu links: ' . $e->getMessage(), Log::WARNING);
                break;
            }
        }

        if ($total > 0) {
            $this->log("[OK] Normalized {$total} broken com_contentbuilderng menu link(s).");
        }
    }

    private function ensureAdminMenuRootNodeExists(): void
    {
        $db = $this->db();

        try {
            // 1) Cas normal : id=1 existe
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'alias', 'client_id']))
                    ->from($db->quoteName('#__menu'))
                    ->where($db->quoteName('id') . ' = 1')
            );
            $row = $db->loadAssoc();

            if (is_array($row) && !empty($row)) {
                $alias = strtolower(trim((string) ($row['alias'] ?? '')));
                $clientId = (int) ($row['client_id'] ?? 1);

                // Si c'est déjà root, OK
                if ($alias === 'root') {
                    return;
                }

                // DB non-standard: on n'essaie pas de "forcer" un id=1 existant
                $this->log('[WARNING] Admin menu root check: id=1 exists but is not the expected root node; leaving untouched.', Log::WARNING);
                return;
            }

            // 2) DB cassée : id=1 absent => tenter création best-effort
            // On insère explicitement des colonnes "stables" (schéma Joomla récent).
            // Si certaines colonnes manquent/existent en plus, l'exception est catchée.
            $columns = [
                'id',
                'menutype',
                'title',
                'alias',
                'note',
                'path',
                'link',
                'type',
                'published',
                'parent_id',
                'level',
                'component_id',
                'checked_out',
                'checked_out_time',
                'browserNav',
                'access',
                'img',
                'template_style_id',
                'params',
                'lft',
                'rgt',
                'home',
                'language',
                'client_id',
            ];

            // lft/rgt : on calcule à partir du max(rgt) existant, sinon 0
            $db->setQuery(
                $db->getQuery(true)
                    ->select('COALESCE(MAX(' . $db->quoteName('rgt') . '), 0)')
                    ->from($db->quoteName('#__menu'))
            );
            $maxRgt = (int) $db->loadResult();
            $lft = $maxRgt + 1;
            $rgt = $maxRgt + 2;

            $values = [
                1,
                $db->quote(''), // menutype
                $db->quote('Menu_Item_Root'),
                $db->quote('root'),
                $db->quote(''),
                $db->quote(''), // path
                $db->quote(''),
                $db->quote('component'), // type (peu importe tant que ça n'explose pas)
                1,               // published
                0,               // parent_id
                0,               // level
                0,               // component_id
                0,               // checked_out
                'NULL',          // checked_out_time
                0,               // browserNav
                1,               // access
                $db->quote(''),
                0,               // template_style_id
                $db->quote(''),  // params
                (int) $lft,
                (int) $rgt,
                0,               // home
                $db->quote('*'),
                1,               // client_id (admin)
            ];

            $db->setQuery(
                $db->getQuery(true)
                    ->insert($db->quoteName('#__menu'))
                    ->columns(array_map([$db, 'quoteName'], $columns))
                    ->values(implode(', ', $values))
            )->execute();

            $this->log('[OK] Admin menu root node (id=1, alias=root) recreated (best-effort).');
        } catch (\Throwable $e) {
            $this->log('[WARNING] Failed ensuring admin menu root node: ' . $e->getMessage(), Log::WARNING);
        }
    }

    private function resolveMenuAlias(int $parentId, string $baseAlias): string
    {
        $db = $this->db();
        $alias = $baseAlias;
        $suffix = 2;

        while ($suffix < 100) {
            $q = $db->getQuery(true)
                ->select('COUNT(1)')
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('parent_id') . ' = ' . (int) $parentId)
                ->where($db->quoteName('client_id') . ' = 1')
                ->where($db->quoteName('alias') . ' = ' . $db->quote($alias));
            $db->setQuery($q);
            if ((int) $db->loadResult() === 0) {
                return $alias;
            }
            $alias = $baseAlias . '-' . $suffix;
            $suffix++;
        }

        return $baseAlias . '-' . time();
    }

    private function ensureAdministrationMainMenuEntry(): void
    {
        $db = $this->db();
        $targetElement = 'com_contentbuilderng';

        $componentId = (int) $this->safe(function () use ($db, $targetElement) {
            $q = $db->getQuery(true)
                ->select($db->quoteName('extension_id'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' = ' . $db->quote($targetElement))
                ->where($db->quoteName('client_id') . ' = 1');
            $db->setQuery($q);
            return $db->loadResult();
        }, 0);

        if ($componentId === 0) {
            $this->log('[WARNING] Cannot ensure admin menu entry: com_contentbuilderng extension id is missing.', Log::WARNING);
            return;
        }

        // Check if main entry exists
        $mainRows = $this->safe(function () use ($db, $componentId) {
            $q = $db->getQuery(true)
                ->select($db->quoteName(['id', 'alias', 'path']))
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('client_id') . ' = 1')
                ->where($db->quoteName('parent_id') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where(
                    '('
                        . $db->quoteName('component_id') . ' = ' . (int) $componentId
                        . ' OR ' . $db->quoteName('link') . ' LIKE ' . $db->quote('index.php?option=com_contentbuilderng%')
                        . ')'
                )
                ->order($db->quoteName('id') . ' ASC');
            $db->setQuery($q);
            return $db->loadAssocList() ?: [];
        }, []);

        if (!empty($mainRows)) {
            $mainId = (int) $mainRows[0]['id'];
            $alias = trim((string) ($mainRows[0]['alias'] ?? ''));
            $path  = trim((string) ($mainRows[0]['path'] ?? ''));

            if ($alias === '') {
                $alias = $this->resolveMenuAlias(1, 'contentbuilderng');
            }
            if ($path === '') {
                $path = $alias;
            }

            $this->safe(function () use ($db, $mainId, $alias, $path, $componentId) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__menu'))
                        ->set($db->quoteName('title') . ' = ' . $db->quote('COM_CONTENTBUILDERNG'))
                        ->set($db->quoteName('alias') . ' = ' . $db->quote($alias))
                        ->set($db->quoteName('path') . ' = ' . $db->quote($path))
                        ->set($db->quoteName('link') . ' = ' . $db->quote('index.php?option=com_contentbuilderng'))
                        ->set($db->quoteName('type') . ' = ' . $db->quote('component'))
                        ->set($db->quoteName('published') . ' = 1')
                        ->set($db->quoteName('component_id') . ' = ' . (int) $componentId)
                        ->set($db->quoteName('client_id') . ' = 1')
                        ->where($db->quoteName('id') . ' = ' . (int) $mainId)
                )->execute();
            });

            $this->log('[OK] Administration component menu entry checked and updated.');
            return;
        }

        // Need to create it under root
        $root = $this->safe(function () use ($db) {
            $q = $db->getQuery(true)
                ->select($db->quoteName(['id', 'rgt']))
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('id') . ' = 1');
            $db->setQuery($q);
            return $db->loadAssoc() ?: [];
        }, []);

        $rootId = (int) ($root['id'] ?? 1);
        $rootRgt = (int) ($root['rgt'] ?? 0);

        if ($rootRgt <= 0) {
            $this->log('[ERROR] Cannot recreate admin menu entry: invalid root rgt value.', Log::ERROR);
            return;
        }

        $alias = $this->resolveMenuAlias($rootId, 'contentbuilderng');

        try {
            // Shift tree
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__menu'))
                    ->set($db->quoteName('rgt') . ' = ' . $db->quoteName('rgt') . ' + 2')
                    ->where($db->quoteName('rgt') . ' >= ' . (int) $rootRgt)
            )->execute();

            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__menu'))
                    ->set($db->quoteName('lft') . ' = ' . $db->quoteName('lft') . ' + 2')
                    ->where($db->quoteName('lft') . ' > ' . (int) $rootRgt)
            )->execute();

            $columns = [
                'menutype',
                'title',
                'alias',
                'note',
                'path',
                'link',
                'type',
                'published',
                'parent_id',
                'level',
                'component_id',
                'checked_out',
                'checked_out_time',
                'browserNav',
                'access',
                'img',
                'template_style_id',
                'params',
                'lft',
                'rgt',
                'home',
                'language',
                'client_id',
            ];

            $values = [
                $db->quote('main'),
                $db->quote('COM_CONTENTBUILDERNG'),
                $db->quote($alias),
                $db->quote(''),
                $db->quote($alias),
                $db->quote('index.php?option=com_contentbuilderng'),
                $db->quote('component'),
                1,
                $rootId,
                1,
                $componentId,
                0,
                'NULL',
                0,
                1,
                $db->quote('class:component'),
                0,
                $db->quote(''),
                $rootRgt,
                $rootRgt + 1,
                0,
                $db->quote('*'),
                1,
            ];

            $db->setQuery(
                $db->getQuery(true)
                    ->insert($db->quoteName('#__menu'))
                    ->columns($db->quoteName($columns))
                    ->values(implode(', ', $values))
            )->execute();

            $this->log('[OK] Administration component menu entry recreated.');
        } catch (\Throwable $e) {
            $this->log('[ERROR] Failed recreating administration menu entry: ' . $e->getMessage(), Log::ERROR);
        }
    }

    private function ensureSubmenuQuickTasks(): void
    {
        $db = $this->db();

        $componentId = (int) $this->safe(function () use ($db) {
            $q = $db->getQuery(true)
                ->select($db->quoteName('extension_id'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_contentbuilderng'))
                ->where($db->quoteName('client_id') . ' = 1');
            $db->setQuery($q);
            return $db->loadResult();
        }, 0);

        if ($componentId === 0) {
            return;
        }

        $targets = [
            [
                'label' => 'Storages',
                'links' => [
                    'index.php?option=com_contentbuilderng&view=storages',
                    'index.php?option=com_contentbuilderng&task=storages.display',
                ],
                'quicktask' => 'index.php?option=com_contentbuilderng&task=storage.add',
                'quicktask_title' => 'COM_CONTENTBUILDERNG_MENUS_NEW_STORAGE',
            ],
            [
                'label' => 'Views',
                'links' => [
                    'index.php?option=com_contentbuilderng&view=forms',
                    'index.php?option=com_contentbuilderng&task=forms.display',
                ],
                'quicktask' => 'index.php?option=com_contentbuilderng&task=form.add',
                'quicktask_title' => 'COM_CONTENTBUILDERNG_MENUS_NEW_VIEW',
            ],
        ];

        foreach ($targets as $t) {
            $quotedLinks = array_map(fn(string $l) => $db->quote($l), $t['links']);

            $rows = $this->safe(function () use ($db, $componentId, $quotedLinks) {
                $q = $db->getQuery(true)
                    ->select($db->quoteName(['id', 'params']))
                    ->from($db->quoteName('#__menu'))
                    ->where($db->quoteName('client_id') . ' = 1')
                    ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                    ->where($db->quoteName('component_id') . ' = ' . (int) $componentId)
                    ->where($db->quoteName('parent_id') . ' > 1')
                    ->where($db->quoteName('link') . ' IN (' . implode(',', $quotedLinks) . ')');
                $db->setQuery($q);
                return $db->loadAssocList() ?: [];
            }, []);

            if (empty($rows)) {
                continue;
            }

            $updated = 0;
            foreach ($rows as $row) {
                $menuId = (int) ($row['id'] ?? 0);
                if ($menuId < 1) {
                    continue;
                }

                $params = new Registry((string) ($row['params'] ?? ''));
                $changed = false;

                if ((string) $params->get('menu-quicktask') !== $t['quicktask']) {
                    $params->set('menu-quicktask', $t['quicktask']);
                    $changed = true;
                }
                if ((string) $params->get('menu-quicktask-title') !== $t['quicktask_title']) {
                    $params->set('menu-quicktask-title', $t['quicktask_title']);
                    $changed = true;
                }
                if ((string) $params->get('menu-quicktask-icon') !== 'plus') {
                    $params->set('menu-quicktask-icon', 'plus');
                    $changed = true;
                }

                if (!$changed) {
                    continue;
                }

                $this->safe(function () use ($db, $menuId, $params, &$updated) {
                    $db->setQuery(
                        $db->getQuery(true)
                            ->update($db->quoteName('#__menu'))
                            ->set($db->quoteName('params') . ' = ' . $db->quote($params->toString('JSON')))
                            ->where($db->quoteName('id') . ' = ' . (int) $menuId)
                    )->execute();
                    $updated++;
                });
            }

            if ($updated > 0) {
                $this->log('[OK] Updated Joomla quicktask (+) for ' . $t['label'] . ' submenu (' . $updated . ' entry).');
            }
        }
    }

    private function normalizeLegacyMenuTitleKey(string $title): string
    {
        $title = strtoupper(trim($title));

        if ($title === 'COM_CONTENTBUILDER' || $title === 'COM_CONTENTBUILDER_NG') {
            return 'COM_CONTENTBUILDERNG';
        }
        if (str_starts_with($title, 'COM_CONTENTBUILDER_NG_')) {
            return 'COM_CONTENTBUILDERNG_' . substr($title, strlen('COM_CONTENTBUILDER_NG_'));
        }
        if (str_starts_with($title, 'COM_CONTENTBUILDER_')) {
            return 'COM_CONTENTBUILDERNG_' . substr($title, strlen('COM_CONTENTBUILDER_'));
        }
        return $title;
    }

    private function repairLegacyMenuTitleKeys(): void
    {
        $db = $this->db();

        $rows = $this->safe(function () use ($db) {
            $q = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'link', 'alias', 'path']))
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('client_id') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('title') . ' LIKE ' . $db->quote('COM_CONTENTBUILDER%'))
                ->where(
                    '('
                        . $db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_contentbuilder%')
                        . ' OR ' . $db->quoteName('alias') . ' LIKE ' . $db->quote('contentbuilder%')
                        . ' OR ' . $db->quoteName('alias') . ' LIKE ' . $db->quote('com-contentbuilder%')
                        . ' OR ' . $db->quoteName('path') . ' LIKE ' . $db->quote('contentbuilder%')
                        . ')'
                )
                ->order($db->quoteName('id') . ' ASC');
            $db->setQuery($q);
            return $db->loadAssocList() ?: [];
        }, []);

        if (empty($rows)) {
            return;
        }

        $detected = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $old = strtoupper(trim((string) ($row['title'] ?? '')));
            if ($id < 1 || $old === '' || str_starts_with($old, 'COM_CONTENTBUILDERNG')) {
                continue;
            }

            $new = $this->normalizeLegacyMenuTitleKey($old);
            if ($new === $old) {
                continue;
            }

            $detected++;
            $ok = $this->safe(function () use ($db, $id, $new) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__menu'))
                        ->set($db->quoteName('title') . ' = ' . $db->quote($new))
                        ->where($db->quoteName('id') . ' = ' . (int) $id)
                )->execute();
                return true;
            }, false);

            if ($ok) {
                $updated++;
            }
        }

        if ($detected > 0) {
            $this->log("[OK] Normalized {$updated} legacy menu title key(s).");
        }
    }

    // ---------------------------------------------------------------------
    // Storages ordering normalization (update only)
    // ---------------------------------------------------------------------
    private function normalizeStoragesOrdering(): void
    {
        $db = $this->db();
        if (!$this->tableExists('contentbuilderng_storages')) {
            return;
        }

        try {
            $table = $db->quoteName('#__contentbuilderng_storages');

            $db->setQuery("SELECT COUNT(*) FROM $table WHERE ordering = 0");
            $needFix = (int) $db->loadResult();

            if ($needFix <= 0) {
                return;
            }

            $db->setQuery("SELECT COALESCE(MAX(ordering), 0) FROM $table");
            $max = (int) $db->loadResult();

            $db->setQuery("SELECT id FROM $table WHERE ordering = 0 ORDER BY id");
            $ids = $db->loadColumn() ?: [];

            $order = $max;
            foreach ($ids as $id) {
                $order++;
                $db->setQuery("UPDATE $table SET ordering = " . (int) $order . " WHERE id = " . (int) $id)->execute();
            }

            $this->log("[OK] Normalized storages ordering for {$needFix} row(s).");
        } catch (\Throwable $e) {
            $this->log('[ERROR] Failed to normalize storages ordering: ' . $e->getMessage(), Log::ERROR);
        }
    }

    private function removeLegacyAdminMenuBranchByAlias(string $alias = 'contentbuilder'): void
{
    $db = $this->db();

    // 1) Trouver le parent admin à supprimer
    $q = $db->getQuery(true)
        ->select($db->quoteName(['id', 'lft', 'rgt', 'menutype', 'title', 'path', 'link']))
        ->from($db->quoteName('#__menu'))
        ->where($db->quoteName('client_id') . ' = 1')
        ->where($db->quoteName('parent_id') . ' = 1')
        ->where($db->quoteName('alias') . ' = ' . $db->quote($alias))
        ->order($db->quoteName('id') . ' ASC');

    $db->setQuery($q);
    $parent = $db->loadAssoc();

    if (!$parent) {
        $this->log("[INFO] No legacy admin menu branch found for alias={$alias}.", \Joomla\CMS\Log\Log::INFO);
        return;
    }

    $parentId = (int) ($parent['id'] ?? 0);
    $lft = (int) ($parent['lft'] ?? 0);
    $rgt = (int) ($parent['rgt'] ?? 0);

    if ($parentId < 1 || $lft < 1 || $rgt <= $lft) {
        $this->log("[WARNING] Legacy admin menu branch alias={$alias} has invalid nested set values; skipping delete.", \Joomla\CMS\Log\Log::WARNING);
        return;
    }

    $width = $rgt - $lft + 1;

    // 2) Collecter les ids supprimés (utile pour nettoyer les assets ensuite)
    $db->setQuery(
        $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('client_id') . ' = 1')
            ->where($db->quoteName('lft') . ' BETWEEN ' . $lft . ' AND ' . $rgt)
    );
    $ids = $db->loadColumn() ?: [];

    // 3) Supprimer toute la branche
    $db->setQuery(
        $db->getQuery(true)
            ->delete($db->quoteName('#__menu'))
            ->where($db->quoteName('client_id') . ' = 1')
            ->where($db->quoteName('lft') . ' BETWEEN ' . $lft . ' AND ' . $rgt)
    )->execute();

    // 4) Réparer l’arbre nested set (shift)
    $db->setQuery(
        $db->getQuery(true)
            ->update($db->quoteName('#__menu'))
            ->set($db->quoteName('lft') . ' = ' . $db->quoteName('lft') . ' - ' . (int) $width)
            ->where($db->quoteName('client_id') . ' = 1')
            ->where($db->quoteName('lft') . ' > ' . (int) $rgt)
    )->execute();

    $db->setQuery(
        $db->getQuery(true)
            ->update($db->quoteName('#__menu'))
            ->set($db->quoteName('rgt') . ' = ' . $db->quoteName('rgt') . ' - ' . (int) $width)
            ->where($db->quoteName('client_id') . ' = 1')
            ->where($db->quoteName('rgt') . ' > ' . (int) $rgt)
    )->execute();

    // 5) Best-effort : nettoyer les assets de menus (sinon orphelins)
    // Dans Joomla, les assets des items admin sont typiquement "com_menus.menu.<id>"
    if (!empty($ids)) {
        $quotedNames = array_map(static fn($id) => $db->quote('com_menus.menu.' . (int) $id), $ids);
        $this->safe(function () use ($db, $quotedNames) {
            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__assets'))
                    ->where($db->quoteName('name') . ' IN (' . implode(',', $quotedNames) . ')')
            )->execute();
        });
    }

    $this->log("[OK] Legacy admin menu branch '{$alias}' removed: parent id={$parentId}, deleted " . count($ids) . " menu row(s).");

    // Purge caches (menu + admin)
    $this->purgeCaches('postflight:removeLegacyAdminMenuBranchByAlias');
}
}
