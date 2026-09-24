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
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Session\Session;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;

/** @var AdministratorApplication $app */
$app = \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication();
$session = $app->getSession();

$listOrder = $this->state ? (string) $this->state->get('list.ordering', 'ordering') : 'ordering';
$listDirn  = $this->state ? (string) $this->state->get('list.direction', 'asc') : 'asc';
$listDirn  = strtolower($listDirn) === 'desc' ? 'desc' : 'asc';
$storageId = (int) ($this->item->id ?? 0);
$limitValue = (int) $this->state?->get('list.limit', 0);
$fields = $this->fields ?? [];
$fieldsCount = is_countable($fields) ? count($fields) : 0;
$recordsCount = isset($this->storageRecordsCount) ? $this->storageRecordsCount : null;
$storageTableExists = $this->storageTableExists ?? null;
$storageTableLookupName = trim((string) ($this->storageTableLookupName ?? ''));
$storageTableErrorMessage = trim((string) ($this->storageTableErrorMessage ?? ''));
$storageMode = (int) ($this->item->bytable ?? 0);
$storageModeKey = match ($storageMode) {
    2 => 'COM_CONTENTBUILDERNG_STORAGE_MODE_EXTERNAL_SYSTEM',
    1 => 'COM_CONTENTBUILDERNG_STORAGE_MODE_EXTERNAL',
    default => 'COM_CONTENTBUILDERNG_STORAGE_MODE_INTERNAL',
};
$storageName = trim((string) ($this->item->name ?? ''));
$dataTableName = $this->dataTableName !== '' ? $this->dataTableName : '-';
$createdBy = trim((string) ($this->item->created_by ?? ''));
$modifiedBy = trim((string) ($this->item->modified_by ?? ''));
$csvImportRequested = $app->getInput()->getBool('csv_import', false);
$requestedTab = trim((string) $app->getInput()->getCmd('tabStartOffset', ''));
// Le nouvel onglet "Stockage" est le point d'entrée, à la création comme à
// l'édition. Les champs restent accessibles dans l'onglet "Champs".
$defaultTab = 'tab1';
$activeTab = preg_match('/^tab(?:\d+|Data)$/', $requestedTab) ? $requestedTab : $defaultTab;
$isPublished = ((int) ($this->item->published ?? 0) === 1);
$publishedIconClass = $isPublished ? 'fa-solid fa-check text-success' : 'fa-solid fa-circle-xmark text-danger';
$publishedIconTitle = $isPublished ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED');
$publishedToggleHtml = '';
if ((int) ($this->item->id ?? 0) > 0) {
    $publishedToggleHtml = HTMLHelper::_(
        'jgrid.published',
        $isPublished ? 1 : 0,
        0,
        'storage.',
        true,
        'cbstorageitem'
    );
    $publishedToggleHtml = preg_replace(
        ['/storage\.publish\b/', '/storage\.unpublish\b/'],
        ['storage.publishItem', 'storage.unpublishItem'],
        (string) $publishedToggleHtml
    ) ?? (string) $publishedToggleHtml;
    $publishedToggleHtml = preg_replace('/\saria-labelledby="[^"]*"/', '', (string) $publishedToggleHtml) ?? (string) $publishedToggleHtml;
    $publishedToggleHtml = preg_replace('#<div role="tooltip"[^>]*>.*?</div>#s', '', (string) $publishedToggleHtml) ?? (string) $publishedToggleHtml;
}
$csvToggleTooltip = Text::_('COM_CONTENTBUILDERNG_STORAGE_CSV_TOGGLE_TOOLTIP');
$addFieldTooltip = Text::_('COM_CONTENTBUILDERNG_STORAGE_ADD_FIELD_TOOLTIP');
$tabStorageTooltip = Text::_('COM_CONTENTBUILDERNG_STORAGE_TAB_TOOLTIP');
$tabInfoTooltip = Text::_('COM_CONTENTBUILDERNG_STORAGE_INFO_TAB_TOOLTIP');
$tabIndexTooltip = Text::_('COM_CONTENTBUILDERNG_STORAGE_INDEX_TAB_TOOLTIP');
$tabDataTooltip = Text::_('COM_CONTENTBUILDERNG_STORAGE_DATA_TAB_TOOLTIP');
$tabFormsTooltip = Text::_('COM_CONTENTBUILDERNG_STORAGE_USING_FORMS_TAB_TOOLTIP');
$storageTabLabel = static function (string $iconClass, string $labelKey): string {
    return '<span class="' . htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></span> '
        . htmlspecialchars(Text::_($labelKey), ENT_QUOTES, 'UTF-8');
};

$formatDate = static function ($date): string {
    $value = trim((string) $date);

    if ($value === '' || str_starts_with($value, '0000-00-00')) {
        return '-';
    }

    return HTMLHelper::_('date', $value, Text::_('DATE_FORMAT_LC5'));
};

$fullOrdering = trim($listOrder . ' ' . strtoupper($listDirn));

$sortLink = static function (string $label, string $field) use ($listDirn, $listOrder): string {
    return HTMLHelper::_('searchtools.sort', $label, $field, $listDirn, $listOrder);
};

$renderCheckbox = static function (string $name, string $id, bool $checked = false): string {
    return '<span class="form-check d-inline-block mb-0"><input class="form-check-input" type="checkbox" name="'
        . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8')
        . '" value="1"' . ($checked ? ' checked="checked"' : '') . ' /></span>';
};

$wa = $app->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
$wa->useStyle('com_contentbuilderng.admin-storage');

?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('adminForm');

    if (!form) {
        return;
    }

    var setValue = function(name, value) {
        var element = form.elements[name];
        if (element) {
            element.value = value;
        }
    };

    document.querySelectorAll('#adminForm .js-stools-column-order').forEach(function(link) {
        link.addEventListener('click', function(event) {
            event.preventDefault();

            var order = String(link.getAttribute('data-order') || '');
            var dir = String(link.getAttribute('data-direction') || 'ASC').toUpperCase();

            setValue('filter_order', order);
            setValue('filter_order_Dir', dir.toLowerCase());
            setValue('list[ordering]', order);
            setValue('list[direction]', dir.toLowerCase());
            setValue('list[fullordering]', order !== '' ? (order + ' ' + dir) : '');
            setValue('limitstart', 0);
            setValue('task', 'storage.display');

            form.submit();
        });
    });

    var storageFieldsTable = document.querySelector('.cb-storage-fields-table[data-cb-storage-fields-ordering="1"]');
    if (storageFieldsTable && storageFieldsTable.tBodies.length) {
        var storageFieldsBody = storageFieldsTable.tBodies[0];
        var draggedRow = null;

        var getOrderRows = function() {
            return Array.prototype.slice.call(storageFieldsBody.querySelectorAll('tr[data-cb-row-id]'));
        };

        var getRowOrderInput = function(row) {
            return row ? row.querySelector('input[name="order[]"]') : null;
        };

        var refreshStorageFieldOrderValues = function() {
            var rows = getOrderRows();
            var values = rows.map(function(row, index) {
                var input = getRowOrderInput(row);
                var value = input ? parseInt(input.value, 10) : 0;

                return Number.isFinite(value) && value > 0 ? value : index + 1;
            }).sort(function(left, right) { return left - right; });

            rows.forEach(function(row, index) {
                var input = getRowOrderInput(row);
                if (input) {
                    input.value = String(values[index] || index + 1);
                }
            });
        };

        var prepareOrderSubmitIds = function() {
            form.querySelectorAll('input[data-cb-storage-fields-order-cid="1"]').forEach(function(input) {
                input.remove();
            });

            getOrderRows().forEach(function(row) {
                var rowId = String(row.getAttribute('data-cb-row-id') || '');
                if (rowId === '') {
                    return;
                }

                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'cid[]';
                input.value = rowId;
                input.setAttribute('data-cb-storage-fields-order-cid', '1');
                form.appendChild(input);
            });
        };

        var getDropTargetRow = function(clientY) {
            var rows = getOrderRows().filter(function(row) { return row !== draggedRow; });

            return rows.reduce(function(closest, row) {
                var box = row.getBoundingClientRect();
                var offset = clientY - box.top - (box.height / 2);

                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, row: row };
                }

                return closest;
            }, { offset: Number.NEGATIVE_INFINITY, row: null }).row;
        };

        storageFieldsBody.querySelectorAll('.cb-storage-fields-drag-handle:not([disabled])').forEach(function(handle) {
            var row = handle.closest('tr[data-cb-row-id]');
            if (!row) {
                return;
            }

            handle.setAttribute('draggable', 'true');

            handle.addEventListener('dragstart', function(event) {
                draggedRow = row;
                row.classList.add('cb-elements-row-dragging');

                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', String(row.getAttribute('data-cb-row-id') || ''));
                }
            });

            handle.addEventListener('dragend', function() {
                row.classList.remove('cb-elements-row-dragging');
                draggedRow = null;
            });
        });

        storageFieldsBody.addEventListener('dragover', function(event) {
            if (!draggedRow) {
                return;
            }

            event.preventDefault();
            var targetRow = getDropTargetRow(event.clientY);

            if (targetRow) {
                storageFieldsBody.insertBefore(draggedRow, targetRow);
            } else {
                storageFieldsBody.appendChild(draggedRow);
            }
        });

        storageFieldsBody.addEventListener('drop', function(event) {
            if (!draggedRow) {
                return;
            }

            event.preventDefault();
            refreshStorageFieldOrderValues();
            prepareOrderSubmitIds();

            if (typeof Joomla !== 'undefined' && typeof Joomla.submitbutton === 'function') {
                Joomla.submitbutton('storage.saveorder');
            }
        });
    }
});

