<?php

/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper\Audit;

\defined('_JEXEC') or die('Restricted access');

use Joomla\Filesystem\File;

/**
 * Detects and removes stale ContentBuilder language files left in the global
 * Joomla language directories (administrator/language/ and language/) by
 * previous installations that used a different naming convention or component name.
 */
final class StaleLanguageFilesAuditHelper
{
    /**
     * Scan global language directories for any *contentbuilder* files.
     *
     * @return array{0: array<int, array{path: string, file: string, lang_tag: string, scope: string}>, 1: array<int, string>}
     */
    public static function inspect(): array
    {
        $basePaths = [
            'admin' => JPATH_ADMINISTRATOR . '/language',
            'site'  => JPATH_ROOT . '/language',
        ];

        $found  = [];
        $errors = [];

        foreach ($basePaths as $scope => $basePath) {
            if (!is_dir($basePath)) {
                continue;
            }

            try {
                $langDirs = glob($basePath . '/*', GLOB_ONLYDIR) ?: [];
            } catch (\Throwable $e) {
                $errors[] = 'Failed to scan ' . $basePath . ': ' . $e->getMessage();
                continue;
            }

            foreach ($langDirs as $langDir) {
                $langTag = basename($langDir);

                try {
                    $matches = glob($langDir . '/*contentbuilder*') ?: [];
                } catch (\Throwable $e) {
                    $errors[] = 'Failed to scan ' . $langDir . ': ' . $e->getMessage();
                    continue;
                }

                foreach ($matches as $match) {
                    if (!is_file($match)) {
                        continue;
                    }

                    $found[] = [
                        'path'     => $match,
                        'file'     => basename($match),
                        'lang_tag' => $langTag,
                        'scope'    => $scope,
                    ];
                }
            }
        }

        return [$found, $errors];
    }

    /**
     * Delete all stale ContentBuilder language files found by inspect().
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

            if ($path === '' || !is_file($path)) {
                $unchanged++;
                continue;
            }

            try {
                if (File::delete($path)) {
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
}
