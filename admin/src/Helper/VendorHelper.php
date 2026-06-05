<?php
/**
 * @package     ContentBuilder NG
 * @author      Xavier DANO / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper;

\defined('_JEXEC') or die;

final class VendorHelper
{
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        $autoload = JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/vendor/autoload.php';

        if (!is_file($autoload)) {
            throw new \RuntimeException('Composer autoload not found: ' . $autoload);
        }

        require_once $autoload;
        self::$loaded = true;
    }
}