const cbSaveAnimationDurationMs = 500;
const cbStorageColumnsStateKey = 'cbng.storage.columns.<?php echo (int) ($this->item->id ?? 0); ?>';
const cbStorageColumnsLabel = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_COLUMNS'), JSON_UNESCAPED_UNICODE); ?>;
const cbPublishedTitle = <?php echo json_encode(Text::_('JPUBLISHED'), JSON_UNESCAPED_UNICODE); ?>;
const cbUnpublishedTitle = <?php echo json_encode(Text::_('JUNPUBLISHED'), JSON_UNESCAPED_UNICODE); ?>;
const cbCloseUnsavedMessage = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_CONFIRM_CLOSE_UNSAVED'), JSON_UNESCAPED_UNICODE); ?>;
const cbSaveFailedMessage = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_SAVE_FAILED'), JSON_UNESCAPED_UNICODE); ?>;
const cbFieldNamePlaceholder = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_NAME'), JSON_UNESCAPED_UNICODE); ?>;
const cbFieldTitlePlaceholder = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_LIST_STATES_TITLE'), JSON_UNESCAPED_UNICODE); ?>;
const cbFieldGroupLabel = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_STORAGE_GROUP'), JSON_UNESCAPED_UNICODE); ?>;
const cbFieldRequiredLabel = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_STORAGE_FIELD_REQUIRED'), JSON_UNESCAPED_UNICODE); ?>;
const cbFieldConfirmLabel = <?php echo json_encode(Text::_('JSAVE'), JSON_UNESCAPED_UNICODE); ?>;
const cbFieldCancelLabel = <?php echo json_encode(Text::_('JCANCEL'), JSON_UNESCAPED_UNICODE); ?>;
const cbStorageId = <?php echo (int) $storageId; ?>;
const cbStorageEditUrl = <?php echo json_encode('index.php?option=com_contentbuilderng&view=storage&layout=edit&id=' . $storageId . '&tabStartOffset=tab0#tab0', JSON_UNESCAPED_SLASHES); ?>;
let cbAjaxBusy = false;
let cbSaveButtonTimer = null;
let cbStorageDirtyState = false;
let cbStorageDirtySnapshot = '';
let cbStorageDirtyTrackingInitialized = false;
let cbStorageDirtyBypassBeforeUnload = false;

function cbAnimateSaveButton() {
    var selectors = [
        'joomla-toolbar-button#save-group-children-apply button',
        'joomla-toolbar-button#save-group-children-save button',
        'joomla-toolbar-button#save-group-children-save2new button',
        '#save-group-children-apply button',
        '#save-group-children-save button',
        '#save-group-children-save2new button',
        '#toolbar .button-apply',
        '#toolbar .button-save',
        '#toolbar .button-save2new',
        '#toolbar .button-save-new'
    ];

    var targets = [];
    selectors.forEach(function(selector) {
        document.querySelectorAll(selector).forEach(function(el) {
            if (!el) {
                return;
            }
            if (el.classList && el.classList.contains('dropdown-toggle-split')) {
                return;
            }
            if (typeof el.closest === 'function' && el.closest('.dropdown-menu')) {
                return;
            }
            if (targets.indexOf(el) === -1) {
                targets.push(el);
            }
        });
    });

    if (!targets.length) {
        return;
    }

    targets.forEach(function(el) {
        el.classList.remove('cb-save-animate');
        void el.offsetWidth;
        el.classList.add('cb-save-animate');
    });

    if (cbSaveButtonTimer) {
        clearTimeout(cbSaveButtonTimer);
        cbSaveButtonTimer = null;
    }

    cbSaveButtonTimer = setTimeout(function() {
        targets.forEach(function(el) {
            el.classList.remove('cb-save-animate');
        });
    }, cbSaveAnimationDurationMs);
}

function cbDismissTransientTooltips() {
    if (window.bootstrap && typeof window.bootstrap.Tooltip === 'function') {
        document.querySelectorAll('[data-bs-toggle="tooltip"], .hasTip, .editlinktip, .js-grid-item-action').forEach(function(el) {
            var instance = window.bootstrap.Tooltip.getInstance(el);
            if (instance && typeof instance.hide === 'function') {
                instance.hide();
            }
        });
    }

    document.querySelectorAll('.tooltip.show').forEach(function(el) {
        el.classList.remove('show');
        el.setAttribute('aria-hidden', 'true');
    });
}

function cbGetToggleTaskMeta(task) {
    var map = {
        'storage.publish': { nextTask: 'storage.unpublish', enabled: true },
        'storage.unpublish': { nextTask: 'storage.publish', enabled: false }
    };

    return map[String(task || '')] || null;
}

function cbUpdateToggleIconClasses(container, enabled) {
    if (!container || !container.classList) {
        return;
    }

    var icons = [];

    function collectIcon(el) {
        if (!el || !el.classList) {
            return;
        }

        var className = String(el.className || '');
        if (className.indexOf('fa-') === -1 && className.indexOf('icon-') === -1) {
            return;
        }

        if (icons.indexOf(el) === -1) {
            icons.push(el);
        }
    }

    collectIcon(container);
    if (typeof container.querySelectorAll === 'function') {
        container.querySelectorAll('span, i').forEach(collectIcon);
    }

    icons.forEach(function(icon) {
        var className = String(icon.className || '');
        var isFontAwesomeIcon = className.indexOf('fa-') !== -1;
        var isLegacyJoomlaIcon = className.indexOf('icon-') !== -1;

        icon.classList.remove(
            'fa-check',
            'fa-circle-xmark',
            'fa-xmark',
            'fa-times',
            'icon-publish',
            'icon-unpublish',
            'icon-check',
            'icon-times',
            'icon-checkbox',
            'icon-checkbox-partial'
        );

        if (isFontAwesomeIcon) {
            icon.classList.add('fa-solid', enabled ? 'fa-check' : 'fa-circle-xmark');
        }

        if (isLegacyJoomlaIcon) {
            icon.classList.add(enabled ? 'icon-publish' : 'icon-unpublish');
        }
    });
}

function cbApplyAjaxToggleState(actionElement, task) {
    if (!actionElement) {
        return;
    }

    var meta = cbGetToggleTaskMeta(task);
    if (!meta) {
        return;
    }

    if (actionElement.hasAttribute('data-item-task')) {
        actionElement.setAttribute('data-item-task', meta.nextTask);
    }
    if (actionElement.hasAttribute('data-submit-task')) {
        actionElement.setAttribute('data-submit-task', meta.nextTask);
    }
    if (actionElement.hasAttribute('data-task')) {
        actionElement.setAttribute('data-task', meta.nextTask);
    }

    var onclick = String(actionElement.getAttribute('onclick') || '');
    if (onclick.indexOf('listItemTask(') !== -1) {
        actionElement.setAttribute(
            'onclick',
            onclick.replace(
                /(listItemTask\(\s*['"][^'"]+['"]\s*,\s*['"])([^'"]+)(['"]\s*\))/,
                '$1' + meta.nextTask + '$3'
            )
        );
    }

    if (actionElement.classList) {
        actionElement.classList.toggle('active', !!meta.enabled);
    }

    var visualHost = actionElement;
    if (typeof actionElement.closest === 'function') {
        var host = actionElement.closest('.tbody-icon, .js-grid-item-action, button, a');
        if (host) {
            visualHost = host;
        }
    }

    if (visualHost !== actionElement && visualHost.classList) {
        visualHost.classList.toggle('active', !!meta.enabled);
    }

    cbUpdateToggleIconClasses(visualHost, !!meta.enabled);
    if (visualHost !== actionElement) {
        cbUpdateToggleIconClasses(actionElement, !!meta.enabled);
    }

    var title = meta.enabled ? cbPublishedTitle : cbUnpublishedTitle;
    visualHost.setAttribute('title', title);
    visualHost.setAttribute('aria-label', title);
    if (visualHost !== actionElement) {
        actionElement.setAttribute('title', title);
        actionElement.setAttribute('aria-label', title);
    }

    var hiddenLabel = visualHost.querySelector('.visually-hidden, .sr-only');
    if (hiddenLabel) {
        hiddenLabel.textContent = title;
    }

    var row = typeof actionElement.closest === 'function'
        ? actionElement.closest('tr[data-cb-row-id][data-cb-system-field="1"]')
        : null;
    if (row) {
        var visibilityToggle = document.querySelector('[data-cb-storage-hide-unpublished-system-fields="1"]');
        var shouldHide = !meta.enabled && (!visibilityToggle || visibilityToggle.checked);

        row.classList.toggle('cb-storage-system-field-unpublished', !meta.enabled);
        row.classList.toggle('d-none', shouldHide);
    }
}

