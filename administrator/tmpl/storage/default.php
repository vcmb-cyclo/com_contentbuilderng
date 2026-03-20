<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL 
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
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
$requestedTab = trim((string) $app->input->getCmd('tabStartOffset', ''));
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
$csvToggleTooltip = 'Show or hide CSV/Excel import options.';
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

$sortFields = ['name', 'title', 'group_definition', 'ordering', 'published'];
$sortLinks = [];

foreach ($sortFields as $field) {
    $isActive = ($listOrder === $field);
    $nextDir = ($isActive && $listDirn === 'asc') ? 'desc' : 'asc';
    $indicator = '';

    if ($isActive) {
        $indicator = ($listDirn === 'asc')
            ? ' <span class="ms-1 fa-solid fa-sort fa-solid fa-sort-up" aria-hidden="true"></span>'
            : ' <span class="ms-1 fa-solid fa-sort fa-solid fa-sort-down" aria-hidden="true"></span>';
    }

    $sortLinks[$field] = [
        'url' => \Joomla\CMS\Router\Route::_(
            'index.php?option=com_contentbuilderng&task=storage.display&layout=edit&id='
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

$wa = $app->getDocument()->getWebAssetManager();
$wa->addInlineStyle(
    '.cb-storage-fields-table .cb-order-col{width:84px;min-width:84px;text-align:right;white-space:nowrap}'
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

    if (task === 'storage.publishItem' || task === 'storage.unpublishItem') {
        var tabField = form.querySelector('input[name="tabStartOffset"]');
        if (tabField) {
            tabField.value = 'tab1';
        }
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
    if (window.bootstrap && typeof window.bootstrap.Tooltip === 'function') {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            window.bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }
}

function getStorageTabTargetId(el) {
    if (!el || typeof el.getAttribute !== 'function') {
        return null;
    }

    return (
        el.getAttribute('aria-controls') ||
        el.getAttribute('data-tab') ||
        (el.getAttribute('data-bs-target') && el.getAttribute('data-bs-target').startsWith('#') ? el.getAttribute('data-bs-target').slice(1) : null) ||
        (el.getAttribute('href') && el.getAttribute('href').startsWith('#') ? el.getAttribute('href').slice(1) : null) ||
        (el.getAttribute('data-target') && el.getAttribute('data-target').startsWith('#') ? el.getAttribute('data-target').slice(1) : null)
    );
}

function setStorageHidden(name, value) {
    var el = document.querySelector('input[name="' + name + '"], input[name="jform[' + name + ']"]');
    if (el) {
        el.value = value;
    }
}

function persistStorageTabset(tabsetId, storageKey, onSave) {
    var tabset = document.getElementById(tabsetId);
    if (!tabset) {
        return;
    }

    var jTab = tabset.matches('joomla-tab') ? tabset : tabset.querySelector('joomla-tab');
    if (!jTab) {
        return;
    }

    var saved = localStorage.getItem(storageKey);
    if (saved) {
        if (typeof jTab.show === 'function') {
            try {
                jTab.show(saved);
            } catch (e) {
            }
        }

        var btn =
            jTab.querySelector('button[aria-controls="' + saved + '"]') ||
            jTab.querySelector('button[data-tab="' + saved + '"]') ||
            jTab.querySelector('button[data-bs-target="#' + saved + '"]') ||
            jTab.querySelector('button[data-target="#' + saved + '"]') ||
            jTab.querySelector('a[aria-controls="' + saved + '"]') ||
            jTab.querySelector('a[href="#' + saved + '"]') ||
            (jTab.shadowRoot && (
                jTab.shadowRoot.querySelector('button[aria-controls="' + saved + '"]') ||
                jTab.shadowRoot.querySelector('button[data-tab="' + saved + '"]') ||
                jTab.shadowRoot.querySelector('button[data-bs-target="#' + saved + '"]') ||
                jTab.shadowRoot.querySelector('button[data-target="#' + saved + '"]') ||
                jTab.shadowRoot.querySelector('a[aria-controls="' + saved + '"]') ||
                jTab.shadowRoot.querySelector('a[href="#' + saved + '"]')
            ));

        if (btn) {
            btn.click();
            if (typeof btn.blur === 'function') {
                btn.blur();
            }
        }
    }

    var saveActiveTab = function(ev) {
        var trigger = (ev.target && typeof ev.target.closest === 'function') ? (ev.target.closest('button,a') || ev.target) : ev.target;
        var id = getStorageTabTargetId(trigger);

        if (!id) {
            return;
        }

        localStorage.setItem(storageKey, id);
        if (typeof onSave === 'function') {
            onSave(id);
        }
    };

    jTab.addEventListener('click', saveActiveTab, { passive: true });

    if (jTab.shadowRoot) {
        jTab.shadowRoot.addEventListener('click', saveActiveTab, { passive: true });
    }
}

function initStorageTabTooltips(attempt) {
    var tabset = document.getElementById('view-pane');

    if (!tabset) {
        return;
    }

    var jTab = tabset.matches('joomla-tab') ? tabset : tabset.querySelector('joomla-tab');
    if (!jTab) {
        return;
    }

    var roots = [jTab];
    if (jTab.shadowRoot) {
        roots.push(jTab.shadowRoot);
    }

    var selector = 'button[aria-controls],button[data-tab],button[data-target],button[data-bs-target],a[aria-controls],a[data-tab],a[data-target],a[data-bs-target],a[href^="#"]';
    var tips = {
        tab0: <?php echo json_encode($tabStorageTooltip, JSON_UNESCAPED_UNICODE); ?>,
        tab1: <?php echo json_encode($tabInfoTooltip, JSON_UNESCAPED_UNICODE); ?>
    };
    var applied = 0;

    roots.forEach(function(root) {
        root.querySelectorAll(selector).forEach(function(trigger) {
            var id = getStorageTabTargetId(trigger);
            var tip = id ? tips[id] : null;

            if (!tip) {
                return;
            }

            trigger.setAttribute('title', String(tip));
            trigger.setAttribute('data-bs-toggle', 'tooltip');
            trigger.setAttribute('data-bs-placement', 'top');
            trigger.setAttribute('data-bs-title', String(tip));
            applied++;
        });
    });

    initStorageTooltips();

    if (!applied && (attempt || 0) < 12) {
        window.setTimeout(function() {
            initStorageTabTooltips((attempt || 0) + 1);
        }, 120);
    }
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
    initStorageTabTooltips();
    persistStorageTabset('view-pane', 'cb_active_storage_tab', function(id) {
        setStorageHidden('tabStartOffset', id);
    });
}

function initStorageTabTooltips(attempt) {
    var tabset = document.getElementById('view-pane');

    if (!tabset) {
        return;
    }

    var jTab = tabset.matches('joomla-tab') ? tabset : tabset.querySelector('joomla-tab');
    if (!jTab) {
        return;
    }

    var roots = [jTab];
    if (jTab.shadowRoot) {
        roots.push(jTab.shadowRoot);
    }

    var selector = 'button[aria-controls],button[data-tab],button[data-target],button[data-bs-target],a[aria-controls],a[data-tab],a[data-target],a[data-bs-target],a[href^="#"]';
    var tips = {
        tab0: <?php echo json_encode($tabStorageTooltip, JSON_UNESCAPED_UNICODE); ?>,
        tab1: <?php echo json_encode($tabInfoTooltip, JSON_UNESCAPED_UNICODE); ?>
    };
    var applied = 0;

    roots.forEach(function(root) {
        root.querySelectorAll(selector).forEach(function(trigger) {
            var id = trigger.getAttribute('aria-controls')
                || trigger.getAttribute('data-tab')
                || (trigger.getAttribute('data-bs-target') || '').replace(/^#/, '')
                || (trigger.getAttribute('data-target') || '').replace(/^#/, '')
                || ((trigger.getAttribute('href') || '').charAt(0) === '#' ? trigger.getAttribute('href').slice(1) : '');
            var tip = tips[id];

            if (!tip) {
                return;
            }

            trigger.setAttribute('title', String(tip));
            trigger.setAttribute('data-bs-toggle', 'tooltip');
            trigger.setAttribute('data-bs-placement', 'top');
            trigger.setAttribute('data-bs-title', String(tip));
            applied++;
        });
    });

    initStorageTooltips();

    if (!applied && (attempt || 0) < 12) {
        window.setTimeout(function() {
            initStorageTabTooltips((attempt || 0) + 1);
        }, 120);
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

<table width="100%">
        <tr>
            <td width="200" valign="top">

                <fieldset class="border rounded p-3 mb-3">
                    <table width="100%">
                        <tr>
                            <td style="min-width: 150px;">
                                <label for="name">
                                    <b>
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_NAME'); ?>
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
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_CHOOSE_TABLE'); ?>
                                    </b>
                                    <br />
                                    <select class="form-select-sm"
                                        onchange="if(this.selectedIndex != 0){ document.getElementById('name').disabled = true; document.getElementById('csvUploadHead').style.display = 'none'; document.getElementById('csvUpload').style.display = 'none'; alert('<?php echo addslashes(Text::_('COM_CONTENTBUILDERNG_CUSTOM_STORAGE_MSG')); ?>'); }else{ document.getElementById('name').disabled = false; document.getElementById('csvUploadHead').style.display = ''; document.getElementById('csvUpload').style.display = ''; }"
                                        name="jform[bytable]" id="bytable" style="max-width: 150px;">
                                        <option value=""> -
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_NONE'); ?> -
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
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_TITLE'); ?>
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
                                    data-cb-default-text="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_STORAGE_UPDATE_FROM_CSV'), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-cb-create-text="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_STORAGE_CREATE_FROM_FILE'), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-cb-new-storage="<?php echo ((int) $storageId === 0) ? '1' : '0'; ?>"
                                    data-cb-preview-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_STORAGE_PREVIEW_FROM_FILE'), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-cb-token="<?php echo Session::getFormToken(); ?>"
                                >
                                    <i class="fa fa-file-excel me-1" aria-hidden="true"></i>
                                    <span class="cb-csv-button-label">
                                        <?php echo ((int) $storageId === 0)
                                            ? Text::_('COM_CONTENTBUILDERNG_STORAGE_CREATE_FROM_FILE')
                                            : Text::_('COM_CONTENTBUILDERNG_STORAGE_UPDATE_FROM_CSV'); ?>
                                    </span>
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
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_UPDATE_FROM_CSV_DROP_RECORDS'); ?>
                                </label> <?php echo $renderCheckbox('jform[csv_drop_records]', 'csv_drop_records', true); ?>
                                <br />
                                <label for="csv_published">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_AUTO_PUBLISH'); ?>
                                </label> <?php echo $renderCheckbox('jform[csv_published]', 'csv_published', true); ?>
                                <br />
                                <label for="csv_delimiter">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_UPDATE_FROM_CSV_DELIMITER'); ?>
                                </label> <input class="form-control form-control-sm" maxlength="3" type="text"
                                    size="1" id="csv_delimiter" name="jform[csv_delimiter]" value="," />
                                <br />
                                <br />
                                <label class="editlinktip hasTip"
                                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_UPDATE_FROM_CSV_REPAIR_ENCODING_TIP'); ?>"
                                    for="csv_repair_encoding">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_UPDATE_FROM_CSV_REPAIR_ENCODING'); ?>*
                                </label>
                                <br />
                                <select class="form-select-sm" style="width: 150px;" name="jform[csv_repair_encoding]"
                                    id="csv_repair_encoding">
                                    <option value=""> -
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_UPDATE_FROM_CSV_NO_REPAIR_ENCODING'); ?>
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
                                <div id="cbCsvPreviewPanel" class="cb-csv-preview-panel" style="display:none;">
                                    <div class="cb-csv-preview-head"><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_PREVIEW_FROM_FILE'); ?></div>
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th style="width:40%;"><?php echo Text::_('COM_CONTENTBUILDERNG_NAME'); ?></th>
                                                <th style="width:45%;"><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_TITLE'); ?></th>
                                                <th style="width:15%;"><?php echo Text::_('JSTATUS'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="cbCsvPreviewBody"></tbody>
                                    </table>
                                </div>
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
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_NAME'); ?>
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
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_TITLE'); ?>
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
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_GROUP'); ?>
                                        </b>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <input class="form-check-input" type="radio" id="is_group" name="jform[is_group]"
                                        value="1" /> <label for="is_group">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_YES'); ?>
                                    </label>
                                    <input class="form-check-input" type="radio" id="is_group_no" name="jform[is_group]"
                                        value="0" checked="checked" /> <label for="is_group_no">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_NO'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td width="100">
                                    <label for="group_definition">
                                        <b>
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_GROUP_DEFINITION'); ?>
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
                                    <?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_NAME'), ENT_QUOTES, 'UTF-8'); ?><?php echo $sortLinks['name']['indicator']; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo htmlspecialchars((string) $sortLinks['title']['url'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_STORAGE_TITLE'), ENT_QUOTES, 'UTF-8'); ?><?php echo $sortLinks['title']['indicator']; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo htmlspecialchars((string) $sortLinks['group_definition']['url'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_STORAGE_GROUP'), ENT_QUOTES, 'UTF-8'); ?><?php echo $sortLinks['group_definition']['indicator']; ?>
                                </a>
                            </th>
                            <th class="cb-order-col">
                                <a href="<?php echo htmlspecialchars((string) $sortLinks['ordering']['url'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ORDERBY'), ENT_QUOTES, 'UTF-8'); ?><?php echo $sortLinks['ordering']['indicator']; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo htmlspecialchars((string) $sortLinks['published']['url'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_PUBLISHED'), ENT_QUOTES, 'UTF-8'); ?><?php echo $sortLinks['published']['indicator']; ?>
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

                        $published = ContentbuilderngHelper::listPublish('storage', $row, $i);

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
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_YES'); ?>
                                </label>

                                <input class="form-check-input" type="radio"
                                    name="itemIsGroup[<?php echo $id; ?>]"
                                    value="0"
                                    id="itemIsGroupNo_<?php echo $id; ?>"
                                    <?php echo !$isGroup ? 'checked="checked"' : ''; ?> />
                                <label for="itemIsGroupNo_<?php echo $id; ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_NO'); ?>
                                </label>

                                <div id="itemGroupDefinitions_<?php echo $id; ?>">
                                    <button type="button" class="btn btn-link btn-sm p-0"
                                        onclick="document.getElementById('itemGroupDefinitions<?php echo $id; ?>').style.display='block'; this.parentNode.style.display='none'; document.getElementById('itemGroupDefinitions<?php echo $id; ?>').focus(); return false;">
                                        [<?php echo Text::_('COM_CONTENTBUILDERNG_EDIT'); ?>]
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
                                        echo '<span>' . Text::_('COM_CONTENTBUILDERNG_DISPLAY_NUM') . '&nbsp;</span>';
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
    echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab1', $storageTabLabel('fa-solid fa-circle-info', 'COM_CONTENTBUILDERNG_STORAGE_INFORMATION'));
    ?>

    <?php if ($storageTableExists === false) : ?>
        <div class="alert alert-danger mb-3" role="alert">
            <strong>Missing storage table</strong><br>
            <?php echo htmlspecialchars($storageTableErrorMessage !== '' ? $storageTableErrorMessage : 'Storage table not found.', ENT_QUOTES, 'UTF-8'); ?>
            <?php if ($storageTableLookupName !== '') : ?>
                <br><code><?php echo htmlspecialchars($storageTableLookupName, ENT_QUOTES, 'UTF-8'); ?></code>
            <?php endif; ?>
        </div>
    <?php elseif ($storageTableErrorMessage !== '') : ?>
        <div class="alert alert-warning mb-3" role="alert">
            <?php echo htmlspecialchars($storageTableErrorMessage, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="mb-2">
        <span class="badge bg-body-tertiary text-body border">
            <?php echo Text::_('COM_CONTENTBUILDERNG_ID'); ?> #<?php echo (int) ($this->item->id ?? 0); ?>
        </span>
    </div>

    <div class="card border rounded-3 mb-3">
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_NAME'); ?></th>
                        <td colspan="3"><?php echo htmlspecialchars($storageName !== '' ? $storageName : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_TITLE'); ?></th>
                        <td colspan="3"><?php echo htmlspecialchars($storageTitle !== '' ? $storageTitle : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_PUBLISHED'); ?></th>
                        <td colspan="3">
                            <?php if ((int) ($this->item->id ?? 0) > 0) : ?>
                                <?php echo $publishedToggleHtml; ?>
                                <input type="checkbox"
                                    name="cid[]"
                                    id="cbstorageitem0"
                                    value="<?php echo (int) ($this->item->id ?? 0); ?>"
                                    style="display:none" />
                            <?php else : ?>
                                <span class="<?php echo $publishedIconClass; ?>" aria-hidden="true" title="<?php echo htmlspecialchars($publishedIconTitle, ENT_QUOTES, 'UTF-8'); ?>"></span>
                                <span class="visually-hidden"><?php echo htmlspecialchars($publishedIconTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_TABLE'); ?></th>
                        <td><?php echo htmlspecialchars($dataTableName, ENT_QUOTES, 'UTF-8'); ?></td>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_MODE'); ?></th>
                        <td><?php echo htmlspecialchars(Text::_($storageModeKey), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_FIELDS_COUNT'); ?></th>
                        <td colspan="3"><?php echo (int) $fieldsCount; ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_RECORDS_COUNT'); ?></th>
                        <td colspan="3"><?php echo $recordsCount === null ? '-' : (int) $recordsCount; ?></td>
                    </tr>
                    <tr class="text-secondary">
                        <th scope="row" style="width: 240px;"><?php echo Text::_('COM_CONTENTBUILDERNG_CREATED_ON'); ?></th>
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
    <input type="hidden" name="limitstart" value="<?php echo (int) Factory::getApplication()->input->getInt('limitstart', 0); ?>" />
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
