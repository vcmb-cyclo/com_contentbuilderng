<?php

/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

$composerAutoload = JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/vendor/autoload.php';
$vendorSecurimage = JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/vendor/bgli100/securimage/securimage.php';

if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

if (!class_exists('Securimage') && is_file($vendorSecurimage)) {
    require_once $vendorSecurimage;
}

if (!class_exists('Securimage')) {
    throw new \RuntimeException(
        'Securimage class not found. Checked: '
        . $composerAutoload
        . ' and '
        . $vendorSecurimage
    );
}