function cbIsAjaxToggleTask(task) {
    return task === 'storage.publish' || task === 'storage.unpublish';
}

function cbExtractListItemTask(actionElement) {
    if (!actionElement) {
        return null;
    }

    var onclick = String(actionElement.getAttribute('onclick') || '');
    var match = onclick.match(/listItemTask\(\s*['"]([^'"]+)['"]\s*,\s*['"]([^'"]+)['"]/);
    if (match) {
        return {
            checkboxId: String(match[1] || ''),
            task: String(match[2] || '')
        };
    }

    var dataTask = String(
        actionElement.getAttribute('data-item-task')
        || actionElement.getAttribute('data-submit-task')
        || actionElement.getAttribute('data-task')
        || ''
    ).trim();

    if (dataTask === '') {
        return null;
    }

    return {
        checkboxId: '',
        task: dataTask.indexOf('.') === -1 ? ('storage.' + dataTask) : dataTask
    };
}

function cbResolveRowId(actionElement, checkboxId) {
    if (actionElement && typeof actionElement.closest === 'function') {
        var row = actionElement.closest('tr[data-cb-row-id]');
        if (row) {
            var rowId = String(row.getAttribute('data-cb-row-id') || '');
            if (rowId !== '') {
                return rowId;
            }
        }
    }

    if (checkboxId !== '') {
        var checkbox = document.getElementById(checkboxId);
        if (checkbox && typeof checkbox.value !== 'undefined' && String(checkbox.value) !== '') {
            return String(checkbox.value);
        }
    }

    return '';
}

function cbSubmitTaskAjax(task, rowId, onSuccess, onError, triggerElement) {
    var form = document.getElementById('adminForm') || document.adminForm;
    if (!form) {
        if (typeof onError === 'function') {
            onError('Form not found.');
        }
        return;
    }

    if (cbAjaxBusy) {
        return;
    }

    cbAjaxBusy = true;
    cbDismissTransientTooltips();

    var formData = new FormData(form);
    formData.set('task', task);
    formData.set('cb_ajax', '1');
    formData.set('option', 'com_contentbuilderng');

    if (rowId) {
        formData.delete('cid[]');
        formData.append('cid[]', String(rowId));
        formData.set('boxchecked', '1');
    }

    var endpoint = form.getAttribute('action') || 'index.php';
    fetch(endpoint, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(function(response) {
            return response.text().then(function(text) {
                var payload = null;
                try {
                    payload = JSON.parse(text);
                } catch (e) {
                    payload = null;
                }

                if (!response.ok || !payload || payload.success === false) {
                    throw new Error((payload && payload.message) ? payload.message : 'Save failed');
                }

                return payload;
            });
        })
        .then(function(payload) {
            cbAnimateSaveButton();
            if (typeof onSuccess === 'function') {
                onSuccess(payload);
            }
        })
        .catch(function(error) {
            if (typeof onError === 'function') {
                onError(error && error.message ? error.message : 'Save failed');
                return;
            }
            alert(error && error.message ? error.message : 'Save failed');
        })
        .finally(function() {
            cbDismissTransientTooltips();
            if (triggerElement && typeof triggerElement.blur === 'function') {
                triggerElement.blur();
            }
            cbAjaxBusy = false;
        });
}

function listItemTask(id, task) {
    var form = document.getElementById('adminForm');
    if (!form) return false;

    form.querySelectorAll('input[type="checkbox"][id^="cb"]').forEach(function (cb) {
        cb.checked = false;
    });

    var target = form.elements[id] || document.getElementById(id);
    if (!target) return false;

    target.checked = true;
    var boxchecked = form.querySelector('input[name="boxchecked"]');
    if (boxchecked) {
        boxchecked.value = 1;
    }

    if (cbIsAjaxToggleTask(task)) {
        var rowId = (typeof target.value !== 'undefined' && target.value !== '') ? String(target.value) : '';
        var actionElement = null;

        if (typeof target.closest === 'function') {
            var row = target.closest('tr[data-cb-row-id]');
            if (row) {
                actionElement = row.querySelector(
                    '[data-item-task="' + task + '"], [data-submit-task="' + task + '"], [data-task="' + task + '"], [onclick*="' + task + '"]'
                );
            }
        }

        cbSubmitTaskAjax(task, rowId, function() {
            cbApplyAjaxToggleState(actionElement, task);
        }, null, actionElement);
        return false;
    }

    if (task === 'storage.publishItem' || task === 'storage.unpublishItem') {
        var tabField = form.querySelector('input[name="tabStartOffset"]');
        if (tabField) {
            tabField.value = 'tab1';
        }
    }

    cbStorageBypassDirtyBeforeUnload();
    Joomla.submitform(task, form);
    return false;
}

if (typeof Joomla !== 'undefined') {
    Joomla.listItemTask = listItemTask;
}

function cbDeleteStorageField(checkboxId) {
    var checkbox = document.getElementById(checkboxId);
    var row = checkbox && typeof checkbox.closest === 'function' ? checkbox.closest('tr[data-cb-item-label]') : null;
    var label = row ? row.getAttribute('data-cb-item-label') : '';
    var message = (label && window.Joomla && typeof Joomla.Text._ === 'function')
        ? Joomla.Text._('COM_CONTENTBUILDERNG_CONFIRM_DELETE_ONE').replace('%s', label)
        : null;

    if (message !== null && !window.confirm(message)) {
        return false;
    }

    return listItemTask(checkboxId, 'storage.listDelete');
}

function cbDeleteStorageIndex(indexName) {
    var message = (window.Joomla && typeof Joomla.Text._ === 'function')
        ? Joomla.Text._('COM_CONTENTBUILDERNG_CONFIRM_DELETE_ONE').replace('%s', indexName)
        : null;

    if (message !== null && !window.confirm(message)) {
        return false;
    }

    var input = document.getElementById('cb-storage-index-name-input');
    if (input) {
        input.value = indexName;
    }

    cbStorageSubmitbutton('storage.deleteindex');
    return false;
}

function cbStorageShouldIgnoreDirtyField(field) {
    if (!field || !field.name) {
        return true;
    }

    return /^(task|boxchecked|filter_order|filter_order_Dir|list\[ordering\]|list\[direction\]|list\[fullordering\]|limitstart|tabStartOffset|cid\[\])$/.test(field.name)
        || (field.type === 'hidden' && field.name.indexOf('jform[') !== 0);
}

function cbStorageSerializeFormState(form) {
    var data = [];

    Array.prototype.forEach.call(form.elements, function(field) {
        if (cbStorageShouldIgnoreDirtyField(field) || field.disabled) {
            return;
        }

        if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
            return;
        }

        if (field.type === 'file') {
            var files = Array.prototype.map.call(field.files || [], function(file) {
                return file.name + ':' + file.size + ':' + file.lastModified;
            });
            data.push(field.name + '=' + files.join(','));
            return;
        }

        data.push(field.name + '=' + String(field.value || ''));
    });

    return data.sort().join('&');
}

function cbStorageSetDirtyState(isDirty) {
    cbStorageDirtyState = !!isDirty;
}

function cbStorageRefreshDirtyState() {
    var form = document.getElementById('adminForm') || document.adminForm;
    if (!form) {
        return;
    }

    cbStorageSetDirtyState(cbStorageSerializeFormState(form) !== cbStorageDirtySnapshot);
}

function cbStorageMarkDirtySnapshot() {
    var form = document.getElementById('adminForm') || document.adminForm;
    if (!form) {
        return;
    }

    cbStorageDirtySnapshot = cbStorageSerializeFormState(form);
    cbStorageSetDirtyState(false);
}

function cbStorageBypassDirtyBeforeUnload() {
    cbStorageDirtyBypassBeforeUnload = true;
    cbStorageSetDirtyState(false);
}

function cbStorageSubmitbutton(task) {
    var form = document.getElementById('adminForm') || document.adminForm;
    if (!form) {
        return;
    }

    if (task === 'storage.cancel') {
        cbStorageRefreshDirtyState();
        if (cbStorageDirtyState && !confirm(cbCloseUnsavedMessage)) {
            return;
        }
    }

    cbStorageBypassDirtyBeforeUnload();
    if (task === 'storage.cancel') {
        cbStorageSubmitCancel(form, task);
        return;
    }

    Joomla.submitform(task, form);
}

function cbStorageSubmitCancel(form, task) {
    if (!form) {
        return;
    }

    var taskField = form.querySelector('input[name="task"]');
    if (taskField) {
        taskField.value = task;
    }

    form.setAttribute('novalidate', 'novalidate');
    form.noValidate = true;

    HTMLFormElement.prototype.submit.call(form);
}

function cbStorageInitDirtyTracking() {
    var form = document.getElementById('adminForm') || document.adminForm;
    if (!form || cbStorageDirtyTrackingInitialized) {
        return;
    }

    cbStorageDirtyTrackingInitialized = true;
    cbStorageMarkDirtySnapshot();

    form.addEventListener('input', cbStorageRefreshDirtyState, true);
    form.addEventListener('change', cbStorageRefreshDirtyState, true);
    window.addEventListener('focus', cbStorageRefreshDirtyState);
    document.addEventListener('visibilitychange', cbStorageRefreshDirtyState);

    window.addEventListener('beforeunload', function(event) {
        if (cbStorageDirtyBypassBeforeUnload || !cbStorageDirtyState) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });
}

if (typeof Joomla !== 'undefined') {
    Joomla.submitbutton = cbStorageSubmitbutton;
}

function cbStorageInitColumnPicker() {
    var toggleButton = document.getElementById('cb-storage-columns-toggle');
    var menu = document.querySelector('.cb-storage-columns-menu');
    var countLabel = toggleButton ? toggleButton.querySelector('.cb-storage-columns-count') : null;
    var checkboxes = Array.from(document.querySelectorAll('.cb-storage-column-toggle[data-cb-storage-column-toggle="1"]'));
    var resetButton = menu ? menu.querySelector('.cb-storage-columns-reset[data-cb-storage-columns-reset="1"]') : null;

    if (!toggleButton || !menu || !countLabel || !checkboxes.length) {
        return;
    }

    var defaultState = {};
    checkboxes.forEach(function(input) {
        defaultState[String(input.value || '')] = true;
    });

    var totalCount = checkboxes.length;

    var readState = function() {
        try {
            var raw = window.localStorage.getItem(cbStorageColumnsStateKey);
            if (!raw) {
                return Object.assign({}, defaultState);
            }

            var parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') {
                return Object.assign({}, defaultState);
            }

            return Object.assign({}, defaultState, parsed);
        } catch (e) {
            return Object.assign({}, defaultState);
        }
    };

    var writeState = function(state) {
        try {
            window.localStorage.setItem(cbStorageColumnsStateKey, JSON.stringify(state));
        } catch (e) {
            // ignore storage failures
        }
    };

    var updateCountLabel = function(state) {
        var visibleCount = Object.keys(defaultState).filter(function(key) {
            return state[key] !== false;
        }).length;

        countLabel.textContent = visibleCount + '/' + totalCount + ' ' + cbStorageColumnsLabel;
    };

    var applyState = function(state) {
        Object.keys(defaultState).forEach(function(key) {
            var visible = state[key] !== false;

            document.querySelectorAll('[data-cb-storage-col="' + key + '"]').forEach(function(cell) {
                cell.classList.toggle('cb-storage-col-hidden', !visible);
            });
        });

        checkboxes.forEach(function(input) {
            var key = String(input.value || '');
            input.checked = state[key] !== false;
        });

        updateCountLabel(state);
    };

    var state = readState();
    applyState(state);

    checkboxes.forEach(function(input) {
        input.addEventListener('change', function() {
            var key = String(input.value || '');
            var visibleCount = checkboxes.filter(function(item) {
                return item !== input && item.checked;
            }).length;

            if (!input.checked && visibleCount === 0) {
                input.checked = true;
                return;
            }

            state[key] = input.checked;
            writeState(state);
            applyState(state);
        });
    });

    if (resetButton) {
        resetButton.addEventListener('click', function() {
            state = Object.assign({}, defaultState);
            writeState(state);
            applyState(state);
        });
    }
}

