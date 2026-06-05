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

use Joomla\Database\DatabaseInterface;

final class MenuViewAuditHelper
{
    /**
     * @return array{0:array<int,array{menu_id:int,title:string,access:int,link:string,target:string,issues:array<int,string>}>,1:array<int,string>}
     */
    public static function inspect(DatabaseInterface $db): array
    {
        $issues = [];
        $errors = [];

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'access', 'link', 'params']))
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('client_id') . ' = 0')
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_contentbuilderng%'))
                ->order($db->quoteName('id') . ' ASC');
            $db->setQuery($query);
            $menus = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            return [[], ['Could not inspect ContentBuilder frontend menus: ' . $e->getMessage()]];
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'name', 'type', 'reference_id']))
                    ->from($db->quoteName('#__contentbuilderng_forms'))
            );
            $formRows = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            return [[], ['Could not inspect ContentBuilder views for menu audit: ' . $e->getMessage()]];
        }

        $formsById = [];
        foreach ($formRows as $formRow) {
            $formsById[(int) ($formRow['id'] ?? 0)] = $formRow;
        }

        $recordCache = [];

        foreach ($menus as $menu) {
            $menuId = (int) ($menu['id'] ?? 0);
            $link = trim((string) ($menu['link'] ?? ''));
            $params = self::decodeMenuParams($menu['params'] ?? null);
            $query = [];
            parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

            $target = trim((string) ($query['task'] ?? $query['view'] ?? 'list.display'));
            $formIdFromLink = (int) ($query['id'] ?? 0);
            $formIdFromParams = (int) self::extractMenuParam($params, 'form_id', 0);
            $formIdsFromParams = self::normalizeIdList(self::extractMenuParam($params, 'forms', []));
            $recordId = (int) ($query['record_id'] ?? self::extractMenuParam($params, 'record_id', 0));
            $issuesForMenu = [];

            if ($formIdFromLink > 0 && $formIdFromParams > 0 && $formIdFromLink !== $formIdFromParams) {
                $issuesForMenu[] = 'Link form id and menu form_id do not match.';
            }

            $effectiveFormId = $formIdFromLink > 0 ? $formIdFromLink : $formIdFromParams;

            if ($effectiveFormId <= 0 && $formIdsFromParams === []) {
                $issuesForMenu[] = 'No target view is configured on this menu.';
            }

            if ($effectiveFormId > 0 && !isset($formsById[$effectiveFormId])) {
                $issuesForMenu[] = 'Referenced view #' . $effectiveFormId . ' no longer exists.';
            }

            foreach ($formIdsFromParams as $multiFormId) {
                if (!isset($formsById[$multiFormId])) {
                    $issuesForMenu[] = 'Referenced multi-view #' . $multiFormId . ' no longer exists.';
                }
            }

            if ($recordId > 0 && $effectiveFormId > 0 && isset($formsById[$effectiveFormId])) {
                $cacheKey = $effectiveFormId . ':' . $recordId;

                if (!array_key_exists($cacheKey, $recordCache)) {
                    $form = $formsById[$effectiveFormId];
                    $type = trim((string) ($form['type'] ?? ''));
                    $referenceId = trim((string) ($form['reference_id'] ?? ''));

                    if ($type !== '' && $referenceId !== '') {
                        try {
                            $db->setQuery(
                                $db->getQuery(true)
                                    ->select('COUNT(*)')
                                    ->from($db->quoteName('#__contentbuilderng_records'))
                                    ->where($db->quoteName('type') . ' = ' . $db->quote($type))
                                    ->where($db->quoteName('reference_id') . ' = ' . $db->quote($referenceId))
                                    ->where($db->quoteName('record_id') . ' = ' . $db->quote($recordId))
                            );
                            $recordCache[$cacheKey] = ((int) $db->loadResult()) > 0;
                        } catch (\Throwable $e) {
                            $recordCache[$cacheKey] = null;
                            $errors[] = 'Could not inspect record #' . $recordId . ' for menu #' . $menuId . ': ' . $e->getMessage();
                        }
                    } else {
                        $recordCache[$cacheKey] = null;
                    }
                }

                if ($recordCache[$cacheKey] === false) {
                    $issuesForMenu[] = 'Referenced record #' . $recordId . ' no longer exists for view #' . $effectiveFormId . '.';
                }
            }

            if ($issuesForMenu !== []) {
                $issues[] = [
                    'menu_id' => $menuId,
                    'title' => trim((string) ($menu['title'] ?? '')),
                    'access' => (int) ($menu['access'] ?? 0),
                    'link' => $link,
                    'target' => $target !== '' ? $target : 'list.display',
                    'issues' => $issuesForMenu,
                ];
            }
        }

        return [$issues, $errors];
    }

    /**
     * @return array<string,mixed>
     */
    public static function decodeMenuParams($params): array
    {
        if (is_array($params)) {
            return $params;
        }

        if (is_object($params)) {
            if (method_exists($params, 'toArray')) {
                $params = $params->toArray();
                return is_array($params) ? $params : [];
            }

            return (array) $params;
        }

        if (is_string($params) && trim($params) !== '') {
            $decoded = json_decode($params, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @param array<string,mixed> $params
     * @param mixed $default
     * @return mixed
     */
    public static function extractMenuParam(array $params, string $key, $default = null)
    {
        if (array_key_exists($key, $params)) {
            return $params[$key];
        }

        if (isset($params['settings']) && is_array($params['settings']) && array_key_exists($key, $params['settings'])) {
            return $params['settings'][$key];
        }

        return $default;
    }

    /**
     * @param mixed $value
     * @return array<int,int>
     */
    public static function normalizeIdList($value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', trim($value)) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        $ids = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $value),
                    static fn(int $id): bool => $id > 0
                )
            )
        );

        sort($ids, SORT_NUMERIC);

        return $ids;
    }
}
