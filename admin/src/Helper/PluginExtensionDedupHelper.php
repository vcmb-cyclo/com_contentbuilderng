<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper;

\defined('_JEXEC') or die('Restricted access');

use Joomla\Database\DatabaseInterface;

final class PluginExtensionDedupHelper
{
    /**
     * @return array{
     *   scanned:int,
     *   groups_count:int,
     *   rows_to_remove:int,
     *   groups:array<int,array{
     *     canonical_folder:string,
     *     canonical_element:string,
     *     keep_id:int,
     *     duplicate_ids:array<int,int>,
     *     rows:array<int,array{
     *       extension_id:int,
     *       folder:string,
     *       element:string,
     *       enabled:int,
     *       is_canonical:int
     *     }>
     *   }>,
     *   warnings:array<int,string>
     * }
     */
    public static function audit(DatabaseInterface $db): array
    {
        $summary = [
            'scanned' => 0,
            'groups_count' => 0,
            'rows_to_remove' => 0,
            'groups' => [],
            'warnings' => [],
        ];

        try {
            $rows = self::loadPluginRows($db);
        } catch (\Throwable $e) {
            $summary['warnings'][] = 'Could not inspect plugin rows for duplicate detection: ' . $e->getMessage();
            return $summary;
        }

        $summary['scanned'] = count($rows);
        $groups = self::buildGroups($rows);
        $summary['groups'] = $groups;
        $summary['groups_count'] = count($groups);

        $rowsToRemove = 0;
        foreach ($groups as $group) {
            $rowsToRemove += count((array) ($group['duplicate_ids'] ?? []));
        }

        $summary['rows_to_remove'] = $rowsToRemove;

        return $summary;
    }

