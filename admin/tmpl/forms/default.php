<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL 
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;

$app = Factory::getApplication();
$app->getDocument()->getWebAssetManager()->useScript('core');

// Sécurité: valeurs par défaut
$order     = $this->lists['order'] ?? 'a.ordering';
$orderDir  = $this->lists['order_Dir'] ?? 'asc';
$orderDir  = strtolower($orderDir) === 'desc' ? 'desc' : 'asc';

// Les flèches d'ordering ne doivent être actives QUE sur le tri naturel d'ordering.
$saveOrder = ($order === 'a.ordering' && $orderDir === 'asc');

$n = is_countable($this->items) ? count($this->items) : 0;

// Keep start synced with model state, then fallback to request.
$list = (array) $app->getInput()->get('list', [], 'array');
$listStart = (int) ($this->pagination->start ?? ($this->lists['list.start'] ?? 0));
if ($listStart < 0) {
    $listStart = 0;
}
if (isset($list['start'])) {
    $listStart = (int) $list['start'];
} elseif ($app->getInput()->get('limitstart', null, 'raw') !== null) {
    $listStart = (int) $app->getInput()->getInt('limitstart', 0);
}
$limitValue = (int) ($this->state->get('list.limit', (int) ($this->pagination->limit ?? 0)));

$limitOptions = [];
for ($i = 5; $i <= 30; $i += 5) {
    $limitOptions[(string) $i] = (string) $i;
}
$limitOptions['50'] = Text::_('J50');
$limitOptions['100'] = Text::_('J100');
$limitOptions['0'] = Text::_('JALL');

$filterSearch = (string) ($this->lists['filter_search'] ?? '');
$filterStateRaw = strtoupper((string) ($this->lists['filter_state'] ?? ''));
$filterState = in_array($filterStateRaw, ['P', '1', 'PUBLISHED'], true)
    ? 'P'
    : (in_array($filterStateRaw, ['U', '0', 'UNPUBLISHED'], true) ? 'U' : '');
$filterTag = (string) ($this->lists['filter_tag'] ?? '');
$previewLinks = is_array($this->previewLinks ?? null) ? $this->previewLinks : [];
$fullOrdering = trim($order . ' ' . strtoupper($orderDir));
?>
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
const form = document.getElementById('adminForm');

if (!form) {
    return;
}

const setValue = (name, value) => {
    const element = form.elements[name];
    if (element) {
        element.value = value;
    }
};

document.querySelectorAll('#adminForm .js-stools-column-order').forEach(function(link) {
    link.addEventListener('click', function(event) {
        event.preventDefault();

        const order = String(link.getAttribute('data-order') || '');
        const dir = String(link.getAttribute('data-direction') || 'ASC').toUpperCase();

        setValue('filter_order', order);
        setValue('filter_order_Dir', dir.toLowerCase());
        setValue('list[ordering]', order);
        setValue('list[direction]', dir.toLowerCase());
        setValue('list[fullordering]', order !== '' ? (order + ' ' + dir) : '');
        setValue('limitstart', 0);
        setValue('list[start]', 0);

        form.submit();
    });
});

const clearButton = document.getElementById('cb-forms-clear');
const searchInput = document.getElementById('filter_search');
const stateInput = document.getElementById('filter_state');
const tagInput = document.getElementById('filter_tag');

const updateClearButtonState = () => {
    if (!clearButton) {
        return;
    }

    const hasSearch = !!String(searchInput && searchInput.defaultValue || '').trim();
    const hasState = !!String(stateInput && stateInput.value || '').trim();
    const hasTag = !!String(tagInput && tagInput.value || '').trim();
    const isActive = hasSearch || hasState || hasTag;

    clearButton.disabled = !isActive;
    clearButton.classList.toggle('btn-primary', isActive);
    clearButton.classList.toggle('btn-outline-secondary', !isActive);
    clearButton.setAttribute('aria-disabled', isActive ? 'false' : 'true');
};

