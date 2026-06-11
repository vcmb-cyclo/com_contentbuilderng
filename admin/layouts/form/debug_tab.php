<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$item = $displayData['item'] ?? null;
$renderCheckbox = $displayData['renderCheckbox'] ?? null;

if (!is_object($item) || !is_callable($renderCheckbox)) {
    return;
}
?>
<h3 id="cb-form-debug" class="mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_DEBUG'); ?>
</h3>
<p class="text-muted mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_DEBUG_INTRO'); ?>
</p>
<div class="row gx-3 gy-1 mt-0 align-items-stretch mb-3">
    <div class="col-12 col-xl-4 d-flex">
        <div class="border rounded bg-body p-3 d-flex flex-column flex-grow-1">
            <h4 class="h6 text-body-secondary mb-2">
                <?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_OPTIONS'); ?>
            </h4>
            <?php
            $debugOptions = [
                'debug_show_bf_id' => ['COM_CONTENTBUILDERNG_DEBUG_SHOW_BF_ID', 'COM_CONTENTBUILDERNG_DEBUG_SHOW_BF_ID_TIP'],
                'debug_show_cb_id' => ['COM_CONTENTBUILDERNG_DEBUG_SHOW_CB_ID', 'COM_CONTENTBUILDERNG_DEBUG_SHOW_CB_ID_TIP'],
                'debug_enable_logs' => ['COM_CONTENTBUILDERNG_DEBUG_ENABLE_LOGS', 'COM_CONTENTBUILDERNG_DEBUG_ENABLE_LOGS_TIP'],
                'debug_show_request_logs' => ['COM_CONTENTBUILDERNG_DEBUG_SHOW_REQUEST_LOGS', 'COM_CONTENTBUILDERNG_DEBUG_SHOW_REQUEST_LOGS_TIP'],
                'debug_show_permissions' => ['COM_CONTENTBUILDERNG_DEBUG_SHOW_PERMISSIONS', 'COM_CONTENTBUILDERNG_DEBUG_SHOW_PERMISSIONS_TIP'],
                'debug_show_filters' => ['COM_CONTENTBUILDERNG_DEBUG_SHOW_FILTERS', 'COM_CONTENTBUILDERNG_DEBUG_SHOW_FILTERS_TIP'],
            ];
            ?>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($debugOptions as $field => [$labelKey, $tipKey]) : ?>
                    <div class="form-check">
                        <input type="hidden" name="jform[<?php echo $field; ?>]" value="0" />
                        <?php echo $renderCheckbox('jform[' . $field . ']', $field, !empty($item->$field)); ?>
                        <label class="form-check-label" for="<?php echo $field; ?>">
                            <span class="editlinktip hasTip" title="<?php echo Text::_($tipKey); ?>">
                                <?php echo Text::_($labelKey); ?>
                            </span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
