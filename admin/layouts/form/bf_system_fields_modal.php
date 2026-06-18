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

$item              = $displayData['item'] ?? null;
$allBfSystemFields = is_array($displayData['allBfSystemFields'] ?? null) ? $displayData['allBfSystemFields'] : [];

if (!is_object($item) || $allBfSystemFields === []) {
    return;
}
?>
<div id="cb-bf-system-field-add" class="d-flex align-items-center ms-auto">
    <button type="button"
        id="cb_bf_system_field_add_button"
        class="btn btn-sm btn-outline-secondary hasTooltip"
        title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_MODAL_BUTTON_TIP'), ENT_QUOTES, 'UTF-8'); ?>"
        data-bs-toggle="modal"
        data-bs-target="#cbBfSystemFieldModal">
        <span class="fa-solid fa-list-check me-1" aria-hidden="true"></span><?php echo Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_MODAL_BUTTON'); ?>
    </button>
</div>

<div class="modal fade" id="cbBfSystemFieldModal" tabindex="-1"
    aria-labelledby="cbBfSystemFieldModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cbBfSystemFieldModalLabel">
                    <span class="fa-solid fa-list-check me-2" aria-hidden="true"></span><?php echo Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_MODAL_TITLE'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="<?php echo htmlspecialchars(Text::_('JCLOSE'), ENT_QUOTES, 'UTF-8'); ?>"></button>
            </div>
            <div class="modal-body px-3 py-0">
                <div id="cbBfSystemFieldError" class="alert alert-danger mt-3 mb-0 d-none" role="alert"></div>
                <table class="table table-striped mb-0" id="cbBfSystemFieldTable">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th class="cb-bf-col-num text-end text-muted pe-3" style="width:3rem">#</th>
                            <th class="cb-bf-sortable-col" data-sort-col="label" style="cursor:pointer">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_MODAL_COL_LABEL'); ?>
                                <span class="cb-bf-sort-icon ms-1" aria-hidden="true"></span>
                            </th>
                            <th class="cb-bf-sortable-col" data-sort-col="name" style="cursor:pointer">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_MODAL_COL_NAME'); ?>
                                <span class="cb-bf-sort-icon ms-1" aria-hidden="true"></span>
                            </th>
                            <th class="cb-bf-sortable-col" data-sort-col="type" style="cursor:pointer">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_MODAL_COL_TYPE'); ?>
                                <span class="cb-bf-sort-icon ms-1" aria-hidden="true"></span>
                            </th>
                            <th class="cb-bf-sortable-col" data-sort-col="description" style="cursor:pointer">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_MODAL_COL_DESC'); ?>
                                <span class="cb-bf-sort-icon ms-1" aria-hidden="true"></span>
                            </th>
                            <th class="cb-bf-sortable-col text-center" data-sort-col="included" style="cursor:pointer">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_MODAL_COL_INCLUDED'); ?>
                                <span class="cb-bf-sort-icon ms-1" aria-hidden="true"></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rowNum = 1; foreach ($allBfSystemFields as $systemRefId => $systemField) : ?>
                        <tr>
                            <td class="cb-bf-row-num text-end text-muted pe-3"><?php echo $rowNum++; ?></td>
                            <td data-sort-col="label" data-sort="<?php echo htmlspecialchars(mb_strtolower($systemField['label']), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($systemField['label'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td data-sort-col="name" data-sort="<?php echo htmlspecialchars($systemField['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <code><?php echo htmlspecialchars($systemField['name'], ENT_QUOTES, 'UTF-8'); ?></code>
                            </td>
                            <td data-sort-col="type" data-sort="<?php echo htmlspecialchars($systemField['type'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($systemField['type'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td class="text-muted small" data-sort-col="description" data-sort="<?php echo htmlspecialchars(mb_strtolower($systemField['description']), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($systemField['description'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td class="text-center" data-sort-col="included" data-sort="<?php echo $systemField['added'] ? '1' : '0'; ?>">
                                <div class="form-check form-switch d-inline-block mb-0">
                                    <input type="checkbox"
                                        role="switch"
                                        class="form-check-input cb-bf-system-field-toggle"
                                        id="cbBfField<?php echo abs((int) $systemRefId); ?>"
                                        data-reference-id="<?php echo (int) $systemRefId; ?>"
                                        data-element-id="<?php echo (int) $systemField['element_id']; ?>"
                                        <?php echo $systemField['added'] ? 'checked' : ''; ?>
                                        aria-label="<?php echo htmlspecialchars($systemField['label'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto" id="cbBfSystemFieldStatus"></small>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?php echo Text::_('JCLOSE'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