function initStorageSystemFieldVisibility() {
    var toggle = document.querySelector('[data-cb-storage-hide-unpublished-system-fields="1"]');
    var rows = Array.prototype.slice.call(document.querySelectorAll('tr.cb-storage-system-field-unpublished'));

    if (!toggle || !rows.length) {
        return;
    }

    var stateKey = 'cbng.storage.hide-unpublished-system-fields.<?php echo (int) ($this->item->id ?? 0); ?>';
    var hideFields = true;

    try {
        var storedState = window.localStorage.getItem(stateKey);
        if (storedState !== null) {
            hideFields = storedState !== '0';
        }
    } catch (e) {
        // Keep the default when local storage is unavailable.
    }

    var applyVisibility = function () {
        rows.forEach(function (row) {
            row.classList.toggle('d-none', hideFields);
        });
        toggle.checked = hideFields;
    };

    applyVisibility();

    toggle.addEventListener('change', function () {
        hideFields = toggle.checked;
        applyVisibility();

        try {
            window.localStorage.setItem(stateKey, hideFields ? '1' : '0');
        } catch (e) {
            // Ignore storage failures; the current page state still applies.
        }
    });
}

function toggleCsvUploadOptions() {
    var panel = document.getElementById('csvUpload');
    if (!panel) return false;

    var isHidden = panel.style.display === 'none' || window.getComputedStyle(panel).display === 'none';
    panel.style.display = isHidden ? '' : 'none';

    var trigger = document.getElementById('csvToggleButton');
    if (trigger) {
        trigger.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
    }

    return false;
}


