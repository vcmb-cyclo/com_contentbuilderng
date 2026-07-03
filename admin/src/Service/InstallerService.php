<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
*/

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Registry\Registry;

final class InstallerService
{
    public function __construct(
        private readonly \Closure $dbProvider,
        private readonly \Closure $logger,
        private readonly \Closure $safeRunner,
        private readonly \Closure $cachePurger,
    ) {
    }

    public function ensureUploadDirectoryExists(): void
    {
        $uploadDir = JPATH_ROOT . '/media/com_contentbuilderng/upload';
        $parentDir = \dirname($uploadDir);

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

    public function ensureMediaListTemplateInstalled(): void
    {
        $source = JPATH_SITE . '/components/com_contentbuilderng/tmpl/list/default.php';
        $target = JPATH_ROOT . '/media/com_contentbuilderng/images/list/tmpl/default.php';
        $targetDir = \dirname($target);

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

    public function removeOldDirectories(): void
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

    public function removeObsoleteFiles(): void
    {
        $paths = [
            JPATH_ADMINISTRATOR . '/components/contentbuilder/classes/PHPExcel',
            JPATH_ADMINISTRATOR . '/components/com_contentbuilder/classes/PHPExcel',
            JPATH_ADMINISTRATOR . '/components/com_contentbuilder/classes/PHPExcel.php',
            JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/contentbuilderng.php',
            JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/controller.php',
            JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/src/contentbuilderng_classes.php',
            JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/src/controller.php',
            JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/src/View/Verify/view.raw.php',
            JPATH_SITE . '/components/com_contentbuilderng/contentbuilderng.php',
            JPATH_SITE . '/components/com_contentbuilderng/controller.php',
            JPATH_SITE . '/components/com_contentbuilderng/router.php',
            JPATH_SITE . '/components/com_contentbuilderng/src/Table/list.php',
            // Legacy plugin root files replaced by src/Extension/ + services/provider.php
            JPATH_PLUGINS . '/contentbuilderng_themes/blank/blank.php',
            JPATH_PLUGINS . '/contentbuilderng_themes/dark/dark.php',
            JPATH_PLUGINS . '/contentbuilderng_themes/thoth/thoth.php',
            JPATH_PLUGINS . '/contentbuilderng_themes/khepri/khepri.php',
            JPATH_PLUGINS . '/contentbuilderng_validation/email/email.php',
            JPATH_PLUGINS . '/contentbuilderng_validation/notempty/notempty.php',
            JPATH_PLUGINS . '/contentbuilderng_validation/equal/equal.php',
            JPATH_PLUGINS . '/contentbuilderng_validation/date_is_valid/date_is_valid.php',
            JPATH_PLUGINS . '/contentbuilderng_validation/date_not_before/date_not_before.php',
            JPATH_PLUGINS . '/contentbuilderng_listaction/trash/trash.php',
            JPATH_PLUGINS . '/contentbuilderng_listaction/untrash/untrash.php',
            JPATH_PLUGINS . '/contentbuilderng_submit/submit_sample/submit_sample.php',
            JPATH_PLUGINS . '/contentbuilderng_verify/passthrough/passthrough.php',
            JPATH_PLUGINS . '/contentbuilderng_verify/paypal/paypal.php',
            JPATH_PLUGINS . '/content/contentbuilderng_download/contentbuilderng_download.php',
            JPATH_PLUGINS . '/content/contentbuilderng_image_scale/contentbuilderng_image_scale.php',
            JPATH_PLUGINS . '/content/contentbuilderng_permission_observer/contentbuilderng_permission_observer.php',
            JPATH_PLUGINS . '/content/contentbuilderng_rating/contentbuilderng_rating.php',
            JPATH_PLUGINS . '/content/contentbuilderng_stats/contentbuilderng_stats.php',
            JPATH_PLUGINS . '/content/contentbuilderng_verify/contentbuilderng_verify.php',
            JPATH_PLUGINS . '/system/contentbuilderng_system/contentbuilderng_system.php',
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

        // Remove the legacy `languages/` (plural) folder — an old naming convention that shadows
        // the current `language/` (singular) folder. Done in postflight so the new language/
        // folder is already in place before the old one is deleted.
        $staleDir = JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/languages';

        if (is_dir($staleDir)) {
            try {
                if (Folder::delete($staleDir)) {
                    $this->log('[OK] Removed stale component language directory (languages/ → language/).');
                } else {
                    $this->log('[WARNING] Could not remove stale component language directory: ' . $staleDir, Log::WARNING);
                }
            } catch (\Throwable $e) {
                $this->log('[WARNING] Error removing stale component language directory: ' . $e->getMessage(), Log::WARNING);
            }
        }
    }

    /**
     * Purge ALL *contentbuilder* language files before Joomla copies the new package files.
     * Called from preflight so stale files (wrong name format, wrong language, old version)
     * cannot pollute the fresh installation.
     */
    public function purgeStaleLanguageFiles(): void
    {
        $basePaths = [
            JPATH_ADMINISTRATOR . '/language',
            JPATH_ROOT . '/language',
        ];

        $totalRemoved = 0;

        foreach ($basePaths as $basePath) {
            if (!is_dir($basePath)) {
                continue;
            }

            $langDirs = glob($basePath . '/*', GLOB_ONLYDIR) ?: [];

            foreach ($langDirs as $langDir) {
                $matches = glob($langDir . '/*contentbuilder*') ?: [];

                foreach ($matches as $match) {
                    if (!is_file($match)) {
                        continue;
                    }

                    try {
                        if (File::delete($match)) {
                            $this->log("[OK] Purged stale language file: {$match}.");
                            $totalRemoved++;
                        } else {
                            $this->log("[WARNING] Could not purge stale language file: {$match}.", Log::WARNING);
                        }
                    } catch (\Throwable $e) {
                        $this->log("[WARNING] Error purging stale language file {$match}: " . $e->getMessage(), Log::WARNING);
                    }
                }
            }
        }

        if ($totalRemoved === 0) {
            $this->log('[INFO] No stale ContentBuilder language files found to purge.');
        } else {
            $this->log("[OK] Purged {$totalRemoved} stale ContentBuilder language file(s) before installation.");
        }

    }

    public function removeObsoleteLanguageFiles(): void
    {
        $languageTags = ['en-GB', 'fr-FR', 'de-DE'];

        // Only OLD naming patterns — current com_contentbuilderng.ini files are NOT listed here.
        // Covers: old lang-prefix format (fr-FR.com_contentbuilderng.ini),
        //         legacy component names (com_contentbuilder, com_contentbuilder_ng, etc.).
        $patterns = [];
        $legacyBases = [
            'com_contentbuilder',
            'com_contentbuilder-ng',
            'com_contentbuilder_ng',
            'com_contentbuilderng',  // catches old fr-FR.com_contentbuilderng.ini prefix format
        ];
        $legacySuffixes = ['.ini', '.menu.ini', '.sys.ini'];

        foreach ($legacyBases as $legacyBase) {
            foreach ($legacySuffixes as $legacySuffix) {
                // Old Joomla 3-style: fr-FR.com_contentbuilderng.ini
                $patterns[] = '*.' . $legacyBase . $legacySuffix;
                // Bare legacy name (but never the current com_contentbuilderng.ini)
                if ($legacyBase !== 'com_contentbuilderng') {
                    $patterns[] = $legacyBase . $legacySuffix;
                }
            }
        }

        $basePaths = [
            JPATH_ADMINISTRATOR . '/language',
            JPATH_ROOT . '/language',
        ];

        foreach ($basePaths as $basePath) {
            foreach ($languageTags as $tag) {
                $languagePath = $basePath . '/' . $tag;

                if (!is_dir($languagePath)) {
                    continue;
                }

                foreach ($patterns as $pattern) {
                    $matches = glob($languagePath . '/' . $pattern) ?: [];

                    foreach ($matches as $match) {
                        try {
                            if (!is_file($match)) {
                                continue;
                            }

                            if (File::delete($match)) {
                                $this->log("[OK] Removed obsolete language file {$match}.");
                            } else {
                                $this->log("[WARNING] Failed to remove obsolete language file {$match}.", Log::WARNING);
                            }
                        } catch (\Throwable $e) {
                            $this->log("[WARNING] Failed removing obsolete language file {$match}: " . $e->getMessage(), Log::WARNING);
                        }
                    }
                }
            }
        }
    }

    public function ensureAdminMenuRootNodeExists(): void
    {
        $db = $this->db();

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'alias', 'client_id']))
                    ->from($db->quoteName('#__menu'))
                    ->where($db->quoteName('id') . ' = 1')
            );
            $row = $db->loadAssoc();

