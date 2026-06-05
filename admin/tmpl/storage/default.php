<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL 
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
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
$app = Factory::getApplication();
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
$storageModeKey = ((int) ($this->item->bytable ?? 0) === 1)
    ? 'COM_CONTENTBUILDERNG_STORAGE_MODE_EXTERNAL'
    : 'COM_CONTENTBUILDERNG_STORAGE_MODE_INTERNAL';
$storageName = trim((string) ($this->item->name ?? ''));
$storageTitle = trim((string) ($this->item->title ?? ''));
$dataTableName = $storageName !== '' ? $storageName : '-';
$createdBy = trim((string) ($this->item->created_by ?? ''));
$modifiedBy = trim((string) ($this->item->modified_by ?? ''));
$requestedTab = trim((string) $app->getInput()->getCmd('tabStartOffset', ''));
$activeTab = preg_match('/^tab\d+$/', $requestedTab) ? $requestedTab : 'tab0';
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
$addFieldTooltip = 'Add a new field to this storage.';
$tabStorageTooltip = Text::_('COM_CONTENTBUILDERNG_STORAGE_TAB_TOOLTIP');
$tabInfoTooltip = Text::_('COM_CONTENTBUILDERNG_STORAGE_INFO_TAB_TOOLTIP');
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
$wa->addInlineStyle(
    '.cb-storage-fields-table{width:100%;min-width:620px;table-layout:auto}'
    . '.cb-storage-columns-menu{min-width:14rem;max-width:min(22rem,90vw)}'
    . '.cb-storage-columns-menu .dropdown-item{padding:.35rem .5rem;white-space:normal}'
    . '.cb-storage-col-hidden{display:none!important}'
    . '.cb-storage-fields-table .cb-order-col{width:84px;min-width:84px;text-align:right;white-space:nowrap}'
    . '.cb-storage-fields-table .cb-order-icons{display:inline-flex;justify-content:flex-end;gap:.5rem;width:100%}'
    . '.cb-storage-fields-table .cb-order-icons>span{display:inline-flex}'
    . '.cb-storage-pagination{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:.5rem}'
    . '.cb-storage-pagination .cb-storage-pages{margin-left:auto}'
    . '.cb-storage-pagination .cb-storage-pages .pagination{margin:0!important;text-align:right!important}'
    . '.cb-storage-pagination .cb-storage-pages .pagination ul{display:flex;justify-content:flex-end;flex-wrap:wrap;gap:.35rem;margin:0;padding:0}'
    . '.cb-save-animate{background-color:var(--alert-heading-bg,var(--bs-success,#198754))!important;background-image:none!important;border-color:var(--bs-success,#198754)!important;color:var(--bs-white)!important;filter:brightness(1.2)!important;box-shadow:0 0 0 .38rem rgba(25,135,84,.36)!important;transition:none!important}'
    . '.cb-save-animate .fa-check,.cb-save-animate .fa-xmark,.cb-save-animate .fa-xmark-new{color:var(--bs-white)!important}'
    . '.cb-csv-preview-panel{margin-top:1rem;border:1px solid var(--bs-border-color);border-radius:.5rem;background:var(--bs-body-bg)}'
    . '.cb-csv-preview-panel .cb-csv-preview-head{padding:.5rem .75rem;border-bottom:1px solid var(--bs-border-color);font-weight:600}'
    . '.cb-csv-preview-panel .table{margin-bottom:0}'
    . '.cb-csv-preview-panel .table td,.cb-csv-preview-panel .table th{vertical-align:middle}'
    . 'joomla-tab#view-pane > div[role="tablist"]{display:flex;gap:0;flex-wrap:wrap;padding:0!important;margin-bottom:1rem;background:transparent;white-space:normal;border-block-end:var(--joomla-tablist-border-bottom)}'
    . 'joomla-tab#view-pane > div[role="tablist"] > button[role="tab"]{position:relative;border:0!important;border-radius:0!important;padding:.6rem 1rem!important;font-weight:600;color:var(--body-color)!important;background:var(--body-bg)!important;transition:color .16s ease,background-color .16s ease;display:inline-flex;align-items:center;box-shadow:none!important}'
    . 'joomla-tab#view-pane > div[role="tablist"] > button[role="tab"] > span[class*="fa-"]{margin-inline-end:.35rem}'
    . 'joomla-tab#view-pane > div[role="tablist"] > button[role="tab"] + button[role="tab"]{border-inline-start:1px solid #d7dde5!important}'
    . 'joomla-tab#view-pane > div[role="tablist"] > button[role="tab"]:hover,joomla-tab#view-pane > div[role="tablist"] > button[role="tab"]:focus{background:var(--body-bg)!important;border-radius:0!important;color:var(--joomla-tab-btn-hvr)!important;box-shadow:none!important}'
    . 'joomla-tab#view-pane > div[role="tablist"] > button[role="tab"]:focus-visible{outline:2px solid var(--bs-primary);outline-offset:1px}'
    . 'joomla-tab#view-pane > div[role="tablist"] > button[role="tab"][aria-selected="true"]{color:var(--joomla-tab-btn-hvr)!important;background:var(--joomla-tab-btn-aria-exp-bg)!important;box-shadow:none!important}'
    . 'joomla-tab#view-pane > div[role="tablist"] > button[role="tab"][aria-selected="true"]::after{content:"";position:absolute;left:0;right:0;bottom:0;height:3px;border-radius:0;background:var(--btn-primary-bg)}'
    . '@media (max-width:991.98px){joomla-tab#view-pane > div[role="tablist"]{flex-wrap:nowrap;overflow:auto;-webkit-overflow-scrolling:touch}joomla-tab#view-pane > div[role="tablist"] > button[role="tab"]{white-space:nowrap}}'
);

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
});