function initStorageInlineAddField() {
    var addButtons = Array.prototype.slice.call(document.querySelectorAll('.cb-storage-field-add'));
    var tbody = document.getElementById('cb-storage-fields-tbody');
    if (!addButtons.length || !tbody) {
        return;
    }

    var setAddButtonsDisabled = function (disabled) {
        addButtons.forEach(function (button) {
            button.disabled = disabled;
        });
    };

    var optionsScript = document.getElementById('cb-storage-field-sql-types');
    var sqlTypeOptions = {};
    if (optionsScript) {
        try {
            sqlTypeOptions = JSON.parse(optionsScript.textContent || '{}');
        } catch (e) {
            sqlTypeOptions = {};
        }
    }

    var sizeLimitsScript = document.getElementById('cb-storage-field-sql-size-limits');
    var sqlTypeSizeLimits = {};
    if (sizeLimitsScript) {
        try {
            sqlTypeSizeLimits = JSON.parse(sizeLimitsScript.textContent || '{}');
        } catch (e) {
            sqlTypeSizeLimits = {};
        }
    }

    function buildTypeOptionsHtml() {
        var html = '';
        Object.keys(sqlTypeOptions).forEach(function (value) {
            html += '<option value="' + value + '">' + sqlTypeOptions[value] + '</option>';
        });
        return html;
    }

    function insertNewFieldRow() {
        if (tbody.querySelector('.cb-storage-field-new-row')) {
            return;
        }

        var row = document.createElement('tr');
        row.className = 'cb-storage-field-new-row table-active';
        row.innerHTML =
            '<td class="text-center" data-cb-storage-col="check"><span class="fa-solid fa-plus text-success" aria-hidden="true"></span></td>' +
            '<td class="text-nowrap" data-cb-storage-col="id">—</td>' +
            '<td data-cb-storage-col="name"><input type="text" class="form-control form-control-sm" name="jform[fieldname]" placeholder="' + cbFieldNamePlaceholder + '"></td>' +
            '<td data-cb-storage-col="title"><input type="text" class="form-control form-control-sm" name="jform[fieldtitle]" placeholder="' + cbFieldTitlePlaceholder + '"></td>' +
            '<td data-cb-storage-col="sql_type"><select class="form-select form-select-sm" name="jform[sql_type]" style="width:auto; max-width:12rem;">' + buildTypeOptionsHtml() + '</select></td>' +
            '<td class="text-nowrap" data-cb-storage-col="field_size"></td>' +
            '<td class="text-center" data-cb-storage-col="required">' +
                '<div class="form-check form-switch d-inline-block">' +
                    '<input class="form-check-input cb-storage-field-new-required" type="checkbox" role="switch" name="jform[required]" value="1" title="' + cbFieldRequiredLabel + '" aria-label="' + cbFieldRequiredLabel + '">' +
                '</div>' +
            '</td>' +
            '<td data-cb-storage-col="group">' +
                '<div class="form-check form-switch mb-1">' +
                    '<input class="form-check-input cb-storage-field-new-is-group" type="checkbox" role="switch" id="cb-storage-field-new-is-group">' +
                    '<label class="form-check-label" for="cb-storage-field-new-is-group">' + cbFieldGroupLabel + '</label>' +
                '</div>' +
                '<textarea class="form-control form-control-sm cb-storage-field-new-group-definition" name="jform[group_definition]" style="display:none;" rows="3">Label 1;value1\nLabel 2;value2\nLabel 3;value3</textarea>' +
                '<input type="hidden" name="jform[is_group]" value="0" class="cb-storage-field-new-is-group-value">' +
            '</td>' +
            '<td class="cb-order-col" data-cb-storage-col="order"></td>' +
            '<td class="text-center" data-cb-storage-col="publish"></td>' +
            '<td class="text-center text-nowrap" data-cb-storage-col="actions">' +
                '<div class="btn-group btn-group-sm cb-storage-field-actions" role="group">' +
                    '<button type="button" class="btn btn-primary cb-storage-field-new-confirm" title="' + cbFieldConfirmLabel + '"><span class="fa-solid fa-floppy-disk" aria-hidden="true"></span></button>' +
                    '<button type="button" class="btn btn-outline-secondary cb-storage-field-new-cancel" title="' + cbFieldCancelLabel + '"><span class="fa-solid fa-xmark" aria-hidden="true"></span></button>' +
                '</div>' +
            '</td>';

        tbody.insertBefore(row, tbody.firstChild);

        var typeSelect = row.querySelector('select[name="jform[sql_type]"]');
        var fieldSizeCell = row.querySelector('[data-cb-storage-col="field_size"]');
        var updateFieldSizeControl = function () {
            var sqlType = typeSelect ? typeSelect.value : '';
            var maximum = Number(sqlTypeSizeLimits[sqlType] || 0);

            if (!fieldSizeCell || !maximum) {
                if (fieldSizeCell) {
                    fieldSizeCell.innerHTML = '';
                }
                return;
            }

            fieldSizeCell.innerHTML = '<input type="number" min="1" max="' + maximum + '" value="' + maximum + '" class="form-control form-control-sm cb-storage-field-new-size" name="jform[field_size]" inputmode="numeric">';
            var fieldSizeInput = fieldSizeCell.querySelector('.cb-storage-field-new-size');
            if (fieldSizeInput) {
                fieldSizeInput.addEventListener('input', function () {
                    if (fieldSizeInput.value !== '' && Number(fieldSizeInput.value) > maximum) {
                        fieldSizeInput.value = String(maximum);
                    }
                });
            }
        };

        if (typeSelect) {
            typeSelect.addEventListener('change', updateFieldSizeControl);
            updateFieldSizeControl();
        }

        var isGroupToggle = row.querySelector('.cb-storage-field-new-is-group');
        var groupDefinition = row.querySelector('.cb-storage-field-new-group-definition');
        var isGroupValue = row.querySelector('.cb-storage-field-new-is-group-value');
        if (isGroupToggle && groupDefinition && isGroupValue) {
            isGroupToggle.addEventListener('change', function () {
                groupDefinition.style.display = isGroupToggle.checked ? '' : 'none';
                isGroupValue.value = isGroupToggle.checked ? '1' : '0';
            });
        }

        var nameInput = row.querySelector('input[name="jform[fieldname]"]');
        if (nameInput) {
            nameInput.focus();
        }

        setAddButtonsDisabled(true);

        function cancelNewFieldRow() {
            row.remove();
            setAddButtonsDisabled(false);
            document.removeEventListener('keydown', onKeyDown);
        }

        function onKeyDown(event) {
            if (event.key === 'Escape') {
                cancelNewFieldRow();
            }
        }

        document.addEventListener('keydown', onKeyDown);

        row.querySelector('.cb-storage-field-new-confirm').addEventListener('click', function () {
            submitNewField(row);
        });
        row.querySelector('.cb-storage-field-new-cancel').addEventListener('click', cancelNewFieldRow);
    }

    function submitNewField(row) {
        var nameInput = row.querySelector('input[name="jform[fieldname]"]');
        if (!nameInput || !nameInput.value.trim()) {
            nameInput.classList.add('is-invalid');
            nameInput.focus();
            return;
        }

        var confirmButton = row.querySelector('.cb-storage-field-new-confirm');
        confirmButton.disabled = true;

        var form = document.getElementById('adminForm') || document.adminForm;
        var formData = new FormData(form);
        // Only the current inline row is relevant to this request. Replacing
        // these keys explicitly prevents stale/duplicate controls from the
        // storage form from being reused on a subsequent field addition.
        formData.set('option', 'com_contentbuilderng');
        formData.set('id', String(cbStorageId));
        formData.set('jform[id]', String(cbStorageId));
        formData.set('cb_ajax', '1');
        formData.set('task', 'storage.ajax_addfield');
        formData.set('jform[fieldname]', nameInput.value);
        formData.set('jform[fieldtitle]', row.querySelector('input[name="jform[fieldtitle]"]').value);
        formData.set('jform[sql_type]', row.querySelector('select[name="jform[sql_type]"]').value);
        formData.set('jform[required]', row.querySelector('.cb-storage-field-new-required').checked ? '1' : '0');
        formData.set('jform[is_group]', row.querySelector('.cb-storage-field-new-is-group-value').value);
        formData.set('jform[group_definition]', row.querySelector('.cb-storage-field-new-group-definition').value);

        var sizeInput = row.querySelector('.cb-storage-field-new-size');
        if (sizeInput) {
            formData.set('jform[field_size]', sizeInput.value);
        }

        fetch('index.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                if (!payload.success) {
                    throw new Error(payload.message || '');
                }
                cbStorageBypassDirtyBeforeUnload();
                window.location.assign(cbStorageEditUrl);
            })
            .catch(function (error) {
                confirmButton.disabled = false;
                window.alert((error && error.message) || cbSaveFailedMessage);
            });
    }

    addButtons.forEach(function (button) {
        button.addEventListener('click', insertNewFieldRow);
    });
}

function initStorageFieldGroupToggle() {
    document.addEventListener('change', function (event) {
        var input = event.target;
        if (!input || !input.matches('input[type="radio"][name^="itemIsGroup["]')) {
            return;
        }

        var row = input.closest('tr');
        if (!row) {
            return;
        }

        var editControl = row.querySelector('[data-cb-group-definition-edit]');
        var definition = row.querySelector('textarea[id^="itemGroupDefinitions"]');
        var isGroup = input.value === '1';

        if (editControl) {
            editControl.hidden = !isGroup;
        }
        if (!isGroup && definition) {
            definition.style.display = 'none';
        }
    });
}

function initStorageFieldEditToggle() {
    document.addEventListener('click', function (event) {
        var button = typeof event.target.closest === 'function'
            ? event.target.closest('.cb-storage-field-edit')
            : null;
        if (!button) {
            return;
        }

        event.preventDefault();

        var row = button.closest('tr[data-cb-row-id]');
        if (!row) {
            return;
        }

        var editing = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', editing ? 'true' : 'false');
        row.querySelectorAll('.cb-storage-field-editable').forEach(function (control) {
            control.disabled = !editing;
        });
    });
}

