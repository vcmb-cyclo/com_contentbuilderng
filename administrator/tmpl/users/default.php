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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;

$app = Factory::getApplication();
$app->getDocument()->getWebAssetManager()->useScript('core');

$ordering  = (string) $this->state->get('list.ordering', 'u.id');
$direction = strtolower((string) $this->state->get('list.direction', 'asc'));
$direction = ($direction === 'desc') ? 'desc' : 'asc';
$search    = $this->state->get('filter.search');
$input     = $app->getInput();
$formId    = (int) $input->getInt('form_id', 0);
$tmpl      = $input->getWord('tmpl', '');
$userColumns = [
    'id' => 'ID',
    'name' => Text::_('COM_CONTENTBUILDERNG_FIELD_NAME'),
    'username' => Text::_('JGLOBAL_USERNAME'),
    'verified_view' => Text::_('COM_CONTENTBUILDERNG_VERIFIED_VIEW'),
    'verified_new' => Text::_('COM_CONTENTBUILDERNG_VERIFIED_NEW'),
    'verified_edit' => Text::_('COM_CONTENTBUILDERNG_VERIFIED_EDIT'),
    'published' => Text::_('COM_CONTENTBUILDERNG_LIST_STATES_PUBLISHED'),
];

$sortLink = function (string $label, string $field) use ($ordering, $direction, $formId, $tmpl): string {
    $isActive = ($ordering === $field);
    $nextDir = ($isActive && $direction === 'asc') ? 'desc' : 'asc';
    $indicator = $isActive
        ? ($direction === 'asc'
            ? ' <span class="ms-1 fa-solid fa-sort fa-solid fa-sort-up" aria-hidden="true"></span>'
            : ' <span class="ms-1 fa-solid fa-sort fa-solid fa-sort-down" aria-hidden="true"></span>')
        : '';
    $tmplParam = $tmpl !== '' ? '&tmpl=' . $tmpl : '';
    $url = Route::_(
        'index.php?option=com_contentbuilderng&view=users&form_id='
        . $formId . $tmplParam . '&list[start]=0&list[ordering]=' . $field
        . '&list[direction]=' . $nextDir
    );

    return '<a href="' . $url . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . $indicator . '</a>';
};
?>
<form action="index.php" method="post" name="adminForm" id="adminForm">
    <style>
        .cb-users-columns-toolbar {
            display: flex;
            justify-content: flex-end;
            margin: 0 0 1rem;
        }

        .cb-users-columns-menu {
            min-width: 16rem;
            max-width: min(24rem, 90vw);
        }

        .cb-users-columns-menu .dropdown-item {
            padding: .35rem .5rem;
            white-space: normal;
        }

        .cb-users-col-hidden {
            display: none !important;
        }

        .cb-users-columns-pending {
            visibility: hidden;
        }
    </style>

    <input class="form-control form-control-sm w-25"
        type="text"
        name="filter_search"
        id="filter_search"
        value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
        onchange="document.adminForm.submit();" />

    <input type="button" class="btn btn-sm btn-primary" value="<?php echo Text::_('COM_CONTENTBUILDERNG_SEARCH'); ?>"
        onclick="this.form.submit();" />
    <input type="button" class="btn btn-sm btn-primary"
        value="<?php echo Text::_('COM_CONTENTBUILDERNG_RESET'); ?>"
        onclick="document.getElementById('filter_search').value='';document.adminForm.submit();" />



    <div style="float:right">
        <select id="cb-users-bulk-status"
            class="form-select-sm"
            disabled="disabled"
            onchange="if(this.selectedIndex == 1 || this.selectedIndex == 2){document.adminForm.task.value=this.options[this.selectedIndex].value;document.adminForm.submit();}">
            <option> -
                <?php echo Text::_('COM_CONTENTBUILDERNG_UPDATE_STATUS'); ?> -
            </option>
            <option value="users.publish">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PUBLISH'); ?>
            </option>
            <option value="users.unpublish">
                <?php echo Text::_('COM_CONTENTBUILDERNG_UNPUBLISH'); ?>
            </option>
        </select>
        <select id="cb-users-bulk-verify"
            class="form-select-sm"
            disabled="disabled"
            onchange="if(this.selectedIndex != 0){document.adminForm.task.value=this.options[this.selectedIndex].value;document.adminForm.submit();}">
            <option> -
                <?php echo Text::_('COM_CONTENTBUILDERNG_SET_VERIFICATION'); ?> -
            </option>
            <option value="users.verified_view">
                <?php echo Text::_('COM_CONTENTBUILDERNG_VERIFIED_VIEW'); ?>
            </option>
            <option value="users.not_verified_view">
                <?php echo Text::_('COM_CONTENTBUILDERNG_UNVERIFIED_VIEW'); ?>
            </option>
            <option value="users.verified_new">
                <?php echo Text::_('COM_CONTENTBUILDERNG_VERIFIED_NEW'); ?>
            </option>
            <option value="users.not_verified_new">
                <?php echo Text::_('COM_CONTENTBUILDERNG_UNVERIFIED_NEW'); ?>
            </option>
            <option value="users.verified_edit">
                <?php echo Text::_('COM_CONTENTBUILDERNG_VERIFIED_EDIT'); ?>
            </option>
            <option value="users.not_verified_edit">
                <?php echo Text::_('COM_CONTENTBUILDERNG_UNVERIFIED_EDIT'); ?>
            </option>
        </select>
    </div>

    <div style="clear:both;"></div>

    <div class="cb-users-columns-toolbar cb-users-columns-pending">
        <div class="dropdown">
            <button
                type="button"
                class="btn btn-primary btn-sm dropdown-toggle"
                id="cb-users-columns-toggle"
                data-bs-toggle="dropdown"
                data-bs-auto-close="outside"
                aria-expanded="false">
                <span class="cb-users-columns-count"><?php echo count($userColumns); ?>/<?php echo count($userColumns); ?> <?php echo Text::_('COM_CONTENTBUILDERNG_COLUMNS'); ?></span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-2 cb-users-columns-menu" aria-labelledby="cb-users-columns-toggle">
                <?php foreach ($userColumns as $columnName => $columnLabel): ?>
                    <label class="dropdown-item d-flex align-items-start gap-2 mb-1">
                        <input
                            class="form-check-input mt-1"
                            type="checkbox"
                            value="1"
                            checked
                            data-cb-user-col-toggle="<?php echo htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8'); ?>">
                        <span><?php echo htmlspecialchars($columnLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    </label>
                <?php endforeach; ?>
                <div class="dropdown-divider my-1"></div>
                <button type="button" class="btn btn-link btn-sm px-2 cb-users-columns-reset" data-cb-users-columns-reset="1">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_RESET'); ?>
                </button>
            </div>
        </div>
    </div>

    <div id="editcell" class="cb-users-columns-pending">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th width="5" data-cb-user-col="id">
                        <?php echo $sortLink('ID', 'u.id'); ?>
                    </th>
                    <th width="20">
                        <input class="form-check-input" type="checkbox" name="checkall-toggle" value="" onclick="Joomla.checkAll(this);" aria-label="<?php echo htmlspecialchars(Text::_('JGLOBAL_CHECK_ALL'), ENT_QUOTES, 'UTF-8'); ?>">
                    </th>
                    <th data-cb-user-col="name">
                        <?php echo $sortLink('Name', 'u.name'); ?>
                    </th>
                    <th data-cb-user-col="username">
                        <?php echo $sortLink('Username', 'u.username'); ?>
                    </th>
                    <th data-cb-user-col="verified_view">
                        <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_VERIFIED_VIEW'), 'a.verified_view'); ?>
                    </th>
                    <th data-cb-user-col="verified_new">
                        <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_VERIFIED_NEW'), 'a.verified_new'); ?>
                    </th>
                    <th data-cb-user-col="verified_edit">
                        <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_VERIFIED_EDIT'), 'a.verified_edit'); ?>
                    </th>
                    <th width="5" data-cb-user-col="published">
                        <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_LIST_STATES_PUBLISHED'), 'a.published'); ?>
                    </th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($this->items as $i => $item):
                    $checked = '<input class="form-check-input" type="checkbox" id="cb' . (int) $i . '" name="cid[]" value="' . (int) $item->id . '" onclick="Joomla.isChecked(this.checked);">';
                    $link = Route::_('index.php?option=com_contentbuilderng&task=user.edit&form_id=' . $formId . '&joomla_userid=' . (int) $item->id);
                    if ($item->published === null) {
                        $item->published = 1;
                    }
                    $published = ContentbuilderngHelper::listPublish('users', $item, $i);
                    $verified_view = ContentbuilderngHelper::listVerifiedView('users', $item, $i);
                    $verified_new = ContentbuilderngHelper::listVerifiedNew('users', $item, $i);
                    $verified_edit = ContentbuilderngHelper::listVerifiedEdit('users', $item, $i);
                ?>
                    <tr>
                        <td data-cb-user-col="id">
                            <?php echo (int) $item->id; ?>
                        </td>
                        <td>
                            <?php echo $checked; ?>
                        </td>
                        <td data-cb-user-col="name">
                            <a href="<?php echo $link; ?>">
                                <?php echo htmlspecialchars($item->name, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </td>
                        <td data-cb-user-col="username">
                            <a href="<?php echo $link; ?>">
                                <?php echo htmlspecialchars($item->username, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </td>
                        <td data-cb-user-col="verified_view">
                            <?php echo $verified_view; ?>
                        </td>
                        <td data-cb-user-col="verified_new">
                            <?php echo $verified_new; ?>
                        </td>
                        <td data-cb-user-col="verified_edit">
                            <?php echo $verified_edit; ?>
                        </td>
                        <td data-cb-user-col="published">
                            <?php echo $published; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="9">
                        <?php echo $this->pagination->getListFooter(); ?>
                    </td>
                </tr>
            </tfoot>

        </table>
    </div>

    <input type="hidden" name="option" value="com_contentbuilderng" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="view" value="users" />
    <input type="hidden" name="form_id" value="<?php echo $formId; ?>" />
    <input type="hidden" name="tmpl" value="<?php echo $tmpl; ?>" />
    <input type="hidden" name="list[ordering]" value="<?php echo htmlspecialchars($ordering, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="list[direction]" value="<?php echo htmlspecialchars($direction, ENT_QUOTES, 'UTF-8'); ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
(function () {
    'use strict';

    var cbUsersAjaxBusy = false;
    var cbUsersForm = document.getElementById('adminForm') || document.adminForm;
    var cbUsersBulkStatus = document.getElementById('cb-users-bulk-status');
    var cbUsersBulkVerify = document.getElementById('cb-users-bulk-verify');
    var cbUsersColumnsStateKey = 'cbng.users.columns.' + <?php echo (int) $formId; ?>;
    var cbUsersColumnsLabel = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_COLUMNS'), JSON_UNESCAPED_UNICODE); ?>;
    var cbUsersTaskMeta = {
        'users.publish': { nextTask: 'users.unpublish', enabled: true },
        'users.unpublish': { nextTask: 'users.publish', enabled: false },
        'users.verified_view': { nextTask: 'users.not_verified_view', enabled: true },
        'users.not_verified_view': { nextTask: 'users.verified_view', enabled: false },
        'users.verified_new': { nextTask: 'users.not_verified_new', enabled: true },
        'users.not_verified_new': { nextTask: 'users.verified_new', enabled: false },
        'users.verified_edit': { nextTask: 'users.not_verified_edit', enabled: true },
        'users.not_verified_edit': { nextTask: 'users.verified_edit', enabled: false }
    };

    function cbUsersLoadColumnsState(defaultState) {
        try {
            var storedState = window.localStorage.getItem(cbUsersColumnsStateKey);
            if (!storedState) {
                return defaultState;
            }

            var parsedState = JSON.parse(storedState);
            return Object.assign({}, defaultState, parsedState || {});
        } catch (error) {
            return defaultState;
        }
    }

    function cbUsersSaveColumnsState(state) {
        try {
            window.localStorage.setItem(cbUsersColumnsStateKey, JSON.stringify(state));
        } catch (error) {
        }
    }

    function cbUsersInitColumnPicker() {
        var toggleButton = document.getElementById('cb-users-columns-toggle');
        var menu = document.querySelector('.cb-users-columns-menu');
        var countLabel = toggleButton ? toggleButton.querySelector('.cb-users-columns-count') : null;
        var resetButton = menu ? menu.querySelector('.cb-users-columns-reset[data-cb-users-columns-reset="1"]') : null;

        if (!toggleButton || !menu || !countLabel) {
            document.querySelectorAll('.cb-users-columns-pending').forEach(function (node) {
                node.classList.remove('cb-users-columns-pending');
            });
            return;
        }

        var toggles = Array.prototype.slice.call(menu.querySelectorAll('input[data-cb-user-col-toggle]'));
        var defaultState = {};

        toggles.forEach(function (toggle) {
            defaultState[String(toggle.getAttribute('data-cb-user-col-toggle') || '')] = true;
        });

        var state = cbUsersLoadColumnsState(defaultState);

        function applyState() {
            var visibleCount = 0;
            var lastVisibleKey = null;

            toggles.forEach(function (toggle) {
                var key = String(toggle.getAttribute('data-cb-user-col-toggle') || '');
                var isVisible = state[key] !== false;

                toggle.checked = isVisible;
                toggle.disabled = false;

                document.querySelectorAll('[data-cb-user-col="' + key + '"]').forEach(function (cell) {
                    cell.classList.toggle('cb-users-col-hidden', !isVisible);
                });

                if (isVisible) {
                    visibleCount++;
                    lastVisibleKey = key;
                }
            });

            if (visibleCount <= 1 && lastVisibleKey !== null) {
                var lastVisibleToggle = menu.querySelector('[data-cb-user-col-toggle="' + lastVisibleKey + '"]');
                if (lastVisibleToggle) {
                    lastVisibleToggle.disabled = true;
                }
            }

            countLabel.textContent = visibleCount + '/' + toggles.length + ' ' + cbUsersColumnsLabel;
            cbUsersSaveColumnsState(state);

            document.querySelectorAll('.cb-users-columns-pending').forEach(function (node) {
                node.classList.remove('cb-users-columns-pending');
            });
        }

        toggles.forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                var key = String(toggle.getAttribute('data-cb-user-col-toggle') || '');
                state[key] = !!toggle.checked;
                applyState();
            });
        });

        if (resetButton) {
            resetButton.addEventListener('click', function () {
                state = Object.assign({}, defaultState);
                applyState();
            });
        }

        applyState();
    }

    function cbUsersUpdateBulkSelectState() {
        if (!cbUsersForm) {
            return;
        }

        var rowCheckboxes = cbUsersForm.querySelectorAll('input[type="checkbox"][name="cid[]"]');
        var hasSelection = false;

        for (var i = 0; i < rowCheckboxes.length; i++) {
            if (rowCheckboxes[i] && rowCheckboxes[i].checked) {
                hasSelection = true;
                break;
            }
        }

        [cbUsersBulkStatus, cbUsersBulkVerify].forEach(function (select) {
            if (!select) {
                return;
            }

            select.disabled = !hasSelection;
            if (!hasSelection) {
                select.selectedIndex = 0;
            }
        });
    }

    function cbUsersIsAjaxToggleTask(task) {
        return Object.prototype.hasOwnProperty.call(cbUsersTaskMeta, String(task || ''));
    }

    function cbUsersGetToggleTaskMeta(task) {
        return cbUsersTaskMeta[String(task || '')] || null;
    }

    function cbUsersEscapeRegExp(value) {
        return String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function cbUsersEscapeId(id) {
        var raw = String(id || '');
        if (raw === '') {
            return raw;
        }

        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(raw);
        }

        return raw.replace(/([ !"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1');
    }

    function cbUsersUpdateToggleIconClasses(host, enabled) {
        if (!host) {
            return;
        }

        var icons = host.querySelectorAll('span, i');
        if (!icons.length) {
            return;
        }

        icons.forEach(function (icon) {
            if (!icon || !icon.classList) {
                return;
            }

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

    function cbUsersApplyAjaxToggleState(actionElement, task) {
        if (!actionElement) {
            return;
        }

        var meta = cbUsersGetToggleTaskMeta(task);
        if (!meta) {
            return;
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

        cbUsersUpdateToggleIconClasses(actionElement, !!meta.enabled);
    }

    function cbUsersFindActionElement(row, task) {
        if (!row || typeof row.querySelector !== 'function') {
            return null;
        }

        var candidates = row.querySelectorAll('[onclick*="listItemTask("], [data-item-task], [data-submit-task], [data-task]');
        for (var i = 0; i < candidates.length; i++) {
            var onclick = String(candidates[i].getAttribute('onclick') || '');
            if (onclick.indexOf("'" + task + "'") !== -1 || onclick.indexOf('"' + task + '"') !== -1) {
                return candidates[i];
            }
        }

        return null;
    }

    function cbUsersFindActionElementByCheckboxAndTask(root, checkboxId, task) {
        if (!root || typeof root.querySelectorAll !== 'function') {
            return null;
        }

        var cbId = String(checkboxId || '').trim();
        var taskName = String(task || '').trim();
        if (cbId === '' || taskName === '') {
            return null;
        }

        var candidates = root.querySelectorAll('[onclick*="listItemTask("]');
        var pattern = new RegExp(
            'listItemTask\\(\\s*[\'"]' + cbUsersEscapeRegExp(cbId) + '[\'"]\\s*,\\s*[\'"]' + cbUsersEscapeRegExp(taskName) + '[\'"]',
            'i'
        );

        for (var i = 0; i < candidates.length; i++) {
            var onclick = String(candidates[i].getAttribute('onclick') || '');
            if (pattern.test(onclick)) {
                return candidates[i];
            }
        }

        return null;
    }

    function cbUsersSubmitTaskAjax(form, checkbox, task, actionElement) {
        if (!form || cbUsersAjaxBusy) {
            return false;
        }

        cbUsersAjaxBusy = true;

        var checkboxId = checkbox && typeof checkbox.id !== 'undefined' ? String(checkbox.id || '') : '';
        var rowId = checkbox && typeof checkbox.value !== 'undefined' ? String(checkbox.value || '') : '';
        var formData = new FormData(form);
        formData.set('task', task);
        formData.set('cb_ajax', '1');
        formData.set('option', 'com_contentbuilderng');

        if (rowId !== '') {
            formData.delete('cid[]');
            formData.append('cid[]', rowId);
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
            .then(function (response) {
                return response.text().then(function (text) {
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
            .then(function () {
                var resolvedActionElement = actionElement;
                if (!resolvedActionElement && checkbox && typeof checkbox.closest === 'function') {
                    var row = checkbox.closest('tr');
                    resolvedActionElement =
                        cbUsersFindActionElementByCheckboxAndTask(row, checkboxId, task)
                        || cbUsersFindActionElement(row, task);
                }
                if (!resolvedActionElement) {
                    resolvedActionElement =
                        cbUsersFindActionElementByCheckboxAndTask(form, checkboxId, task);
                }

                if (!resolvedActionElement) {
                    window.location.reload();
                    return;
                }

                cbUsersApplyAjaxToggleState(resolvedActionElement, task);
            })
            .catch(function (error) {
                var message = error && error.message ? error.message : 'Save failed';
                if (window.Joomla && typeof Joomla.renderMessages === 'function') {
                    Joomla.renderMessages({ error: [message] });
                } else {
                    alert(message);
                }
            })
            .finally(function () {
                var boxchecked = form.querySelector('input[name="boxchecked"]');
                if (checkbox) {
                    checkbox.checked = false;
                }
                if (boxchecked) {
                    boxchecked.value = '0';
                }
                cbUsersUpdateBulkSelectState();
                cbUsersAjaxBusy = false;
            });

        return false;
    }

    var cbUsersOriginalListItemTask = (typeof Joomla !== 'undefined' && typeof Joomla.listItemTask === 'function')
        ? Joomla.listItemTask
        : null;

    function cbUsersListItemTask(id, task, form) {
        form = form || document.getElementById('adminForm') || document.adminForm;

        if (!form) {
            return false;
        }

        var checkboxes = form.querySelectorAll('input[type="checkbox"][id^="cb"]');
        checkboxes.forEach(function (cb) {
            cb.checked = false;
        });

        var target = form.querySelector('#' + cbUsersEscapeId(id)) || form.elements[id];
        if (!target) {
            if (typeof cbUsersOriginalListItemTask === 'function') {
                return cbUsersOriginalListItemTask(id, task, form);
            }
            return false;
        }

        target.checked = true;

        var boxchecked = form.querySelector('input[name="boxchecked"]');
        if (boxchecked) {
            boxchecked.value = 1;
        }

        if (!cbUsersIsAjaxToggleTask(task)) {
            if (typeof cbUsersOriginalListItemTask === 'function') {
                return cbUsersOriginalListItemTask(id, task, form);
            }
            if (typeof Joomla !== 'undefined' && typeof Joomla.submitform === 'function') {
                Joomla.submitform(task, form);
            } else {
                form.submit();
            }
            return false;
        }

        var row = (typeof target.closest === 'function') ? target.closest('tr') : null;
        var actionElement = cbUsersFindActionElement(row, task);

        return cbUsersSubmitTaskAjax(form, target, task, actionElement);
    }

    if (typeof Joomla !== 'undefined') {
        Joomla.listItemTask = cbUsersListItemTask;
    }

    if (cbUsersForm) {
        cbUsersForm.addEventListener('change', function (event) {
            var target = event && event.target ? event.target : null;
            if (!target || target.type !== 'checkbox') {
                return;
            }
            cbUsersUpdateBulkSelectState();
        }, true);
    }

    cbUsersUpdateBulkSelectState();
    cbUsersInitColumnPicker();
})();
</script>
