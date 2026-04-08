<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

final class PluginInstallerService
{
    public function __construct(
        private readonly \Closure $dbProvider,
        private readonly \Closure $logger,
        private readonly \Closure $safeRunner,
        private readonly \Closure $cachePurger,
        private readonly \Closure $updateHighlighter,
    ) {
    }

    public function resolveInstallSourcePath(mixed $parent): ?string
    {
        $candidates = [];
        $push = static function (array &$list, mixed $path): void {
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
            $this->safe(function () use ($parent, &$push, &$candidates): void {
                $push($candidates, $parent->getPath('source'));
                $push($candidates, $parent->getPath('manifest'));
            });
        }

        if (is_object($parent) && method_exists($parent, 'getParent')) {
            $this->safe(function () use ($parent, &$push, &$candidates): void {
                $installerParent = $parent->getParent();

                if (is_object($installerParent) && method_exists($installerParent, 'getPath')) {
                    $push($candidates, $installerParent->getPath('source'));
                    $push($candidates, $installerParent->getPath('manifest'));
                }
            });
        }

        $push($candidates, __DIR__ . '/../../../..');

        foreach ($candidates as $candidate) {
            if (is_dir($candidate . '/plugins')) {
                return $candidate;
            }
        }

        return $candidates[0] ?? null;
    }

