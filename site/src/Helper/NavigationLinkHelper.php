<?php

/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Uri\Uri;

final class NavigationLinkHelper
{
    public static function buildRouteLink(array $query, string $suffix = ''): string
    {
        $base = 'index.php';
        $queryString = http_build_query($query);

        if ($queryString !== '') {
            $base .= '?' . $queryString;
        }

        if ($suffix !== '') {
            $base .= $suffix;
        }

        return $base;
    }

    public static function buildListQuery(int $start, int $limit, string $ordering, string $direction): string
    {
        return http_build_query([
            'list' => [
                'start' => max(0, $start),
                'limit' => max(0, $limit),
                'ordering' => $ordering,
                'direction' => $direction,
            ],
        ]);
    }

    public static function buildHref(
        string $baseLink,
        int $recordId,
        int $start,
        int $limit,
        string $ordering,
        string $direction,
        string $suffix = ''
    ): string {
        $baseLink = trim($baseLink);
        if ($baseLink === '' || $recordId < 1) {
            return '';
        }

        $href = $baseLink . '&record_id=' . $recordId;
        $listQuery = self::buildListQuery($start, $limit, $ordering, $direction);
        if ($listQuery !== '') {
            $href .= '&' . $listQuery;
        }

        if ($suffix !== '') {
            $href .= $suffix;
        }

        return $href;
    }

    public static function encodeInternalReturn(string $return): string
    {
        $return = trim($return);
        if ($return === '') {
            return '';
        }

        $decoded = base64_decode($return, true);
        if ($decoded !== false && Uri::isInternal($decoded)) {
            return $return;
        }

        if (Uri::isInternal($return)) {
            return base64_encode($return);
        }

        return '';
    }

    public static function decodeInternalReturn(string $return): string
    {
        $return = trim($return);
        if ($return === '') {
            return '';
        }

        $decoded = base64_decode($return, true);
        if ($decoded !== false && Uri::isInternal($decoded)) {
            return $decoded;
        }

        if (Uri::isInternal($return)) {
            return $return;
        }

        return '';
    }

    public static function resolveListState(
        CMSApplication $app,
        array $list,
        int $formId,
        string $layout,
        int $itemId,
        bool $directStorageMode = false,
        int $directStorageId = 0
    ): array {
        $scope = $directStorageMode ? ('storage.' . max(0, $directStorageId)) : (string) max(0, $formId);
        if ($scope === '0') {
            $scope = (string) max(0, (int) $app->input->getInt('id', 0));
        }

        if ($layout === '') {
            $layout = 'default';
        }

        $prefix = 'com_contentbuilderng.liststate.' . $scope . '.' . $layout . '.' . max(0, $itemId);
        $limitKey = $prefix . '.limit';
        $startKey = $prefix . '.start';
        $configuredLimit = MenuParamHelper::getConfiguredListLimit($app, $formId);
        $explicitLimitRequest = MenuParamHelper::hasExplicitListLimitRequest();

        $limit = $explicitLimitRequest && isset($list['limit']) ? (int) $list['limit'] : 0;
        if ($limit === 0) {
            $limit = $configuredLimit;
        }
        if ($limit === 0) {
            $limit = (int) $app->getUserState($limitKey, 0);
        }
        if ($limit === 0) {
            $limit = (int) $app->get('list_limit');
        }
        if ($limit < 1) {
            $limit = 20;
        }

        if ($explicitLimitRequest && array_key_exists('start', $list)) {
            $start = max(0, (int) $list['start']);
        } elseif ($configuredLimit > 0) {
            $start = 0;
        } else {
            $start = (int) $app->getUserState($startKey, 0);
        }

        $ordering = isset($list['ordering']) ? preg_replace('/[^A-Za-z0-9_\\.]/', '', (string) $list['ordering']) : '';
        if ($ordering === '') {
            $ordering = (string) $app->getUserState('com_contentbuilderng.formsd_filter_order', '');
        }
        if ($ordering === '' && isset($list['fullordering'])) {
            $parts = preg_split('/\s+/', trim((string) $list['fullordering']));
            $ordering = isset($parts[0]) ? preg_replace('/[^A-Za-z0-9_\\.]/', '', (string) $parts[0]) : '';
        }

        $direction = isset($list['direction']) ? strtolower((string) $list['direction']) : '';
        if ($direction === '') {
            $direction = (string) $app->getUserState('com_contentbuilderng.formsd_filter_order_Dir', '');
        }
        if ($direction === '' && isset($list['fullordering'])) {
            $parts = preg_split('/\s+/', trim((string) $list['fullordering']));
            $direction = isset($parts[1]) ? strtolower((string) $parts[1]) : '';
        }

        return [
            'start' => $start,
            'limit' => $limit,
            'ordering' => $ordering,
            'direction' => $direction,
        ];
    }
}
