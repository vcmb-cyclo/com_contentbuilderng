<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper\Audit;

\defined('_JEXEC') or die('Restricted access');

use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;
use Joomla\CMS\Access\Access;
use Joomla\Database\DatabaseInterface;

final class FrontendPermissionAuditHelper
{
    /**
     * @return array{0:array<int,array{form_id:int,form_name:string,issues:array<int,string>}>,1:array<int,string>}
     */
    public static function inspect(DatabaseInterface $db): array
    {
        $issues = [];
        $errors = [];

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'name', 'config', 'own_only_fe', 'new_button', 'edit_button']))
                ->from($db->quoteName('#__contentbuilderng_forms'))
                ->order($db->quoteName('id') . ' ASC');
            $db->setQuery($query);
            $forms = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            return [[], ['Could not inspect frontend permission consistency: ' . $e->getMessage()]];
        }

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'access', 'link', 'params']))
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('client_id') . ' = 0')
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_contentbuilderng%'));
            $db->setQuery($query);
            $menus = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $menus = [];
            $errors[] = 'Could not inspect frontend menus for permission audit: ' . $e->getMessage();
        }

        $menusByForm = [];
        foreach ($menus as $menu) {
            $params = MenuViewAuditHelper::decodeMenuParams($menu['params'] ?? null);
            $query = [];
            parse_str((string) parse_url((string) ($menu['link'] ?? ''), PHP_URL_QUERY), $query);
            $formId = (int) ($query['id'] ?? MenuViewAuditHelper::extractMenuParam($params, 'form_id', 0));

            if ($formId > 0) {
                $menusByForm[$formId][] = $menu;
            }
        }

        $guestGroups = self::getEffectiveGroupIdsForUser(0, $db);
        $actions = ['listaccess', 'view', 'new', 'edit', 'delete', 'state', 'publish', 'language', 'rating', 'api'];

        foreach ($forms as $form) {
            $formId = (int) ($form['id'] ?? 0);
            $config = PackedDataHelper::decodePackedData((string) ($form['config'] ?? ''), [], true);
            if (!is_array($config)) {
                $config = [];
            }

            $issuesForForm = [];
            $permissionsFe = (array) ($config['permissions_fe'] ?? []);
            $ownFe = (array) ($config['own_fe'] ?? []);
            $hasAnyFrontendPermission = false;

            foreach ($actions as $action) {
                if (!empty($ownFe[$action])) {
                    $hasAnyFrontendPermission = true;
                    break;
                }

                foreach ($permissionsFe as $groupPermissions) {
                    if (!empty($groupPermissions[$action])) {
                        $hasAnyFrontendPermission = true;
                        break 2;
                    }
                }
            }

            if (!$hasAnyFrontendPermission) {
                $issuesForForm[] = 'No frontend permission is granted to any group or owner rule.';
            }

            if (!empty($form['own_only_fe']) && empty($ownFe['view']) && empty($ownFe['listaccess']) && empty($ownFe['edit']) && empty($ownFe['delete']) && empty($ownFe['new'])) {
                $issuesForForm[] = 'own_only_fe is enabled, but no owner-specific frontend action is active.';
            }

            if (!empty($form['new_button']) && empty($ownFe['new']) && !self::configHasFrontendPermission($permissionsFe, 'new')) {
                $issuesForForm[] = 'New button is enabled, but no frontend New permission is granted.';
            }

            if (!empty($form['edit_button']) && empty($ownFe['edit']) && !self::configHasFrontendPermission($permissionsFe, 'edit')) {
                $issuesForForm[] = 'Edit button is enabled, but no frontend Edit permission is granted.';
            }

            foreach ((array) ($menusByForm[$formId] ?? []) as $menu) {
                if ((int) ($menu['access'] ?? 0) !== 1) {
                    continue;
                }

                $params = MenuViewAuditHelper::decodeMenuParams($menu['params'] ?? null);
                $query = [];
                parse_str((string) parse_url((string) ($menu['link'] ?? ''), PHP_URL_QUERY), $query);
                $task = trim((string) ($query['task'] ?? $query['view'] ?? 'list.display'));
                $requiredAction = match (true) {
                    str_contains($task, 'edit') => ((int) ($query['record_id'] ?? MenuViewAuditHelper::extractMenuParam($params, 'record_id', 0)) > 0 ? 'edit' : 'new'),
                    str_contains($task, 'details') => 'view',
                    default => 'listaccess',
                };

                $guestAllowed = !empty($ownFe[$requiredAction]) || self::configHasFrontendPermission($permissionsFe, $requiredAction, $guestGroups);

                if (!$guestAllowed) {
                    $issuesForForm[] = 'Public menu #' . (int) ($menu['id'] ?? 0) . ' targets this view, but guest users do not have frontend "' . $requiredAction . '" permission.';
                }
            }

            if ($issuesForForm !== []) {
                $issues[] = [
                    'form_id' => $formId,
                    'form_name' => trim((string) ($form['name'] ?? '')),
                    'issues' => array_values(array_unique($issuesForForm)),
                ];
            }
        }

        return [$issues, $errors];
    }

    /**
     * @param array<string,mixed>|array<int,mixed> $permissionsFe
     * @param array<int,int>|null $allowedGroups
     */
    private static function configHasFrontendPermission(array $permissionsFe, string $action, ?array $allowedGroups = null): bool
    {
        foreach ($permissionsFe as $groupId => $groupPermissions) {
            if ($allowedGroups !== null && !in_array((int) $groupId, $allowedGroups, true)) {
                continue;
            }

            if (!empty($groupPermissions[$action])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return int[]
     */
    private static function getEffectiveGroupIdsForUser(int $userId, DatabaseInterface $db): array
    {
        $groupIds = array_map('intval', Access::getGroupsByUser($userId));

        if ($groupIds === []) {
            return [];
        }

        static $parentByGroupId = null;

        if ($parentByGroupId === null) {
            $db->setQuery('Select id, parent_id From #__usergroups');
            $rows = $db->loadAssocList() ?: [];

            $parentByGroupId = [];
            foreach ($rows as $row) {
                $groupId = (int) ($row['id'] ?? 0);
                if ($groupId < 1) {
                    continue;
                }

                $parentByGroupId[$groupId] = (int) ($row['parent_id'] ?? 0);
            }
        }

        $effectiveGroupIds = [];
        foreach ($groupIds as $groupId) {
            while ($groupId > 0 && !isset($effectiveGroupIds[$groupId])) {
                $effectiveGroupIds[$groupId] = true;
                $groupId = $parentByGroupId[$groupId] ?? 0;
            }
        }

        return array_map('intval', array_keys($effectiveGroupIds));
    }
}
