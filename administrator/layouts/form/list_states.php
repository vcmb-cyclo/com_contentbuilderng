<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$item = $displayData['item'] ?? null;
$renderCheckbox = $displayData['renderCheckbox'] ?? null;
$listStatesActionPlugins = is_array($displayData['listStatesActionPlugins'] ?? null) ? $displayData['listStatesActionPlugins'] : [];
$listStates = is_array($item->list_states ?? null) ? $item->list_states : [];
?>
<h3 id="cb-form-list-states" class="mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES'); ?></h3>
<p class="text-muted mb-3">
    <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_INTRO'); ?>
</p>
<div class="table-responsive mb-3">
<table id="cb-form-list-states-table" class="table table-striped">
    <thead>
        <tr>
            <th>
                <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_PUBLISHED') ?>
            </th>
            <th>
                <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_TITLE') ?>
            </th>
            <th>
                <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_COLOR') ?>
            </th>
            <th>
                <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_ACTION') ?>
            </th>
        </tr>
    </thead>
    <?php
    foreach ($listStates as $state) {
        $k = 0;
        $stateRawColor = (string) ($state['color'] ?? '');
        $previewHex = strtoupper(ltrim(trim($stateRawColor), '#'));
        if (preg_match('/^[0-9A-F]{3}$/', $previewHex)) {
            $previewHex = $previewHex[0] . $previewHex[0]
                . $previewHex[1] . $previewHex[1]
                . $previewHex[2] . $previewHex[2];
        }
        $stateColorStyle = '';
        if (preg_match('/^[0-9A-F]{6}$/', $previewHex)) {
            $red = hexdec(substr($previewHex, 0, 2));
            $green = hexdec(substr($previewHex, 2, 2));
            $blue = hexdec(substr($previewHex, 4, 2));
            $textColor = ((($red * 299) + ($green * 587) + ($blue * 114)) / 1000) >= 160 ? '#000000' : '#FFFFFF';
            $stateColorStyle = 'background-color:#' . $previewHex . ';color:' . $textColor . ';';
        }
        $stateColorInputId = 'list_state_color_' . (int) $state['id'];
        $stateColorPickerId = 'list_state_color_picker_' . (int) $state['id'];
        $stateNativePickerValue = preg_match('/^[0-9A-F]{6}$/', $previewHex) ? '#' . $previewHex : '#FFFFFF';
    ?>
        <tr class="<?php echo 'row' . $k; ?>">
            <td>
                <?php echo is_callable($renderCheckbox) ? $renderCheckbox('jform[list_states][' . $state['id'] . '][published]', 'list_state_published_' . $state['id'], (bool) $state['published']) : ''; ?>
            </td>
            <td>
                <input class="form-control form-control-sm w-100" type="text"
                    name="jform[list_states][<?php echo $state['id']; ?>][title]"
                    value="<?php echo htmlspecialchars($state['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            </td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <input
                        class="form-control form-control-sm w-100"
                        type="text"
                        id="<?php echo $stateColorInputId; ?>"
                        data-cb-color-text="1"
                        data-cb-color-picker-target="<?php echo $stateColorPickerId; ?>"
                        value="<?php echo htmlspecialchars($stateRawColor, ENT_QUOTES, 'UTF-8'); ?>"
                        style="<?php echo $stateColorStyle; ?>"
                        name="jform[list_states][<?php echo $state['id']; ?>][color]" />
                    <input
                        class="form-control form-control-color form-control-sm"
                        type="color"
                        id="<?php echo $stateColorPickerId; ?>"
                        data-cb-color-picker="1"
                        data-cb-color-target="<?php echo $stateColorInputId; ?>"
                        value="<?php echo $stateNativePickerValue; ?>"
                        title="<?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_COLOR'); ?>"
                        aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_COLOR'); ?>"
                        style="width: 3rem; min-width: 3rem; padding: 0.2rem;" />
                </div>
            </td>
            <td>
                <select class="form-select-sm" name="jform[list_states][<?php echo $state['id']; ?>][action]">
                    <option value=""> -
                        <?php echo Text::_('COM_CONTENTBUILDERNG_NONE'); ?> -
                    </option>
                    <?php foreach ($listStatesActionPlugins as $listStateActionPlugin) : ?>
                        <option value="<?php echo $listStateActionPlugin; ?>" <?php echo $listStateActionPlugin == $state['action'] ? ' selected="selected"' : ''; ?>>
                            <?php echo $listStateActionPlugin; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    <?php
        $k = 1 - $k;
    }
    ?>
</table>
</div>