    /**
     * @return array{
     *   scanned:int,
     *   issues:int,
     *   repaired:int,
     *   unchanged:int,
     *   errors:int,
     *   rows_removed:int,
     *   groups:array<int,array{
     *     canonical_folder:string,
     *     canonical_element:string,
     *     keep_id:int,
     *     removed_ids:array<int,int>,
     *     status:string,
     *     error:string
     *   }>,
     *   warnings:array<int,string>
     * }
     */
    public static function repair(DatabaseInterface $db): array
    {
        $audit = self::audit($db);

        $summary = [
            'scanned' => (int) ($audit['groups_count'] ?? 0),
            'issues' => (int) ($audit['groups_count'] ?? 0),
            'repaired' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'rows_removed' => 0,
            'groups' => [],
            'warnings' => (array) ($audit['warnings'] ?? []),
        ];

        $groups = (array) ($audit['groups'] ?? []);
        if ($groups === []) {
            return $summary;
        }

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $canonicalFolder = (string) ($group['canonical_folder'] ?? '');
            $canonicalElement = (string) ($group['canonical_element'] ?? '');
            $keepId = (int) ($group['keep_id'] ?? 0);
            $duplicateIds = array_values(array_unique(array_map(
                static fn($id): int => (int) $id,
                (array) ($group['duplicate_ids'] ?? [])
            )));
            $duplicateIds = array_values(array_filter($duplicateIds, static fn(int $id): bool => $id > 0));

            if ($keepId <= 0 || $duplicateIds === []) {
                $summary['unchanged']++;
                $summary['groups'][] = [
                    'canonical_folder' => $canonicalFolder,
                    'canonical_element' => $canonicalElement,
                    'keep_id' => $keepId,
                    'removed_ids' => [],
                    'status' => 'unchanged',
                    'error' => '',
                ];
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
                            ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $duplicateIds) . ')')
                    )->execute();
                }

                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__extensions'))
                        ->where($db->quoteName('extension_id') . ' IN (' . implode(',', $duplicateIds) . ')')
                )->execute();

                $removed = (int) $db->getAffectedRows();
                $summary['rows_removed'] += $removed;
                $summary['repaired']++;
                $summary['groups'][] = [
                    'canonical_folder' => $canonicalFolder,
                    'canonical_element' => $canonicalElement,
                    'keep_id' => $keepId,
                    'removed_ids' => $duplicateIds,
                    'status' => 'repaired',
                    'error' => '',
                ];
            } catch (\Throwable $e) {
                $summary['errors']++;
                $summary['groups'][] = [
                    'canonical_folder' => $canonicalFolder,
                    'canonical_element' => $canonicalElement,
                    'keep_id' => $keepId,
                    'removed_ids' => $duplicateIds,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $summary;
    }

    /**
     * @return array<int,array{
     *   extension_id:int,
     *   folder:string,
     *   element:string,
     *   enabled:int,
     *   manifest_cache:string
     * }>
     */
    private static function loadPluginRows(DatabaseInterface $db): array
    {
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

        $rows = $db->loadAssocList() ?: [];
        $normalized = [];
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

            $normalized[] = [
                'extension_id' => $id,
                'folder' => $folder,
                'element' => $element,
                'enabled' => (int) ($row['enabled'] ?? 0),
                'manifest_cache' => (string) ($row['manifest_cache'] ?? ''),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int,array{
     *   extension_id:int,
     *   folder:string,
     *   element:string,
     *   enabled:int,
     *   manifest_cache:string
     * }> $rows
     * @return array<int,array{
     *   canonical_folder:string,
     *   canonical_element:string,
     *   keep_id:int,
     *   duplicate_ids:array<int,int>,
     *   rows:array<int,array{
     *     extension_id:int,
     *     folder:string,
     *     element:string,
     *     enabled:int,
     *     is_canonical:int
     *   }>
     * }>
     */
    private static function buildGroups(array $rows): array
    {
        $bucket = [];

        foreach ($rows as $row) {
            $folder = (string) ($row['folder'] ?? '');
            $element = (string) ($row['element'] ?? '');
            if ($folder === '' || $element === '') {
                continue;
            }

            $canonicalFolder = self::canonicalizeFolder($folder);
            $canonicalElement = self::canonicalizeElement($element);
            $key = $canonicalFolder . '/' . $canonicalElement;

            $row['canonical_folder'] = $canonicalFolder;
            $row['canonical_element'] = $canonicalElement;
            $bucket[$key][] = $row;
        }

        $groups = [];
        foreach ($bucket as $key => $groupRows) {
            if (!is_array($groupRows) || count($groupRows) < 2) {
                continue;
            }

            [$canonicalFolder, $canonicalElement] = explode('/', (string) $key, 2);
            $keepId = self::pickKeepId($groupRows, $canonicalFolder, $canonicalElement);
            if ($keepId <= 0) {
                continue;
            }

            $rowsPayload = [];
            $duplicateIds = [];
            foreach ($groupRows as $groupRow) {
                $id = (int) ($groupRow['extension_id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $folder = (string) ($groupRow['folder'] ?? '');
                $element = (string) ($groupRow['element'] ?? '');
                $isCanonical = ($folder === $canonicalFolder && $element === $canonicalElement) ? 1 : 0;

                $rowsPayload[] = [
                    'extension_id' => $id,
                    'folder' => $folder,
                    'element' => $element,
                    'enabled' => (int) ($groupRow['enabled'] ?? 0),
                    'is_canonical' => $isCanonical,
                ];

                if ($id !== $keepId) {
                    $duplicateIds[] = $id;
                }
            }

            usort(
                $rowsPayload,
                static function (array $a, array $b): int {
                    $aCanonical = (int) ($a['is_canonical'] ?? 0);
                    $bCanonical = (int) ($b['is_canonical'] ?? 0);
                    if ($aCanonical !== $bCanonical) {
                        return $bCanonical <=> $aCanonical;
                    }

                    $aEnabled = (int) ($a['enabled'] ?? 0);
                    $bEnabled = (int) ($b['enabled'] ?? 0);
                    if ($aEnabled !== $bEnabled) {
                        return $bEnabled <=> $aEnabled;
                    }

                    return (int) ($b['extension_id'] ?? 0) <=> (int) ($a['extension_id'] ?? 0);
                }
            );

            if ($duplicateIds === []) {
                continue;
            }

            $groups[] = [
                'canonical_folder' => $canonicalFolder,
                'canonical_element' => $canonicalElement,
                'keep_id' => $keepId,
                'duplicate_ids' => array_values(array_unique($duplicateIds)),
                'rows' => $rowsPayload,
            ];
        }

        usort(
            $groups,
            static fn(array $a, array $b): int => strcmp(
                (string) (($a['canonical_folder'] ?? '') . '/' . ($a['canonical_element'] ?? '')),
                (string) (($b['canonical_folder'] ?? '') . '/' . ($b['canonical_element'] ?? ''))
            )
        );

        return $groups;
    }

    /**
     * @param array<int,array{
     *   extension_id:int,
     *   folder:string,
     *   element:string,
     *   enabled:int,
     *   manifest_cache:string
     * }> $groupRows
     */
    private static function pickKeepId(array $groupRows, string $canonicalFolder, string $canonicalElement): int
    {
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

            $folder = (string) ($row['folder'] ?? '');
            $element = (string) ($row['element'] ?? '');
            $isCanonical = ($folder === $canonicalFolder && $element === $canonicalElement) ? 1 : 0;
            $enabled = (int) ($row['enabled'] ?? 0);
            $hasManifest = trim((string) ($row['manifest_cache'] ?? '')) !== '' ? 1 : 0;

            $better = false;
            if ($keepId === 0) {
                $better = true;
            } elseif ($isCanonical > $bestCanonical) {
                $better = true;
            } elseif ($isCanonical === $bestCanonical && $enabled > $bestEnabled) {
                $better = true;
            } elseif ($isCanonical === $bestCanonical && $enabled === $bestEnabled && $hasManifest > $bestManifest) {
                $better = true;
            } elseif ($isCanonical === $bestCanonical && $enabled === $bestEnabled && $hasManifest === $bestManifest && $id > $bestId) {
                $better = true;
            }

            if ($better) {
                $keepId = $id;
                $bestCanonical = $isCanonical;
                $bestEnabled = $enabled;
                $bestManifest = $hasManifest;
                $bestId = $id;
            }
        }

        return $keepId;
    }

    private static function canonicalizeFolder(string $folder): string
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

    private static function canonicalizeElement(string $element): string
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
}