function cbSubmitFieldTypeUpdate(fieldId, sqlType, fieldSize, onSuccess, onError) {
    var form = document.getElementById('adminForm') || document.adminForm;
    var formData = new FormData(form);
    formData.set('option', 'com_contentbuilderng');
    formData.set('cb_ajax', '1');
    formData.set('task', 'storage.ajax_update_field_type');
    formData.set('field_id', String(fieldId));
    formData.set('sql_type', sqlType);
    formData.set('field_size', String(fieldSize || 0));

    fetch('index.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function (response) { return response.json(); })
        .then(function (payload) {
            if (!payload.success) {
                throw new Error(payload.message || '');
            }
            if (typeof onSuccess === 'function') {
                onSuccess();
            }
        })
        .catch(function (error) {
            if (typeof onError === 'function') {
                onError(error);
            }
        });
}

function cbSubmitFieldRequiredUpdate(fieldId, required, onSuccess, onError) {
    var form = document.getElementById('adminForm') || document.adminForm;
    var formData = new FormData(form);
    formData.set('option', 'com_contentbuilderng');
    formData.set('cb_ajax', '1');
    formData.set('task', 'storage.ajax_update_field_required');
    formData.set('field_id', String(fieldId));
    formData.set('required', required ? '1' : '0');

    fetch('index.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function (response) { return response.json(); })
        .then(function (payload) {
            if (!payload.success) {
                throw new Error(payload.message || '');
            }
            if (typeof onSuccess === 'function') {
                onSuccess();
            }
        })
        .catch(function (error) {
            if (typeof onError === 'function') {
                onError(error);
            }
        });
}

function cbSubmitFieldTitleUpdate(fieldId, title, onSuccess, onError) {
    var form = document.getElementById('adminForm') || document.adminForm;
    var formData = new FormData(form);
    formData.set('option', 'com_contentbuilderng');
    formData.set('cb_ajax', '1');
    formData.set('task', 'storage.ajax_update_field_title');
    formData.set('field_id', String(fieldId));
    formData.set('title', title);

    fetch('index.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function (response) { return response.json(); })
        .then(function (payload) {
            if (!payload.success) {
                throw new Error(payload.message || '');
            }
            if (typeof onSuccess === 'function') {
                onSuccess();
            }
        })
        .catch(function (error) {
            if (typeof onError === 'function') {
                onError(error);
            }
        });
}

function initStorageFieldTitleInput() {
    document.addEventListener('change', function (event) {
        var input = event.target;
        if (!input || !input.classList || !input.classList.contains('cb-storage-field-title-input')) {
            return;
        }

        var previousValue = input.dataset.previousValue || '';
        var nextValue = input.value.trim();
        var fieldId = parseInt(input.dataset.fieldId || '0', 10);

        if (nextValue === previousValue) {
            input.value = nextValue;
            return;
        }

        input.disabled = true;
        cbSubmitFieldTitleUpdate(fieldId, nextValue, function () {
            input.value = nextValue;
            input.dataset.previousValue = nextValue;
            var row = input.closest('tr');
            var hiddenTitle = row ? row.querySelector('input[name="itemTitles[' + fieldId + ']"]') : null;
            if (hiddenTitle) {
                hiddenTitle.value = nextValue;
            }
            var editButton = row ? row.querySelector('.cb-storage-field-edit') : null;
            input.disabled = editButton ? editButton.getAttribute('aria-expanded') !== 'true' : false;
        }, function (error) {
            input.value = previousValue;
            var row = input.closest('tr');
            var editButton = row ? row.querySelector('.cb-storage-field-edit') : null;
            input.disabled = editButton ? editButton.getAttribute('aria-expanded') !== 'true' : false;
            window.alert((error && error.message) || cbSaveFailedMessage);
        });
    });
}

function initStorageFieldRequiredToggle() {
    document.addEventListener('click', function (event) {
        var toggle = typeof event.target.closest === 'function'
            ? event.target.closest('.cb-storage-field-required-toggle')
            : null;
        if (!toggle) {
            return;
        }

        event.preventDefault();

        var fieldId = parseInt(toggle.dataset.fieldId || '0', 10);
        var nextRequired = toggle.dataset.required !== '1';
        var icon = toggle.querySelector('.cb-storage-field-required-icon');
        var hiddenLabel = toggle.querySelector('.visually-hidden');

        toggle.disabled = true;
        cbSubmitFieldRequiredUpdate(fieldId, nextRequired, function () {
            toggle.dataset.required = nextRequired ? '1' : '0';
            var title = nextRequired ? toggle.dataset.yesTitle : toggle.dataset.noTitle;
            toggle.setAttribute('title', title);
            toggle.setAttribute('data-bs-original-title', title);
            if (hiddenLabel) {
                hiddenLabel.textContent = title;
            }
            if (icon) {
                icon.className = 'cb-storage-field-required-icon '
                    + (nextRequired ? 'fa-solid fa-asterisk text-danger' : 'fa-regular fa-circle text-muted');
            }
            toggle.disabled = false;
        }, function (error) {
            toggle.disabled = false;
            window.alert((error && error.message) || cbSaveFailedMessage);
        });
    });
}

function initStorageFieldTypeSelect() {
    document.addEventListener('change', function (event) {
        var select = event.target;
        if (select && select.classList && select.classList.contains('cb-storage-field-type-select')) {
            var fieldId = parseInt(select.dataset.fieldId || '0', 10);
            var sqlType = select.value;
            var previousValue = select.dataset.previousValue || sqlType;
            var row = select.closest('tr');
            var sizeCell = row ? row.querySelector('[data-cb-storage-col="field_size"]') : null;
            var sizeInput = sizeCell ? sizeCell.querySelector('.cb-storage-field-size-input') : null;
            var selectedOption = select.options[select.selectedIndex];
            var supportsSize = selectedOption && selectedOption.dataset.supportsSize === '1';
            var defaultSize = selectedOption ? parseInt(selectedOption.dataset.defaultSize || '0', 10) : 0;
            var maximumSize = selectedOption ? parseInt(selectedOption.dataset.maxSize || '0', 10) : 0;

            // La taille dépend du type : on régénère la cellule pour
            // basculer entre "champ modifiable" et "—" (non applicable).
            if (sizeCell) {
                if (supportsSize) {
                    var editEnabled = row && row.querySelector('.cb-storage-field-edit[aria-expanded="true"]') !== null;
                    sizeCell.innerHTML = '<input type="number" min="1" class="form-control form-control-sm cb-storage-field-size-input cb-storage-field-editable" '
                        + 'max="' + maximumSize + '" data-field-id="' + fieldId + '" data-sql-type="' + sqlType + '" data-previous-value="' + defaultSize + '" '
                        + 'style="width:6rem;" value="' + defaultSize + '"' + (editEnabled ? '' : ' disabled') + '>';
                } else {
                    sizeCell.innerHTML = '&mdash;';
                }
            }

            select.disabled = true;
            cbSubmitFieldTypeUpdate(fieldId, sqlType, supportsSize ? defaultSize : 0, function () {
                select.dataset.previousValue = sqlType;
                select.disabled = !row || row.querySelector('.cb-storage-field-edit[aria-expanded="true"]') === null;
            }, function (error) {
                select.value = previousValue;
                select.disabled = !row || row.querySelector('.cb-storage-field-edit[aria-expanded="true"]') === null;
                window.alert((error && error.message) || cbSaveFailedMessage);
            });
            return;
        }

        var sizeInputChanged = event.target;
        if (!sizeInputChanged || !sizeInputChanged.classList || !sizeInputChanged.classList.contains('cb-storage-field-size-input')) {
            return;
        }

        var sizeRow = sizeInputChanged.closest('tr');
        var typeSelect = sizeRow ? sizeRow.querySelector('.cb-storage-field-type-select') : null;
        var sizeSqlType = typeSelect ? typeSelect.value : (sizeInputChanged.dataset.sqlType || '');
        if (!sizeSqlType) {
            return;
        }

        var sizeFieldId = parseInt(sizeInputChanged.dataset.fieldId || '0', 10);
        var previousSize = sizeInputChanged.dataset.previousValue || sizeInputChanged.value;
        var maximumSize = parseInt(sizeInputChanged.getAttribute('max') || '0', 10);
        if (maximumSize > 0 && Number(sizeInputChanged.value) > maximumSize) {
            sizeInputChanged.value = String(maximumSize);
        }
        sizeInputChanged.disabled = true;

        cbSubmitFieldTypeUpdate(sizeFieldId, sizeSqlType, sizeInputChanged.value, function () {
            sizeInputChanged.dataset.previousValue = sizeInputChanged.value;
            sizeInputChanged.disabled = !sizeRow || sizeRow.querySelector('.cb-storage-field-edit[aria-expanded="true"]') === null;
        }, function (error) {
            sizeInputChanged.value = previousSize;
            sizeInputChanged.disabled = !sizeRow || sizeRow.querySelector('.cb-storage-field-edit[aria-expanded="true"]') === null;
            window.alert((error && error.message) || cbSaveFailedMessage);
        });
    });
}

function initStorageTooltips() {
    var adminUi = window.ContentBuilderNgAdmin;

    if (adminUi && typeof adminUi.initBootstrapTooltips === 'function') {
        adminUi.initBootstrapTooltips(document);
    }
}

function initStorageTabTooltips(attempt) {
    var adminUi = window.ContentBuilderNgAdmin;

    if (!adminUi || typeof adminUi.applyTabTooltips !== 'function') {
        return;
    }

    adminUi.applyTabTooltips('view-pane', {
        tab0: <?php echo json_encode($tabStorageTooltip, JSON_UNESCAPED_UNICODE); ?>,
        tab1: <?php echo json_encode($tabInfoTooltip, JSON_UNESCAPED_UNICODE); ?>,
        tab2: <?php echo json_encode($tabIndexTooltip, JSON_UNESCAPED_UNICODE); ?>
        <?php if ($this->showDataTab) : ?>
        , tabData: <?php echo json_encode($tabDataTooltip, JSON_UNESCAPED_UNICODE); ?>
        <?php endif; ?>
        <?php if (!empty($this->usingForms)) : ?>
        , tab3: <?php echo json_encode($tabFormsTooltip, JSON_UNESCAPED_UNICODE); ?>
        <?php endif; ?>
    }, attempt || 0);
}

function initStorageAjaxToggles() {
    var form = document.getElementById('adminForm') || document.adminForm;
    if (!form) {
        return;
    }

    form.addEventListener('click', function(event) {
        var target = event.target;
        if (!target || typeof target.closest !== 'function') {
            return;
        }

        var actionElement = target.closest('[onclick*="listItemTask("], [data-item-task], [data-submit-task], [data-task]');
        if (!actionElement) {
            return;
        }

        var parsed = cbExtractListItemTask(actionElement);
        if (!parsed) {
            return;
        }

        var task = String(parsed.task || '').trim();
        if (!cbIsAjaxToggleTask(task)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }

        var rowId = cbResolveRowId(actionElement, parsed.checkboxId);
        cbSubmitTaskAjax(task, rowId, function() {
            cbApplyAjaxToggleState(actionElement, task);
        }, null, actionElement);
    }, true);
}

function initStorageDataControls() {
    var wrap = document.querySelector('.cb-storage-data-search');

    if (wrap) {
        var input = wrap.querySelector('.cb-storage-data-search-input');
        var button = wrap.querySelector('.cb-storage-data-search-submit');
        var base = wrap.getAttribute('data-cb-search-base') || 'index.php';

        var run = function () {
            var term = input ? input.value.trim() : '';
            var url = base.split('#')[0].replace(/([?&])data_search=[^&]*/, '$1').replace(/[?&]$/, '');
            var sep = url.indexOf('?') === -1 ? '?' : '&';
            cbStorageBypassDirtyBeforeUnload();
            window.location.assign(url + sep + 'data_search=' + encodeURIComponent(term) + '#tabData');
        };

        if (button) {
            button.addEventListener('click', run);
        }
        if (input) {
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    run();
                }
            });
        }
    }

    var limitSelect = document.querySelector('.cb-storage-data-limit');
    if (limitSelect) {
        limitSelect.addEventListener('change', function () {
            var tpl = limitSelect.getAttribute('data-cb-limit-base') || '';
            if (!tpl) {
                return;
            }
            cbStorageBypassDirtyBeforeUnload();
            window.location.assign(tpl.replace('__CBLIMIT__', encodeURIComponent(limitSelect.value)));
        });
    }
}

