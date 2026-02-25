<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms.vcmb.fr
 * @copyright   Copyright (C) 2026 by XDA+GIL 
 * @license     GNU/GPL
 */

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use CB\Component\Contentbuilder_ng\Administrator\Helper\ContentbuilderHelper;

$listOrder = $this->state ? (string) $this->state->get('list.ordering', 'ordering') : 'ordering';
$listDirn  = $this->state ? (string) $this->state->get('list.direction', 'asc') : 'asc';
$listDirn  = strtolower($listDirn) === 'desc' ? 'desc' : 'asc';
$storageId = (int) ($this->item->id ?? 0);
$limitValue = (int) $this->state?->get('list.limit', 0);
$fields = $this->fields ?? [];
$fieldsCount = is_countable($fields) ? count($fields) : 0;
$recordsCount = isset($this->storageRecordsCount) ? $this->storageRecordsCount : null;
$storageModeKey = ((int) ($this->item->bytable ?? 0) === 1)
    ? 'COM_CONTENTBUILDER_NG_STORAGE_MODE_EXTERNAL'
    : 'COM_CONTENTBUILDER_NG_STORAGE_MODE_INTERNAL';
$storageName = trim((string) ($this->item->name ?? ''));
$storageTitle = trim((string) ($this->item->title ?? ''));
$dataTableName = $storageName !== '' ? $storageName : '-';
$createdBy = trim((string) ($this->item->created_by ?? ''));
$modifiedBy = trim((string) ($this->item->modified_by ?? ''));
$isPublished = ((int) ($this->item->published ?? 0) === 1);
$publishedIconClass = $isPublished ? 'icon-publish text-success' : 'icon-unpublish text-danger';
$publishedIconTitle = $isPublished ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED');
$csvToggleTooltip = 'Show or hide CSV/Excel import options.';
$addFieldTooltip = 'Add a new field to this storage.';

$formatDate = static function ($date): string {
    $value = trim((string) $date);

    if ($value === '' || str_starts_with($value, '0000-00-00')) {
        return '-';
    }

    return HTMLHelper::_('date', $value, Text::_('DATE_FORMAT_LC5'));
};

$sortFields = ['name', 'title', 'group_definition', 'ordering', 'published'];
$sortLinks = [];

foreach ($sortFields as $field) {
    $isActive = ($listOrder === $field);
    $nextDir = ($isActive && $listDirn === 'asc') ? 'desc' : 'asc';
    $indicator = '';

    if ($isActive) {
        $indicator = ($listDirn === 'asc')
            ? ' <span class="ms-1 icon-sort icon-sort-asc" aria-hidden="true"></span>'
            : ' <span class="ms-1 icon-sort icon-sort-desc" aria-hidden="true"></span>';
    }

    $sortLinks[$field] = [
        'url' => \Joomla\CMS\Router\Route::_(
            'index.php?option=com_contentbuilder_ng&task=storage.display&layout=edit&id='
            . $storageId
            . '&limitstart=0'
            . '&list[ordering]=' . $field
            . '&list[direction]=' . $nextDir
            . '&list[limit]=' . max(0, $limitValue),
            false
        ),
        'indicator' => $indicator,
    ];
}

$renderCheckbox = static function (string $name, string $id, bool $checked = false): string {
    return '<span class="form-check d-inline-block mb-0"><input class="form-check-input" type="checkbox" name="'
        . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8')
        . '" value="1"' . ($checked ? ' checked="checked"' : '') . ' /></span>';
};

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->addInlineStyle(
    '.cb-storage-fields-table .cb-order-col{width:84px;min-width:84px;text-align:right;white-space:nowrap}'
    . '.cb-storage-fields-table .cb-order-icons{display:inline-flex;justify-content:flex-end;gap:.5rem;width:100%}'
    . '.cb-storage-fields-table .cb-order-icons>span{display:inline-flex}'
    . '.cb-storage-pagination{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:.5rem}'
    . '.cb-storage-pagination .cb-storage-pages{margin-left:auto}'
    . '.cb-storage-pagination .cb-storage-pages .pagination{margin:0!important;text-align:right!important}'
    . '.cb-storage-pagination .cb-storage-pages .pagination ul{display:flex;justify-content:flex-end;flex-wrap:wrap;gap:.35rem;margin:0;padding:0}'
    . '.cb-save-animate{background-color:var(--alert-heading-bg,var(--bs-success,#198754))!important;background-image:none!important;border-color:var(--bs-success,#198754)!important;color:#fff!important;filter:brightness(1.2)!important;box-shadow:0 0 0 .38rem rgba(25,135,84,.36)!important;transition:none!important}'
    . '.cb-save-animate .icon-apply,.cb-save-animate .icon-save,.cb-save-animate .icon-save-new{color:#fff!important}'
);