const filterAndResetPage = () => {
    setValue('limitstart', 0);
    setValue('list[start]', 0);
    form.submit();
};

form.addEventListener('change', function(e) {
    if (e.target.classList.contains('js-cb-filter-change')) {
        updateClearButtonState();
        filterAndResetPage();
    }
});

if (clearButton) {
    clearButton.addEventListener('click', function() {
        if (searchInput) searchInput.value = '';
        if (stateInput) stateInput.value = '';
        if (tagInput) tagInput.value = '';
        filterAndResetPage();
    });
}

updateClearButtonState();

const formsTable = document.querySelector('#cb-forms-list[data-cb-forms-ordering="1"]');
if (formsTable && formsTable.tBodies.length) {
    const formsTableBody = formsTable.tBodies[0];
    let draggedRow = null;

    const getOrderRows = () => Array.prototype.slice.call(formsTableBody.querySelectorAll('tr[data-cb-row-id]'));
    const getRowOrderInput = (row) => row ? row.querySelector('input[name="order[]"]') : null;

    const refreshFormOrderValues = () => {
        const rows = getOrderRows();
        const values = rows.map((row, index) => {
            const input = getRowOrderInput(row);
            const value = input ? parseInt(input.value, 10) : 0;

            return Number.isFinite(value) && value > 0 ? value : index + 1;
        }).sort((left, right) => left - right);

        rows.forEach((row, index) => {
            const input = getRowOrderInput(row);
            if (input) {
                input.value = String(values[index] || index + 1);
            }
        });
    };

    const prepareOrderSubmitIds = () => {
        form.querySelectorAll('input[data-cb-forms-order-cid="1"]').forEach((input) => {
            input.remove();
        });

        getOrderRows().forEach((row) => {
            const rowId = String(row.getAttribute('data-cb-row-id') || '');
            if (rowId === '') {
                return;
            }

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'cid[]';
            input.value = rowId;
            input.setAttribute('data-cb-forms-order-cid', '1');
            form.appendChild(input);
        });
    };

    const getDropTargetRow = (clientY) => {
        const rows = getOrderRows().filter((row) => row !== draggedRow);

        return rows.reduce((closest, row) => {
            const box = row.getBoundingClientRect();
            const offset = clientY - box.top - (box.height / 2);

            if (offset < 0 && offset > closest.offset) {
                return {
                    offset,
                    row
                };
            }

            return closest;
        }, {
            offset: Number.NEGATIVE_INFINITY,
            row: null
        }).row;
    };

    formsTableBody.querySelectorAll('.cb-forms-drag-handle:not([disabled])').forEach((handle) => {
        const row = handle.closest('tr[data-cb-row-id]');
        if (!row) {
            return;
        }

        handle.setAttribute('draggable', 'true');

        handle.addEventListener('dragstart', (event) => {
            draggedRow = row;
            row.classList.add('cb-elements-row-dragging');

            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(row.getAttribute('data-cb-row-id') || ''));
            }
        });

        handle.addEventListener('dragend', () => {
            row.classList.remove('cb-elements-row-dragging');
            draggedRow = null;
        });
    });

    formsTableBody.addEventListener('dragover', (event) => {
        if (!draggedRow) {
            return;
        }

        event.preventDefault();
        const targetRow = getDropTargetRow(event.clientY);

        if (targetRow) {
            formsTableBody.insertBefore(draggedRow, targetRow);
        } else {
            formsTableBody.appendChild(draggedRow);
        }
    });

    formsTableBody.addEventListener('drop', (event) => {
        if (!draggedRow) {
            return;
        }

        event.preventDefault();
        refreshFormOrderValues();
        prepareOrderSubmitIds();

        if (typeof Joomla !== 'undefined' && typeof Joomla.submitbutton === 'function') {
            Joomla.submitbutton('forms.saveorder');
        }
    });
}
});
</script>
<style>
    .cb-forms-preview-link::before,
    .cb-forms-preview-link::after {
        content: none !important;
        display: none !important;
    }

    .cb-preview-head-icon{
        display:inline-flex;
        align-items:center;
        justify-content:center;
    }