            if (is_array($row) && $row !== []) {
                $alias = strtolower(trim((string) ($row['alias'] ?? '')));

                if ($alias === 'root') {
                    return;
                }

                $this->log('[WARNING] Admin menu root check: id=1 exists but is not the expected root node; leaving untouched.', Log::WARNING);

                return;
            }

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
                $db->quote(''),
                $db->quote('Menu_Item_Root'),
                $db->quote('root'),
                $db->quote(''),
                $db->quote(''),
                $db->quote(''),
                $db->quote('component'),
                1,
                0,
                0,
                0,
                0,
                'NULL',
                0,
                1,
                $db->quote(''),
                0,
                $db->quote(''),
                $lft,
                $rgt,
                0,
                $db->quote('*'),
                1,
            ];

            $db->setQuery(
                $db->getQuery(true)
                    ->insert($db->quoteName('#__menu'))
                    ->columns(array_map([$db, 'quoteName'], $columns))
                    ->values(implode(', ', $values))
            );
            $db->execute();

            $this->log('[OK] Admin menu root node (id=1, alias=root) recreated (best-effort).');
        } catch (\Throwable $e) {
            $this->log('[WARNING] Failed ensuring admin menu root node: ' . $e->getMessage(), Log::WARNING);
        }
    }

    public function ensureAdministrationMainMenuEntry(): void
    {
        $db = $this->db();
        $targetElement = 'com_contentbuilderng';

        $componentId = (int) $this->safe(function () use ($db, $targetElement) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('extension_id'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' = ' . $db->quote($targetElement))
                ->where($db->quoteName('client_id') . ' = 1');
            $db->setQuery($query);

            return $db->loadResult();
        }, 0);

        if ($componentId === 0) {
            $this->log('[WARNING] Cannot ensure admin menu entry: com_contentbuilderng extension id is missing.', Log::WARNING);

            return;
        }

        $mainRows = $this->safe(function () use ($db, $componentId) {
            $query = $db->getQuery(true)
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
            $db->setQuery($query);

            return $db->loadAssocList() ?: [];
        }, []);

        if ($mainRows !== []) {
            $mainId = (int) $mainRows[0]['id'];
            $alias = trim((string) ($mainRows[0]['alias'] ?? ''));
            $path = trim((string) ($mainRows[0]['path'] ?? ''));

            if ($alias === '') {
                $alias = $this->resolveMenuAlias(1, 'contentbuilderng');
            }

            if ($path === '') {
                $path = $alias;
            }

            $this->safe(function () use ($db, $mainId, $alias, $path, $componentId): void {
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
                );
                $db->execute();
            });

            $this->log('[OK] Administration component menu entry checked and updated.');

            return;
        }

        $root = $this->safe(function () use ($db) {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'rgt']))
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('id') . ' = 1');
            $db->setQuery($query);

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
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__menu'))
                    ->set($db->quoteName('rgt') . ' = ' . $db->quoteName('rgt') . ' + 2')
                    ->where($db->quoteName('rgt') . ' >= ' . (int) $rootRgt)
            );
            $db->execute();

            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__menu'))
                    ->set($db->quoteName('lft') . ' = ' . $db->quoteName('lft') . ' + 2')
                    ->where($db->quoteName('lft') . ' > ' . (int) $rootRgt)
            );
            $db->execute();

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
            );
            $db->execute();

            $this->log('[OK] Administration component menu entry recreated.');
        } catch (\Throwable $e) {
            $this->log('[ERROR] Failed recreating administration menu entry: ' . $e->getMessage(), Log::ERROR);
        }
    }

    public function ensureSubmenuQuickTasks(): void
    {
        $db = $this->db();

        $componentId = (int) $this->safe(function () use ($db) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('extension_id'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_contentbuilderng'))
                ->where($db->quoteName('client_id') . ' = 1');
            $db->setQuery($query);

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

        foreach ($targets as $target) {
            $quotedLinks = array_map(fn(string $link): string => $db->quote($link), $target['links']);

            $rows = $this->safe(function () use ($db, $componentId, $quotedLinks) {
                $query = $db->getQuery(true)
                    ->select($db->quoteName(['id', 'params']))
                    ->from($db->quoteName('#__menu'))
                    ->where($db->quoteName('client_id') . ' = 1')
                    ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                    ->where($db->quoteName('component_id') . ' = ' . (int) $componentId)
                    ->where($db->quoteName('parent_id') . ' > 1')
                    ->where($db->quoteName('link') . ' IN (' . implode(',', $quotedLinks) . ')');
                $db->setQuery($query);

                return $db->loadAssocList() ?: [];
            }, []);

            if ($rows === []) {
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

                $this->safe(function () use ($db, $menuId, $params, &$updated): void {
                    $db->setQuery(
                        $db->getQuery(true)
                            ->update($db->quoteName('#__menu'))
                            ->set($db->quoteName('params') . ' = ' . $db->quote($params->toString('JSON')))
                            ->where($db->quoteName('id') . ' = ' . (int) $menuId)
                    );
                    $db->execute();
                    $updated++;
                });
            }

            if ($updated > 0) {
                $this->log('[OK] Updated Joomla quicktask (+) for ' . $target['label'] . ' submenu (' . $updated . ' entry).');
            }
        }
    }

    public function removeLegacyAdminMenuBranchByAlias(string $alias = 'contentbuilder'): void
    {
        $db = $this->db();

        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'lft', 'rgt', 'menutype', 'title', 'path', 'link']))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('client_id') . ' = 1')
            ->where($db->quoteName('parent_id') . ' = 1')
            ->where($db->quoteName('alias') . ' = ' . $db->quote($alias))
            ->order($db->quoteName('id') . ' ASC');

        $db->setQuery($query);
        $parent = $db->loadAssoc();

        if (!$parent) {
            $this->log("[INFO] No legacy admin menu branch found for alias={$alias}.", Log::INFO);

            return;
        }

        $parentId = (int) ($parent['id'] ?? 0);
        $lft = (int) ($parent['lft'] ?? 0);
        $rgt = (int) ($parent['rgt'] ?? 0);

        if ($parentId < 1 || $lft < 1 || $rgt <= $lft) {
            $this->log("[WARNING] Legacy admin menu branch alias={$alias} has invalid nested set values; skipping delete.", Log::WARNING);

            return;
        }

        $width = $rgt - $lft + 1;

        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('client_id') . ' = 1')
                ->where($db->quoteName('lft') . ' BETWEEN ' . $lft . ' AND ' . $rgt)
        );
        $ids = $db->loadColumn() ?: [];

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__menu'))
                ->where($db->quoteName('client_id') . ' = 1')
                ->where($db->quoteName('lft') . ' BETWEEN ' . $lft . ' AND ' . $rgt)
        );
        $db->execute();

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__menu'))
                ->set($db->quoteName('lft') . ' = ' . $db->quoteName('lft') . ' - ' . (int) $width)
                ->where($db->quoteName('client_id') . ' = 1')
                ->where($db->quoteName('lft') . ' > ' . (int) $rgt)
        );
        $db->execute();

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__menu'))
                ->set($db->quoteName('rgt') . ' = ' . $db->quoteName('rgt') . ' - ' . (int) $width)
                ->where($db->quoteName('client_id') . ' = 1')
                ->where($db->quoteName('rgt') . ' > ' . (int) $rgt)
        );
        $db->execute();

        if ($ids !== []) {
            $quotedNames = array_map(static fn($id): string => $db->quote('com_menus.menu.' . (int) $id), $ids);
            $this->safe(function () use ($db, $quotedNames): void {
                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__assets'))
                        ->where($db->quoteName('name') . ' IN (' . implode(',', $quotedNames) . ')')
                );
                $db->execute();
            });
        }

        $this->log("[OK] Legacy admin menu branch '{$alias}' removed: parent id={$parentId}, deleted " . count($ids) . ' menu row(s).');
        $this->purgeCaches('postflight:removeLegacyAdminMenuBranchByAlias');
    }

    private function resolveMenuAlias(int $parentId, string $baseAlias): string
    {
        $db = $this->db();
        $alias = $baseAlias;
        $suffix = 2;

        while ($suffix < 100) {
            $query = $db->getQuery(true)
                ->select('COUNT(1)')
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('parent_id') . ' = ' . $parentId)
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
