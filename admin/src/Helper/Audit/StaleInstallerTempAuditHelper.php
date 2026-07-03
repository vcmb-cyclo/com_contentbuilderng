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

namespace CB\Component\Contentbuilderng\Administrator\Helper\Audit;

\defined('_JEXEC') or die('Restricted access');

use Joomla\Filesystem\Folder;

/**
 * Detects and removes stale Joomla installer temporary directories
 * (tmp/install_*) left behind by interrupted or failed extension
 * installations. Directories modified recently are left untouched so a
 * concurrent installation is never disturbed.
 */
final class StaleInstallerTempAuditHelper
{
    /**
     * Directories younger than this many seconds are considered in use.
     */
    private const MIN_AGE_SECONDS = 3600;

    /**
     * Scan the Joomla installer temp roots for stale install_* directories.
     *
     * @return array{0: array<int, array{path: string, name: string, root: string, modified: string}>, 1: array<int, string>}
     */
    public static function inspect(): array
    {
        $found  = [];
        $errors = [];
        $cutoff = time() - self::MIN_AGE_SECONDS;

        foreach (self::getInstallerTempRoots() as $tmpRoot) {
            try {
                $installDirs = glob($tmpRoot . '/install_*', GLOB_ONLYDIR) ?: [];
            } catch (\Throwable $e) {
                $errors[] = 'Failed to scan ' . $tmpRoot . ': ' . $e->getMessage();
                continue;
            }

            foreach ($installDirs as $installDir) {
                $realInstallDir = realpath($installDir);

                if (
                    $realInstallDir === false
                    || !is_dir($realInstallDir)
                    || \dirname($realInstallDir) !== $tmpRoot
                    || !str_starts_with(basename($realInstallDir), 'install_')
                ) {
                    continue;
                }

                $modified = @filemtime($realInstallDir) ?: 0;

                if ($modified > $cutoff) {
                    continue;
                }

                $found[] = [
                    'path'     => $realInstallDir,
                    'name'     => basename($realInstallDir),
                    'root'     => $tmpRoot,
                    'modified' => $modified > 0 ? date('Y-m-d H:i:s', $modified) : '',
                ];
            }
        }

        return [$found, $errors];
    }

    /**
     * Delete all stale installer temporary directories found by inspect().
     *
     * @return array{scanned: int, issues: int, repaired: int, unchanged: int, errors: int, removed: array<string>, warnings: array<string>}
     */
    public static function repair(): array
    {
        [$found, $inspectErrors] = self::inspect();

        $scanned    = count($found);
        $repaired   = 0;
        $unchanged  = 0;
        $errorCount = count($inspectErrors);
        $removed    = [];
        $warnings   = $inspectErrors;

        foreach ($found as $entry) {
            $path = (string) ($entry['path'] ?? '');

            if ($path === '' || !is_dir($path)) {
                $unchanged++;
                continue;
            }

            try {
                if (Folder::delete($path)) {
                    $removed[] = $path;
                    $repaired++;
                } else {
                    $warnings[] = 'Could not delete: ' . $path;
                    $unchanged++;
                    $errorCount++;
                }
            } catch (\Throwable $e) {
                $warnings[] = 'Error deleting ' . $path . ': ' . $e->getMessage();
                $unchanged++;
                $errorCount++;
            }
        }

        return [
            'scanned'   => $scanned,
            'issues'    => $scanned,
            'repaired'  => $repaired,
            'unchanged' => $unchanged,
            'errors'    => $errorCount,
            'removed'   => $removed,
            'warnings'  => $warnings,
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function getInstallerTempRoots(): array
    {
        $roots = [];

        foreach ([JPATH_ROOT . '/tmp', sys_get_temp_dir()] as $path) {
            $realPath = realpath($path);

            if ($realPath !== false && is_dir($realPath) && !in_array($realPath, $roots, true)) {
                $roots[] = $realPath;
            }
        }

        return $roots;
    }
}
