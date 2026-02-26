<?php

/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   (C) 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\Filesystem\File;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\Folder;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Log\Log;
use Joomla\Registry\Registry;

class com_contentbuilderngInstallerScript extends InstallerScript
{
  private const LEGACY_TABLE_RENAMES = [
    'contentbuilder_articles' => 'contentbuilderng_articles',
    'contentbuilder_ng_articles' => 'contentbuilderng_articles',
    'contentbuilder_elements' => 'contentbuilderng_elements',
    'contentbuilder_ng_elements' => 'contentbuilderng_elements',
    'contentbuilder_forms' => 'contentbuilderng_forms',
    'contentbuilder_ng_forms' => 'contentbuilderng_forms',
    'contentbuilder_list_records' => 'contentbuilderng_list_records',
    'contentbuilder_ng_list_records' => 'contentbuilderng_list_records',
    'contentbuilder_list_states' => 'contentbuilderng_list_states',
    'contentbuilder_ng_list_states' => 'contentbuilderng_list_states',
    'contentbuilder_rating_cache' => 'contentbuilderng_rating_cache',
    'contentbuilder_ng_rating_cache' => 'contentbuilderng_rating_cache',
    'contentbuilder_records' => 'contentbuilderng_records',
    'contentbuilder_ng_records' => 'contentbuilderng_records',
    'contentbuilder_registered_users' => 'contentbuilderng_registered_users',
    'contentbuilder_ng_registered_users' => 'contentbuilderng_registered_users',
    'contentbuilder_resource_access' => 'contentbuilderng_resource_access',
    'contentbuilder_ng_resource_access' => 'contentbuilderng_resource_access',
    'contentbuilder_storage_fields' => 'contentbuilderng_storage_fields',
    'contentbuilder_ng_storage_fields' => 'contentbuilderng_storage_fields',
    'contentbuilder_storages' => 'contentbuilderng_storages',
    'contentbuilder_ng_storages' => 'contentbuilderng_storages',
    'contentbuilder_users' => 'contentbuilderng_users',
    'contentbuilder_ng_users' => 'contentbuilderng_users',
    'contentbuilder_verifications' => 'contentbuilderng_verifications',
    'contentbuilder_ng_verifications' => 'contentbuilderng_verifications',
  ];
  private bool $criticalFailureDetected = false;
  private array $criticalFailureMessages = [];
  private float $installStartedAt = 0.0;
  protected $minimumPhp = '8.1';
  protected $minimumJoomla = '5.0';

  public function __construct()
  {
    $this->installStartedAt = microtime(true);

    // Logger personnalisé
    $app = Factory::getApplication();
    $logPath = '';
    if (is_object($app) && method_exists($app, 'get')) {
      $logPath = (string) $app->get('log_path', '');
    }
    if ($logPath === '') {
      $logPath = JPATH_ROOT . '/logs';
    }
    if (!Folder::exists($logPath)) {
      Folder::create($logPath);
    }

    Log::addLogger(
      [
        'text_file' => 'contentbuilderng_install.log',
        'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}',
        'text_file_path'     => $logPath,
      ],
      Log::ALL,
      ['com_contentbuilderng.install']
    );


    // Starting logs: compact runtime header block.
    $this->writeInstallLogEntry('---------------------------------------------------------', Log::INFO);
    $detectedInstalledVersion = 'unknown';
    try {
      $detectedInstalledVersion = $this->getCurrentInstalledVersion();
    } catch (\Throwable) {
      $detectedInstalledVersion = 'unknown';
    }
    $this->log(
      '[OK] ContentBuilder NG installation/update started (detected installed version: '
      . $detectedInstalledVersion
      . ').',
      Log::INFO,
      false
    );
    $this->log('[INFO] Joomla version: <strong>' . htmlspecialchars(JVERSION, ENT_QUOTES, 'UTF-8') . '</strong>.', Log::INFO, false);
    $this->log('[INFO] PHP version: ' . PHP_VERSION . '.', Log::INFO, false);
    $this->logDatabaseRuntimeInfo(false);
    $this->log('[INFO] User agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'CLI') . '.', Log::INFO, false);
    $this->log('[INFO] ===================================================================', Log::INFO, false);
    $this->writeInstallLogEntry('---------------------------------------------------------', Log::INFO);
  }

  private function logDatabaseRuntimeInfo(bool $enqueue = true): void
  {
    try {
      $db = Factory::getContainer()->get(DatabaseInterface::class);

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
        try {
          $db->setQuery('SELECT VERSION()');
          $version = trim((string) $db->loadResult());
        } catch (\Throwable) {
          $version = '';
        }
      }
      if ($version === '') {
        $version = 'unknown';
      }

      $prefix = '';
      if (method_exists($db, 'getPrefix')) {
        $prefix = trim((string) $db->getPrefix());
      }
      if ($prefix === '') {
        $prefix = '(none)';
      }

      $this->log('[INFO] Database: ' . $type . ' ' . $version . ' (prefix: ' . $prefix . ').', Log::INFO, $enqueue);

      // Session-level charset/collation (most relevant for runtime SQL behavior).
      try {
        $db->setQuery('SELECT @@character_set_connection, @@collation_connection');
        $sessionInfo = $db->loadRow();
        $sessionCharset = trim((string) ($sessionInfo[0] ?? ''));
        $sessionCollation = trim((string) ($sessionInfo[1] ?? ''));
        if ($sessionCharset !== '' || $sessionCollation !== '') {
          $this->log(
            '[INFO] Database session charset/collation: '
            . ($sessionCharset !== '' ? $sessionCharset : 'unknown')
            . ' / '
            . ($sessionCollation !== '' ? $sessionCollation : 'unknown')
            . '.',
            Log::INFO,
            $enqueue
          );

          if ($sessionCharset !== '' && stripos($sessionCharset, 'utf8mb4') === false) {
            $this->log(
              '[WARNING] Database session charset is not utf8mb4 (' . $sessionCharset . ').',
              Log::WARNING,
              $enqueue
            );
          }
        }
      } catch (\Throwable $e) {
        $this->log('[WARNING] Could not read database session charset/collation: ' . $e->getMessage(), Log::WARNING, $enqueue);
      }

      // Database-level default charset/collation (fallback if available).
      try {
        $db->setQuery('SELECT DATABASE()');
        $dbName = trim((string) $db->loadResult());
        if ($dbName !== '') {
          $db->setQuery(
            'SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME'
            . ' FROM information_schema.SCHEMATA'
            . ' WHERE SCHEMA_NAME = ' . $db->quote($dbName)
          );
          $schemaInfo = $db->loadRow();
          $schemaCharset = trim((string) ($schemaInfo[0] ?? ''));
          $schemaCollation = trim((string) ($schemaInfo[1] ?? ''));
          if ($schemaCharset !== '' || $schemaCollation !== '') {
            $this->log(
              '[INFO] Database default charset/collation: '
              . ($schemaCharset !== '' ? $schemaCharset : 'unknown')
              . ' / '
              . ($schemaCollation !== '' ? $schemaCollation : 'unknown')
              . '.',
              Log::INFO,
              $enqueue
            );

            if ($schemaCharset !== '' && stripos($schemaCharset, 'utf8mb4') === false) {
              $this->log(
                '[WARNING] Database default charset is not utf8mb4 (' . $schemaCharset . ').',
                Log::WARNING,
                $enqueue
              );
            }
          }
        }
      } catch (\Throwable $e) {
        $this->log('[WARNING] Could not read database default charset/collation: ' . $e->getMessage(), Log::WARNING, $enqueue);
      }
    } catch (\Throwable $e) {
      $this->log('[WARNING] Could not resolve database runtime info: ' . $e->getMessage(), Log::WARNING, $enqueue);
    }
  }

