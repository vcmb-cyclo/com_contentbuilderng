<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

final class MigrationService
{
    public function __construct(
        private readonly \Closure $dbProvider,
        private readonly \Closure $logger,
        private readonly \Closure $safeRunner,
        private readonly array $legacyTableRenames,
    ) {
    }

    public function buildMenuLinkOptionWhereClauses(string $option): array
    {
        $db = $this->db();
        $param = 'option=' . $option;

        return [
            $db->quoteName('link') . ' = ' . $db->quote('index.php?' . $param),
            $db->quoteName('link') . ' LIKE ' . $db->quote('index.php?' . $param . '&%'),
            $db->quoteName('link') . ' LIKE ' . $db->quote('%&' . $param),
            $db->quoteName('link') . ' LIKE ' . $db->quote('%&' . $param . '&%'),
        ];
    }

    public function reportLegacyTableCollisions(): void
    {
        try {
            $db = $this->db();
            $prefix = $db->getPrefix();
            $existing = $db->getTableList();

            $collisionCount = 0;

            foreach ($this->legacyTableRenames as $legacy => $target) {
                $legacyFull = $prefix . $legacy;
                $targetFull = $prefix . $target;

                if (!in_array($legacyFull, $existing, true) || !in_array($targetFull, $existing, true)) {
                    continue;
                }

                $legacyHasRows = false;
                $targetHasRows = false;

                try {
                    $db->setQuery(
                        $db->getQuery(true)->select('1')->from($db->quoteName($legacyFull))->setLimit(1)
                    );
                    $legacyHasRows = (bool) $db->loadResult();
                    $db->setQuery(
                        $db->getQuery(true)->select('1')->from($db->quoteName($targetFull))->setLimit(1)
                    );
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

    public function renameLegacyTables(): void
    {
        $db = $this->db();
        $prefix = $db->getPrefix();
        $existing = $this->safe(fn() => $db->getTableList(), []);
        $renamed = 0;
        $skipped = 0;
        $missing = 0;

        foreach ($this->legacyTableRenames as $legacy => $target) {
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
                    $db->setQuery(
                        $db->getQuery(true)->select('1')->from($db->quoteName($targetFull))->setLimit(1)
                    );
                    $targetHasRows = (bool) $db->loadResult();
                    $db->setQuery(
                        $db->getQuery(true)->select('1')->from($db->quoteName($legacyFull))->setLimit(1)
                    );
                    $legacyHasRows = (bool) $db->loadResult();
                } catch (\Throwable $e) {
                    $this->log("[WARNING] Could not inspect {$targetFull}/{$legacyFull}: " . $e->getMessage(), Log::WARNING);
                    $skipped++;
                    continue;
                }

                if (!$legacyHasRows) {
                    try {
                        $db->setQuery('DROP TABLE ' . $db->quoteName($legacyFull));
                        $db->execute();
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
                    $this->log("[WARNING] Legacy table {$legacyFull} detected but {$targetFull} already contains data, skipping rename.", Log::WARNING);
                    $skipped++;
                    continue;
                }

                try {
                    $db->setQuery('DROP TABLE ' . $db->quoteName($targetFull));
                    $db->execute();
                    $this->log("[WARNING] Target table {$targetFull} existed but was empty; it has been replaced by legacy table {$legacyFull}.", Log::WARNING);
                    $existing = array_values(array_filter($existing, static fn($name) => $name !== $targetFull));
                } catch (\Throwable $e) {
                    $this->log("[WARNING] Failed dropping empty target table {$targetFull}: " . $e->getMessage(), Log::WARNING);
                    $skipped++;
                    continue;
                }
            }

            try {
                $db->setQuery('RENAME TABLE ' . $db->quoteName($legacyFull) . ' TO ' . $db->quoteName($targetFull));
                $db->execute();
                $this->log("[OK] Renamed table {$legacyFull} to {$targetFull}.");
                $renamed++;
                $existing[] = $targetFull;
                $existing = array_values(array_filter($existing, static fn($name) => $name !== $legacyFull));
            } catch (\Throwable $e) {
                $this->log("[WARNING] Failed to rename table {$legacyFull}: " . $e->getMessage(), Log::WARNING);
            }
        }

        $total = count($this->legacyTableRenames);
        $this->log("[OK] Table migration summary: renamed {$renamed}, skipped {$skipped}, missing {$missing} of {$total}.");
    }

    public function updateMenuLinks(string $legacyElement, string $targetElement): void
    {
        $db = $this->db();
        $conditions = $this->buildMenuLinkOptionWhereClauses($legacyElement);

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__menu'))
                    ->set(
                        $db->quoteName('link') . ' = REPLACE(' . $db->quoteName('link') . ', '
                        . $db->quote('option=' . $legacyElement) . ', ' . $db->quote('option=' . $targetElement) . ')'
                    )
                    ->where('(' . implode(' OR ', $conditions) . ')')
            );
            $db->execute();
            $this->log("[OK] Updated menu links to point to {$targetElement}.");
        } catch (\Throwable $e) {
            $this->log("[WARNING] Could not update menu links for legacy option {$legacyElement}: " . $e->getMessage(), Log::WARNING);
        }
    }

    public function normalizeBrokenTargetMenuLinks(): void
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
                );
                $db->execute();

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

    public function repairLegacyMenuTitleKeys(): void
    {
        $db = $this->db();

        $rows = $this->safe(function () use ($db) {
            $query = $db->getQuery(true)
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
            $db->setQuery($query);

            return $db->loadAssocList() ?: [];
        }, []);

        if ($rows === []) {
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
                );
                $db->execute();

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
}
