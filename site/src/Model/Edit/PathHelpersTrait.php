<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Site\Model\Edit;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\Filesystem\Folder;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;
use CB\Component\Contentbuilderng\Administrator\Service\PathService;

/**
 * Storage-path normalization and token-substitution helpers extracted from
 * EditModel, used to resolve upload destination folders safely.
 */
trait PathHelpersTrait
{
    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        return preg_replace('#/+#', '/', $path) ?? $path;
    }

    private function toSafePathToken(mixed $value): string
    {
        if (is_array($value)) {
            $value = array_values(array_filter($value, static fn($v) => $v !== null && $v !== '' && $v !== 'cbGroupMark'));
            $value = implode('/', array_map(static fn($v) => (string) $v, $value));
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '_empty_';
        }

        $value = preg_replace('#[^A-Za-z0-9._/\-]#', '_', $value) ?? $value;
        return $value === '' ? '_empty_' : $value;
    }

    private function isSafeStoragePath(string $path): bool
    {
        $siteRoot = realpath(JPATH_SITE);
        if ($siteRoot === false) {
            return false;
        }

        $siteRoot = rtrim($this->normalizePath($siteRoot), '/');
        $realPath = realpath($path);
        if ($realPath === false) {
            return false;
        }

        $realPath = $this->normalizePath($realPath);
        return strncasecmp($realPath, $siteRoot . '/', strlen($siteRoot) + 1) === 0 || strcasecmp($realPath, $siteRoot) === 0;
    }

    private function createPathByTokens(string $path, array $names): string
    {
        if (trim($path) === '') {
            return '';
        }

        $path = str_replace('|', '/', $path);
        $path = str_replace(array('{CBSite}', '{cbsite}'), JPATH_SITE, $path);

        foreach ($names as $id => $name) {
            $value = $this->app->getInput()->post->get('cb_' . $id, '', 'raw');
            $value = $this->toSafePathToken($value);
            $path = str_replace('{' . strtolower($name) . ':value}', $value, $path);
        }

        $path = str_replace('{userid}', (string) (int) ($this->app->getIdentity()->id ?? 0), $path);
        $path = str_replace('{username}', $this->toSafePathToken((string) ($this->app->getIdentity()->username ?? 'anonymous') . '_' . (int) ($this->app->getIdentity()->id ?? 0)), $path);
        $path = str_replace('{name}', $this->toSafePathToken((string) ($this->app->getIdentity()->name ?? 'Anonymous') . '_' . (int) ($this->app->getIdentity()->id ?? 0)), $path);

        $_now = Factory::getDate();

        $path = str_replace('{date}', $_now->format('Y-m-d'), $path);
        $path = str_replace('{time}', $_now->format('His'), $path);
        $path = str_replace('{datetime}', $_now->format('Y-m-d_His'), $path);

        $endpath = (new PathService())->makeSafeFolder($path);
        $endpath = $this->normalizePath($endpath);

        $isAbsolute = strpos($endpath, '/') === 0 || (bool) preg_match('#^[A-Za-z]:/#', $endpath);
        if (!$isAbsolute) {
            $endpath = rtrim($this->normalizePath(JPATH_SITE), '/') . '/' . ltrim($endpath, '/');
        }

        if (!is_dir($endpath) && !Folder::create($endpath)) {
            return '';
        }

        if (!$this->isSafeStoragePath($endpath) || !ContentbuilderngHelper::is_internal_path($endpath)) {
            return '';
        }

        $real = realpath($endpath);
        return $real === false ? '' : $this->normalizePath($real);
    }
}