const cbSaveAnimationDurationMs = 500;
const cbStorageColumnsStateKey = 'cbng.storage.columns.<?php echo (int) ($this->item->id ?? 0); ?>';
const cbStorageColumnsLabel = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_COLUMNS'), JSON_UNESCAPED_UNICODE); ?>;
const cbPublishedTitle = <?php echo json_encode(Text::_('JPUBLISHED'), JSON_UNESCAPED_UNICODE); ?>;
const cbUnpublishedTitle = <?php echo json_encode(Text::_('JUNPUBLISHED'), JSON_UNESCAPED_UNICODE); ?>;
const cbCloseUnsavedMessage = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_CONFIRM_CLOSE_UNSAVED'), JSON_UNESCAPED_UNICODE); ?>;
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
        tab1: <?php echo json_encode($tabInfoTooltip, JSON_UNESCAPED_UNICODE); ?>
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

function initStorageUi() {
    var adminUi = window.ContentBuilderNgAdmin;

    initStorageTooltips();
    cbStorageInitDirtyTracking();
    cbStorageInitColumnPicker();
    initStorageAjaxToggles();
    initStorageTabTooltips();
    if (adminUi && typeof adminUi.persistJoomlaTabset === 'function') {
        adminUi.persistJoomlaTabset('view-pane', 'cb_active_storage_tab', function(id) {
            adminUi.setHiddenInputValue('tabStartOffset', id);
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStorageUi, { once: true });
} else {
    initStorageUi();
}
</script>

<form action="index.php"
    method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

<?php
// Démarrer les onglets
echo HTMLHelper::_('uitab.startTabSet', 'view-pane', ['active' => $activeTab]);
// Premier onglet
echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab0', $storageTabLabel('fa-solid fa-database', 'COM_CONTENTBUILDERNG_STORAGE'));
?>

<?php
echo LayoutHelper::render('storage.storage_tab', [
    'item' => $this->item,
    'tables' => $this->tables,
    'storageId' => $storageId,
    'renderCheckbox' => $renderCheckbox,
    'csvToggleTooltip' => $csvToggleTooltip,
    'addFieldTooltip' => $addFieldTooltip,
    'sortLink' => $sortLink,
    'fields' => $fields,
    'fieldsCount' => $fieldsCount,
    'pagination' => $this->pagination,
    'ordering' => $this->ordering,
], JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
echo HTMLHelper::_('uitab.endTab');
echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab1', $storageTabLabel('fa-solid fa-circle-info', 'COM_CONTENTBUILDERNG_STORAGE_INFORMATION'));
echo LayoutHelper::render('storage.information_tab', [
    'item' => $this->item,
    'storageTableExists' => $storageTableExists,
    'storageTableLookupName' => $storageTableLookupName,
    'storageTableErrorMessage' => $storageTableErrorMessage,
    'storageName' => $storageName,
    'storageTitle' => $storageTitle,
    'publishedToggleHtml' => $publishedToggleHtml,
    'publishedIconClass' => $publishedIconClass,
    'publishedIconTitle' => $publishedIconTitle,
    'dataTableName' => $dataTableName,
    'storageModeKey' => $storageModeKey,
    'fieldsCount' => $fieldsCount,
    'recordsCount' => $recordsCount,
    'createdBy' => $createdBy,
    'modifiedBy' => $modifiedBy,
    'formatDate' => $formatDate,
], JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
echo HTMLHelper::_('uitab.endTab');
echo HTMLHelper::_('uitab.endTabSet');
?>

    <div class="clr">
    </div>

    <input type="hidden" name="option" value="com_contentbuilderng" />
    <input type="hidden" name="view" value="storage" />
    <input type="hidden" name="layout" value="edit" />
    <input type="hidden" name="id" value="<?php echo (int) $this->item->id; ?>">
    <input type="hidden" name="task" value="storage.display">
    <input type="hidden" name="jform[id]" value="<?php echo (int) $this->item->id; ?>" />
    <input type="hidden" name="jform[ordering]" value="<?php echo $this->item->ordering; ?>" />
    <input type="hidden" name="jform[published]" value="<?php echo $this->item->published; ?>" />
    <input type="hidden" name="filter_order" value="<?php echo htmlspecialchars($listOrder, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="filter_order_Dir" value="<?php echo htmlspecialchars($listDirn, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="list[ordering]" value="<?php echo htmlspecialchars($listOrder, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="list[direction]" value="<?php echo htmlspecialchars($listDirn, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" id="list_fullordering" name="list[fullordering]" value="<?php echo htmlspecialchars($fullOrdering, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="limitstart" value="<?php echo (int) Factory::getApplication()->getInput()->getInt('limitstart', 0); ?>" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="tabStartOffset" value="<?php echo htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8'); ?>" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
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

    function removePreviewRows() {
        previewBody.innerHTML = '';
        previewPanel.style.display = 'none';
        previewNames.clear();
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

    function appendPreviewRow(name, title, statusText) {
        var row = document.createElement('tr');
        row.className = 'table-info';
        var cells = [
            '<code>' + name + '</code>',
            '<span>' + (title || name) + '</span>',
            '<span class="text-muted small">' + statusText + '</span>'
        ];

        row.innerHTML = cells.map(function (cell) {
            return '<td>' + cell + '</td>';
        }).join('');
        previewBody.appendChild(row);
    }

    function renderPreview(columns) {
        if (!Array.isArray(columns) || !columns.length) {
            removePreviewRows();
            return;
        }
        var existing = getExistingFieldNames();

        removePreviewRows();
        previewPanel.style.display = '';

        columns.forEach(function (raw) {
            var column = (raw || '').replace(/\uFEFF/g, '').trim();
            if (column === '') {
                return;
            }

            var sanitized = sanitizeName(column);
            var key = sanitized.toLowerCase();
            if (existing.has(key) || previewNames.has(key)) {
                appendPreviewRow(sanitized, column, '<?php echo addslashes(Text::_('COM_CONTENTBUILDERNG_SKIPPED')); ?>');
                return;
            }

            previewNames.add(key);
            existing.add(key);

            appendPreviewRow(sanitized, column, '<?php echo addslashes(Text::_('COM_CONTENTBUILDERNG_NEW')); ?>');
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