?>

<script>
const cbSaveAnimationDurationMs = 500;
const cbPublishedTitle = <?php echo json_encode(Text::_('JPUBLISHED'), JSON_UNESCAPED_UNICODE); ?>;
const cbUnpublishedTitle = <?php echo json_encode(Text::_('JUNPUBLISHED'), JSON_UNESCAPED_UNICODE); ?>;
let cbAjaxBusy = false;
let cbSaveButtonTimer = null;

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

    var icon = actionElement.querySelector('span[class*="icon-"]');
    if (icon && icon.classList) {
        icon.classList.remove('icon-publish', 'icon-unpublish');
        icon.classList.add(meta.enabled ? 'icon-publish' : 'icon-unpublish');
    }

    var title = meta.enabled ? cbPublishedTitle : cbUnpublishedTitle;
    actionElement.setAttribute('title', title);
    actionElement.setAttribute('aria-label', title);

    var hiddenLabel = actionElement.querySelector('.visually-hidden, .sr-only');
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

    if (rowId) {
        formData.delete('cid[]');
        formData.append('cid[]', String(rowId));
        formData.set('boxchecked', '1');
    }

    fetch(form.getAttribute('action') || 'index.php', {
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

    var target = form.querySelector('#' + CSS.escape(id)) || form.elements[id];
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

    Joomla.submitform(task, form);
    return false;
}

if (typeof Joomla !== 'undefined') {
    Joomla.listItemTask = listItemTask;
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
    if (!window.bootstrap || !window.bootstrap.Tooltip) return;

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        window.bootstrap.Tooltip.getOrCreateInstance(el);
    });
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
    initStorageTooltips();
    initStorageAjaxToggles();
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
echo HTMLHelper::_('uitab.startTabSet', 'view-pane', ['active' => 'tab0']);
// Premier onglet
echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab0', Text::_('COM_CONTENTBUILDER_NG_STORAGE'));
?>

