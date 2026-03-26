<?php

/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   (C) 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Helper;

\defined('_JEXEC') or die;

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
}