  private function enqueueRuntimeHeaderBlock(): void
  {
    $this->log('[INFO] Joomla version: <strong>' . htmlspecialchars(JVERSION, ENT_QUOTES, 'UTF-8') . '</strong>.', Log::INFO);
    $this->log('[INFO] PHP version: ' . PHP_VERSION . '.', Log::INFO);
    $this->logDatabaseRuntimeInfo(true);
    $this->log('[INFO] User agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'CLI') . '.', Log::INFO);
    $this->log('[INFO] ===================================================================', Log::INFO);
  }

  private function reportLegacyTableCollisions(): void
  {
    try {
      $db = Factory::getContainer()->get(DatabaseInterface::class);
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

  function getPlugins()
  {
    $plugins = array();
    $plugins['contentbuilderng_verify'] = array();
    $plugins['contentbuilderng_verify'][] = 'paypal';
    $plugins['contentbuilderng_verify'][] = 'passthrough';
    $plugins['contentbuilderng_validation'] = array();
    $plugins['contentbuilderng_validation'][] = 'notempty';
    $plugins['contentbuilderng_validation'][] = 'equal';
    $plugins['contentbuilderng_validation'][] = 'email';
    $plugins['contentbuilderng_validation'][] = 'date_not_before';
    $plugins['contentbuilderng_validation'][] = 'date_is_valid';
    $plugins['contentbuilderng_themes'] = array();
    $plugins['contentbuilderng_themes'][] = 'khepri';
    $plugins['contentbuilderng_themes'][] = 'blank';
    $plugins['contentbuilderng_themes'][] = 'joomla6';
    $plugins['contentbuilderng_themes'][] = 'dark';
    $plugins['system'] = array();
    $plugins['system'][] = 'contentbuilderng_system';
    $plugins['contentbuilderng_submit'] = array();
    $plugins['contentbuilderng_submit'][] = 'submit_sample';
    $plugins['contentbuilderng_listaction'] = array();
    $plugins['contentbuilderng_listaction'][] = 'trash';
    $plugins['contentbuilderng_listaction'][] = 'untrash';
    $plugins['content'] = array();
    $plugins['content'][] = 'contentbuilderng_verify';
    $plugins['content'][] = 'contentbuilderng_permission_observer';
    $plugins['content'][] = 'contentbuilderng_image_scale';
    $plugins['content'][] = 'contentbuilderng_download';
    $plugins['content'][] = 'contentbuilderng_rating';
    return $plugins;
  }

  private function resolveJoomlaTimezoneName(): string
  {
    $timezoneName = '';

    try {
      $app = Factory::getApplication();
      if (is_object($app) && method_exists($app, 'get')) {
        $timezoneName = trim((string) $app->get('offset', ''));
      }
    } catch (\Throwable) {
      // Ignore and fallback below.
    }

    if ($timezoneName === '') {
      try {
        $app = Factory::getApplication();
        if (is_object($app) && method_exists($app, 'get')) {
          $timezoneName = trim((string) $app->get('offset', ''));
        }
      } catch (\Throwable) {
        $timezoneName = '';
      }
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

  private function writeInstallLogEntry(string $message, int $priority = Log::INFO): void
  {
    $previousTimezone = date_default_timezone_get();
    $targetTimezone = $this->resolveJoomlaTimezoneName();
    $switchedTimezone = false;

    if ($previousTimezone !== $targetTimezone) {
      $switchedTimezone = (bool) @date_default_timezone_set($targetTimezone);
    }

    try {
      Log::add($message, $priority, 'com_contentbuilderng.install');
    } finally {
      if ($switchedTimezone) {
        @date_default_timezone_set($previousTimezone);
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

  private function log(string $message, int $priority = Log::INFO, bool $enqueue = true): void
  {
    if ($priority === Log::ERROR) {
      $this->criticalFailureDetected = true;
      $normalizedMessage = trim(strip_tags($message));
      if ($normalizedMessage !== '' && !in_array($normalizedMessage, $this->criticalFailureMessages, true)) {
        $this->criticalFailureMessages[] = $normalizedMessage;
      }
    }

    $this->writeInstallLogEntry($message, $priority);

    if (!$enqueue) {
      return;
    }

    $app = Factory::getApplication();
    $type = match ($priority) {
      Log::ERROR => 'error',
      Log::WARNING => 'warning',
      default => 'message',
    };

    $app->enqueueMessage($this->formatInstallMessageForDisplay($message), $type);
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

  private function getIndexedColumns(DatabaseInterface $db, string $tableQN): array
  {
    $indexedColumns = [];

    try {
      $db->setQuery('SHOW INDEX FROM ' . $tableQN);
      $indexRows = $db->loadAssocList() ?: [];

      foreach ($indexRows as $indexRow) {
        $columnName = strtolower((string) ($indexRow['Column_name'] ?? $indexRow['column_name'] ?? ''));
        if ($columnName !== '') {
          $indexedColumns[$columnName] = true;
        }
      }
    } catch (\Throwable $e) {
      $this->log(
        '[WARNING] Could not inspect existing indexes on ' . $tableQN . ': ' . $e->getMessage(),
        Log::WARNING
      );
    }

    return $indexedColumns;
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

      $seqInIndex = (int) ($row['Seq_in_index'] ?? $row['seq_in_index'] ?? 0);
      if ($seqInIndex < 1) {
        $seqInIndex = count($indexMap[$keyName]['columns'] ?? []) + 1;
      }

      $columnName = strtolower((string) ($row['Column_name'] ?? $row['column_name'] ?? ''));
      if ($columnName === '') {
        $columnName = strtolower(trim((string) ($row['Expression'] ?? $row['expression'] ?? '')));
      }

      if ($columnName === '') {
        continue;
      }

      if (!isset($indexMap[$keyName])) {
        $indexMap[$keyName] = [
          'non_unique' => (int) ($row['Non_unique'] ?? $row['non_unique'] ?? 1),
          'index_type' => strtoupper((string) ($row['Index_type'] ?? $row['index_type'] ?? 'BTREE')),
          'columns' => [],
        ];
      }

      $indexMap[$keyName]['columns'][$seqInIndex] = [
        'name' => $columnName,
        'sub_part' => (string) ($row['Sub_part'] ?? $row['sub_part'] ?? ''),
        'collation' => strtoupper((string) ($row['Collation'] ?? $row['collation'] ?? 'A')),
      ];
    }

    foreach ($indexMap as &$indexDefinition) {
      ksort($indexDefinition['columns'], SORT_NUMERIC);
      $indexDefinition['columns'] = array_values($indexDefinition['columns']);
      $indexDefinition['signature'] = $this->indexDefinitionSignature($indexDefinition);
    }
    unset($indexDefinition);

    return $indexMap;
  }

  private function indexDefinitionSignature(array $indexDefinition): string
  {
    $columnParts = [];

    foreach ((array) ($indexDefinition['columns'] ?? []) as $columnDefinition) {
      $columnParts[] = implode(':', [
        (string) ($columnDefinition['name'] ?? ''),
        (string) ($columnDefinition['sub_part'] ?? ''),
        (string) ($columnDefinition['collation'] ?? ''),
      ]);
    }

    return implode('|', [
      (string) ($indexDefinition['non_unique'] ?? 1),
      strtoupper((string) ($indexDefinition['index_type'] ?? 'BTREE')),
      implode(',', $columnParts),
    ]);
  }

  private function removeDuplicateIndexes(DatabaseInterface $db, string $tableQN, string $tableAlias, int $storageId = 0): int
  {
    $removedIndexes = 0;

    try {
      $tableIndexes = $this->getTableIndexes($db, $tableQN);
    } catch (\Throwable $e) {
      $this->log(
        "[WARNING] Could not inspect indexes on {$tableAlias} for duplicate cleanup: " . $e->getMessage(),
        Log::WARNING
      );

      return 0;
    }

    if ($tableIndexes === []) {
      return 0;
    }

    $signatures = [];

    foreach ($tableIndexes as $keyName => $indexDefinition) {
      if (strtoupper((string) $keyName) === 'PRIMARY') {
        continue;
      }

      $signature = (string) ($indexDefinition['signature'] ?? '');
      if ($signature === '') {
        continue;
      }

      $signatures[$signature][] = (string) $keyName;
    }

    foreach ($signatures as $duplicateNames) {
      if (count($duplicateNames) < 2) {
        continue;
      }

      usort(
        $duplicateNames,
        static fn(string $a, string $b): int => strcasecmp($a, $b)
      );

      $keptIndexName = array_shift($duplicateNames);

      foreach ($duplicateNames as $duplicateName) {
        try {
          $db->setQuery(
            'ALTER TABLE ' . $tableQN
            . ' DROP INDEX ' . $db->quoteName($duplicateName)
          )->execute();

          $removedIndexes++;
          $storageSuffix = $storageId > 0 ? " (storage {$storageId})" : '';
          $this->log("[OK] Removed duplicate index {$duplicateName} on {$tableAlias}{$storageSuffix}; kept {$keptIndexName}.");
        } catch (\Throwable $e) {
          $storageSuffix = $storageId > 0 ? " (storage {$storageId})" : '';
          $this->log(
            "[WARNING] Failed removing duplicate index {$duplicateName} on {$tableAlias}{$storageSuffix}: " . $e->getMessage(),
            Log::WARNING
          );
        }
      }
    }

    return $removedIndexes;
  }

  private function getCurrentInstalledVersion(): string
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $query = $db->getQuery(true)
      ->select($db->quoteName('manifest_cache'))
      ->from($db->quoteName('#__extensions'))
      ->where($db->quoteName('element') . ' = ' . $db->quote('com_contentbuilderng'));

    $db->setQuery($query);
    $manifest = $db->loadResult();

    if ($manifest) {
      $manifest = json_decode($manifest, true);
      $version = $manifest['version'] ?? '0.0.0';
    } else {
      $version = '0.0.0';
    }

    return $version;
  }

  private function getIncomingPackageVersion(?InstallerAdapter $parent): string
  {
    if ($parent) {
      try {
        if (method_exists($parent, 'getManifest')) {
          $manifest = $parent->getManifest();
          if ($manifest instanceof \SimpleXMLElement) {
            $version = trim((string) ($manifest->version ?? ''));
            if ($version !== '') {
              return $version;
            }

            $attrVersion = trim((string) ($manifest['version'] ?? ''));
            if ($attrVersion !== '') {
              return $attrVersion;
            }
          }
        }
      } catch (\Throwable) {
        // Ignore and fallback below.
      }
    }

    return 'unknown';
  }

  private function getInstallerPackageInfo($parent): array
  {
    $candidates = [];
    $pushCandidate = static function (array &$list, $path): void {
      $path = trim((string) ($path ?? ''));

      if ($path === '') {
        return;
      }

      if (!in_array($path, $list, true)) {
        $list[] = $path;
      }
    };

    if (is_object($parent)) {
      if (method_exists($parent, 'getPath')) {
        foreach (['packagefile', 'package', 'archive', 'source', 'manifest'] as $key) {
          try {
            $pushCandidate($candidates, $parent->getPath($key));
          } catch (\Throwable) {
            // Ignore path accessor issues.
          }
        }
      }

      if (method_exists($parent, 'getParent')) {
        try {
          $parentInstaller = $parent->getParent();
        } catch (\Throwable) {
          $parentInstaller = null;
        }

        if (is_object($parentInstaller) && method_exists($parentInstaller, 'getPath')) {
          foreach (['packagefile', 'package', 'archive', 'source', 'manifest'] as $key) {
            try {
              $pushCandidate($candidates, $parentInstaller->getPath($key));
            } catch (\Throwable) {
              // Ignore path accessor issues.
            }
          }
        }
      }
    }

    $installerName = trim((string) ($_FILES['install_package']['name'] ?? ''));
    $sizeBytes = null;

    if (isset($_FILES['install_package']['size']) && is_numeric($_FILES['install_package']['size'])) {
      $sizeBytes = (int) $_FILES['install_package']['size'];
    }

    $selectedPath = null;

    foreach ($candidates as $candidate) {
      if (is_dir($candidate)) {
        continue;
      }

      if (!is_file($candidate)) {
        continue;
      }

      $filename = strtolower(basename($candidate));
      if (
        str_ends_with($filename, '.zip')
        || str_ends_with($filename, '.tar')
        || str_ends_with($filename, '.tar.gz')
        || str_ends_with($filename, '.tgz')
      ) {
        $selectedPath = $candidate;
        break;
      }

      if ($selectedPath === null) {
        $selectedPath = $candidate;
      }
    }

    if ($selectedPath !== null && is_file($selectedPath)) {
      if ($installerName === '') {
        $installerName = basename($selectedPath);
      }

      if ($sizeBytes === null) {
        $size = filesize($selectedPath);
        if ($size !== false) {
          $sizeBytes = (int) $size;
        }
      }
    }

    if ($installerName === '') {
      $installerName = 'unknown';
    }

    return [
      'name' => $installerName,
      'size_bytes' => $sizeBytes,
    ];
  }

  private function formatBytesForLog(?int $bytes): string
  {
    if ($bytes === null || $bytes < 0) {
      return 'unknown';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = (float) $bytes;
    $unitIndex = 0;

    while ($value >= 1024 && $unitIndex < count($units) - 1) {
      $value /= 1024;
      $unitIndex++;
    }

    $formatted = $unitIndex === 0
      ? (string) (int) $value
      : number_format($value, 2, '.', '');

    return $formatted . ' ' . $units[$unitIndex] . ' (' . $bytes . ' bytes)';
  }


  function installAndUpdate(): bool
  {
    try {
      /*    $db = Factory::getContainer()->get(DatabaseInterface::class);
      $plugins = $this->getPlugins();
      $base_path = JPATH_SITE . '/administrator/components/com_contentbuilderng/plugins';
      $folders = Folder::folders($base_path);

      foreach ($folders as $folder) {
        $installer = new Installer();
        $installer->setDatabase(\Joomla\CMS\Factory::getContainer()->get('DatabaseDriver'));

        Factory::getApplication()->enqueueMessage('Installing plugin <b>' . $folder . '</b>', 'message');
        $success = $installer->install($base_path . '/' . $folder);
        if (!$success) {
          Factory::getApplication()->enqueueMessage('Install failed for plugin <b>' . $folder . '</b>', 'error');
        }
      }*/

      // Publication des plugins.
      /*
      foreach ($plugins as $folder => $subplugs) {
        foreach ($subplugs as $plugin) {
          $query = 'UPDATE #__extensions SET `enabled` = 1 WHERE `type` = "plugin" AND `element` = ' . $db->quote($plugin) . ' AND `folder` = ' . $db->quote($folder);
          $db->setQuery($query);
          $db->execute();
          $this->log("Plugin {$plugin} in folder {$folder} enabled.");
          Factory::getApplication()->enqueueMessage('Published plugin <b>' . $plugin . '</b>', 'message');
        }
      }*/

      $this->ensureUploadDirectoryExists();
    } catch (\Throwable $e) {
      $this->log('[ERROR] installAndUpdate aborted: ' . $e->getMessage(), Log::ERROR);
      return false;
    }

    return !$this->hasCriticalFailure();
  }

  private function ensureUploadDirectoryExists(): void
  {
    $uploadDir = JPATH_ROOT . '/media/com_contentbuilderng/upload';
    $parentDir = dirname($uploadDir);

    if (!Folder::exists($parentDir) && !Folder::create($parentDir)) {
      $this->log('[WARNING] Could not create upload parent directory: ' . $parentDir, Log::WARNING);
      return;
    }

    if (!Folder::exists($uploadDir) && !Folder::create($uploadDir)) {
      $this->log('[WARNING] Could not create upload directory: ' . $uploadDir, Log::WARNING);
      return;
    }

    $indexFile = $uploadDir . '/index.html';
    if (!File::exists($indexFile)) {
      File::write($indexFile, '');
    }
  }

  /**
   * method to install the component
   *
   * @return bool
   */
  public function install(InstallerAdapter $parent): bool
  {
    $this->resetCriticalFailures();

    if (!version_compare(PHP_VERSION, '8.1', '>=')) {
      Factory::getApplication()->enqueueMessage('"WARNING: YOU ARE RUNNING PHP VERSION "' . PHP_VERSION . '". ContentBuilder NG WON\'T WORK WITH THIS VERSION. PLEASE UPGRADE TO AT LEAST PHP 8.1, SORRY BUT YOU BETTER UNINSTALL THIS COMPONENT NOW!"', 'error');
    }


    try {
      $result = $this->installAndUpdate();
      return $result && !$this->hasCriticalFailure();
    } catch (\Throwable $e) {
      $this->log('[ERROR] Install aborted: ' . $e->getMessage(), Log::ERROR);
      return false;
    }
  }

  /**
   * method to update the component
   *
   * @return bool
   */
  public function update(InstallerAdapter $parent): bool
  {
    $this->resetCriticalFailures();

    if (!version_compare(PHP_VERSION, '8.1', '>=')) {
      Factory::getApplication()->enqueueMessage('"WARNING: YOU ARE RUNNING PHP VERSION "' . PHP_VERSION . '". ContentBuilder NG WON\'T WORK WITH THIS VERSION. PLEASE UPGRADE TO AT LEAST PHP 8.1, SORRY BUT YOU BETTER UNINSTALL THIS COMPONENT NOW!"', 'error');
    }

    try {
      $result = $this->installAndUpdate();
      return $result && !$this->hasCriticalFailure();
    } catch (\Throwable $e) {
      $this->log('[ERROR] Update aborted: ' . $e->getMessage(), Log::ERROR);
      return false;
    }
  }

  /**
   * method to uninstall the component
   *
   * @return bool
   */
  public function uninstall(InstallerAdapter $parent): bool
  {
    $this->resetCriticalFailures();
    $this->log('Uninstall of ContentBuilder NG.');

    try {
      $db = Factory::getContainer()->get(DatabaseInterface::class);

      try {
        $conditions = array_merge(
          $this->buildMenuLinkOptionWhereClauses($db, 'com_contentbuilderng'),
          $this->buildMenuLinkOptionWhereClauses($db, 'com_contentbuilder')
        );

        $db->setQuery(
          $db->getQuery(true)
            ->delete($db->quoteName('#__menu'))
            ->where('(' . implode(' OR ', $conditions) . ')')
        )->execute();
      } catch (\Throwable $e) {
        $this->log('[WARNING] Failed to remove component menu entries on uninstall: ' . $e->getMessage(), Log::WARNING);
      }

      $plugins = $this->getPlugins();
      $installer = new Installer();
      $installer->setDatabase(\Joomla\CMS\Factory::getContainer()->get('DatabaseDriver'));

      foreach ($plugins as $folder => $subplugs) {
        foreach ($subplugs as $plugin) {
          $query = 'SELECT `extension_id` FROM #__extensions WHERE `type` = "plugin" AND `element` = ' . $db->quote($plugin) . ' AND `folder` = ' . $db->quote($folder);
          $db->setQuery($query);
          $id = $db->loadResult();

          if ($id) {
            $installer->uninstall('plugin', $id, 1);
          }
        }
      }

      $db->setQuery("SELECT id FROM `#__menu` WHERE `alias` = 'root'");
      if (!$db->loadResult()) {
        $db->setQuery("INSERT INTO `#__menu` VALUES(1, '', 'Menu_Item_Root', 'root', '', '', '', '', 1, 0, 0, 0, 0, 0, NULL, 0, 0, '', 0, '', 0, (SELECT MAX(mlft.rgt)+1 FROM #__menu AS mlft), 0, '*', 0)");
        $db->execute();
      }
    } catch (\Throwable $e) {
      $this->log('[ERROR] Uninstall aborted: ' . $e->getMessage(), Log::ERROR);
      return false;
    }

    return !$this->hasCriticalFailure();
  }

  /**
   * method to run before an install/update/uninstall method
   *
   * @return bool
   */
  public function preflight($type, $parent): bool
  {
    $this->resetCriticalFailures();

    try {
      if (!parent::preflight($type, $parent)) {
        return false;
      }

      $db = Factory::getContainer()->get(DatabaseInterface::class);
      $incomingVersion = $this->getIncomingPackageVersion($parent);
      $installerInfo = $this->getInstallerPackageInfo($parent);
      $timezoneName = (string) Factory::getApplication()->get('offset', 'UTC');
      if ($timezoneName === '') {
        $timezoneName = 'UTC';
      }

      try {
        $timezone = new \DateTimeZone($timezoneName !== '' ? $timezoneName : 'UTC');
      } catch (\Throwable) {
        $timezone = new \DateTimeZone('UTC');
      }

      $startedAt = (new \DateTimeImmutable('now', $timezone))->format('Y-m-d H:i:s T');

      // === LOG POUR DÉBOGAGE ===
      $this->log(
        '[OK] Préflight : '
        . $startedAt
        . ' | Installateur : '
        . $installerInfo['name']
        . ' | Taille : '
        . $this->formatBytesForLog($installerInfo['size_bytes'] ?? null)
        . '.'
      );
      $incomingVersionHtml = htmlspecialchars($incomingVersion, ENT_QUOTES, 'UTF-8');
      $currentVersion = $this->getCurrentInstalledVersion();
      $currentVersionHtml = htmlspecialchars($currentVersion, ENT_QUOTES, 'UTF-8');
      $actionLabel = strtolower(trim((string) $type)) !== '' ? strtoupper((string) $type) : 'UNKNOWN';
      $this->log(
        '<span style="color:#0d6efd;font-weight:700;" title="Version currently being installed" aria-hidden="true">&#11014;</span> '
        . 'ContentBuilder NG <strong>'
        . $incomingVersionHtml
        . '</strong> installation/update started.',
        Log::INFO
      );
      $this->enqueueRuntimeHeaderBlock();
      $this->log('[OK] Detected current version in manifest_cache : ' . $currentVersionHtml . '.');
      $this->log(
        '[OK] ContentBuilder NG versions : '
        . '<span style="color:#0d6efd;font-weight:700;" title="Version currently being installed" aria-hidden="true">&#11014;</span> '
        . 'package <strong>'
        . $incomingVersionHtml
        . '</strong> | installed <strong>'
        . $currentVersionHtml
        . '</strong> | action <strong>'
        . $actionLabel
        . '</strong>.'
      );

      if ($type !== 'uninstall') {
        $context = 'preflight:' . (string) $type;
        $this->disableLegacyPluginsInPriorityOrder($context);
        $this->removeLegacySystemPluginFolderEarly($context);
        $this->reportLegacyTableCollisions();
      }

      $db->setQuery("Select id From `#__menu` Where `alias` = 'root'");
      if (!$db->loadResult()) {
        $db->setQuery("INSERT INTO `#__menu` VALUES(1, '', 'Menu_Item_Root', 'root', '', '', '', '', 1, 0, 0, 0, 0, 0, NULL, 0, 0, '', 0, '', 0, ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone ), 0, '*', 0)");
        $db->execute();
      }

      if ($type !== 'uninstall') {
        $this->renameLegacyTables();
        $this->migrateLegacyContentbuilderName('com_contentbuilder');
        $this->migrateLegacyContentbuilderName('com_contentbuilder_ng');
      }
    } catch (\Throwable $e) {
      $this->log('[ERROR] Preflight aborted: ' . $e->getMessage(), Log::ERROR);
      return false;
    }

    return !$this->hasCriticalFailure();
  }


  /**
   * method to remove old folders.
   *
   * @return void
   */
  private function removeOldDirectories(): void
  {
    $paths = [
      JPATH_ADMINISTRATOR . '/components/com_contentbuilder/',
      JPATH_ADMINISTRATOR . '/components/com_contentbuilder_ng/',
      JPATH_SITE . '/components/com_contentbuilder/',
      JPATH_SITE . '/components/com_contentbuilder_ng/',
      JPATH_ROOT . '/media/contentbuilder/',
      JPATH_ROOT . '/media/contentbuilder_ng/',
      JPATH_SITE . '/media/com_contentbuilder/',
      JPATH_SITE . '/media/com_contentbuilder_ng/'
    ];

    foreach ($paths as $path) {
      if (Folder::exists($path)) {
        if (Folder::delete($path)) {
          $this->log("[OK] Old {$path} folder successfully deleted.");
        } else {
          $this->log("[ERROR] Failed to delete {$path} folder.", Log::ERROR);
        }
      } elseif (File::exists($path)) {
          $this->log("[ERROR] Not a {$path} folder, but a file.", Log::ERROR);
      } else {
        $this->log("[OK] No previous {$path} directory found.", Log::INFO, false);
      }
    }
  }

    /**
   * method to remove old files.
   *
   * @return void
   */
  private function removeObsoleteFiles(): void
  {
    $paths = [
      JPATH_ADMINISTRATOR . '/components/com_contentbuilder/classes/PHPExcel',
      JPATH_ADMINISTRATOR . '/components/com_contentbuilder/classes/PHPExcel.php',
      JPATH_ADMINISTRATOR . '/components/com_contentbuilder_ng/src/Model/EditModel.php',
    ];

    $app = Factory::getApplication();

    foreach ($paths as $path) {
      if (File::exists($path)) {
        if (File::delete($path)) {
          $this->log("[OK] Removed obsolete file {$path}.");
        } else {
          $this->log("[ERROR] Failed to remove obsolete file {$path}.", Log::ERROR);
        }
      } else {
        $this->log("[OK] Obsolete file {$path} not found.", Log::INFO, false);
      }
    }
  }

  private function ensureMediaListTemplateInstalled(): void
  {
    $source = JPATH_SITE . '/components/com_contentbuilderng/tmpl/list/default.php';
    $target = JPATH_ROOT . '/media/com_contentbuilderng/images/list/tmpl/default.php';
    $targetDir = \dirname($target);

    if (File::exists($target)) {
      return;
    }

    if (!File::exists($source)) {
      $this->log("[WARNING] Missing source list template {$source}; cannot install media list template.", Log::WARNING);
      return;
    }

    if (!Folder::exists($targetDir) && !Folder::create($targetDir)) {
      $this->log("[WARNING] Could not create media template directory {$targetDir}.", Log::WARNING);
      return;
    }

    if (!File::copy($source, $target)) {
      $this->log("[WARNING] Could not install media list template {$target}.", Log::WARNING);
      return;
    }

    $this->log('[OK] Installed missing media list template: images/list/tmpl/default.php.');
  }

  /**
   * method to change the DATE default value for strict MySQL databases.
   *
   * @return void
   */

  private function updateDateColumns(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $alterQueries = [
      // Table #__contentbuilderng_forms
      "ALTER TABLE `#__contentbuilderng_forms` MODIFY `created` DATETIME NULL DEFAULT CURRENT_TIMESTAMP",
      "UPDATE `#__contentbuilderng_forms` SET `created` = NULL WHERE `created` = '0000-00-00'",

      "ALTER TABLE `#__contentbuilderng_forms` MODIFY `modified` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilderng_forms` SET `modified` = NULL WHERE `modified` = '0000-00-00'",

      "ALTER TABLE `#__contentbuilderng_forms` MODIFY `last_update` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilderng_forms` SET `last_update` = NULL WHERE `last_update` = '0000-00-00'",

      "ALTER TABLE `#__contentbuilderng_forms` MODIFY `rand_date_update` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilderng_forms` SET `rand_date_update` = NULL WHERE `rand_date_update` = '0000-00-00'",

      // Table #__contentbuilderng_records
      "ALTER TABLE `#__contentbuilderng_records` MODIFY `publish_up` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilderng_records` SET `publish_up` = NULL WHERE `publish_up` = '0000-00-00'",
      "ALTER TABLE `#__contentbuilderng_records` MODIFY `publish_down` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilderng_records` SET `publish_down` = NULL WHERE `publish_down` = '0000-00-00'",
      "ALTER TABLE `#__contentbuilderng_records` MODIFY `last_update` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilderng_records` SET `last_update` = NULL WHERE `last_update` = '0000-00-00'",
      "ALTER TABLE `#__contentbuilderng_records` MODIFY `rand_date` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilderng_records` SET `rand_date` = NULL WHERE `rand_date` = '0000-00-00'",

      // Table #__contentbuilderng_articles (si présent)
      "ALTER TABLE `#__contentbuilderng_articles` MODIFY `last_update` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilderng_articles` SET `last_update` = NULL WHERE `last_update` = '0000-00-00'",

      // Table #__contentbuilderng_users (dates de vérification)
      "ALTER TABLE `#__contentbuilderng_users` MODIFY `verification_date_view` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilderng_users` SET `verification_date_view` = NULL WHERE `verification_date_view` = '0000-00-00'",
      "ALTER TABLE `#__contentbuilderng_users` MODIFY `verification_date_new` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilderng_users` SET `verification_date_new` = NULL WHERE `verification_date_new` = '0000-00-00'",
      "ALTER TABLE `#__contentbuilderng_users` MODIFY `verification_date_edit` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilderng_users` SET `verification_date_edit` = NULL WHERE `verification_date_edit` = '0000-00-00'",

      "ALTER TABLE `#__contentbuilderng_rating_cache` MODIFY COLUMN `date` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilderng_rating_cache` SET `date` = NULL WHERE `date` = '0000-00-00'",

      // Table #__contentbuilderng_verifications
      "ALTER TABLE `#__contentbuilderng_verifications` MODIFY `start_date` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilderng_verifications` SET `start_date` = NULL WHERE `start_date` = '0000-00-00'",
      "ALTER TABLE `#__contentbuilderng_verifications` MODIFY `verification_date` DATETIME NULL DEFAULT NULL",
      "UPDATE `#__contentbuilderng_verifications` SET `verification_date` = NULL WHERE `verification_date` = '0000-00-00'"
    ];

    foreach ($alterQueries as $query) {
      try {
        $db->setQuery($query)->execute();
      } catch (\Exception $e) {
        // Silencieux si la colonne est déjà correcte ou table inexistante
        $msg = '[WARNING] Could not alter date column: ' . $e->getMessage() . '.';
        $this->log($msg, Log::WARNING);
      }
    }

    $this->migrateStoragesAuditColumns();
    $this->migrateInternalStorageDataTablesAuditColumns();

    $msg = '[OK] Date fields updated to support NULL correctly, if necessary.';
    $this->log($msg);
  }

  private function storageAuditColumnDefinition(string $column): string
  {
    return match ($column) {
      'created' => 'DATETIME NULL DEFAULT CURRENT_TIMESTAMP',
      'modified' => 'DATETIME NULL DEFAULT NULL',
      'created_by' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
      'modified_by' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
      default => 'TEXT NULL',
    };
  }

  private function getStoragesTableColumnsLower(): array
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
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
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $columns = $this->getStoragesTableColumnsLower();
    if (empty($columns)) {
      return;
    }

    $legacyToStandard = [
      'last_update' => 'modified',
      'last_updated' => 'modified',
      'createdby' => 'created_by',
      'modifiedby' => 'modified_by',
      'updated_by' => 'modified_by',
    ];

    foreach ($legacyToStandard as $legacy => $target) {
      if (!array_key_exists($legacy, $columns)) {
        continue;
      }

      $targetDefinition = $this->storageAuditColumnDefinition($target);

      if (!array_key_exists($target, $columns)) {
        try {
          $db->setQuery(
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_storages') .
            ' CHANGE ' . $db->quoteName($legacy) . ' ' . $db->quoteName($target) . ' ' . $targetDefinition
          )->execute();
          $this->log("[OK] Renamed storage audit column {$legacy} to {$target}.");
          $columns = $this->getStoragesTableColumnsLower();
          continue;
        } catch (\Throwable $e) {
          $this->log("[WARNING] Failed renaming storage audit column {$legacy} to {$target}: " . $e->getMessage(), Log::WARNING);
        }
      }

      try {
        if ($target === 'modified' || $target === 'created') {
          $db->setQuery(
            'UPDATE ' . $db->quoteName('#__contentbuilderng_storages') .
            ' SET ' . $db->quoteName($target) . ' = ' . $db->quoteName($legacy) .
            ' WHERE (' . $db->quoteName($target) . ' IS NULL OR ' . $db->quoteName($target) . " IN ('0000-00-00', '0000-00-00 00:00:00'))" .
            ' AND ' . $db->quoteName($legacy) . ' IS NOT NULL' .
            ' AND ' . $db->quoteName($legacy) . " NOT IN ('0000-00-00', '0000-00-00 00:00:00')"
          )->execute();
        } else {
          $db->setQuery(
            'UPDATE ' . $db->quoteName('#__contentbuilderng_storages') .
            ' SET ' . $db->quoteName($target) . ' = ' . $db->quoteName($legacy) .
            ' WHERE (' . $db->quoteName($target) . " = '' OR " . $db->quoteName($target) . ' IS NULL)' .
            ' AND ' . $db->quoteName($legacy) . ' IS NOT NULL' .
            ' AND ' . $db->quoteName($legacy) . " <> ''"
          )->execute();
        }
      } catch (\Throwable $e) {
        $this->log("[WARNING] Failed copying data from {$legacy} to {$target}: " . $e->getMessage(), Log::WARNING);
      }

      if ($legacy !== $target) {
        try {
          $db->setQuery(
            'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_storages') .
            ' DROP COLUMN ' . $db->quoteName($legacy)
          )->execute();
          $this->log("[OK] Removed legacy storage audit column {$legacy}.");
          $columns = $this->getStoragesTableColumnsLower();
        } catch (\Throwable $e) {
          $this->log("[WARNING] Failed removing legacy storage audit column {$legacy}: " . $e->getMessage(), Log::WARNING);
        }
      }
    }

    $required = ['created', 'modified', 'created_by', 'modified_by'];
    foreach ($required as $column) {
      if (array_key_exists($column, $columns)) {
        continue;
      }
      try {
        $db->setQuery(
          'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_storages') .
          ' ADD COLUMN ' . $db->quoteName($column) . ' ' . $this->storageAuditColumnDefinition($column)
        )->execute();
        $this->log("[OK] Added storage audit column {$column}.");
      } catch (\Throwable $e) {
        $this->log("[WARNING] Failed adding storage audit column {$column}: " . $e->getMessage(), Log::WARNING);
      }
    }

    $normalizationQueries = [
      "ALTER TABLE `#__contentbuilderng_storages` MODIFY `created` DATETIME NULL DEFAULT CURRENT_TIMESTAMP",
      "ALTER TABLE `#__contentbuilderng_storages` MODIFY `modified` DATETIME NULL DEFAULT NULL",
      "ALTER TABLE `#__contentbuilderng_storages` MODIFY `created_by` VARCHAR(255) NOT NULL DEFAULT ''",
      "ALTER TABLE `#__contentbuilderng_storages` MODIFY `modified_by` VARCHAR(255) NOT NULL DEFAULT ''",
      "UPDATE `#__contentbuilderng_storages` SET `created` = NULL WHERE `created` IN ('0000-00-00', '0000-00-00 00:00:00')",
      "UPDATE `#__contentbuilderng_storages` SET `modified` = NULL WHERE `modified` IN ('0000-00-00', '0000-00-00 00:00:00')",
      "UPDATE `#__contentbuilderng_storages` SET `created_by` = '' WHERE `created_by` IS NULL",
      "UPDATE `#__contentbuilderng_storages` SET `modified_by` = '' WHERE `modified_by` IS NULL",
    ];

    foreach ($normalizationQueries as $query) {
      try {
        $db->setQuery($query)->execute();
      } catch (\Throwable $e) {
        $this->log('[WARNING] Could not normalize storage audit columns: ' . $e->getMessage(), Log::WARNING);
      }
    }
  }

  private function migrateInternalStorageDataTablesAuditColumns(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $now = Factory::getDate()->toSql();

    try {
      $query = $db->getQuery(true)
        ->select($db->quoteName(['id', 'name']))
        ->from($db->quoteName('#__contentbuilderng_storages'))
        ->where('(' . $db->quoteName('bytable') . ' = 0 OR ' . $db->quoteName('bytable') . ' IS NULL)')
        ->where($db->quoteName('name') . " <> ''");

      $db->setQuery($query);
      $storages = $db->loadAssocList() ?: [];
    } catch (\Throwable $e) {
      $this->log('[WARNING] Could not load internal storages for audit migration: ' . $e->getMessage(), Log::WARNING);
      return;
    }

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

      if ($storageId < 1 || $name === '') {
        continue;
      }

      if (!preg_match('/^[a-z0-9_]+$/', $name)) {
        $this->log("[WARNING] Skipping internal storage {$storageId}: invalid table name {$name}.", Log::WARNING);
        continue;
      }

      $tableAlias = '#__' . $name;
      $tableQN = $db->quoteName($tableAlias);

      try {
        $columns = $db->getTableColumns($tableAlias, false);
      } catch (\Throwable $e) {
        $exceptionMessage = (string) $e->getMessage();
        $isMissingTable = stripos($exceptionMessage, "doesn't exist") !== false
          || stripos($exceptionMessage, 'does not exist') !== false;

        if ($isMissingTable) {
          $this->log(
            "[INFO] Data table {$tableAlias} (storage {$storageId}) is missing, skipping its audit migration.",
            Log::INFO
          );
        } else {
          $this->log(
            "[WARNING] Could not inspect data table {$tableAlias} (storage {$storageId}): {$exceptionMessage}. Continuing installation.",
            Log::WARNING
          );
        }

        $missingTables++;
        continue;
      }

      if (empty($columns)) {
        $missingTables++;
        continue;
      }

      $columns = array_change_key_case($columns, CASE_LOWER);
      $tableChanged = false;
      $removedForTable = $this->removeDuplicateIndexes($db, $tableQN, $tableAlias, $storageId);
      if ($removedForTable > 0) {
        $duplicatesRemoved += $removedForTable;
        $tableChanged = true;
      }
      $indexedColumns = $this->getIndexedColumns($db, $tableQN);

      $requiredColumns = [
        'id' => 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY',
        'storage_id' => 'INT NOT NULL DEFAULT ' . $storageId,
        'user_id' => 'INT NOT NULL DEFAULT 0',
        'created' => 'DATETIME NOT NULL DEFAULT ' . $db->quote($now),
        'created_by' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
        'modified_user_id' => 'INT NOT NULL DEFAULT 0',
        'modified' => 'DATETIME NULL DEFAULT NULL',
        'modified_by' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
      ];

      foreach ($requiredColumns as $column => $definition) {
        if (array_key_exists($column, $columns)) {
          continue;
        }

        try {
          $db->setQuery(
            'ALTER TABLE ' . $tableQN
            . ' ADD COLUMN ' . $db->quoteName($column) . ' ' . $definition
          )->execute();
          $columns[$column] = true;
          $tableChanged = true;
        } catch (\Throwable $e) {
          $this->log(
            "[WARNING] Failed adding audit column {$column} on {$tableAlias} (storage {$storageId}): " . $e->getMessage(),
            Log::WARNING
          );
        }
      }

      if (array_key_exists('storage_id', $columns)) {
        try {
          $db->setQuery(
            'UPDATE ' . $tableQN
            . ' SET ' . $db->quoteName('storage_id') . ' = ' . $storageId
            . ' WHERE ' . $db->quoteName('storage_id') . ' IS NULL OR ' . $db->quoteName('storage_id') . ' = 0'
          )->execute();
        } catch (\Throwable $e) {
          $this->log(
            "[WARNING] Failed normalizing storage_id on {$tableAlias} (storage {$storageId}): " . $e->getMessage(),
            Log::WARNING
          );
        }
      }

      foreach (['created_by', 'modified_by'] as $actorColumn) {
        if (!array_key_exists($actorColumn, $columns)) {
          continue;
        }
        try {
          $db->setQuery(
            'UPDATE ' . $tableQN
            . ' SET ' . $db->quoteName($actorColumn) . " = ''"
            . ' WHERE ' . $db->quoteName($actorColumn) . ' IS NULL'
          )->execute();
        } catch (\Throwable $e) {
          $this->log(
            "[WARNING] Failed normalizing {$actorColumn} on {$tableAlias} (storage {$storageId}): " . $e->getMessage(),
            Log::WARNING
          );
        }
      }

      foreach (['storage_id', 'user_id', 'created', 'modified_user_id', 'modified'] as $indexColumn) {
        if (!array_key_exists($indexColumn, $columns)) {
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
          $message = strtolower((string) $e->getMessage());
          if (strpos($message, 'too many keys') !== false) {
            $this->log(
              "[WARNING] Max index count reached on {$tableAlias} (storage {$storageId}); skipping remaining index additions.",
              Log::WARNING
            );
            break;
          }
          if (
            strpos($message, 'duplicate') === false
            && strpos($message, 'already exists') === false
          ) {
            $this->log(
              "[WARNING] Failed adding index {$indexColumn} on {$tableAlias} (storage {$storageId}): " . $e->getMessage(),
              Log::WARNING
            );
          } else {
            $indexedColumns[$indexColumn] = true;
          }
        }
      }

      if ($tableChanged) {
        $updated++;
      }
    }

    $this->log("[OK] Internal storage audit migration complete. Processed: {$processed}, updated: {$updated}, missing tables: {$missingTables}, duplicate indexes removed: {$duplicatesRemoved}.");
  }


  /* Rename com_contentbuilder ->  com_contentbuilderng */
  private function migrateLegacyContentbuilderName(string $legacyElement): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $targetElement = 'com_contentbuilderng';

    $legacyQuery = $db->getQuery(true)
      ->select($db->quoteName('extension_id'))
      ->from($db->quoteName('#__extensions'))
      ->where($db->quoteName('element') . ' = ' . $db->quote($legacyElement))
      ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
      ->where($db->quoteName('client_id') . ' = 1');

    $db->setQuery($legacyQuery);
    $legacyId = (int) $db->loadResult();

    if ($legacyId === 0) {
      return;
    }

    $targetQuery = $db->getQuery(true)
      ->select($db->quoteName('extension_id'))
      ->from($db->quoteName('#__extensions'))
      ->where($db->quoteName('element') . ' = ' . $db->quote($targetElement))
      ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
      ->where($db->quoteName('client_id') . ' = 1');

    $db->setQuery($targetQuery);
    if ((int) $db->loadResult() > 0) {
      $this->log("[INFO] Legacy extension {$legacyElement} detected while {$targetElement} already exists; cleanup will run during postflight update.");
      return;
    }

    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__extensions'))
          ->set($db->quoteName('element') . ' = ' . $db->quote($targetElement))
          ->set($db->quoteName('name') . ' = ' . $db->quote($targetElement))
          ->where($db->quoteName('extension_id') . ' = ' . $legacyId)
      )->execute();

      $this->log("[OK] Migrated extension element from {$legacyElement} to {$targetElement}.");
    } catch (\Throwable $e) {
      $message = "[ERROR] Failed to migrate legacy extension element: " . $e->getMessage();
      $this->log($message, Log::ERROR);
      return;
    }

    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__assets'))
          ->set($db->quoteName('name') . ' = ' . $db->quote($targetElement))
          ->where($db->quoteName('name') . ' = ' . $db->quote($legacyElement))
      )->execute();
      $this->log("[OK] Renamed asset ownership from {$legacyElement} to {$targetElement}.");
    } catch (\Throwable $e) {
      $message = "[WARNING] Could not update #__assets for {$legacyElement}: " . $e->getMessage();
      $this->log($message, Log::WARNING);
    }