function initStorageUi() {
    var adminUi = window.ContentBuilderNgAdmin;

    initStorageTooltips();
    cbStorageInitDirtyTracking();
    cbStorageInitColumnPicker();
    initStorageSystemFieldVisibility();
    initStorageAjaxToggles();
    initStorageInlineAddField();
    initStorageFieldGroupToggle();
    initStorageFieldEditToggle();
    initStorageFieldTitleInput();
    initStorageFieldTypeSelect();
    initStorageFieldRequiredToggle();
    initStorageDataControls();
    initStorageTabTooltips();
    if (adminUi && typeof adminUi.persistJoomlaTabset === 'function') {
        // restoreFromStorage désactivé : l'onglet de départ est déterminé
        // côté serveur (Stockage) et ne doit pas être écrasé par le dernier
        // onglet visité sur un autre storage.
        adminUi.persistJoomlaTabset('view-pane', 'cb_active_storage_tab', function(id) {
            adminUi.setHiddenInputValue('tabStartOffset', id);
        }, { restoreFromStorage: false });
    }

    <?php if ($app->getInput()->getBool('csv_import', false)) : ?>
    var csvPanel = document.getElementById('csvUpload');
    if (csvPanel && (csvPanel.style.display === 'none' || window.getComputedStyle(csvPanel).display === 'none')) {
        toggleCsvUploadOptions();
    }
    var csvFileInput = document.getElementById('csv_file');
    if (csvFileInput) {
        csvFileInput.focus();
    }
    <?php endif; ?>
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStorageUi, { once: true });
} else {
    initStorageUi();
}
</script>

<form action="index.php"
    method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