    public function ensurePluginsInstalled(?string $source = null, bool $forceUpdate = false): void
    {
        $db = $this->db();
        $plugins = $this->getPlugins();

        $total = 0;
        if ($forceUpdate) {
            foreach ($plugins as $elements) {
                $total += count($elements);
            }
        }

        $index = 0;

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
                $extensionId = (int) ($row['extension_id'] ?? 0);

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

                $manifestPath = $this->getPluginManifestPath($path);
                if ($manifestPath === null) {
                    $this->log(
                        "[WARNING] Plugin manifest not found or invalid for {$folder}/{$element}: {$path}. "
                        . 'Skipped plugin installer call to avoid Joomla warnings about missing XML setup files.',
                        Log::WARNING
                    );
                    continue;
                }

                if ($forceUpdate) {
                    $index++;
                    $rank = $total > 0 ? " ({$index}/{$total})" : '';
                    $manifestVersion = $this->getPluginManifestVersion($path);
                    $versionChanged = !$installedVersion || !$manifestVersion || ((string) $installedVersion !== (string) $manifestVersion);
                    $ok = $this->safe(fn() => $this->installPluginFromPath($path), false);

                    if ($ok) {
                        if ($versionChanged) {
                            $detail = $installedVersion && $manifestVersion
                                ? " ({$installedVersion} -> {$manifestVersion})"
                                : ($manifestVersion ? " ({$manifestVersion})" : '');
                            $this->addUpdateHighlight("Plugin refreshed: {$folder}/{$element}{$detail}");
                        }
                    } else {
                        $this->log("[ERROR] Plugin refresh failed{$rank}: {$folder}/{$element}", Log::ERROR);
                    }

                    continue;
                }

                if ($extensionId > 0) {
                    $manifestVersion = $this->getPluginManifestVersion($path);
                    $needsUpdate = !$installedVersion || !$manifestVersion
                        || version_compare((string) $installedVersion, (string) $manifestVersion, '<')
                        || ((string) $installedVersion !== (string) $manifestVersion);

                    if (!$needsUpdate) {
                        $this->log("[INFO] Plugin already installed: {$folder}/{$element} (version {$installedVersion})");
                        continue;
                    }

                    $ok = $this->safe(fn() => $this->installPluginFromPath($path), false);
                    if ($ok) {
                        $this->log("[OK] Plugin updated: {$folder}/{$element} (version {$installedVersion} -> {$manifestVersion})");
                        $this->addUpdateHighlight("Plugin updated: {$folder}/{$element} ({$installedVersion} -> {$manifestVersion})");
                    } else {
                        $this->log("[ERROR] Plugin update failed: {$folder}/{$element}", Log::ERROR);
                    }
                } else {
                    $ok = $this->safe(fn() => $this->installPluginFromPath($path), false);
                    if ($ok) {
                        $this->log("[OK] Plugin installed: {$folder}/{$element}");
                        $manifestVersion = $this->getPluginManifestVersion($path);
                        $this->addUpdateHighlight("Plugin installed: {$folder}/{$element}" . ($manifestVersion ? " ({$manifestVersion})" : ''));
                    } else {
                        $this->log("[ERROR] Plugin install failed: {$folder}/{$element}", Log::ERROR);
                    }
                }
            }
        }
    }

    public function activatePlugins(): void
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
                    $db->setQuery($query);
                    $db->execute();
                    $this->log("[OK] Plugin enabled: {$folder}/{$element}");
                } catch (\Throwable $e) {
                    $this->log("[ERROR] Failed enabling {$folder}/{$element}: " . $e->getMessage(), Log::ERROR);
                }
            }
        }
    }

    public function removeDeprecatedThemePlugins(): void
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

    public function normalizeFormThemePlugins(): void
    {
        $db = $this->db();

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

        $migratedLegacy = 0;
        $migratedUnsupported = 0;

        try {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_forms'))
                ->set($db->quoteName('theme_plugin') . ' = ' . $db->quote('joomla6'))
                ->where($db->quoteName('theme_plugin') . ' = ' . $db->quote('joomla3'));
            $db->setQuery($query);
            $db->execute();
            $migratedLegacy = (int) $db->getAffectedRows();
        } catch (\Throwable $e) {
            $this->log('[WARNING] Failed migrating joomla3 theme references: ' . $e->getMessage(), Log::WARNING);
        }

        try {
            $query = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('theme_plugin'))
                ->from($db->quoteName('#__contentbuilderng_forms'))
                ->where($db->quoteName('theme_plugin') . ' IS NOT NULL')
                ->where($db->quoteName('theme_plugin') . " <> ''");
            $db->setQuery($query);
            $stored = $db->loadColumn() ?: [];
            $unsupported = array_values(array_diff($stored, $supported));

            if ($unsupported !== []) {
                $quoted = array_map(static fn($theme) => $db->quote((string) $theme), $unsupported);
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__contentbuilderng_forms'))
                    ->set($db->quoteName('theme_plugin') . ' = ' . $db->quote('joomla6'))
                    ->where($db->quoteName('theme_plugin') . ' IN (' . implode(',', $quoted) . ')');
                $db->setQuery($query);
                $db->execute();
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

    public function disableLegacyPluginsInPriorityOrder(string $context = 'update'): int
    {
        $disabled = 0;
        $disabled += $this->disableLegacySystemPluginFirst($context . ':first');
        $disabled += $this->disableLegacyContentbuilderPlugins($context . ':others', true);

        return $disabled;
    }

    public function removeLegacySystemPluginFolderEarly(string $context = 'postflight'): void
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

    public function removeLegacyPluginsDisableOnly(): void
    {
        $disabled = $this->disableLegacyContentbuilderPlugins('postflight:disableOnly');

        if ($disabled > 0) {
            $this->log('[INFO] Legacy plugins are disabled (NOT uninstalled) to avoid destructive uninstall hooks.');
        }
    }

    public function removeLegacyPluginFoldersBestEffort(): void
    {
        $pluginRoot = JPATH_ROOT . '/plugins';
        if (!is_dir($pluginRoot)) {
            return;
        }

        $paths = [];

        foreach ($this->getPlugins() as $folder => $elements) {
            foreach ($elements as $element) {
                [$legacyFolder, $legacyElement] = $this->mapToLegacyPlugin($folder, $element);
                if ($legacyFolder && $legacyElement) {
                    $paths[] = $pluginRoot . '/' . $legacyFolder . '/' . $legacyElement;
                }
            }
        }

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

        $paths = array_values(array_unique(array_map(static fn($path) => rtrim((string) $path, '/\\'), $paths)));
        if ($paths === []) {
            return;
        }

        usort($paths, static fn($left, $right) => strlen($right) <=> strlen($left));

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

    public function deduplicateTargetPluginExtensions(): void
    {
        $db = $this->db();

        $rows = $this->safe(function () use ($db) {
            $query = $db->getQuery(true)
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
            $db->setQuery($query);

            return $db->loadAssocList() ?: [];
        }, []);

        if ($rows === []) {
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

            $canonicalFolder = $this->canonicalizePluginFolder($folder);
            $canonicalElement = $this->canonicalizePluginElement($element);
            $key = $canonicalFolder . '/' . $canonicalElement;

            $row['extension_id'] = $id;
            $row['folder'] = $folder;
            $row['element'] = $element;
            $row['canonical_folder'] = $canonicalFolder;
            $row['canonical_element'] = $canonicalElement;
            $groups[$key][] = $row;
        }

        $removedTotal = 0;
        $groupCount = 0;

        foreach ($groups as $key => $groupRows) {
            if (!is_array($groupRows) || count($groupRows) < 2) {
                continue;
            }

            [$canonicalFolder, $canonicalElement] = explode('/', $key, 2);
            $keepId = 0;
            $best = [-1, -1, -1, -1];

            foreach ($groupRows as $row) {
                $id = (int) ($row['extension_id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $isCanonical = ((string) ($row['folder'] ?? '') === $canonicalFolder && (string) ($row['element'] ?? '') === $canonicalElement) ? 1 : 0;
                $enabled = (int) ($row['enabled'] ?? 0);
                $hasManifest = trim((string) ($row['manifest_cache'] ?? '')) !== '' ? 1 : 0;
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
            foreach ($groupRows as $row) {
                $id = (int) ($row['extension_id'] ?? 0);
                if ($id > 0 && $id !== $keepId) {
                    $removeIds[] = $id;
                }
            }

            $removeIds = array_values(array_unique($removeIds));
            if ($removeIds === []) {
                continue;
            }

            try {
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__extensions'))
                        ->set($db->quoteName('folder') . ' = ' . $db->quote($canonicalFolder))
                        ->set($db->quoteName('element') . ' = ' . $db->quote($canonicalElement))
                        ->where($db->quoteName('extension_id') . ' = ' . (int) $keepId)
                );
                $db->execute();

                foreach (['#__schemas', '#__update_sites_extensions'] as $table) {
                    $db->setQuery(
                        $db->getQuery(true)
                            ->delete($db->quoteName($table))
                            ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $removeIds) . ')')
                    );
                    $db->execute();
                }

                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__extensions'))
                        ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $removeIds) . ')')
                );
                $db->execute();

                $removedTotal += count($removeIds);
                $groupCount++;
                $this->log("[OK] Deduplicated plugin {$canonicalFolder}/{$canonicalElement}: kept {$keepId}, removed " . implode(',', $removeIds) . '.');
            } catch (\Throwable $e) {
                $this->log("[WARNING] Failed deduplicating plugin {$canonicalFolder}/{$canonicalElement}: " . $e->getMessage(), Log::WARNING);
            }
        }

        if ($groupCount > 0) {
            $this->log("[OK] Plugin deduplication completed: {$groupCount} group(s), {$removedTotal} duplicate row(s) removed.");
        }
    }

    private function getPlugins(): array
    {
        return [
            'contentbuilderng_verify' => ['paypal', 'passthrough'],
            'contentbuilderng_validation' => ['notempty', 'equal', 'email', 'date_not_before', 'date_is_valid'],
            'contentbuilderng_themes' => ['khepri', 'blank', 'joomla6', 'dark'],
            'system' => ['contentbuilderng_system'],
            'contentbuilderng_submit' => ['submit_sample'],
            'contentbuilderng_listaction' => ['trash', 'untrash'],
            'content' => ['contentbuilderng_verify', 'contentbuilderng_permission_observer', 'contentbuilderng_image_scale', 'contentbuilderng_download', 'contentbuilderng_rating'],
        ];
    }

    private function resolvePluginSourcePath(?string $source, string $folder, string $element): string
    {
        $source = $source ? rtrim($source, '/') : null;
        $candidates = [];

        if ($source) {
            $candidates[] = $source . '/plugins/' . $folder . '/' . $element;
            $legacy = $this->getLegacyPluginSourcePath($source, $folder, $element);
            if ($legacy) {
                $candidates[] = $legacy;
            }
        }

        $candidates[] = JPATH_ROOT . '/plugins/' . $folder . '/' . $element;

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
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

            foreach ($candidates as $candidate) {
                if (is_dir($candidate)) {
                    return $candidate;
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
        if ($this->getPluginManifestPath($path) === null) {
            return false;
        }

        $installer = new Installer();
        $installer->setDatabase(Factory::getContainer()->get(DatabaseInterface::class));

        return (bool) $installer->install($path);
    }

    private function getPluginManifestVersion(string $path): ?string
    {
        $manifestPath = $this->getPluginManifestPath($path);

        if ($manifestPath === null) {
            return null;
        }

        try {
            $xml = simplexml_load_file($manifestPath);

            if (!$xml || $xml->getName() !== 'extension') {
                return null;
            }

            $version = isset($xml->version) ? trim((string) $xml->version) : '';
            if ($version !== '') {
                return $version;
            }

            $attribute = isset($xml['version']) ? trim((string) $xml['version']) : '';
            if ($attribute !== '') {
                return $attribute;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function getPluginManifestPath(string $path): ?string
    {
        $files = glob(rtrim($path, '/') . '/*.xml') ?: [];

        foreach ($files as $file) {
            try {
                $xml = simplexml_load_file($file);

                if ($xml && $xml->getName() === 'extension') {
                    return $file;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function disableLegacySystemPluginFirst(string $context = 'update'): int
    {
        $db = $this->db();

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['extension_id', 'enabled']))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('contentbuilder_system'));

            $db->setQuery($query);
            $rows = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $this->log("[WARNING] Failed to detect legacy system plugin during {$context}: " . $e->getMessage(), Log::WARNING);
            return 0;
        }

        if ($rows === []) {
            return 0;
        }

        $ids = [];
        $alreadyDisabled = 0;

        foreach ($rows as $row) {
            $id = (int) ($row['extension_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
            if ((int) ($row['enabled'] ?? 0) === 0) {
                $alreadyDisabled++;
            }
        }

        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return 0;
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('enabled') . ' = 0')
                    ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $ids) . ')')
            );
            $db->execute();
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
        $likeNg = $db->quote('contentbuilderng%');
        $folderCondition = $db->quoteName('folder') . ' LIKE ' . $likeLegacy . ' AND ' . $db->quoteName('folder') . ' NOT LIKE ' . $likeNg;
        $elementCondition = $db->quoteName('element') . ' LIKE ' . $likeLegacy . ' AND ' . $db->quoteName('element') . ' NOT LIKE ' . $likeNg;

        $query = $db->getQuery(true)
            ->select($db->quoteName(['extension_id', 'folder', 'element', 'enabled']))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where("(($folderCondition) OR ($elementCondition))");

        try {
            $db->setQuery($query);
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

        if ($rows === []) {
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
        if ($ids === []) {
            return 0;
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('enabled') . ' = 0')
                    ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $ids) . ')')
            );
            $db->execute();
        } catch (\Throwable $e) {
            $this->log('[WARNING] Failed disabling legacy ContentBuilder plugins: ' . $e->getMessage(), Log::WARNING);
            return 0;
        }

        $disabledNow = max(0, count($ids) - $alreadyDisabled);
        $this->log("[OK] Legacy ContentBuilder plugins disabled ({$disabledNow} newly disabled, " . count($ids) . " total) during {$context}.");

        return count($ids);
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

    private function addUpdateHighlight(string $message): void
    {
        ($this->updateHighlighter)($message);
    }

    private function db(): DatabaseInterface
    {
        return ($this->dbProvider)();
    }

    private function log(string $message, int $priority = Log::INFO): void
    {
        ($this->logger)($message, $priority);
    }

    private function safe(callable $callback, mixed $fallback = null): mixed
    {
        return ($this->safeRunner)($callback, $fallback);
    }

    private function purgeCaches(string $context): void
    {
        ($this->cachePurger)($context);
    }
}