</style>
<form action="<?php echo Route::_('index.php?option=com_contentbuilderng&view=forms'); ?>"
    method="post"
    name="adminForm"
    id="adminForm">

    <div id="editcell">
        <div class="js-stools mb-3">
            <div class="clearfix">
                <div class="js-stools-container-bar">
                    <div class="btn-toolbar flex-wrap gap-2" role="toolbar">
                        <div class="input-group input-group-sm" style="max-width: 380px;">
                            <input
                                type="text"
                                name="filter_search"
                                id="filter_search"
                                class="form-control"
                                value="<?php echo htmlspecialchars($filterSearch, ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="<?php echo Text::_('JSEARCH_FILTER'); ?>">
                            <button
                                type="submit"
                                class="btn btn-primary"
                                aria-label="<?php echo htmlspecialchars(Text::_('JSEARCH_FILTER_SUBMIT'), ENT_QUOTES, 'UTF-8'); ?>"
                                title="<?php echo htmlspecialchars(Text::_('JSEARCH_FILTER_SUBMIT'), ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="icon-search" aria-hidden="true"></span>
                                <span class="visually-hidden"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></span>
                            </button>
                            <button
                                id="cb-forms-clear"
                                type="button"
                                class="btn btn-outline-secondary">
                                <?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
                            </button>
                        </div>

                        <div class="btn-group">
                            <label for="filter_state" class="visually-hidden"><?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?></label>
                            <select
                                name="filter_state"
                                id="filter_state"
                                class="form-select form-select-sm js-cb-filter-change">
                                <option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?></option>
                                <option value="P" <?php echo $filterState === 'P' ? 'selected="selected"' : ''; ?>>
                                    <?php echo Text::_('JPUBLISHED'); ?>
                                </option>
                                <option value="U" <?php echo $filterState === 'U' ? 'selected="selected"' : ''; ?>>
                                    <?php echo Text::_('JUNPUBLISHED'); ?>
                                </option>
                            </select>
                        </div>

                        <div class="btn-group">
                            <label for="filter_tag" class="visually-hidden"><?php echo Text::_('COM_CONTENTBUILDERNG_FILTER_TAG'); ?></label>
                            <select
                                class="form-select form-select-sm js-cb-filter-change"
                                id="filter_tag"
                                name="filter_tag">
                                <option value="">
                                    <?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_FILTER_TAG_ALL'), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                                <?php foreach ($this->tags as $tag) : ?>
                                    <option
                                        value="<?php echo htmlspecialchars($tag->tag, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo strtolower($filterTag) === strtolower((string) $tag->tag) ? 'selected="selected"' : ''; ?>>
                                        <?php echo htmlspecialchars($tag->tag, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <table class="table table-striped" id="cb-forms-list" data-name="contentbuilderng-forms" data-cb-forms-ordering="<?php echo $this->ordering ? '1' : '0'; ?>">
            <thead>
                <tr>
                    <th width="5" class="hasTooltip" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_FORMS_COLUMN_ID_TIP'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_CONTENTBUILDERNG_ID', 'a.id', $orderDir, $order); ?>
                    </th>
                    <th width="20" class="hasTooltip" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_COLUMN_SELECT_TIP'), ENT_QUOTES, 'UTF-8'); ?>">
                        <input class="form-check-input" type="checkbox" name="checkall-toggle" value="" onclick="Joomla.checkAll(this);" aria-label="<?php echo htmlspecialchars(Text::_('JGLOBAL_CHECK_ALL'), ENT_QUOTES, 'UTF-8'); ?>">
                    </th>
                    <th width="60" class="text-center hasTooltip" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_FORMS_COLUMN_PREVIEW_TIP'), ENT_QUOTES, 'UTF-8'); ?>">
                        <span
                            class="cb-preview-head-icon"
                        >
                            <span class="fa-solid fa-eye" aria-hidden="true"></span>
                            <span class="visually-hidden"><?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW'); ?></span>
                        </span>
                    </th>
                    <th class="hasTooltip" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_FORMS_COLUMN_NAME_TIP'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_CONTENTBUILDERNG_VIEW_NAME', 'a.name', $orderDir, $order); ?>
                    </th>
                    <th class="hasTooltip" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_FORMS_COLUMN_TAG_TIP'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_CONTENTBUILDERNG_TAG', 'a.tag', $orderDir, $order); ?>
                    </th>
                    <th class="hasTooltip" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_FORMS_COLUMN_SOURCE_TIP'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_CONTENTBUILDERNG_FORM_SOURCE', 'a.title', $orderDir, $order); ?>
                    </th>
                    <th width="90" class="text-center hasTooltip" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_FORMS_COLUMN_TYPE_TIP'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_CONTENTBUILDERNG_TYPE', 'a.type', $orderDir, $order); ?>
                    </th>
                    <th class="w-10 text-nowrap hasTooltip" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_COLUMN_ORDERING_TIP'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_CONTENTBUILDERNG_ORDERBY', 'a.ordering', $orderDir, $order); ?>
                    </th>
                    <th class="text-nowrap hasTooltip" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_COLUMN_MODIFIED_TIP'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_MODIFIED', 'a.modified', $orderDir, $order); ?>
                    </th>
                    <th class="w-1 text-center hasTooltip" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_FORMS_COLUMN_DEBUG_TIP'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_CONTENTBUILDERNG_DEBUG_MODE', 'a.debug_mode', $orderDir, $order); ?>
                    </th>
                    <th class="w-1 text-center hasTooltip" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_FORMS_COLUMN_PUBLISHED_TIP'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_CONTENTBUILDERNG_PUBLISHED', 'a.published', $orderDir, $order); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php
                $k = 0;
                $n = count($this->items);
                for ($i = 0; $i < $n; $i++) {
                    $row = $this->items[$i];
                    $checked = '<input class="form-check-input" type="checkbox" id="cb' . (int) $i . '" name="cid[]" value="' . (int) $row->id . '" onclick="Joomla.isChecked(this.checked);">';
                    $link = Route::_('index.php?option=com_contentbuilderng&task=form.edit&id=' . $row->id);
                    $debug = ContentbuilderngHelper::listDebug('forms', $row, $i);
                    $published = ContentbuilderngHelper::listPublish('forms', $row, $i);
                ?>
                    <tr class="<?php echo "row$k"; ?>" data-cb-row-id="<?php echo (int) $row->id; ?>">
                        <td>
                            <?php echo $row->id; ?>
                        </td>
                        <td>
                            <?php echo $checked; ?>
                        </td>
                        <td class="text-center">
                            <?php $previewUrl = (string) ($previewLinks[(int) $row->id] ?? ''); ?>
                            <?php if ($previewUrl !== '') : ?>
                                <a
                                    class="btn btn-sm btn-link p-0 cb-forms-preview-link"
                                    href="<?php echo htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW'); ?>"
                                >
                                    <span class="fa-solid fa-eye" aria-hidden="true"></span>
                                    <span class="visually-hidden"><?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW'); ?></span>
                                </a>
                            <?php else : ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo $link; ?>">
                                <?php echo $row->name; ?>
                            </a>
                        </td>
                        <td>
                            <a href="<?php echo $link; ?>">
                                <?php echo $row->tag; ?>
                            </a>
                        </td>
                        <td>
                            <a href="<?php echo $link; ?>">
                                <?php
                                $sourceTitle = (string) ($row->source_title ?? $row->title ?? '');
                                echo htmlspecialchars($sourceTitle, ENT_QUOTES, 'UTF-8');
                                ?>
                            </a>
                        </td>
                        <td class="text-center">
                            <a href="<?php echo $link; ?>">
                                <?php
                                $typeCode = (string) ($row->type ?? '');
                                $typeShortMap = [
                                    'com_breezingforms'  => 'BF',
                                    'com_contentbuilderng' => 'CB',
                                    'com_contentbuilderng'  => 'CB',
                                ];
                                $typeShort = $typeShortMap[$typeCode] ?? $typeCode;
                                echo htmlspecialchars($typeShort, ENT_QUOTES, 'UTF-8');
                                ?>
                            </a>
                        </td>
                        <td class="order,text-nowrap">
                            <span>
                                <?php echo $this->pagination->orderUpIcon($i, $saveOrder, 'forms.orderup', Text::_('JLIB_HTML_MOVE_UP'), $this->ordering);
                                ?>
                            </span>
                            <span>
                                <?php echo 
                                $this->pagination->orderDownIcon($i, $n, $saveOrder, 'forms.orderdown', Text::_('JLIB_HTML_MOVE_DOWN'), $this->ordering);
                                ?>
                            </span>
                            <?php $disabled = $this->ordering ? '' : 'disabled="disabled"'; ?>
                            <button type="button"
                                class="btn btn-sm btn-link p-0 me-1 cb-forms-drag-handle<?php echo $this->ordering ? '' : ' disabled'; ?>"
                                title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_DRAG_TO_REORDER'), ENT_QUOTES, 'UTF-8'); ?>"
                                aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_DRAG_TO_REORDER'), ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $this->ordering ? '' : 'disabled="disabled"'; ?>>
                                <span class="fa-solid fa-grip-lines" aria-hidden="true"></span>
                            </button>
                            <input type="hidden"
                                name="order[]"
                                value="<?php echo (int) $row->ordering; ?>"
                                class="cb-forms-order-input" />
                        </td>
                        <td class="text-nowrap">
                            <?php
                            $m = $row->modified ?? '';
                            if ($m && $m !== '0000-00-00 00:00:00') {
                                echo HTMLHelper::_('date', $m, Text::_('DATE_FORMAT_LC5'));
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php echo $debug; ?>
                        </td>
                        <td>
                            <?php echo $published; ?>
                        </td>


                    </tr>
                <?php
                    $k = 1 - $k;
                }
                ?>
            </tbody>
            <tfoot>
            <tr>
                <td colspan="12">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">

                    <div class="d-flex flex-wrap align-items-center gap-2">
                    <?php echo $this->pagination->getPagesCounter(); ?>
                    <span><?php echo Text::_('COM_CONTENTBUILDERNG_DISPLAY_NUM'); ?></span>
                    <span class="d-inline-block">
                        <select name="list[limit]" class="form-select js-select-submit-on-change active" id="list_limit" onchange="document.adminForm.submit();">
                            <?php foreach ($limitOptions as $value => $label) : ?>
                                <option value="<?php echo htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ((string) $value === (string) $limitValue) ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </span>
                    <span><?php echo Text::_('COM_CONTENTBUILDERNG_OF'); ?></span>
                    <span><?php echo (int) ($this->pagination->total ?? 0); ?></span>
                    </div>

                    <div>
                    <?php echo $this->pagination->getPagesLinks(); ?>
                    </div>

                </div>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>

    <input type="hidden" name="option" value="com_contentbuilderng" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="view" value="forms" />
    <input type="hidden" name="limitstart" value="<?php echo (int) $listStart; ?>" />
    <input type="hidden" name="list[start]" value="<?php echo (int) $listStart; ?>" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="filter_order" value="<?php echo htmlspecialchars($order, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="filter_order_Dir" value="<?php echo htmlspecialchars($orderDir, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="list[ordering]" value="<?php echo htmlspecialchars($order, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="list[direction]" value="<?php echo htmlspecialchars($orderDir, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" id="list_fullordering" name="list[fullordering]" value="<?php echo htmlspecialchars($fullOrdering, ENT_QUOTES, 'UTF-8'); ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