<div class="position-relative">
    <div class="cb-storage-publish-topright position-absolute top-0 end-0 mt-2 me-2" style="z-index:5;">
        <?php if ($storageId > 0) : ?>
            <?php echo $publishedToggleHtml; ?>
            <input type="checkbox"
                name="cid[]"
                id="cbstorageitem0"
                value="<?php echo $storageId; ?>"
                style="display:none" />
        <?php else : ?>
            <span class="<?php echo $publishedIconClass; ?>" aria-hidden="true" title="<?php echo htmlspecialchars($publishedIconTitle, ENT_QUOTES, 'UTF-8'); ?>"></span>
            <span class="visually-hidden"><?php echo htmlspecialchars($publishedIconTitle, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </div>
<?php
// Démarrer les onglets
echo HTMLHelper::_('uitab.startTabSet', 'view-pane', ['active' => $activeTab]);
// Premier onglet : stockage
echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab1', $storageTabLabel('fa-solid fa-database', 'COM_CONTENTBUILDERNG_STORAGE_ADMINISTRATION'));
echo LayoutHelper::render('storage.information_tab', [
    'item' => $this->item,
    'storageId' => $storageId,
    'tables' => $this->tables,
    'tableModes' => $this->tableModes,
    'tableSourceTypes' => $this->tableSourceTypes,
    'tableSourceLabels' => $this->tableSourceLabels,
    'tableSourceType' => $this->tableSourceType,
    'renderCheckbox' => $renderCheckbox,
    'csvToggleTooltip' => $csvToggleTooltip,
    'csvImportRequested' => $csvImportRequested,
    'storageTableExists' => $storageTableExists,
    'storageTableLookupName' => $storageTableLookupName,
    'storageTableErrorMessage' => $storageTableErrorMessage,
    'publishedToggleHtml' => $publishedToggleHtml,
    'publishedIconClass' => $publishedIconClass,
    'publishedIconTitle' => $publishedIconTitle,
    'dataTableName' => $dataTableName,
    'storageModeKey' => $storageModeKey,
    'recordsCount' => $recordsCount,
    'createdBy' => $createdBy,
    'modifiedBy' => $modifiedBy,
    'formatDate' => $formatDate,
], JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
echo HTMLHelper::_('uitab.endTab');

// Deuxième onglet : champs
echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab0', $storageTabLabel('fa-solid fa-table-list', 'COM_CONTENTBUILDERNG_STORAGE'));
?>

<?php
echo LayoutHelper::render('storage.storage_tab', [
    'item' => $this->item,
    'storageId' => $storageId,
    'renderCheckbox' => $renderCheckbox,
    'csvToggleTooltip' => $csvToggleTooltip,
    'addFieldTooltip' => $addFieldTooltip,
    'sortLink' => $sortLink,
    'fields' => $fields,
    'fieldsCount' => $fieldsCount,
    'recordsCount' => $recordsCount,
    'pagination' => $this->pagination,
    'ordering' => $this->ordering,
], JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
echo HTMLHelper::_('uitab.endTab');
echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab2', $storageTabLabel('fa-solid fa-list-ol', 'COM_CONTENTBUILDERNG_STORAGE_INDEX'));
echo LayoutHelper::render('storage.index_tab', [
    'item' => $this->item,
    'storageId' => $storageId,
    'indexes' => $this->indexes,
    'indexableColumns' => $this->indexableColumns,
], JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
echo HTMLHelper::_('uitab.endTab');
if ($this->showDataTab) :
    echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tabData', $storageTabLabel('fa-solid fa-table-cells', 'COM_CONTENTBUILDERNG_STORAGE_DATA_TAB'));
    echo LayoutHelper::render('storage.data_tab', [
        'storageId' => $storageId,
        'item' => $this->item,
        'records' => $this->recordItems,
        'columnLabels' => $this->recordColumnLabels,
        'hasPrimaryKey' => $this->recordsHavePrimaryKey,
        'pagination' => $this->recordPagination,
        'listLimit' => $this->recordListLimit,
        'listStart' => $this->recordListStart,
        'search' => $this->recordSearch,
        'ordering' => $this->recordOrdering,
        'direction' => $this->recordDirection,
        'editBaseUrl' => $this->recordEditBaseUrl,
        'activeTab' => $activeTab,
    ], JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    echo HTMLHelper::_('uitab.endTab');
endif;
if (!empty($this->usingForms)) :
    echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab3', $storageTabLabel('fa-solid fa-file-lines', 'COM_CONTENTBUILDERNG_STORAGE_USING_FORMS_LABEL'));
    echo LayoutHelper::render('storage.forms_tab', [
        'usingForms' => $this->usingForms,
    ], JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    echo HTMLHelper::_('uitab.endTab');
endif;
echo HTMLHelper::_('uitab.endTabSet');
?>
</div>

    <div class="clr">
    </div>

    <input type="hidden" name="option" value="com_contentbuilderng" />
    <input type="hidden" name="view" value="storage" />
    <input type="hidden" name="layout" value="edit" />
    <input type="hidden" name="id" value="<?php echo (int) $this->item->id; ?>">
    <input type="hidden" name="task" value="storage.display">
    <?php if ($this->wizardReturnUrl !== '') : ?>
        <input type="hidden" name="return" value="<?php echo htmlspecialchars($this->wizardReturnUrl, ENT_QUOTES, 'UTF-8'); ?>" />
        <?php /* Le formulaire soumet en POST vers action="index.php" (sans querystring) :
                 sans ce champ, wizard=1 (présent seulement dans l'URL GET initiale) serait
                 perdu dès la première action (addfield/apply/save), et donc plus jamais
                 reporté dans les redirections suivantes. */ ?>
        <input type="hidden" name="wizard" value="1" />
    <?php endif; ?>
    <input type="hidden" name="jform[id]" value="<?php echo (int) $this->item->id; ?>" />
    <input type="hidden" name="jform[ordering]" value="<?php echo $this->item->ordering; ?>" />
    <input type="hidden" name="jform[published]" value="<?php echo $this->item->published; ?>" />
    <input type="hidden" name="filter_order" value="<?php echo htmlspecialchars($listOrder, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="filter_order_Dir" value="<?php echo htmlspecialchars($listDirn, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="list[ordering]" value="<?php echo htmlspecialchars($listOrder, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="list[direction]" value="<?php echo htmlspecialchars($listDirn, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" id="list_fullordering" name="list[fullordering]" value="<?php echo htmlspecialchars($fullOrdering, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="limitstart" value="<?php echo (int) \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->getInt('limitstart', 0); ?>" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="tabStartOffset" value="<?php echo htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8'); ?>" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
<script>
// Onglet "Données" : suppression d'un enregistrement via la tâche admin
// storage.deleteRecord (soumission classique de #adminForm).
function cbDeleteStorageRecord(recordId, label) {
    recordId = parseInt(recordId, 10) || 0;
    if (recordId <= 0) {
        return false;
    }

    var message = (label && window.Joomla && typeof Joomla.Text._ === 'function')
        ? Joomla.Text._('COM_CONTENTBUILDERNG_CONFIRM_DELETE_ONE').replace('%s', label)
        : null;
    if (message !== null && !window.confirm(message)) {
        return false;
    }

    var form = document.getElementById('adminForm');
    if (!form) {
        return false;
    }

    form.querySelectorAll('input[data-cb-record-cid="1"]').forEach(function (el) {
        el.remove();
    });

    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'cid[]';
    input.value = String(recordId);
    input.setAttribute('data-cb-record-cid', '1');
    form.appendChild(input);

    var boxchecked = form.querySelector('input[name="boxchecked"]');
    if (boxchecked) {
        boxchecked.value = 1;
    }

    var tabField = form.querySelector('input[name="tabStartOffset"]');
    if (tabField) {
        tabField.value = 'tabData';
    }

    cbStorageBypassDirtyBeforeUnload();
    Joomla.submitform('storage.deleteRecord', form);
    return false;
}
</script>
<script>
(function () {
    var fileInput = document.getElementById('csv_file');
    var toggleButton = document.getElementById('csvToggleButton');
    var fieldsTable = document.querySelector('.cb-storage-fields-table');
    var previewPanel = document.getElementById('cbCsvPreviewPanel');
    var previewBody = document.getElementById('cbCsvPreviewBody');
    if (!fileInput || !toggleButton || !fieldsTable || !previewPanel || !previewBody) {
        return;
    }

    var delimiterInput = document.getElementById('csv_delimiter');
    var repairEncodingInput = document.getElementById('csv_repair_encoding');
    var labelElement = toggleButton.querySelector('.cb-csv-button-label');
    var defaultText = toggleButton.dataset.cbDefaultText || (labelElement ? labelElement.textContent : toggleButton.textContent);
    var createText = toggleButton.dataset.cbCreateText || defaultText;
    var isNewStorage = toggleButton.dataset.cbNewStorage === '1';
    var tokenName = toggleButton.dataset.cbToken || '';
    var previewUrl = 'index.php?option=com_contentbuilderng&task=storage.previewHeaders&format=json';

    var previewNames = new Set();

    function updateButtonLabel(hasFile) {
        if (!labelElement) {
            return;
        }

        var text = defaultText;
        if (isNewStorage) {
            text = createText;
        }

        labelElement.textContent = text;
        toggleButton.setAttribute('title', text);
        toggleButton.setAttribute('data-bs-original-title', text);
        toggleButton.dataset.bsOriginalTitle = text;
    }

    var selectAllCheckbox = document.getElementById('cbCsvSelectAll');

    function removePreviewRows() {
        previewBody.innerHTML = '';
        previewPanel.style.display = 'none';
        previewNames.clear();
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = true;
        }
    }

    function getExistingFieldNames() {
        var inputs = fieldsTable.querySelectorAll('input[name^="itemNames"]');
        var names = new Set();
        inputs.forEach(function (input) {
            var val = (input.value || '').trim();
            if (val !== '') {
                names.add(val.toLowerCase());
            }
        });
        return names;
    }

    function sanitizeName(value) {
        var name = (value || '').trim();
        if (name.normalize) {
            name = name.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        name = name
            .replace(/ß/g, 'ss')
            .replace(/ẞ/g, 'SS')
            .replace(/æ/g, 'ae')
            .replace(/Æ/g, 'AE')
            .replace(/œ/g, 'oe')
            .replace(/Œ/g, 'OE');
        name = name.replace(/[^A-Za-z0-9_]+/g, '_');
        name = name.replace(/^_+|_+$/g, '');
        name = name.replace(/_+/g, '_');
        if (/^[0-9]/.test(name)) {
            name = 'field_' + name;
        }
        if (name === '') {
            name = 'field' + Math.floor(Math.random() * 1000000);
        }
        return name;
    }

    function appendPreviewRow(index, name, title, statusText) {
        var row = document.createElement('tr');
        row.className = 'table-info';
        var checkboxCell = document.createElement('td');
        checkboxCell.innerHTML = '<input type="checkbox" class="form-check-input cb-csv-column-checkbox" '
            + 'name="jform[csv_import_columns][]" value="' + index + '" checked />';
        row.appendChild(checkboxCell);

        var cells = [
            '<code>' + name + '</code>',
            '<span>' + (title || name) + '</span>',
            '<span class="text-muted small">' + statusText + '</span>'
        ];

        row.innerHTML += cells.map(function (cell) {
            return '<td>' + cell + '</td>';
        }).join('');
        previewBody.appendChild(row);
    }

    function updateSelectAllState() {
        if (!selectAllCheckbox) {
            return;
        }
        var checkboxes = previewBody.querySelectorAll('.cb-csv-column-checkbox');
        var checkedCount = previewBody.querySelectorAll('.cb-csv-column-checkbox:checked').length;
        selectAllCheckbox.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            previewBody.querySelectorAll('.cb-csv-column-checkbox').forEach(function (checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
        });
        previewBody.addEventListener('change', function (event) {
            if (event.target && event.target.classList.contains('cb-csv-column-checkbox')) {
                updateSelectAllState();
            }
        });
    }

    function renderPreview(columns) {
        if (!Array.isArray(columns) || !columns.length) {
            removePreviewRows();
            return;
        }
        var existing = getExistingFieldNames();

        removePreviewRows();
        previewPanel.style.display = '';

        columns.forEach(function (raw, index) {
            var column = (raw || '').replace(/\uFEFF/g, '').trim();
            if (column === '') {
                return;
            }

            var sanitized = sanitizeName(column);
            var key = sanitized.toLowerCase();
            if (existing.has(key) || previewNames.has(key)) {
                appendPreviewRow(index, sanitized, column, '<?php echo addslashes(Text::_('COM_CONTENTBUILDERNG_SKIPPED')); ?>');
                return;
            }

            previewNames.add(key);
            existing.add(key);

            appendPreviewRow(index, sanitized, column, '<?php echo addslashes(Text::_('COM_CONTENTBUILDERNG_NEW')); ?>');
        });

        if (!previewBody.children.length) {
            removePreviewRows();
        }
    }

    function handleFileChange() {
        var hasFile = fileInput.files && fileInput.files.length > 0;
        updateButtonLabel(hasFile);
        if (!hasFile) {
            removePreviewRows();
            return;
        }
        if (!tokenName) {
            removePreviewRows();
            return;
        }

        var file = fileInput.files[0];
        var formData = new FormData();
        formData.append('csv_file', file, file.name || 'import.csv');
        formData.append('csv_delimiter', (delimiterInput && delimiterInput.value) ? delimiterInput.value : ',');
        formData.append('csv_repair_encoding', (repairEncodingInput && repairEncodingInput.value) ? repairEncodingInput.value : '');
        formData.append(tokenName, '1');

        fetch(previewUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('preview failed');
                }
                return response.json();
            })
            .then(function (payload) {
                if (payload && payload.success && Array.isArray(payload.data)) {
                    renderPreview(payload.data);
                } else {
                    removePreviewRows();
                }
            })
            .catch(function () {
                removePreviewRows();
            });
    }

    fileInput.addEventListener('change', handleFileChange);
    updateButtonLabel(fileInput.files && fileInput.files.length > 0);
})();
</script>
