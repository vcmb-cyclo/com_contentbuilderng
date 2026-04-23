<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;

$elements = is_array($displayData['elements'] ?? null) ? $displayData['elements'] : [];
$pagination = $displayData['pagination'] ?? null;
$ordering = (bool) ($displayData['ordering'] ?? false);
$item = $displayData['item'] ?? null;
$sortLink = $displayData['sortLink'] ?? null;
$textUtilityService = $displayData['textUtilityService'] ?? null;
$isModifiedElementSettings = $displayData['isModifiedElementSettings'] ?? null;
$columnOptions = [
    'id' => Text::_('COM_CONTENTBUILDERNG_ID'),
    'label' => Text::_('COM_CONTENTBUILDERNG_LABEL'),
    'list' => Text::_('COM_CONTENTBUILDERNG_ELEMENT_HEADING_LIST'),
    'search' => Text::_('COM_CONTENTBUILDERNG_ELEMENT_HEADING_SEARCH'),
    'link' => Text::_('COM_CONTENTBUILDERNG_ELEMENT_HEADING_LINK'),
    'edit' => Text::_('COM_CONTENTBUILDERNG_ELEMENT_HEADING_EDIT'),
    'wordwrap' => Text::_('COM_CONTENTBUILDERNG_LIST_WORDWRAP'),
    'publish' => Text::_('COM_CONTENTBUILDERNG_ELEMENT_HEADING_PUBLISH'),
    'order' => Text::_('COM_CONTENTBUILDERNG_ORDERBY'),
];
$defaultHiddenColumns = ['wordwrap'];
$visibleColumnCount = count($columnOptions);
?>
<div class="d-flex justify-content-end mb-2 cb-elements-columns-pending">
    <div class="dropdown cb-elements-columns-dropdown">
        <button type="button"
            class="btn btn-primary btn-sm dropdown-toggle"
            id="cb-elements-columns-toggle"
            data-bs-toggle="dropdown"
            data-bs-auto-close="outside"
            aria-haspopup="true"
            aria-expanded="false">
            <span class="cb-elements-columns-count"><?php echo (int) $visibleColumnCount; ?>/<?php echo (int) $visibleColumnCount; ?> <?php echo Text::_('COM_CONTENTBUILDERNG_COLUMNS'); ?></span>
        </button>
        <div class="dropdown-menu dropdown-menu-end p-2 cb-elements-columns-menu" aria-labelledby="cb-elements-columns-toggle">
            <?php foreach ($columnOptions as $columnKey => $columnLabel) : ?>
                <label class="dropdown-item form-check d-flex align-items-center gap-2 mb-0">
                    <input class="form-check-input mt-0 cb-elements-column-toggle"
                        type="checkbox"
                        value="<?php echo htmlspecialchars($columnKey, ENT_QUOTES, 'UTF-8'); ?>"
                        data-cb-column-toggle="1"
                        <?php echo in_array($columnKey, $defaultHiddenColumns, true) ? '' : 'checked'; ?>>
                    <span><?php echo htmlspecialchars($columnLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </label>
            <?php endforeach; ?>
            <div class="dropdown-divider my-2"></div>
            <button type="button" class="btn btn-link btn-sm px-2 cb-elements-columns-reset" data-cb-columns-reset="1">
                <?php echo Text::_('COM_CONTENTBUILDERNG_RESET'); ?>
            </button>
        </div>
    </div>
</div>
<div class="table-responsive mb-3 cb-elements-columns-pending">
<table class="table table-striped cb-elements-table">
    <thead>
        <tr>
            <th id="cb-form-view-elements-heading-id" width="5" data-cb-col="id">
                <?php echo is_callable($sortLink) ? $sortLink(Text::_('COM_CONTENTBUILDERNG_ID'), 'id') : Text::_('COM_CONTENTBUILDERNG_ID'); ?>
            </th>
            <th id="cb-form-view-elements-heading-checkall" width="20" data-cb-col="check">
                <input class="form-check-input" type="checkbox" name="checkall-toggle" value="" onclick="Joomla.checkAll(this);" aria-label="<?php echo htmlspecialchars(Text::_('JGLOBAL_CHECK_ALL'), ENT_QUOTES, 'UTF-8'); ?>">
            </th>
            <th id="cb-form-view-elements-heading-label" data-cb-col="label">
                <span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_LABEL_TIP'); ?>">
                    <?php echo is_callable($sortLink) ? $sortLink(Text::_('COM_CONTENTBUILDERNG_LABEL'), 'label') : Text::_('COM_CONTENTBUILDERNG_LABEL'); ?>
                </span>
            </th>
            <th id="cb-form-view-elements-heading-list-include" data-cb-col="list">
                <span class="editlinktip hasTip cb-elements-heading-label"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_LIST_INCLUDE_TIP'); ?>">
                    <?php echo is_callable($sortLink) ? $sortLink(Text::_('COM_CONTENTBUILDERNG_ELEMENT_HEADING_LIST'), 'list_include') : Text::_('COM_CONTENTBUILDERNG_ELEMENT_HEADING_LIST'); ?>
                </span>
            </th>
            <th id="cb-form-view-elements-heading-search-include" data-cb-col="search">
                <span class="editlinktip hasTip cb-elements-heading-label"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_SEARCH_INCLUDE_TIP'); ?>">
                    <?php echo is_callable($sortLink) ? $sortLink(Text::_('COM_CONTENTBUILDERNG_ELEMENT_HEADING_SEARCH'), 'search_include') : Text::_('COM_CONTENTBUILDERNG_ELEMENT_HEADING_SEARCH'); ?>
                </span>
            </th>
            <th id="cb-form-view-elements-heading-linkable" data-cb-col="link">
                <span class="editlinktip hasTip cb-elements-heading-label"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_LINKABLE_TIP'); ?>">
                    <?php echo is_callable($sortLink) ? $sortLink(Text::_('COM_CONTENTBUILDERNG_ELEMENT_HEADING_LINK'), 'linkable') : Text::_('COM_CONTENTBUILDERNG_ELEMENT_HEADING_LINK'); ?>
                </span>
            </th>
            <th id="cb-form-view-elements-heading-editable" data-cb-col="edit">
                <span class="editlinktip hasTip cb-elements-heading-label"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_EDITABLE_TIP'); ?>">
                    <?php echo is_callable($sortLink) ? $sortLink(Text::_('COM_CONTENTBUILDERNG_ELEMENT_HEADING_EDIT'), 'editable') : Text::_('COM_CONTENTBUILDERNG_ELEMENT_HEADING_EDIT'); ?>
                </span>
            </th>
            <th id="cb-form-view-elements-heading-wordwrap" data-cb-col="wordwrap">
                <span class="editlinktip hasTip"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_LIST_WORDWRAP_TIP'); ?>">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_WORDWRAP'); ?>
                </span>
            </th>
            <th id="cb-form-view-elements-heading-published" data-cb-col="publish">
                <span class="cb-elements-heading-label">
                    <?php echo is_callable($sortLink) ? $sortLink(Text::_('COM_CONTENTBUILDERNG_ELEMENT_HEADING_PUBLISH'), 'published') : Text::_('COM_CONTENTBUILDERNG_ELEMENT_HEADING_PUBLISH'); ?>
                </span>
            </th>
            <th id="cb-form-view-elements-heading-ordering" width="120" class="cb-order-head" data-cb-col="order">
                <?php if (!empty($elements)) : ?>
                    <?php echo is_callable($sortLink) ? $sortLink(Text::_('COM_CONTENTBUILDERNG_ORDERBY'), 'ordering') : Text::_('COM_CONTENTBUILDERNG_ORDERBY'); ?>
                    <?php echo HTMLHelper::_('grid.order', $elements); ?>
                <?php endif; ?>
            </th>
        </tr>
    </thead>
    <tbody>
        <?php
        $k = 0;
        $n = count($elements);
        for ($i = 0; $i < $n; $i++) {
            $row = $elements[$i];
            $checked = '<input class="form-check-input" type="checkbox" id="cb' . (int) $i . '" name="cid[]" value="' . (int) $row->id . '" onclick="Joomla.isChecked(this.checked);">';
            $published = ContentbuilderngHelper::listPublish('form', $row, $i);
            $listInclude = ContentbuilderngHelper::listIncludeInList('form', $row, $i);
            $searchInclude = ContentbuilderngHelper::listIncludeInSearch('form', $row, $i);
            $linkable = ContentbuilderngHelper::listLinkable('form', $row, $i);
            $editable = ContentbuilderngHelper::listEditable('form', $row, $i);
            $isModifiedElement = is_callable($isModifiedElementSettings) ? (bool) $isModifiedElementSettings($row) : false;
        ?>
            <tr id="cb-row-<?php echo (int) $row->id; ?>" class="<?php echo 'row' . $k; ?>" data-cb-row-id="<?php echo (int) $row->id; ?>">
                <td class="align-top" data-cb-col="id">
                    <?php echo $row->id; ?>
                </td>
                <td class="align-top" data-cb-col="check">
                    <?php echo $checked; ?>
                </td>
                <td class="align-top" data-cb-col="label">
                    <div class="cb-item-label-cell">
                        <div class="cb-item-label-display"
                            id="itemLabels_<?php echo $row->id ?>"
                            onclick="document.getElementById('itemLabels<?php echo $row->id ?>').style.display='block';this.style.display='none';document.getElementById('itemLabels<?php echo $row->id ?>').focus();">
                            <b>
                                <?php echo htmlentities($row->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </b>
                        </div>
                        <input class="form-control form-control-sm"
                            onblur="cbHandleItemLabelBlur(this, <?php echo (int) $row->id; ?>);"
                            onkeydown="if (event.key === 'Enter') { event.preventDefault(); this.blur(); }"
                            id="itemLabels<?php echo $row->id ?>" type="text" style="display:none; width: 100%;"
                            name="jform[itemLabels][<?php echo $row->id ?>]"
                            data-cb-last-saved="<?php echo htmlentities($row->label ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            value="<?php echo htmlentities($row->label ?? '', ENT_QUOTES, 'UTF-8') ?>" />

                        <select class="form-select form-select-sm d-inline-block w-auto cb-item-order-type-select"
                            id="itemOrderTypes<?php echo $row->id ?>" name="jform[itemOrderTypes][<?php echo $row->id ?>]">
                            <option value=""> -
                                <?php echo Text::_('COM_CONTENTBUILDERNG_ORDER_TYPES'); ?> -
                            </option>
                            <option value="CHAR" <?php echo $row->order_type == 'CHAR' ? ' selected="selected"' : '' ?>>
                                <?php echo Text::_('COM_CONTENTBUILDERNG_ELEMENT_TYPE_TEXT'); ?>
                            </option>
                            <option value="DATETIME" <?php echo $row->order_type == 'DATETIME' ? ' selected="selected"' : '' ?>>
                                <?php echo Text::_('COM_CONTENTBUILDERNG_ORDER_TYPES_DATETIME'); ?>
                            </option>
                            <option value="DATE" <?php echo $row->order_type == 'DATE' ? ' selected="selected"' : '' ?>>
                                <?php echo Text::_('COM_CONTENTBUILDERNG_ORDER_TYPES_DATE'); ?>
                            </option>
                            <option value="TIME" <?php echo $row->order_type == 'TIME' ? ' selected="selected"' : '' ?>>
                                <?php echo Text::_('COM_CONTENTBUILDERNG_ORDER_TYPES_TIME'); ?>
                            </option>
                            <option value="UNSIGNED" <?php echo $row->order_type == 'UNSIGNED' ? ' selected="selected"' : '' ?>>
                                <?php echo Text::_('COM_CONTENTBUILDERNG_ORDER_TYPES_INTEGER'); ?>
                            </option>
                            <option value="DECIMAL" <?php echo $row->order_type == 'DECIMAL' ? ' selected="selected"' : '' ?>>
                                <?php echo Text::_('COM_CONTENTBUILDERNG_ORDER_TYPES_DECIMAL'); ?>
                            </option>
                        </select>
                    </div>
                </td>
                <td class="align-top" data-cb-col="list">
                    <?php echo $listInclude; ?>
                </td>
                <td class="align-top" data-cb-col="search">
                    <?php echo $searchInclude; ?>
                </td>
                <td class="align-top" data-cb-col="link">
                    <?php echo $linkable; ?>
                </td>
                <td class="align-top" data-cb-col="edit">
                    <?php echo $editable; ?>
                    <?php
                    if (!($item->edit_by_type ?? false) && (($row->editable ?? null) || $isModifiedElement)) {
                        $typeBadgeClass = $isModifiedElement ? 'is-modified' : 'is-default';
                        $typeBadgeTitle = $isModifiedElement ? ' title="' . htmlentities('Element settings changed from default', ENT_QUOTES, 'UTF-8') . '"' : '';
                        echo '<div class="mt-1"><a class="cb-item-type-badge ' . $typeBadgeClass . '" href="index.php?option=com_contentbuilderng&amp;view=elementoptions&amp;tmpl=component&amp;element_id=' . $row->id . '&amp;id=' . (int) ($item->id ?? 0) . '" data-bs-toggle="modal" data-bs-target="#text-type-modal"' . $typeBadgeTitle . '>' . ($isModifiedElement ? 'Modified' : 'Default') . '</a></div>';
                    }
                    ?>
                </td>
                <td class="align-top" data-cb-col="wordwrap">
                    <input class="form-control form-control-sm cb-wordwrap-input" type="text" size="4" maxlength="4" inputmode="numeric" pattern="[0-9]{0,4}" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,4);"
                        name="jform[itemWordwrap][<?php echo $row->id ?>]"
                        value="<?php echo htmlentities($row->wordwrap ?? '', ENT_QUOTES, 'UTF-8') ?>" />
                </td>
                <td class="align-top" data-cb-col="publish">
                    <?php echo $published; ?>
                </td>
                <td class="order align-top" data-cb-col="order">
                    <?php
                    $orderUp = '';
                    $orderDown = '';
                    if ($pagination) {
                        $orderUp = (string) $pagination->orderUpIcon($i, true, 'form.orderup', 'Move Up', $ordering);
                        $orderDown = (string) $pagination->orderDownIcon($i, $n, true, 'form.orderdown', 'Move Down', $ordering);
                    }
                    ?>
                    <span class="cb-order-slot">
                        <?php echo $orderUp !== '' ? $orderUp : '<span class="cb-order-placeholder">•</span>'; ?>
                    </span>
                    <span class="cb-order-slot">
                        <?php echo $orderDown !== '' ? $orderDown : '<span class="cb-order-placeholder">•</span>'; ?>
                    </span>
                    <?php $disabled = $ordering ? '' : 'disabled="disabled"'; ?>
                    <input
                        type="text"
                        name="jform[order][<?php echo (int) $row->id; ?>]"
                        size="3"
                        value="<?php echo (int) $row->ordering; ?>"
                        <?php echo $disabled; ?>
                        class="text_area cb-order-input-field" />
                </td>
            </tr>
        <?php
            $k = 1 - $k;
        }
        ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="10">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <?php echo $pagination ? $pagination->getPagesCounter() : ''; ?>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_DISPLAY_NUM'); ?>&nbsp;</span>
                        <span class="d-inline-block cb-form-elements-pagination">
                            <?php echo $pagination ? $pagination->getLimitBox() : ''; ?>
                        </span>
                    </div>

                    <div class="cb-form-elements-pagination">
                        <?php echo $pagination ? $pagination->getPagesLinks() : ''; ?>
                    </div>
                </div>
            </td>
        </tr>
    </tfoot>
</table>
</div>
