<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Helper;

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Component\ComponentHelper;

final class MenuParamHelper
{
    public static function getMenuParam($params, string $key, $default = null)
    {
        $settings = $params->get('settings', null);

        if (is_array($settings) && array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        if (is_object($settings) && method_exists($settings, 'get')) {
            $value = $settings->get($key, null);

            if ($value !== null) {
                return $value;
            }
        }

        if (is_object($settings) && isset($settings->$key)) {
            return $settings->$key;
        }

        $value = $params->get('settings.' . $key, null);

        if ($value !== null) {
            return $value;
        }

        return $params->get($key, $default);
    }

    public static function getConfiguredListLimit($app): int
    {
        $inputLimit = (int) $app->input->getInt('cb_list_limit', 0);

        if ($inputLimit > 0) {
            return $inputLimit;
        }

        if (!$app->isClient('site')) {
            return 0;
        }

        $itemId = (int) $app->input->getInt('Itemid', 0);
        $menu = $app->getMenu();
        $item = $itemId > 0 ? $menu->getItem($itemId) : $menu->getActive();

        if (!$item) {
            return 0;
        }

        return max(0, (int) self::getMenuParam($item->getParams(), 'cb_list_limit', 0));
    }

    public static function resolveToggleValue($value, int $default = 0): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $intValue = (int) $value;

        if ($intValue < 0) {
            return $default;
        }

        return $intValue === 1 ? 1 : 0;
    }

    public static function resolvePageHeadingToggle($value, ?int $globalValue = null, int $default = 1): int
    {
        if ($value === null) {
            return $default;
        }

        if ($value === '') {
            $globalValue = ComponentHelper::getParams('com_menus')->get('show_page_heading', $globalValue ?? $default);

            return self::resolveToggleValue($globalValue, $default);
        }

        return self::resolveToggleValue($value, $default);
    }

    public static function getResolvedMenuToggle($params, string $key, int $default = 0, ?string $legacyKey = null): int
    {
        $value = self::getMenuParam($params, $key, null);

        if (($value === null || $value === '') && $legacyKey !== null) {
            $value = self::getMenuParam($params, $legacyKey, null);
        }

        return self::resolveToggleValue($value, $default);
    }

    public static function resolveInputOrMenuToggle($app, string $key, int $default = 0, ?string $legacyKey = null): int
    {
        $raw = $app->input->get($key, null, 'raw');

        if (($raw === null || $raw === '') && $legacyKey !== null) {
            $raw = $app->input->get($legacyKey, null, 'raw');
        }

        if ($raw !== null && $raw !== '') {
            return self::resolveToggleValue($raw, $default);
        }

        if ($app->isClient('site')) {
            $menu = $app->getMenu()->getActive();

            if ($menu) {
                return self::getResolvedMenuToggle($menu->getParams(), $key, $default, $legacyKey);
            }
        }

        return $default;
    }
}