    $this->updateMenuLinks($legacyElement, $targetElement);

    // Menu alias/title
    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__menu'))
          ->set($db->quoteName('alias') . ' = ' . $db->quote('contentbuilderng'))
          ->set($db->quoteName('path') . ' = ' . $db->quote('contentbuilderng'))
          ->set($db->quoteName('title') . ' = ' . $db->quote('COM_CONTENTBUILDERNG'))
          ->where($db->quoteName('alias') . ' = ' . $db->quote('contentbuilder'))
          ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_contentbuilder%'))
      )->execute();
      $this->log("[OK] Renamed legacy menu entry to contentbuilderng.");
    } catch (\Throwable $e) {
      $message = "[WARNING] Could not rename legacy menu entry: " . $e->getMessage();
      $this->log($message, Log::WARNING);
    }
  }

  private function updateMenuLinks(string $legacyElement, string $targetElement): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    $conditions = $this->buildMenuLinkOptionWhereClauses($db, $legacyElement);

    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__menu'))
          ->set(
            $db->quoteName('link') . ' = REPLACE(' . $db->quoteName('link') . ', ' .
            $db->quote('option=' . $legacyElement) . ', ' . $db->quote('option=' . $targetElement) . ')'
          )
          ->where('(' . implode(' OR ', $conditions) . ')')
      )->execute();
      $this->log("[OK] Updated menu links to point to {$targetElement}.");
    } catch (\Throwable $e) {
      $message = "[WARNING] Could not update menu links for legacy option: " . $e->getMessage();
      $this->log($message, Log::WARNING);
    }
  }

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
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    try {
      $rows = $db->setQuery(
        $db->getQuery(true)
          ->select($db->quoteName(['id', 'title', 'link', 'alias', 'path']))
          ->from($db->quoteName('#__menu'))
          ->where($db->quoteName('client_id') . ' = 1')
          ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
          ->where($db->quoteName('title') . ' LIKE ' . $db->quote('COM_CONTENTBUILDER%'))
          ->where(
            '('
            . $db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_contentbuilder%')
            . ' OR '
            . $db->quoteName('alias') . ' LIKE ' . $db->quote('contentbuilder%')
            . ' OR '
            . $db->quoteName('alias') . ' LIKE ' . $db->quote('com-contentbuilder%')
            . ' OR '
            . $db->quoteName('path') . ' LIKE ' . $db->quote('contentbuilder%')
            . ')'
          )
          ->order($db->quoteName('id') . ' ASC')
      )->loadAssocList() ?: [];
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed reading legacy menu title keys: ' . $e->getMessage(), Log::WARNING);
      return;
    }

    $detected = 0;
    $updated = 0;
    $failed = 0;

    foreach ($rows as $row) {
      $menuId = (int) ($row['id'] ?? 0);
      $oldTitle = strtoupper(trim((string) ($row['title'] ?? '')));

      if ($menuId < 1 || $oldTitle === '' || str_starts_with($oldTitle, 'COM_CONTENTBUILDERNG')) {
        continue;
      }

      $newTitle = $this->normalizeLegacyMenuTitleKey($oldTitle);
      if ($newTitle === $oldTitle) {
        continue;
      }

      $detected++;

      try {
        $db->setQuery(
          $db->getQuery(true)
            ->update($db->quoteName('#__menu'))
            ->set($db->quoteName('title') . ' = ' . $db->quote($newTitle))
            ->where($db->quoteName('id') . ' = ' . $menuId)
        )->execute();
        $updated++;
      } catch (\Throwable $e) {
        $failed++;
        $this->log(
          '[WARNING] Failed normalizing legacy menu title key for menu #' . $menuId . ': ' . $e->getMessage(),
          Log::WARNING
        );
      }
    }

    if ($detected === 0) {
      $this->log('[INFO] No legacy menu title key detected for ContentBuilder menu entries.');
      return;
    }

    if ($updated > 0) {
      $this->log('[OK] Normalized ' . $updated . ' legacy menu title key(s).');
    }

    if ($failed > 0) {
      $this->log('[WARNING] Legacy menu title key normalization finished with ' . $failed . ' failure(s).', Log::WARNING);
    }
  }

  private function normalizeBrokenTargetMenuLinks(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $passes = 0;
    $total = 0;

    // Earlier builds could transform com_contentbuilder_ng into com_contentbuilder_ng_ng.
    while ($passes < 5) {
      $passes++;
      try {
        $db->setQuery(
          $db->getQuery(true)
            ->update($db->quoteName('#__menu'))
            ->set(
              $db->quoteName('link') . ' = REPLACE(' . $db->quoteName('link') . ', ' .
              $db->quote('option=com_contentbuilder_ng_ng') . ', ' . $db->quote('option=com_contentbuilderng') . ')'
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
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    try {
      $rows = $db->setQuery(
        $db->getQuery(true)
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
          ->order($db->quoteName('extension_id') . ' DESC')
      )->loadAssocList() ?: [];
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed reading plugin duplicates: ' . $e->getMessage(), Log::WARNING);
      return;
    }

    $groups = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }

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

    if (empty($groups)) {
      return;
    }

    $removedTotal = 0;
    $groupCount = 0;
    foreach ($groups as $key => $groupRows) {
      if (!is_array($groupRows) || count($groupRows) < 2) {
        continue;
      }

      [$canonicalFolder, $canonicalElement] = explode('/', $key, 2);
      $keepId = 0;
      $bestCanonical = -1;
      $bestEnabled = -1;
      $bestManifest = -1;
      $bestId = -1;

      foreach ($groupRows as $row) {
        $id = (int) ($row['extension_id'] ?? 0);
        if ($id <= 0) {
          continue;
        }

        $isCanonical = ((string) ($row['folder'] ?? '') === $canonicalFolder && (string) ($row['element'] ?? '') === $canonicalElement) ? 1 : 0;
        $enabled = (int) ($row['enabled'] ?? 0);
        $hasManifest = trim((string) ($row['manifest_cache'] ?? '')) !== '' ? 1 : 0;

        $isBetter = false;
        if ($keepId === 0) {
          $isBetter = true;
        } elseif ($isCanonical > $bestCanonical) {
          $isBetter = true;
        } elseif ($isCanonical === $bestCanonical && $enabled > $bestEnabled) {
          $isBetter = true;
        } elseif ($isCanonical === $bestCanonical && $enabled === $bestEnabled && $hasManifest > $bestManifest) {
          $isBetter = true;
        } elseif ($isCanonical === $bestCanonical && $enabled === $bestEnabled && $hasManifest === $bestManifest && $id > $bestId) {
          $isBetter = true;
        }

        if ($isBetter) {
          $keepId = $id;
          $bestCanonical = $isCanonical;
          $bestEnabled = $enabled;
          $bestManifest = $hasManifest;
          $bestId = $id;
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

      if (empty($removeIds)) {
        continue;
      }

      try {
        $db->setQuery(
          $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('folder') . ' = ' . $db->quote($canonicalFolder))
            ->set($db->quoteName('element') . ' = ' . $db->quote($canonicalElement))
            ->where($db->quoteName('extension_id') . ' = ' . $keepId)
        )->execute();

        foreach (['#__schemas', '#__update_sites_extensions'] as $table) {
          $db->setQuery(
            $db->getQuery(true)
              ->delete($db->quoteName($table))
              ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $removeIds) . ')')
          )->execute();
        }

        $db->setQuery(
          $db->getQuery(true)
            ->delete($db->quoteName('#__extensions'))
            ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $removeIds) . ')')
        )->execute();

        $removed = (int) $db->getAffectedRows();
        $removedTotal += $removed;
        $groupCount++;

        $this->log(
          "[OK] Deduplicated plugin {$canonicalFolder}/{$canonicalElement}: kept extension_id {$keepId}, removed " . implode(',', $removeIds) . '.'
        );
      } catch (\Throwable $e) {
        $this->log(
          "[WARNING] Failed deduplicating plugin {$canonicalFolder}/{$canonicalElement}: " . $e->getMessage(),
          Log::WARNING
        );
      }
    }

    if ($groupCount > 0) {
      $this->log("[OK] Plugin deduplication completed: {$groupCount} group(s), {$removedTotal} duplicate row(s) removed.");
    }
  }

  private function deduplicateTargetComponentExtensions(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $targetElement = 'com_contentbuilderng';

    try {
      $rows = $db->setQuery(
        $db->getQuery(true)
          ->select($db->quoteName(['extension_id', 'enabled', 'manifest_cache']))
          ->from($db->quoteName('#__extensions'))
          ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
          ->where($db->quoteName('element') . ' = ' . $db->quote($targetElement))
          ->where($db->quoteName('client_id') . ' = 1')
          ->order($db->quoteName('extension_id') . ' DESC')
      )->loadAssocList() ?: [];
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed reading component duplicates: ' . $e->getMessage(), Log::WARNING);
      return;
    }

    $rows = array_values(array_filter($rows, static function (array $row): bool {
      return (int) ($row['extension_id'] ?? 0) > 0;
    }));

    if (count($rows) <= 1) {
      return;
    }

    $ids = array_values(array_unique(array_map(
      static fn(array $row): int => (int) ($row['extension_id'] ?? 0),
      $rows
    )));

    $menuRefCounts = [];
    foreach ($ids as $id) {
      $menuRefCounts[$id] = 0;
    }

    try {
      $refs = $db->setQuery(
        $db->getQuery(true)
          ->select([
            $db->quoteName('component_id'),
            'COUNT(1) AS refs',
          ])
          ->from($db->quoteName('#__menu'))
          ->where($db->quoteName('client_id') . ' = 1')
          ->where($db->quoteName('component_id') . ' IN (' . implode(',', $ids) . ')')
          ->group($db->quoteName('component_id'))
      )->loadAssocList() ?: [];

      foreach ($refs as $ref) {
        $refId = (int) ($ref['component_id'] ?? 0);
        if ($refId > 0) {
          $menuRefCounts[$refId] = (int) ($ref['refs'] ?? 0);
        }
      }
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed reading menu references for duplicate components: ' . $e->getMessage(), Log::WARNING);
    }

    $keepId = 0;
    $bestEnabled = -1;
    $bestHasManifest = -1;
    $bestMenuRefs = -1;
    $bestId = -1;

    foreach ($rows as $row) {
      $id = (int) ($row['extension_id'] ?? 0);
      if ($id <= 0) {
        continue;
      }

      $enabled = (int) ($row['enabled'] ?? 0);
      $hasManifest = trim((string) ($row['manifest_cache'] ?? '')) !== '' ? 1 : 0;
      $menuRefs = (int) ($menuRefCounts[$id] ?? 0);

      $isBetter = false;
      if ($keepId === 0) {
        $isBetter = true;
      } elseif ($enabled > $bestEnabled) {
        $isBetter = true;
      } elseif ($enabled === $bestEnabled && $hasManifest > $bestHasManifest) {
        $isBetter = true;
      } elseif ($enabled === $bestEnabled && $hasManifest === $bestHasManifest && $menuRefs > $bestMenuRefs) {
        $isBetter = true;
      } elseif ($enabled === $bestEnabled && $hasManifest === $bestHasManifest && $menuRefs === $bestMenuRefs && $id > $bestId) {
        $isBetter = true;
      }

      if ($isBetter) {
        $keepId = $id;
        $bestEnabled = $enabled;
        $bestHasManifest = $hasManifest;
        $bestMenuRefs = $menuRefs;
        $bestId = $id;
      }
    }

    if ($keepId <= 0) {
      return;
    }

    $removeIds = array_values(array_filter($ids, static fn(int $id): bool => $id !== $keepId));
    if (empty($removeIds)) {
      return;
    }

    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__menu'))
          ->set($db->quoteName('component_id') . ' = ' . $keepId)
          ->where($db->quoteName('component_id') . ' IN (' . implode(',', $removeIds) . ')')
      )->execute();
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed remapping menu component ids during dedupe: ' . $e->getMessage(), Log::WARNING);
    }

    foreach (['#__schemas', '#__update_sites_extensions'] as $table) {
      try {
        $db->setQuery(
          $db->getQuery(true)
            ->delete($db->quoteName($table))
            ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $removeIds) . ')')
        )->execute();
      } catch (\Throwable $e) {
        $this->log('[WARNING] Failed cleaning ' . $table . ' during component dedupe: ' . $e->getMessage(), Log::WARNING);
      }
    }

    try {
      $db->setQuery(
        $db->getQuery(true)
          ->delete($db->quoteName('#__extensions'))
          ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $removeIds) . ')')
      )->execute();

      $deleted = (int) $db->getAffectedRows();
      $this->log(
        "[OK] Deduplicated {$targetElement} component rows: kept extension_id {$keepId}, removed {$deleted} duplicate(s)."
      );
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed deleting duplicate component rows: ' . $e->getMessage(), Log::WARNING);
    }
  }

  private function resolveMenuAlias(int $parentId, string $baseAlias): string
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $alias = $baseAlias;
    $suffix = 2;

    while ($suffix < 100) {
      $query = $db->getQuery(true)
        ->select('COUNT(1)')
        ->from($db->quoteName('#__menu'))
        ->where($db->quoteName('parent_id') . ' = ' . (int) $parentId)
        ->where($db->quoteName('client_id') . ' = 1')
        ->where($db->quoteName('alias') . ' = ' . $db->quote($alias));

      $db->setQuery($query);
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
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $targetElement = 'com_contentbuilderng';

    try {
      $componentId = (int) $db->setQuery(
        $db->getQuery(true)
          ->select($db->quoteName('extension_id'))
          ->from($db->quoteName('#__extensions'))
          ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
          ->where($db->quoteName('element') . ' = ' . $db->quote($targetElement))
          ->where($db->quoteName('client_id') . ' = 1')
      )->loadResult();
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed reading component extension id: ' . $e->getMessage(), Log::WARNING);
      return;
    }

    if ($componentId === 0) {
      $this->log('[WARNING] Cannot ensure admin menu entry: com_contentbuilderng extension id is missing.', Log::WARNING);
      return;
    }

    $mainRows = [];
    try {
      $mainRows = $db->setQuery(
        $db->getQuery(true)
          ->select($db->quoteName(['id', 'alias', 'path']))
          ->from($db->quoteName('#__menu'))
          ->where($db->quoteName('client_id') . ' = 1')
          ->where($db->quoteName('parent_id') . ' = 1')
          ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
          ->where(
            '(' .
            $db->quoteName('component_id') . ' = ' . $componentId .
            ' OR ' . $db->quoteName('link') . ' LIKE ' . $db->quote('index.php?option=com_contentbuilderng%') .
            ')'
          )
          ->order($db->quoteName('id') . ' ASC')
      )->loadAssocList() ?: [];
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed checking existing admin menu entry: ' . $e->getMessage(), Log::WARNING);
      return;
    }

    if (!empty($mainRows)) {
      $mainId = (int) $mainRows[0]['id'];
      $alias = trim((string) ($mainRows[0]['alias'] ?? ''));
      $path = trim((string) ($mainRows[0]['path'] ?? ''));

      if ($alias === '') {
        $alias = $this->resolveMenuAlias(1, 'contentbuilderng');
      }
      if ($path === '') {
        $path = $alias;
      }

      try {
        $db->setQuery(
          $db->getQuery(true)
            ->update($db->quoteName('#__menu'))
            ->set($db->quoteName('title') . ' = ' . $db->quote('COM_CONTENTBUILDERNG'))
            ->set($db->quoteName('alias') . ' = ' . $db->quote($alias))
            ->set($db->quoteName('path') . ' = ' . $db->quote($path))
            ->set($db->quoteName('link') . ' = ' . $db->quote('index.php?option=com_contentbuilderng'))
            ->set($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->set($db->quoteName('published') . ' = 1')
            ->set($db->quoteName('component_id') . ' = ' . $componentId)
            ->set($db->quoteName('client_id') . ' = 1')
            ->where($db->quoteName('id') . ' = ' . $mainId)
        )->execute();

        $this->log('[OK] Administration component menu entry checked and updated.');
      } catch (\Throwable $e) {
        $this->log('[WARNING] Failed updating existing admin menu entry: ' . $e->getMessage(), Log::WARNING);
      }

      return;
    }

    $root = [];
    try {
      $root = $db->setQuery(
        $db->getQuery(true)
          ->select($db->quoteName(['id', 'rgt']))
          ->from($db->quoteName('#__menu'))
          ->where($db->quoteName('alias') . ' = ' . $db->quote('root'))
          ->where($db->quoteName('client_id') . ' = 1')
          ->order($db->quoteName('id') . ' ASC')
      )->loadAssoc() ?: [];
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed loading admin menu root: ' . $e->getMessage(), Log::WARNING);
    }

    if (empty($root)) {
      try {
        $root = $db->setQuery(
          $db->getQuery(true)
            ->select($db->quoteName(['id', 'rgt']))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('id') . ' = 1')
        )->loadAssoc() ?: [];
      } catch (\Throwable $e) {
        $this->log('[WARNING] Could not load fallback menu root: ' . $e->getMessage(), Log::WARNING);
      }
    }

    if (empty($root)) {
      $this->log('[ERROR] Cannot recreate admin menu entry: root node not found.', Log::ERROR);
      return;
    }

    $rootId = (int) ($root['id'] ?? 1);
    $rootRgt = (int) ($root['rgt'] ?? 0);
    if ($rootRgt <= 0) {
      $this->log('[ERROR] Cannot recreate admin menu entry: invalid root rgt value.', Log::ERROR);
      return;
    }

    $alias = $this->resolveMenuAlias($rootId, 'contentbuilderng');

    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__menu'))
          ->set($db->quoteName('rgt') . ' = ' . $db->quoteName('rgt') . ' + 2')
          ->where($db->quoteName('rgt') . ' >= ' . $rootRgt)
      )->execute();

      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__menu'))
          ->set($db->quoteName('lft') . ' = ' . $db->quoteName('lft') . ' + 2')
          ->where($db->quoteName('lft') . ' > ' . $rootRgt)
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
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    try {
      $componentId = (int) $db->setQuery(
        $db->getQuery(true)
          ->select($db->quoteName('extension_id'))
          ->from($db->quoteName('#__extensions'))
          ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
          ->where($db->quoteName('element') . ' = ' . $db->quote('com_contentbuilderng'))
          ->where($db->quoteName('client_id') . ' = 1')
      )->loadResult();
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed reading extension id for submenu quicktasks: ' . $e->getMessage(), Log::WARNING);
      return;
    }

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

    foreach ($targets as $target) {
      $quotedLinks = array_map(
        fn(string $link): string => $db->quote($link),
        $target['links']
      );

      try {
        $rows = $db->setQuery(
          $db->getQuery(true)
            ->select($db->quoteName(['id', 'params']))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('client_id') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('component_id') . ' = ' . $componentId)
            ->where($db->quoteName('parent_id') . ' > 1')
            ->where($db->quoteName('link') . ' IN (' . implode(',', $quotedLinks) . ')')
        )->loadAssocList() ?: [];
      } catch (\Throwable $e) {
        $this->log(
          '[WARNING] Failed loading submenu entries for ' . $target['label'] . ': ' . $e->getMessage(),
          Log::WARNING
        );
        continue;
      }

      if (empty($rows)) {
        continue;
      }

      $updated = 0;
      foreach ($rows as $row) {
        $menuId = (int) ($row['id'] ?? 0);
        if ($menuId === 0) {
          continue;
        }

        $params = new Registry((string) ($row['params'] ?? ''));
        $changed = false;

        if ((string) $params->get('menu-quicktask') !== $target['quicktask']) {
          $params->set('menu-quicktask', $target['quicktask']);
          $changed = true;
        }
        if ((string) $params->get('menu-quicktask-title') !== $target['quicktask_title']) {
          $params->set('menu-quicktask-title', $target['quicktask_title']);
          $changed = true;
        }
        if ((string) $params->get('menu-quicktask-icon') !== 'plus') {
          $params->set('menu-quicktask-icon', 'plus');
          $changed = true;
        }

        if (!$changed) {
          continue;
        }

        try {
          $db->setQuery(
            $db->getQuery(true)
              ->update($db->quoteName('#__menu'))
              ->set($db->quoteName('params') . ' = ' . $db->quote($params->toString('JSON')))
              ->where($db->quoteName('id') . ' = ' . $menuId)
          )->execute();
          $updated++;
        } catch (\Throwable $e) {
          $this->log(
            '[WARNING] Failed updating quicktask menu params for menu #' . $menuId . ': ' . $e->getMessage(),
            Log::WARNING
          );
        }
      }

      if ($updated > 0) {
        $this->log('[OK] Updated Joomla quicktask (+) for ' . $target['label'] . ' submenu (' . $updated . ' entry).');
      }
    }
  }

  private function renameLegacyTables(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $prefix = $db->getPrefix();
    $existing = $db->getTableList();
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
      if (in_array($targetFull, $existing, true)) {
        $targetHasRows = false;
        $legacyHasRows = false;
        try {
          $db->setQuery('SELECT 1 FROM ' . $db->quoteName($targetFull) . ' LIMIT 1');
          $targetHasRows = (bool) $db->loadResult();
          $db->setQuery('SELECT 1 FROM ' . $db->quoteName($legacyFull) . ' LIMIT 1');
          $legacyHasRows = (bool) $db->loadResult();
        } catch (\Throwable $e) {
          $this->log(
            "[WARNING] Could not inspect {$targetFull}/{$legacyFull} before legacy replacement decision: " . $e->getMessage(),
            Log::WARNING
          );
          $skipped++;
          continue;
        }

        if (!$legacyHasRows) {
          try {
            $db->setQuery('DROP TABLE ' . $db->quoteName($legacyFull))->execute();
            $this->log(
              "[WARNING] Legacy source table {$legacyFull} was empty and has been removed; keeping {$targetFull}.",
              Log::WARNING
            );
            $existing = array_values(array_filter($existing, static fn($name) => $name !== $legacyFull));
            $skipped++;
            continue;
          } catch (\Throwable $e) {
            $this->log(
              "[WARNING] Failed dropping empty legacy source table {$legacyFull}: " . $e->getMessage(),
              Log::WARNING
            );
            $skipped++;
            continue;
          }
        }

        if ($targetHasRows) {
          $this->log("[WARNING] Legacy table {$legacyFull} detected but {$targetFull} already contains data, skipping rename.", Log::WARNING);
          $skipped++;
          continue;
        }

        try {
          $db->setQuery('DROP TABLE ' . $db->quoteName($targetFull))->execute();
          $this->log(
            "[WARNING] Target table {$targetFull} existed but was empty; it has been replaced by legacy table {$legacyFull}.",
            Log::WARNING
          );
          $existing = array_values(array_filter($existing, static fn($name) => $name !== $targetFull));
        } catch (\Throwable $e) {
          $this->log(
            "[WARNING] Failed dropping empty target table {$targetFull} before legacy replacement: " . $e->getMessage(),
            Log::WARNING
          );
          $skipped++;
          continue;
        }
      }

      try {
        $db->setQuery(
          "RENAME TABLE " . $db->quoteName($legacyFull) . " TO " . $db->quoteName($targetFull)
        )->execute();
        $this->log("[OK] Renamed table {$legacyFull} to {$targetFull}.");
        $renamed++;
        $existing[] = $targetFull;
        $existing = array_filter($existing, static fn($name) => $name !== $legacyFull);
      } catch (\Throwable $e) {
        $this->log("[WARNING] Failed to rename table {$legacyFull}: " . $e->getMessage(), Log::WARNING);
      }
    }

    $total = count(self::LEGACY_TABLE_RENAMES);
    $this->log("[OK] Table migration summary: renamed {$renamed}, skipped {$skipped}, missing {$missing} of {$total}.");
  }

  private function activatePlugins(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    // Active les plugins fournis par le package.
    $plugins = $this->getPlugins();

    foreach ($plugins as $folder => $elements) {
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

  private function installPluginFromPath(string $path): bool
  {
    $installer = new Installer();
    $installer->setDatabase(Factory::getContainer()->get('DatabaseDriver'));

    return (bool) $installer->install($path);
  }

  private function ensurePluginsInstalled(?string $source = null, bool $forceUpdate = false): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    $plugins = $this->getPlugins();
    $refreshTotal = 0;
    $refreshIndex = 0;

    if ($forceUpdate) {
      foreach ($plugins as $elements) {
        $refreshTotal += count($elements);
      }
    }

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
        $id = (int) ($row['extension_id'] ?? 0);
        $installedVersion = null;
        if (!empty($row['manifest_cache'])) {
          $cache = json_decode($row['manifest_cache'], true);
          $installedVersion = is_array($cache) ? ($cache['version'] ?? null) : null;
        }

        if ($id > 0) {
          $path = $this->resolvePluginSourcePath($source, $folder, $element);
          if (!is_dir($path)) {
            $this->log("[WARNING] Plugin folder not found: {$path}", Log::WARNING);
            continue;
          }

          if ($forceUpdate) {
            $refreshIndex++;
            $rank = $refreshTotal > 0 ? " ({$refreshIndex}/{$refreshTotal})" : '';
            try {
              $ok = $this->installPluginFromPath($path);
            } catch (\Throwable $e) {
              $this->log("[ERROR] Plugin refresh exception{$rank}: {$folder}/{$element} | " . $e->getMessage(), Log::ERROR);
              continue;
            }

            if ($ok) {
              $this->log("[OK] Plugin refreshed{$rank}: {$folder}/{$element}");
            } else {
              $this->log("[ERROR] Plugin refresh failed{$rank}: {$folder}/{$element}", Log::ERROR);
            }
            continue;
          }

          $manifestVersion = $this->getPluginManifestVersion($path);
          $needsUpdate = false;

          if (!$installedVersion || !$manifestVersion) {
            $needsUpdate = true;
          } elseif (version_compare($installedVersion, $manifestVersion, '<')) {
            $needsUpdate = true;
          } elseif ($installedVersion !== $manifestVersion) {
            // Same or higher but different string, refresh to keep manifest_cache aligned.
            $needsUpdate = true;
          }

          if (!$needsUpdate) {
            $this->log("[INFO] Plugin already installed: {$folder}/{$element} (version {$installedVersion})");
            continue;
          }

          try {
            $ok = $this->installPluginFromPath($path);
          } catch (\Throwable $e) {
            $this->log("[ERROR] Plugin update exception: {$folder}/{$element} | " . $e->getMessage(), Log::ERROR);
            continue;
          }

          if ($ok) {
            $this->log("[OK] Plugin updated: {$folder}/{$element} (version {$installedVersion} -> {$manifestVersion})");
          } else {
            $this->log("[ERROR] Plugin update failed: {$folder}/{$element}", Log::ERROR);
          }
          continue;
        }

        $path = $this->resolvePluginSourcePath($source, $folder, $element);
        if (!is_dir($path)) {
          $this->log("[WARNING] Plugin folder not found: {$path}", Log::WARNING);
          continue;
        }

        try {
          $ok = $this->installPluginFromPath($path);
        } catch (\Throwable $e) {
          $this->log("[ERROR] Plugin install exception: {$folder}/{$element} | " . $e->getMessage(), Log::ERROR);
          continue;
        }

        if ($ok) {
          $this->log("[OK] Plugin installed: {$folder}/{$element}");
        } else {
          $this->log("[ERROR] Plugin install failed: {$folder}/{$element}", Log::ERROR);
        }
      }
    }
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
      if ($candidate && is_dir($candidate)) {
        return $candidate;
      }
    }

    return $candidates[0] ?? (JPATH_ROOT . '/plugins/' . $folder . '/' . $element);
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
        $version = isset($xml->version) ? trim((string) $xml->version) : '';
        if ($version !== '') {
          return $version;
        }
        $attrVersion = isset($xml['version']) ? trim((string) $xml['version']) : '';
        if ($attrVersion !== '') {
          return $attrVersion;
        }
      } catch (\Throwable $e) {
        continue;
      }
    }
    return null;
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

  private function resolveInstallSourcePath($parent): ?string
  {
    $candidates = [];
    $pushCandidate = static function (array &$list, $path): void {
      $path = trim((string) ($path ?? ''));
      if ($path === '') {
        return;
      }

      if (is_file($path)) {
        $path = dirname($path);
      }

      $path = rtrim($path, '/\\');
      if ($path === '') {
        return;
      }

      if (!in_array($path, $list, true)) {
        $list[] = $path;
      }
    };

    if (is_object($parent)) {
      if (method_exists($parent, 'getPath')) {
        $pushCandidate($candidates, $parent->getPath('source'));
        $pushCandidate($candidates, $parent->getPath('manifest'));
      }

      if (method_exists($parent, 'getParent')) {
        $parentInstaller = $parent->getParent();
        if (is_object($parentInstaller) && method_exists($parentInstaller, 'getPath')) {
          $pushCandidate($candidates, $parentInstaller->getPath('source'));
          $pushCandidate($candidates, $parentInstaller->getPath('manifest'));
        }
      }
    }

    $pushCandidate($candidates, __DIR__);

    foreach ($candidates as $candidate) {
      if (is_dir($candidate . '/plugins')) {
        return $candidate;
      }
    }

    foreach ($candidates as $candidate) {
      if (is_dir($candidate) && is_dir($candidate . '/administrator') && is_dir($candidate . '/site')) {
        return $candidate;
      }
    }

    return null;
  }

  private function removeLegacyComponent(string $legacyElement): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    $targetElement = 'com_contentbuilderng';

    $legacyQuery = $db->getQuery(true)
      ->select($db->quoteName('extension_id'))
      ->from($db->quoteName('#__extensions'))
      ->where($db->quoteName('element') . ' = ' . $db->quote($legacyElement))
      ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
      ->where($db->quoteName('client_id') . ' = 1');

    $db->setQuery($legacyQuery);
    $legacyId = (int) $db->loadResult();
    if ($legacyId === 0) {
      $this->log('[INFO] Legacy component not found; nothing to remove.');
      return;
    }

    $targetQuery = $db->getQuery(true)
      ->select($db->quoteName('extension_id'))
      ->from($db->quoteName('#__extensions'))
      ->where($db->quoteName('element') . ' = ' . $db->quote($targetElement))
      ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
      ->where($db->quoteName('client_id') . ' = 1');

    $db->setQuery($targetQuery);
    $targetId = (int) $db->loadResult();
    if ($targetId === 0) {
      $this->log('[WARNING] Legacy component found but target component is missing; skipping removal.', Log::WARNING);
      return;
    }

    $this->log("[INFO] Legacy component {$legacyElement} detected (id {$legacyId}). Cleaning up stale extension row (safe mode, no uninstall hooks).");

    // Keep admin links pointing to NG even when legacy component stays installed but disabled.
    $this->updateMenuLinks($legacyElement, $targetElement);
    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__menu'))
          ->set($db->quoteName('alias') . ' = ' . $db->quote('contentbuilderng'))
          ->set($db->quoteName('path') . ' = ' . $db->quote('contentbuilderng'))
          ->set($db->quoteName('title') . ' = ' . $db->quote('COM_CONTENTBUILDERNG'))
          ->where($db->quoteName('alias') . ' = ' . $db->quote('contentbuilder'))
          ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_contentbuilder_ng%'))
      )->execute();
      $this->log('[OK] Renamed legacy menu entry to contentbuilderng.');
    } catch (\Throwable $e) {
      $message = "[WARNING] Could not rename legacy menu entry: " . $e->getMessage();
      $this->log($message, Log::WARNING);
    }

    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__extensions'))
          ->set($db->quoteName('enabled') . ' = 0')
          ->where($db->quoteName('extension_id') . ' = ' . (int) $legacyId)
      )->execute();
      $this->log("[OK] Legacy component disabled: {$legacyElement} (id {$legacyId}).");
    } catch (\Throwable $e) {
      $this->log("[WARNING] Failed to disable legacy component {$legacyElement}: " . $e->getMessage(), Log::WARNING);
    }

    try {
      $db->setQuery(
        $db->getQuery(true)
          ->update($db->quoteName('#__menu'))
          ->set($db->quoteName('component_id') . ' = ' . (int) $targetId)
          ->where($db->quoteName('component_id') . ' = ' . (int) $legacyId)
      )->execute();
    } catch (\Throwable $e) {
      $this->log("[WARNING] Failed to remap legacy component_id {$legacyId} to {$targetId}: " . $e->getMessage(), Log::WARNING);
    }

    try {
      foreach (['#__schemas', '#__update_sites_extensions'] as $table) {
        $db->setQuery(
          $db->getQuery(true)
            ->delete($db->quoteName($table))
            ->where($db->quoteName('extension_id') . ' = ' . (int) $legacyId)
        )->execute();
      }

      $db->setQuery(
        $db->getQuery(true)
          ->delete($db->quoteName('#__extensions'))
          ->where($db->quoteName('extension_id') . ' = ' . (int) $legacyId)
      )->execute();
      $removed = (int) $db->getAffectedRows();

      if ($removed > 0) {
        $this->log("[OK] Legacy component extension row removed: {$legacyElement} (id {$legacyId}).");
      } else {
        $this->log("[INFO] Legacy component extension row already absent: {$legacyElement} (id {$legacyId}).");
      }
    } catch (\Throwable $e) {
      $this->log("[WARNING] Failed to remove legacy component extension row {$legacyElement} (id {$legacyId}): " . $e->getMessage(), Log::WARNING);
    }

    $this->log("[INFO] Legacy component uninstall intentionally skipped to avoid destructive uninstall SQL/hooks.");
  }

  private function getLegacyContentbuilderPlugins(): array
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $likeLegacy = $db->quote('contentbuilder%');
    $likeNg = $db->quote('contentbuilderng%');
    $folderCond = $db->quoteName('folder') . ' LIKE ' . $likeLegacy . ' AND ' . $db->quoteName('folder') . ' NOT LIKE ' . $likeNg;
    $elementCond = $db->quoteName('element') . ' LIKE ' . $likeLegacy . ' AND ' . $db->quoteName('element') . ' NOT LIKE ' . $likeNg;

    $query = $db->getQuery(true)
      ->select($db->quoteName(['extension_id', 'folder', 'element', 'enabled']))
      ->from($db->quoteName('#__extensions'))
      ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
      ->where("(($folderCond) OR ($elementCond))");

    try {
      $db->setQuery($query);
      return $db->loadAssocList() ?: [];
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed to detect legacy ContentBuilder plugins: ' . $e->getMessage(), Log::WARNING);
      return [];
    }
  }

  private function disableLegacySystemPluginFirst(string $context = 'update'): int
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    $query = $db->getQuery(true)
      ->select($db->quoteName(['extension_id', 'enabled']))
      ->from($db->quoteName('#__extensions'))
      ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
      ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
      ->where($db->quoteName('element') . ' = ' . $db->quote('contentbuilder_system'));

    try {
      $db->setQuery($query);
      $rows = $db->loadAssocList() ?: [];
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed to detect legacy system plugin during ' . $context . ': ' . $e->getMessage(), Log::WARNING);
      return 0;
    }

    if (empty($rows)) {
      $this->log("[INFO] Legacy system plugin not found during {$context}.");
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
      $this->log('[WARNING] Failed disabling legacy system plugin during ' . $context . ': ' . $e->getMessage(), Log::WARNING);
      return 0;
    }

    $disabledNow = max(0, count($ids) - $alreadyDisabled);
    $this->log("[OK] Legacy plugin disabled first: system/contentbuilder_system ({$disabledNow} newly disabled, " . count($ids) . " total) during {$context}.");

    return count($ids);
  }

  private function disableLegacyContentbuilderPlugins(string $context = 'update', bool $excludeSystemPlugin = false): int
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $rows = $this->getLegacyContentbuilderPlugins();

    if (empty($rows)) {
      $this->log("[INFO] No legacy ContentBuilder plugins found during {$context}.");
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
      $this->log('[WARNING] Failed disabling legacy ContentBuilder plugins: ' . $e->getMessage(), Log::WARNING);
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

    if (Folder::exists($path)) {
      if (Folder::delete($path)) {
        $this->log("[OK] Legacy system plugin folder deleted first during {$context}: {$path}");
      } else {
        $this->log("[WARNING] Failed deleting legacy system plugin folder during {$context}: {$path}", Log::WARNING);
      }
      return;
    }

    if (File::exists($path)) {
      if (File::delete($path)) {
        $this->log("[OK] Legacy system plugin file deleted first during {$context}: {$path}");
      } else {
        $this->log("[WARNING] Failed deleting legacy system plugin file during {$context}: {$path}", Log::WARNING);
      }
    }
  }

  private function removeLegacyPlugins(): void
  {
    $disabled = $this->disableLegacyContentbuilderPlugins('postflight');
    if ($disabled > 0) {
      $this->log('[INFO] Legacy plugins are disabled (not uninstalled) to avoid destructive uninstall hooks.');
    }
  }

  private function removeLegacyPluginFolders(): void
  {
    $pluginRoot = JPATH_ROOT . '/plugins';
    if (!Folder::exists($pluginRoot)) {
      $this->log('[INFO] Plugin root folder not found; skipping legacy plugin folder cleanup.');
      return;
    }

    $paths = [];

    // 1) Known legacy plugin folders derived from current NG plugin map.
    foreach ($this->getPlugins() as $folder => $elements) {
      foreach ($elements as $element) {
        [$legacyFolder, $legacyElement] = $this->mapToLegacyPlugin($folder, $element);
        if (!$legacyFolder || !$legacyElement) {
          continue;
        }
        $paths[] = $pluginRoot . '/' . $legacyFolder . '/' . $legacyElement;
      }
    }

    // 2) Catch-all legacy folders (group or element names starting with contentbuilder but not contentbuilderng).
    $groupFolders = Folder::folders($pluginRoot, '.', false, true) ?: [];
    foreach ($groupFolders as $groupPath) {
      $groupName = basename($groupPath);
      $groupLower = strtolower($groupName);

      if (str_starts_with($groupLower, 'contentbuilder') && !str_starts_with($groupLower, 'contentbuilderng')) {
        $paths[] = $groupPath;
        continue;
      }

      if (!in_array($groupLower, ['content', 'system'], true)) {
        continue;
      }

      $elements = Folder::folders($groupPath, '.', false, true) ?: [];
      foreach ($elements as $elementPath) {
        $elementLower = strtolower(basename($elementPath));
        if (str_starts_with($elementLower, 'contentbuilder') && !str_starts_with($elementLower, 'contentbuilderng')) {
          $paths[] = $elementPath;
        }
      }
    }

    $paths = array_values(array_unique(array_map(static fn($path) => rtrim((string) $path, '/\\'), $paths)));
    if (empty($paths)) {
      $this->log('[INFO] No legacy plugin folders detected on filesystem.');
      return;
    }

    // Delete deeper paths first.
    usort($paths, static fn($a, $b) => strlen($b) <=> strlen($a));

    foreach ($paths as $path) {
      if (!Folder::exists($path)) {
        continue;
      }

      if (Folder::delete($path)) {
        $this->log("[OK] Legacy plugin folder deleted: {$path}");
      } else {
        $this->log("[WARNING] Failed deleting legacy plugin folder: {$path}", Log::WARNING);
      }
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

  private function uninstallPluginById(Installer $installer, int $id, string $folder, string $element): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    try {
      $ok = (bool) $installer->uninstall('plugin', $id, 1);
      if ($ok) {
        $this->log("[OK] Legacy plugin uninstalled: {$folder}/{$element} (id {$id}).");
        return;
      }
      $this->log("[WARNING] Legacy plugin uninstall failed: {$folder}/{$element} (id {$id}). Forcing DB cleanup.", Log::WARNING);
    } catch (\Throwable $e) {
      $this->log("[WARNING] Legacy plugin uninstall error for {$folder}/{$element} (id {$id}): " . $e->getMessage(), Log::WARNING);
    }

    try {
      $query = $db->getQuery(true)
        ->delete($db->quoteName('#__extensions'))
        ->where($db->quoteName('extension_id') . ' = ' . (int) $id);
      $db->setQuery($query)->execute();
      $this->log("[OK] Legacy plugin DB entry removed: {$folder}/{$element} (id {$id}).");
    } catch (\Throwable $e) {
      $this->log("[ERROR] Failed to remove legacy plugin DB entry {$folder}/{$element} (id {$id}): " . $e->getMessage(), Log::ERROR);
    }
  }

  private function removeDeprecatedThemePlugins(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $installer = new Installer();
    $installer->setDatabase(Factory::getContainer()->get('DatabaseDriver'));

    $supportedThemes = [
      'blank',
      'joomla6',
      'dark',
      'khepri',
    ];

    $query = $db->getQuery(true)
      ->select([
        $db->quoteName('extension_id'),
        $db->quoteName('element'),
      ])
      ->from($db->quoteName('#__extensions'))
      ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
      ->where($db->quoteName('folder') . ' = ' . $db->quote('contentbuilderng_themes'));
    $db->setQuery($query);
    $installedThemes = $db->loadAssocList();

    foreach ($installedThemes as $plugin) {
      $id = (int) ($plugin['extension_id'] ?? 0);
      $element = (string) ($plugin['element'] ?? '');

      if ($id === 0 || $element === '' || in_array($element, $supportedThemes, true)) {
        continue;
      }

      $this->log("[INFO] Removing unsupported theme plugin contentbuilderng_themes/{$element} (id {$id}).");
      $this->uninstallPluginById($installer, $id, 'contentbuilderng_themes', $element);
    }
  }

  private function normalizeFormThemePlugins(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);

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

    $supportedThemes = $this->getPlugins()['contentbuilderng_themes'] ?? ['joomla6'];
    if (!in_array('joomla6', $supportedThemes, true)) {
      $supportedThemes[] = 'joomla6';
    }

    $migratedLegacy = 0;
    $migratedUnsupported = 0;

    try {
      $query = $db->getQuery(true)
        ->update($db->quoteName('#__contentbuilderng_forms'))
        ->set($db->quoteName('theme_plugin') . ' = ' . $db->quote('joomla6'))
        ->where($db->quoteName('theme_plugin') . ' = ' . $db->quote('joomla3'));
      $db->setQuery($query)->execute();
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
      $storedThemes = $db->loadColumn() ?: [];
      $unsupportedThemes = array_values(array_diff($storedThemes, $supportedThemes));

      if (!empty($unsupportedThemes)) {
        $quotedThemes = array_map(static fn($theme) => $db->quote((string) $theme), $unsupportedThemes);

        $query = $db->getQuery(true)
          ->update($db->quoteName('#__contentbuilderng_forms'))
          ->set($db->quoteName('theme_plugin') . ' = ' . $db->quote('joomla6'))
          ->where($db->quoteName('theme_plugin') . ' IN (' . implode(',', $quotedThemes) . ')');
        $db->setQuery($query)->execute();
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
    $db = Factory::getContainer()->get(DatabaseInterface::class);
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
    $quotedLegacyTypes = array_map(static fn($legacyType) => $db->quote($legacyType), $legacyTypes);

    $tables = [
      '#__contentbuilderng_forms',
      '#__contentbuilderng_records',
      '#__contentbuilderng_articles',
      '#__contentbuilderng_resource_access',
    ];

    $totalUpdated = 0;
    $normalizedTables = 0;

    foreach ($tables as $table) {
      try {
        $columns = $db->getTableColumns($table, false);
        $columns = is_array($columns) ? array_change_key_case($columns, CASE_LOWER) : [];
        if (!array_key_exists('type', $columns)) {
          $this->log("[INFO] Type normalization skipped: {$table}.type column not found.");
          continue;
        }
      } catch (\Throwable $e) {
        $this->log("[WARNING] Could not inspect {$table} columns for type normalization: " . $e->getMessage(), Log::WARNING);
        continue;
      }

      try {
        $query = $db->getQuery(true)
          ->update($db->quoteName($table))
          ->set($db->quoteName('type') . ' = ' . $db->quote($targetType))
          ->where($db->quoteName('type') . ' IN (' . implode(',', $quotedLegacyTypes) . ')');
        $db->setQuery($query)->execute();
        $updated = (int) $db->getAffectedRows();

        if ($updated > 0) {
          $this->log("[OK] Normalized {$updated} legacy type row(s) in {$table}.");
          $totalUpdated += $updated;
        }

        $normalizedTables++;
      } catch (\Throwable $e) {
        $this->log("[WARNING] Failed normalizing legacy types in {$table}: " . $e->getMessage(), Log::WARNING);
      }
    }

    if ($normalizedTables === 0) {
      $this->log('[INFO] Legacy type normalization skipped: no compatible tables found.');
      return;
    }

    if ($totalUpdated === 0) {
      $this->log('[INFO] No legacy ContentBuilder type value needed normalization.');
      return;
    }

    $this->log("[OK] Legacy type normalization completed: {$totalUpdated} row(s) updated.");
  }

  private function ensureFormsNewButtonColumn(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    try {
      $columns = $db->getTableColumns('#__contentbuilderng_forms', false);
      if (!is_array($columns)) {
        return;
      }
      if (array_key_exists('new_button', $columns)) {
        return;
      }
    } catch (\Throwable $e) {
      $this->log('[WARNING] Could not inspect #__contentbuilderng_forms columns for new_button: ' . $e->getMessage(), Log::WARNING);
      return;
    }

    try {
      $db->setQuery(
        'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_forms')
        . ' ADD COLUMN ' . $db->quoteName('new_button')
        . " TINYINT(1) NOT NULL DEFAULT '0'"
      )->execute();
      $this->log('[OK] Added #__contentbuilderng_forms.new_button column.');
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed adding #__contentbuilderng_forms.new_button column: ' . $e->getMessage(), Log::WARNING);
    }
  }

  private function ensureElementsLinkableDefault(): void
  {
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    try {
      $columns = $db->getTableColumns('#__contentbuilderng_elements', false);
      if (!is_array($columns) || !array_key_exists('linkable', $columns)) {
        return;
      }
    } catch (\Throwable $e) {
      $this->log('[WARNING] Could not inspect #__contentbuilderng_elements.linkable column: ' . $e->getMessage(), Log::WARNING);
      return;
    }

    try {
      $db->setQuery(
        'ALTER TABLE ' . $db->quoteName('#__contentbuilderng_elements')
        . ' MODIFY ' . $db->quoteName('linkable')
        . " TINYINT(1) NOT NULL DEFAULT '0'"
      )->execute();
      $this->log('[OK] Ensured #__contentbuilderng_elements.linkable default is 0.');
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed to set #__contentbuilderng_elements.linkable default to 0: ' . $e->getMessage(), Log::WARNING);
    }
  }

  /**
   * Method to run after an install/update/uninstall method
   *
   * @return void
   */
  function postflight($type, $parent)
  {
    $this->resetCriticalFailures();
    $db = Factory::getContainer()->get(DatabaseInterface::class);

    // === LOG POUR DÉBOGAGE ===
    // $this->log('Postflight installation method call, parameter : ' . $type . '.');

    /*
             $db->setQuery("Select id From `#__menu` Where `alias` = 'root'");
             if(!$db->loadResult()){
                 $db->setQuery("INSERT INTO `#__menu` VALUES(1, '', 'Menu_Item_Root', 'root', '', '', '', '', 1, 0, 0, 0, 0, 0, NULL, 0, 0, '', 0, '', 0, ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone ), 0, '*', 0)");
                 $db->execute();
             }*/

    try {
      $db->setQuery("Update #__menu Set `title` = 'COM_CONTENTBUILDERNG' Where `alias`='contentbuilderng'");
      $db->execute();
    } catch (\Throwable $e) {
      $this->log('[WARNING] Failed to normalize admin menu title: ' . $e->getMessage(), Log::WARNING);
    }

    if ($type !== 'uninstall') {
      $context = 'postflight:' . (string) $type;
      $this->disableLegacyPluginsInPriorityOrder($context);
      $this->removeLegacySystemPluginFolderEarly($context);
    }

    $this->removeOldDirectories();
    $this->removeObsoleteFiles();
    $this->ensureMediaListTemplateInstalled();
    $this->ensureUploadDirectoryExists();
    $this->updateDateColumns();
    $this->ensureFormsNewButtonColumn();
    $this->ensureElementsLinkableDefault();
    $this->updateMenuLinks('com_contentbuilder', 'com_contentbuilderng');
    $this->updateMenuLinks('com_contentbuilder_ng', 'com_contentbuilderng');

    $source = $this->resolveInstallSourcePath($parent);
    if ($source && is_dir($source)) {
      $this->log('[INFO] Plugin install source resolved: ' . $source, Log::INFO, false);
    } else {
      $this->log('[WARNING] Plugin install source not resolved; missing plugins may not be installable in this run.', Log::WARNING);
    }
    $this->ensurePluginsInstalled($source, $type === 'update');
    $this->activatePlugins();

    if ($type === 'update') {
      $this->removeDeprecatedThemePlugins();
      $this->normalizeFormThemePlugins();
      $this->normalizeLegacyComponentTypes();
      $this->removeLegacyComponent('com_contentbuilder');
      $this->removeLegacyComponent('com_contentbuilder_ng');
      $this->removeLegacyPlugins();
      $this->removeLegacyPluginFolders();
      $this->deduplicateTargetPluginExtensions();
    }

    $this->deduplicateTargetComponentExtensions();
    $this->normalizeBrokenTargetMenuLinks();
    $this->ensureAdministrationMainMenuEntry();
    $this->ensureSubmenuQuickTasks();
    $this->repairLegacyMenuTitleKeys();

    // On ne fait ça que sur update (et éventuellement discover_install si tu veux)
    if ($type === 'update') {
      $table = $db->quoteName('#__contentbuilderng_storages');

      // Vérifie l’existence de la table
      try {
        $tables = $db->getTableList();
        $expected = $db->getPrefix() . 'contentbuilderng_storages';

        if (in_array($expected, $tables, true)) {
          // Y a-t-il des ordering à 0 ?
          $db->setQuery("SELECT COUNT(*) FROM $table WHERE ordering = 0");
          $needFix = (int) $db->loadResult();

          if ($needFix > 0) {
            // Max ordering existant (si tout est à 0, max = 0)
            $db->setQuery("SELECT COALESCE(MAX(ordering), 0) FROM $table");
            $max = (int) $db->loadResult();

            // IDs à réparer (ordering = 0)
            $db->setQuery("SELECT id FROM $table WHERE ordering = 0 ORDER BY id");
            $ids = $db->loadColumn() ?: [];

            // Mise à jour séquentielle
            $order = $max;
            foreach ($ids as $id) {
              $order++;

              $db->setQuery(
                "UPDATE $table SET ordering = " . (int) $order . " WHERE id = " . (int) $id
              );
              $db->execute();
            }
          }
        }
      } catch (\Throwable $e) {
        $this->log('[ERROR] Failed to normalize storages ordering in postflight: ' . $e->getMessage(), Log::ERROR);
      }
    }
    // try to restore the main menu items if they got lost
    /*
    $db->setQuery("Select component_id From #__menu Where `link`='index.php?option=com_contentbuilder_ng' And parent_id = 1");
    $result = $db->loadResult();

    if(!$result) {
        
        $db->setQuery("Select extension_id From #__extensions Where `type` = 'component' And `element` = 'com_contentbuilder_ng'");
        $comp_id = $db->loadResult();
        
        if($comp_id){
            $db->setQuery("INSERT INTO `#__menu` (`menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, `home`, `language`, `client_id`) VALUES ('main', 'com_contentbuilder_ng', 'contentbuilderng', '', 'contentbuilderng', 'index.php?option=com_contentbuilder_ng', 'component', 0, 1, 1, ".$comp_id.", 0, NULL, 0, 1, 'media/com_contentbuilder_ng/images/logo_icon_cb.png', 0, '', ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone ),( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__menu As mrgt) As filet ), 0, '', 1)");
            $db->execute();
            $parent_id = $db->insertid();

            $db->setQuery("INSERT INTO `#__menu` (`menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, `home`, `language`, `client_id`) VALUES ('main', 'COM_CONTENTBUILDERNG_STORAGES', 'comcontentbuilderstorages', '', 'contentbuilderng/comcontentbuilderstorages', 'index.php?option=com_contentbuilder&task=storages.display', 'component', 0, ".$parent_id.", 2, ".$comp_id.", 0, NULL, 0, 1, 'media/com_contentbuilder_ng/images/logo_icon_cb.png', 0, '', ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone ),( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__menu As mrgt) As filet ), 0, '', 1)");
            $db->execute();

            $db->setQuery("INSERT INTO `#__menu` (`menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, `home`, `language`, `client_id`) VALUES('main', 'COM_CONTENTBUILDERNG_LIST', 'comcontentbuilderlist', '', 'contentbuilderng/comcontentbuilderlist', 'index.php?option=com_contentbuilder&task=forms.display', 'component', 0, ".$parent_id.", 2, ".$comp_id.", 0, NULL, 0, 1, 'media/com_contentbuilder_ng/images/logo_icon_cb.png', 0, '', ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone ),( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__menu As mrgt) As filet ), 0, '', 1)");
            $db->execute();

            $db->setQuery("INSERT INTO `#__menu` (`menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, `home`, `language`, `client_id`) VALUES('main', 'Try BreezingForms!', 'try-breezingforms', '', 'contentbuilderng/try-breezingforms', 'index.php?option=com_contentbuilder&view=contentbuilder&market=true', 'component', 0, ".$parent_id.", 2, ".$comp_id.", 0, NULL, 0, 1, 'class:component', 0, '', ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone ),( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__menu As mrgt) As filet ), 0, '', 1)");
            $db->execute();

            $db->setQuery("INSERT INTO `#__menu` (`menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, `home`, `language`, `client_id`) VALUES('main', 'COM_CONTENTBUILDERNG_ABOUT', 'comcontentbuilderabout', '', 'contentbuilderng/comcontentbuilderabout', 'index.php?option=com_contentbuilder&view=contentbuilderng', 'component', 0, ".$parent_id.", 2, ".$comp_id.", 0, NULL, 0, 1, 'class:component', 0, '', ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone ),( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__menu As mrgt) As filet ), 0, '', 1)");
            $db->execute();

            $db->setQuery("Select max(mrgt.rgt)+1 From #__menu As mrgt");
            $rgt = $db->loadResult();

            $db->setQuery("Update `#__menu` Set rgt = ".$rgt." Where `title` = 'Menu_Item_Root' And `alias` = 'root'");
            $db->execute();
        }
    }*/

    if ($this->hasCriticalFailure()) {
      $summary = $this->getCriticalFailureSummary();
      $this->log('[ERROR] Postflight completed with critical failures: ' . $summary, Log::ERROR);
      throw new \RuntimeException('ContentBuilder NG postflight failed: ' . $summary);
    }

    $finishedAt = Factory::getDate('now', $this->resolveJoomlaTimezoneName())->format('Y-m-d H:i:s T');
    $durationSeconds = max(0.0, microtime(true) - $this->installStartedAt);
    $this->log(
      '[OK] ContentBuilder NG installation finished. '
      . $finishedAt
      . '. Duration: '
      . number_format($durationSeconds, 2, '.', '')
      . 's.'
    );
  }
}
