<?php

/**
 * ContentBuilder NG template prepare helper.
 *
 * @package     ContentBuilderNG
 * @subpackage  Administrator.Helper
 * @author      XDA+GIL
 * @copyright   Copyright © 2024–2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.1.1
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper;

// No direct access
\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Log\Log;

final class TemplatePrepareHelper
{
    public static function execute(CMSApplicationInterface $app, string $prepareCode, string $fieldName, callable $executor): void
    {
        if ($prepareCode === '') {
            return;
        }

        try {
            $executor($prepareCode);
        } catch (\ParseError $e) {
            $fieldLabel = ucwords(str_replace('_', ' ', trim($fieldName)));
            $msg = 'Invalid ' . $fieldName . ' code; skipped. Check the ' . $fieldLabel . ' field for stray HTML (editor).';
            Log::add($msg . ' Error: ' . $e->getMessage(), Log::WARNING, 'com_contentbuilderng');
            $app->enqueueMessage($msg, 'warning');
        }
    }
}