<table width="100%">
        <tr>
            <td width="200" valign="top">

                <fieldset class="border rounded p-3 mb-3">
                    <table width="100%">
                        <tr>
                            <td style="min-width: 150px;">
                                <label for="name">
                                    <b>
                                        <?php echo Text::_('COM_CONTENTBUILDER_NG_NAME'); ?>
                                    </b>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <?php
                                if (!$this->item->bytable) {
                                ?>
                                    <input class="form-control form-control-sm w-100" type="text" id="name" name="jform[name]"
                                        value="<?php echo htmlentities($this->item->name ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                    <br /><br />
                                <?php
                                } else {
                                ?>
                                    <input type="hidden" id="name" name="jform[name]"
                                        value="<?php echo htmlentities($this->item->name, ENT_QUOTES, 'UTF-8'); ?>" />
                                <?php
                                }

                                if (!$this->item->id) {
                                ?>
                                    <b>
                                        <?php echo Text::_('COM_CONTENTBUILDER_NG_CHOOSE_TABLE'); ?>
                                    </b>
                                    <br />
                                    <select class="form-select-sm"
                                        onchange="if(this.selectedIndex != 0){ document.getElementById('name').disabled = true; document.getElementById('csvUploadHead').style.display = 'none'; document.getElementById('csvUpload').style.display = 'none'; alert('<?php echo addslashes(Text::_('COM_CONTENTBUILDER_NG_CUSTOM_STORAGE_MSG')); ?>'); }else{ document.getElementById('name').disabled = false; document.getElementById('csvUploadHead').style.display = ''; document.getElementById('csvUpload').style.display = ''; }"
                                        name="jform[bytable]" id="bytable" style="max-width: 150px;">
                                        <option value=""> -
                                            <?php echo Text::_('COM_CONTENTBUILDER_NG_NONE'); ?> -
                                        </option>
                                        <?php
                                        foreach ($this->tables as $table) {
                                        ?>
                                            <option value="<?php echo htmlentities($table, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlentities($table, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                <?php
                                } else if ($this->item->bytable) {
                                ?>
                                    <input type="hidden" id="bytable" name="jform[bytable]"
                                        value="<?php echo htmlentities($this->item->name, ENT_QUOTES, 'UTF-8'); ?>" />
                                    <?php echo htmlentities($this->item->name, ENT_QUOTES, 'UTF-8'); ?>
                                <?php
                                } else if (!$this->item->bytable) {
                                ?>
                                    <input type="hidden" id="bytable" name="jform[bytable]" value="" />
                                <?php
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td width="100">
                                <label for="title">
                                    <b>
                                        <?php echo Text::_('COM_CONTENTBUILDER_NG_STORAGE_TITLE'); ?>
                                    </b>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <input class="form-control form-control-sm w-100" type="text" id="title"
                                    name="jform[title]"
                                    value="<?php echo htmlentities($this->item->title ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                            </td>
                        </tr>
                        <tr id="csvUploadHead">
                            <td width="100">
                                <br />
                                <button
                                    type="button"
                                    id="csvToggleButton"
                                    class="btn btn-outline-secondary btn-sm mb-2"
                                    onclick="return toggleCsvUploadOptions();"
                                    title="<?php echo htmlspecialchars($csvToggleTooltip, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    aria-controls="csvUpload"
                                    aria-expanded="false"
                                >
                                    <i class="fa fa-file-excel me-1" aria-hidden="true"></i>
                                    <?php echo Text::_('COM_CONTENTBUILDER_NG_STORAGE_UPDATE_FROM_CSV'); ?>
                                </button>
                            </td>
                        </tr>
                        <tr style="display: none;" id="csvUpload">
                            <td>
                                <input size="9" type="file" id="csv_file" name="csv_file" accept=".csv,.xlsx,.xls,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
                                <br />
                                Max.
                                <?php
                                $max_upload = (int) (ini_get('upload_max_filesize'));
                                $max_post = (int) (ini_get('post_max_size'));
                                $memory_limit = (int) (ini_get('memory_limit'));
                                $upload_mb = min($max_upload, $max_post, $memory_limit);
                                $val = trim($upload_mb);
                                $last = strtolower($val[strlen($val) - 1]);
                                switch ($last) {
                                    // The 'G' modifier is available since PHP 5.1.0
                                    case 'g':
                                        $val .= ' GB';
                                        break;
                                    case 'k':
                                        $val .= ' kb';
                                        break;
                                    default:
                                        $val .= ' MB';
                                }
                                echo $val;
                                ?>
                                <br />
                                <br />
                                <label for="csv_drop_records">
                                    <?php echo Text::_('COM_CONTENTBUILDER_NG_STORAGE_UPDATE_FROM_CSV_DROP_RECORDS'); ?>
                                </label> <?php echo $renderCheckbox('jform[csv_drop_records]', 'csv_drop_records', true); ?>
                                <br />
                                <label for="csv_published">
                                    <?php echo Text::_('COM_CONTENTBUILDER_NG_AUTO_PUBLISH'); ?>
                                </label> <?php echo $renderCheckbox('jform[csv_published]', 'csv_published', true); ?>
                                <br />
                                <label for="csv_delimiter">
                                    <?php echo Text::_('COM_CONTENTBUILDER_NG_STORAGE_UPDATE_FROM_CSV_DELIMITER'); ?>
                                </label> <input class="form-control form-control-sm" maxlength="3" type="text"
                                    size="1" id="csv_delimiter" name="jform[csv_delimiter]" value="," />
                                <br />
                                <br />
                                <label class="editlinktip hasTip"
                                    title="<?php echo Text::_('COM_CONTENTBUILDER_NG_STORAGE_UPDATE_FROM_CSV_REPAIR_ENCODING_TIP'); ?>"
                                    for="csv_repair_encoding">
                                    <?php echo Text::_('COM_CONTENTBUILDER_NG_STORAGE_UPDATE_FROM_CSV_REPAIR_ENCODING'); ?>*
                                </label>
                                <br />
                                <select class="form-select-sm" style="width: 150px;" name="jform[csv_repair_encoding]"
                                    id="csv_repair_encoding">
                                    <option value=""> -
                                        <?php echo Text::_('COM_CONTENTBUILDER_NG_STORAGE_UPDATE_FROM_CSV_NO_REPAIR_ENCODING'); ?>
                                        -
                                    </option>
                                    <option value="WINDOWS-1250">WINDOWS-1250</option>
                                    <option value="WINDOWS-1251">WINDOWS-1251</option>
                                    <option value="WINDOWS-1252">WINDOWS-1252 (ANSI)</option>
                                    <option value="WINDOWS-1253">WINDOWS-1253</option>
                                    <option value="WINDOWS-1254">WINDOWS-1254</option>
                                    <option value="WINDOWS-1255">WINDOWS-1255</option>
                                    <option value="WINDOWS-1256">WINDOWS-1256</option>
                                    <option value="ISO-8859-1">ISO-8859-1 (LATIN1)</option>
                                    <option value="ISO-8859-2">ISO-8859-2</option>
                                    <option value="ISO-8859-3">ISO-8859-3</option>
                                    <option value="ISO-8859-4">ISO-8859-4</option>
                                    <option value="ISO-8859-5">ISO-8859-5</option>
                                    <option value="ISO-8859-6">ISO-8859-6</option>
                                    <option value="ISO-8859-7">ISO-8859-7</option>
                                    <option value="ISO-8859-8">ISO-8859-8</option>
                                    <option value="ISO-8859-9">ISO-8859-9</option>
                                    <option value="ISO-8859-10">ISO-8859-10</option>
                                    <option value="ISO-8859-11">ISO-8859-11</option>
                                    <option value="ISO-8859-12">ISO-8859-12</option>
                                    <option value="ISO-8859-13">ISO-8859-13</option>
                                    <option value="ISO-8859-14">ISO-8859-14</option>
                                    <option value="ISO-8859-15">ISO-8859-15 (LATIN-9)</option>
                                    <option value="ISO-8859-16">ISO-8859-16</option>
                                    <option value="UTF-8-MAC">UTF-8-MAC</option>
                                    <option value="UTF-16">UTF-16</option>
                                    <option value="UTF-16BE">UTF-16BE</option>
                                    <option value="UTF-16LE">UTF-16LE</option>
                                    <option value="UTF-32">UTF-32</option>
                                    <option value="UTF-32BE">UTF-32BE</option>
                                    <option value="UTF-32LE">UTF-32LE</option>
                                    <option value="ASCII">ASCII</option>
                                    <option value="BIG-5">BIG-5</option>
                                    <option value="HEBREW">HEBREW</option>
                                    <option value="CYRILLIC">CYRILLIC</option>
                                    <option value="ARABIC">ARABIC</option>
                                    <option value="GREEK">GREEK</option>
                                    <option value="CHINESE">CHINESE</option>
                                    <option value="KOREAN">KOREAN</option>
                                    <option value="KOI8-R">KOI8-R</option>
                                    <option value="KOI8-U">KOI8-U</option>
                                    <option value="KOI8-RU">KOI8-RU</option>
                                    <option value="EUC-JP">EUC-JP</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </fieldset>
                <?php
                if (!$this->item->bytable) {
                ?>
                    <fieldset class="border rounded p-3 mb-3">
                    <?php if ((int) $this->item->id === 0) : ?>
                    <div class="alert alert-info">
                        Enregistrez d’abord le stockage, puis vous pourrez ajouter des champs.
                    </div>
                    <button
                        type="button"
                        class="btn btn-success"
                        disabled
                        title="<?php echo htmlspecialchars($addFieldTooltip, ENT_QUOTES, 'UTF-8'); ?>"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                    >+ Add Field</button>
                    <?php else : ?>
                    <button type="button"
                        class="btn btn-success"
                        title="<?php echo htmlspecialchars($addFieldTooltip, ENT_QUOTES, 'UTF-8'); ?>"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        onclick="Joomla.submitbutton('storage.addfield');">
                        + Add Field
                    </button>
                        <table class="admintable" width="100%">
                            <tr>
                                <td>
                                    <label for="fieldname">
                                        <b>
                                            <?php echo Text::_('COM_CONTENTBUILDER_NG_NAME'); ?>
                                        </b>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <input class="form-control form-control-sm w-100" type="text" id="fieldname"
                                        name="jform[fieldname]" value="" />
                                </td>
                            </tr>
                            <tr>
                                <td width="100">
                                    <label for="fieldtitle">
                                        <b>
                                            <?php echo Text::_('COM_CONTENTBUILDER_NG_STORAGE_TITLE'); ?>
                                        </b>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <input class="form-control form-control-sm w-100" type="text" id="fieldtitle"
                                        name="jform[fieldtitle]" value="" />
                                </td>
                            </tr>
                            <tr>
                                <td width="100">
                                    <label for="is_group">
                                        <b>
                                            <?php echo Text::_('COM_CONTENTBUILDER_NG_STORAGE_GROUP'); ?>
                                        </b>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <input class="form-check-input" type="radio" id="is_group" name="jform[is_group]"
                                        value="1" /> <label for="is_group">
                                        <?php echo Text::_('COM_CONTENTBUILDER_NG_YES'); ?>
                                    </label>
                                    <input class="form-check-input" type="radio" id="is_group_no" name="jform[is_group]"
                                        value="0" checked="checked" /> <label for="is_group_no">
                                        <?php echo Text::_('COM_CONTENTBUILDER_NG_NO'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td width="100">
                                    <label for="group_definition">
                                        <b>
                                            <?php echo Text::_('COM_CONTENTBUILDER_NG_STORAGE_GROUP_DEFINITION'); ?>
                                        </b>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td align="right">
                                    <textarea class="form-control form-control-sm" style="width: 100%; height: 100px;"
                                        id="group_definition" name="jform[group_definition]">Label 1;value1
    Label 2;value2
    Label 3;value3</textarea>
                                </td>
                            </tr>
                        </table>
                    <?php endif; ?>
                    </fieldset>
                <?php
                }
                ?>
            </td>

            <td valign="top">
                <table class="table table-striped m-3 cb-storage-fields-table" style="min-width: 697px;">
                    <thead>
                        <tr>
                            <th width="20">
                                <?php echo HTMLHelper::_('grid.checkall'); ?>
                            </th>
                            <th>
                                <a href="<?php echo htmlspecialchars((string) $sortLinks['name']['url'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDER_NG_NAME'), ENT_QUOTES, 'UTF-8'); ?><?php echo $sortLinks['name']['indicator']; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo htmlspecialchars((string) $sortLinks['title']['url'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDER_NG_STORAGE_TITLE'), ENT_QUOTES, 'UTF-8'); ?><?php echo $sortLinks['title']['indicator']; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo htmlspecialchars((string) $sortLinks['group_definition']['url'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDER_NG_STORAGE_GROUP'), ENT_QUOTES, 'UTF-8'); ?><?php echo $sortLinks['group_definition']['indicator']; ?>
                                </a>
                            </th>
                            <th class="cb-order-col">
                                <a href="<?php echo htmlspecialchars((string) $sortLinks['ordering']['url'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDER_NG_ORDERBY'), ENT_QUOTES, 'UTF-8'); ?><?php echo $sortLinks['ordering']['indicator']; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo htmlspecialchars((string) $sortLinks['published']['url'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDER_NG_PUBLISHED'), ENT_QUOTES, 'UTF-8'); ?><?php echo $sortLinks['published']['indicator']; ?>
                                </a>
                            </th>
                        </tr>
                    </thead>
                    <?php $n = $fieldsCount; ?>
                    <?php foreach ($fields as $i => $row) :
                        $id    = (int) ($row->id ?? 0);
                        $name  = htmlspecialchars((string) ($row->name ?? ''), ENT_QUOTES, 'UTF-8');
                        $title = htmlspecialchars((string) ($row->title ?? ''), ENT_QUOTES, 'UTF-8');
                        $group_definition = htmlspecialchars((string) ($row->group_definition ?? ''), ENT_QUOTES, 'UTF-8');
                        $isGroup = !empty($row->is_group);

                        $checked   = HTMLHelper::_('grid.id', $i, $id);

                        $published = ContentbuilderHelper::listPublish('storage', $row, $i);

                        // ordering: n’active les flèches que si ordering est vrai
                        $canOrder = !empty($this->ordering);
                    ?>
                        <tr class="row<?php echo $i % 2; ?>" data-cb-row-id="<?php echo $id; ?>">
                            <td class="text-center"><?php echo $checked; ?></td>
                            <td><?php echo $name; ?></td>
                            <td><?php echo $title; ?></td>
                            <td>
                                <input type="hidden" name="itemNames[<?php echo $id; ?>]" value="<?php echo $name; ?>" />
                                <input type="hidden" name="itemTitles[<?php echo $id; ?>]" value="<?php echo $title; ?>" />

                                <input class="form-check-input" type="radio"
                                    name="itemIsGroup[<?php echo $id; ?>]"
                                    value="1"
                                    id="itemIsGroup_<?php echo $id; ?>"
                                    <?php echo $isGroup ? 'checked="checked"' : ''; ?> />
                                <label for="itemIsGroup_<?php echo $id; ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDER_NG_YES'); ?>
                                </label>

                                <input class="form-check-input" type="radio"
                                    name="itemIsGroup[<?php echo $id; ?>]"
                                    value="0"
                                    id="itemIsGroupNo_<?php echo $id; ?>"
                                    <?php echo !$isGroup ? 'checked="checked"' : ''; ?> />
                                <label for="itemIsGroupNo_<?php echo $id; ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDER_NG_NO'); ?>
                                </label>

                                <div id="itemGroupDefinitions_<?php echo $id; ?>">
                                    <button type="button" class="btn btn-link btn-sm p-0"
                                        onclick="document.getElementById('itemGroupDefinitions<?php echo $id; ?>').style.display='block'; this.parentNode.style.display='none'; document.getElementById('itemGroupDefinitions<?php echo $id; ?>').focus(); return false;">
                                        [<?php echo Text::_('COM_CONTENTBUILDER_NG_EDIT'); ?>]
                                    </button>
                                </div>
                                <textarea class="form-control form-control-sm mt-1"
                                    onblur="this.style.display='none'; document.getElementById('itemGroupDefinitions_<?php echo $id; ?>').style.display='block';"
                                    id="itemGroupDefinitions<?php echo $id; ?>"
                                    style="display:none; width:100%; height:50px;"
                                    name="itemGroupDefinitions[<?php echo $id; ?>]"><?php echo $group_definition; ?></textarea>
                            </td>
                          
                            <td class="order cb-order-col">
                                <?php if ($canOrder) : ?>
                                    <span class="cb-order-icons">
                                        <span>
                                            <?php echo $this->pagination->orderUpIcon($i, true, 'storage.orderup', 'JLIB_HTML_MOVE_UP', $this->ordering); ?>
                                        </span>
                                        <span>
                                            <?php echo $this->pagination->orderDownIcon($i, $n, true, 'storage.orderdown', 'JLIB_HTML_MOVE_DOWN', $this->ordering); ?>
                                        </span>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center"><?php echo $published; ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <tfoot>
                        <tr>
                            <td colspan="6">
                                <div class="cb-storage-pagination">
                                    <div class="cbPagesCounter d-flex flex-wrap align-items-center gap-2">
                                        <?php if (!empty($this->pagination)) {
                                            echo $this->pagination->getPagesCounter();
                                        } ?>
                                        <?php
                                        echo '<span>' . Text::_('COM_CONTENTBUILDER_NG_DISPLAY_NUM') . '&nbsp;</span>';
                                        echo '<div class="d-inline-block">' . (empty($this->pagination) ? '' : $this->pagination->getLimitBox()) . '</div>';
                                        ?>
                                    </div>
                                    <div class="cb-storage-pages">
                                        <?php if (!empty($this->pagination)) {
                                            echo $this->pagination->getPagesLinks();
                                        } ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </td>
        </tr>
    </table>

    <?php
    echo HTMLHelper::_('uitab.endTab');
    echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab1', Text::_('COM_CONTENTBUILDER_NG_STORAGE_INFORMATION'));
    ?>

    <div class="mb-2">
        <span class="badge text-bg-light border">
            <?php echo Text::_('COM_CONTENTBUILDER_NG_ID'); ?> #<?php echo (int) ($this->item->id ?? 0); ?>
        </span>
    </div>

    <div class="card border rounded-3 mb-3">
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDER_NG_NAME'); ?></th>
                        <td colspan="3"><?php echo htmlspecialchars($storageName !== '' ? $storageName : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDER_NG_STORAGE_TITLE'); ?></th>
                        <td colspan="3"><?php echo htmlspecialchars($storageTitle !== '' ? $storageTitle : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDER_NG_PUBLISHED'); ?></th>
                        <td colspan="3">
                            <span class="<?php echo $publishedIconClass; ?>" aria-hidden="true" title="<?php echo htmlspecialchars($publishedIconTitle, ENT_QUOTES, 'UTF-8'); ?>"></span>
                            <span class="visually-hidden"><?php echo htmlspecialchars($publishedIconTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDER_NG_STORAGE_TABLE'); ?></th>
                        <td><?php echo htmlspecialchars($dataTableName, ENT_QUOTES, 'UTF-8'); ?></td>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDER_NG_STORAGE_MODE'); ?></th>
                        <td><?php echo htmlspecialchars(Text::_($storageModeKey), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDER_NG_STORAGE_FIELDS_COUNT'); ?></th>
                        <td colspan="3"><?php echo (int) $fieldsCount; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDER_NG_STORAGE_RECORDS_COUNT'); ?></th>
                        <td colspan="3"><?php echo $recordsCount === null ? '-' : (int) $recordsCount; ?></td>
                    </tr>
                    <tr class="text-secondary">
                        <th scope="row" style="width: 240px;"><?php echo Text::_('COM_CONTENTBUILDER_NG_CREATED_ON'); ?></th>
                        <td><?php echo htmlspecialchars($formatDate($this->item->created ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                        <th scope="row" style="width: 240px;"><?php echo Text::_('JGLOBAL_FIELD_CREATED_BY_LABEL'); ?></th>
                        <td><?php echo htmlspecialchars($createdBy !== '' ? $createdBy : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr class="text-secondary">
                        <th scope="row"><?php echo Text::_('JGLOBAL_FIELD_MODIFIED_LABEL'); ?></th>
                        <td><?php echo htmlspecialchars($formatDate($this->item->modified ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                        <th scope="row"><?php echo Text::_('JGLOBAL_FIELD_MODIFIED_BY_LABEL'); ?></th>
                        <td><?php echo htmlspecialchars($modifiedBy !== '' ? $modifiedBy : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    echo HTMLHelper::_('uitab.endTab');
    echo HTMLHelper::_('uitab.endTabSet');
    ?>

    <div class="clr">
    </div>

    <input type="hidden" name="option" value="com_contentbuilder_ng" />
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
    <input type="hidden" name="limitstart" value="<?php echo (int) Factory::getApplication()->input->getInt('limitstart', 0); ?>" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="tabStartOffset" value="<?php echo Factory::getApplication()->getSession()->get('tabStartOffset', 0); ?>" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
