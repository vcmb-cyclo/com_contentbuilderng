<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Site\Service;

\defined('_JEXEC') or die;

final class ExportFilenameService
{
    public const MODE_DEFAULT = 'default';
    public const MODE_CUSTOM = 'custom';

    public static function build(
        string $defaultTitle,
        string $mode,
        string $customTitle,
        string $timestamp
    ): string {
        $timestamp = preg_replace('/_(\d{2})-(\d{2})$/', '_$1$2', $timestamp) ?? $timestamp;
        $defaultFilename = self::buildDefault($defaultTitle, $timestamp);
        if ($mode !== self::MODE_CUSTOM) {
            return $defaultFilename;
        }

        $customBase = self::sanitizeCustomBase($customTitle);
        if ($customBase === '') {
            return $defaultFilename;
        }

        return $customBase . '_' . $timestamp . '.xlsx';
    }

    private static function buildDefault(string $title, string $timestamp): string
    {
        if ($title === '') {
            $title = 'Export';
        }

        $safeTitle = preg_replace('/[^\pL\pN _.-]+/u', '_', $title);
        $safeTitle = trim((string) preg_replace('/\s+/u', ' ', (string) $safeTitle));
        if ($safeTitle === '') {
            $safeTitle = 'Export';
        }

        return 'CB_export_' . $safeTitle . '_' . $timestamp . '.xlsx';
    }

    private static function sanitizeCustomBase(string $title): string
    {
        $title = preg_replace('/[\p{Cc}\p{Cf}]+/u', '', trim($title)) ?? '';
        $title = preg_replace('/[^\pL\pN_-]+/u', '_', $title) ?? '';
        $title = trim((string) preg_replace('/_+/u', '_', $title), '_-');

        return function_exists('mb_substr')
            ? (string) mb_substr($title, 0, 120, 'UTF-8')
            : (string) substr($title, 0, 120);
    }
}
