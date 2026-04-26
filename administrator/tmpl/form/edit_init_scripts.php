<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
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
                setValue('task', 'form.display');

                form.submit();
            });
        });

        form.querySelectorAll('input[name^="jform[order]["]').forEach(function(input) {
            var sanitize = function() {
                input.value = String(input.value || '').replace(/[^0-9]/g, '');
            };

            input.setAttribute('inputmode', 'numeric');
            input.setAttribute('pattern', '[0-9]*');

            input.addEventListener('input', sanitize);
            input.addEventListener('paste', function() {
                window.setTimeout(sanitize, 0);
            });
        });
    });
</script>

<script type="text/javascript">
    const cbViewportStateKey = 'cbng.form.viewport.<?php echo (int) ($this->item->id ?? 0); ?>';
    const cbElementsColumnsStateKey = 'cbng.form.elements.columns.<?php echo (int) ($this->item->id ?? 0); ?>';
    const cbPermissionsColumnsStateKey = 'cbng.form.permissions.columns.<?php echo (int) ($this->item->id ?? 0); ?>';
    const cbElementsColumnsLabel = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_COLUMNS'), JSON_UNESCAPED_UNICODE); ?>;
    const cbElementsColumnsDefaultState = Object.freeze({
        id: true,
        label: true,
        list: true,
        search: true,
        link: true,
        edit: true,
        wordwrap: false,
        publish: true,
        order: true
    });
    const cbFormId = <?php echo (int) ($this->item->id ?? 0); ?>;
    const cbSaveAnimationDurationMs = 500;
    const cbIsBreezingFormsType = <?php echo $isBreezingFormsType ? 'true' : 'false'; ?>;
    const cbBreezingFormsEditableToken = <?php echo json_encode($breezingFormsEditableToken, JSON_UNESCAPED_UNICODE); ?>;
    const cbEditByTypeEnableConfirm = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_TYPE_EDIT_ENABLE_BF_CONFIRM'), JSON_UNESCAPED_UNICODE); ?>;
    const cbFormNotFoundMessage = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), JSON_UNESCAPED_UNICODE); ?>;
    const cbSaveFailedMessage = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_SAVE_FAILED'), JSON_UNESCAPED_UNICODE); ?>;
    const cbCloseUnsavedMessage = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_CONFIRM_CLOSE_UNSAVED'), JSON_UNESCAPED_UNICODE); ?>;
    const cbUnnamedLabel = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_UNNAMED'), JSON_UNESCAPED_UNICODE); ?>;
    const cbInheritedFromLabel = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_INHERITED_FROM'), JSON_UNESCAPED_UNICODE); ?>;
    const cbFirefoxVersionMatch = String(window.navigator.userAgent || '').match(/\bfirefox\/(\d+)/i);
    const cbFirefoxMajorVersion = cbFirefoxVersionMatch ? parseInt(cbFirefoxVersionMatch[1], 10) : 0;
    const cbIsFirefoxBrowser = cbFirefoxMajorVersion > 0;
    let cbLastRowId = '';
    let cbAjaxBusy = false;
    let cbSaveButtonTimer = null;

    function cbSetupFirefoxTinyMceIframeReloadGuard() {
        if (!cbIsFirefoxBrowser) {
            return;
        }

        var iframeProto = window.HTMLIFrameElement && window.HTMLIFrameElement.prototype;
        if (!iframeProto || typeof iframeProto.addEventListener !== 'function') {
            return;
        }

        if (iframeProto.addEventListener.__cbTinyMceReloadGuardApplied === true) {
            return;
        }

        var originalAddEventListener = iframeProto.addEventListener;
        iframeProto.addEventListener = function(type, listener, options) {
            try {
                if (type === 'load' && typeof listener === 'function') {
                    var listenerCode = Function.prototype.toString.call(listener);
                    var listenerCodeLower = listenerCode.toLowerCase();
                    var stack = String((new Error()).stack || '').toLowerCase();
                    var looksLikeJoomlaTinyReloadListener =
                        listenerCodeLower.indexOf('debouncereinit') !== -1 ||
                        /\/media\/plg_editors_tinymce\/js\/tinymce(?:\.min)?\.js/.test(stack);

                    if (looksLikeJoomlaTinyReloadListener) {
                        return;
                    }
                }
            } catch (e) {
                // no-op: keep default registration path
            }

            return originalAddEventListener.call(this, type, listener, options);
        };

        iframeProto.addEventListener.__cbTinyMceReloadGuardApplied = true;
    }

    // TODO: Remove this workaround once the upstream issue is fixed.
    // Reference (observed on Firefox): repeated TinyMCE re-init loop on Joomla admin form edit
    // via media/plg_editors_tinymce/js/tinymce(.min).js (listenIframeReload -> debounceReInit),
    // with recursive editor init path also crossing media/vendor/tinymce/plugins/wordcount/plugin(.min).js setup.
    cbSetupFirefoxTinyMceIframeReloadGuard();

    function cbRememberViewport(rowId) {
        var payload = {
            y: window.scrollY || document.documentElement.scrollTop || 0,
            rowId: rowId ? String(rowId) : (cbLastRowId ? String(cbLastRowId) : ''),
            ts: Date.now()
        };

        try {
            window.sessionStorage.setItem(cbViewportStateKey, JSON.stringify(payload));
        } catch (e) {
            // ignore storage failures
        }
    }

    function cbRestoreViewport() {
        var payloadRaw = null;

        try {
            payloadRaw = window.sessionStorage.getItem(cbViewportStateKey);
        } catch (e) {
            payloadRaw = null;
        }

        if (!payloadRaw) {
            return;
        }

        var payload = null;
        try {
            payload = JSON.parse(payloadRaw);
        } catch (e) {
            payload = null;
        }

        try {
            window.sessionStorage.removeItem(cbViewportStateKey);
        } catch (e) {
            // ignore storage failures
        }

        if (!payload || typeof payload !== 'object') {
            return;
        }

        var ageMs = Date.now() - Number(payload.ts || 0);
        if (!Number.isFinite(ageMs) || ageMs > 120000) {
            return;
        }

        var y = Number(payload.y || 0);
        if (Number.isFinite(y) && y > 0) {
            window.requestAnimationFrame(function() {
                window.scrollTo(0, y);
            });
        }
    }

    function cbAnimateSaveButton() {
        var shouldRestoreDisabled = !cbDirtyState;
        var targets = cbGetSaveButtons();

        if (!targets.length) {
            return;
        }

        cbSetSaveButtonsEnabled(true);

        targets.forEach(function(el) {
            el.classList.remove('cb-save-animate');
            void el.offsetWidth;
            el.classList.add('cb-save-animate');

            if (el.parentElement && el.parentElement.classList) {
                el.parentElement.classList.remove('cb-save-animate');
                void el.parentElement.offsetWidth;
                el.parentElement.classList.add('cb-save-animate');
            }
        });

        if (cbSaveButtonTimer) {
            window.clearTimeout(cbSaveButtonTimer);
            cbSaveButtonTimer = null;
        }

        cbSaveButtonTimer = window.setTimeout(function() {
            targets.forEach(function(el) {
                el.classList.remove('cb-save-animate');
                if (el.parentElement && el.parentElement.classList) {
                    el.parentElement.classList.remove('cb-save-animate');
                }
            });

            if (shouldRestoreDisabled) {
                cbSetSaveButtonsEnabled(false);
            }
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
            'form.list_include': {
                nextTask: 'form.no_list_include',
                enabled: true
            },
            'form.no_list_include': {
                nextTask: 'form.list_include',
                enabled: false
            },
            'form.search_include': {
                nextTask: 'form.no_search_include',
                enabled: true
            },
            'form.no_search_include': {
                nextTask: 'form.search_include',
                enabled: false
            },
            'form.linkable': {
                nextTask: 'form.not_linkable',
                enabled: true
            },
            'form.not_linkable': {
                nextTask: 'form.linkable',
                enabled: false
            },
            'form.editable': {
                nextTask: 'form.not_editable',
                enabled: true
            },
            'form.not_editable': {
                nextTask: 'form.editable',
                enabled: false
            },
            'form.listpublish': {
                nextTask: 'form.listunpublish',
                enabled: true
            },
            'form.listunpublish': {
                nextTask: 'form.listpublish',
                enabled: false
            }
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
    }

    function cbUpdateEditableBadge(actionElement, task, rowId) {
        if (task !== 'form.editable' && task !== 'form.not_editable') {
            return;
        }

        var editByTypeEl = document.getElementById('edit_by_type');
        if (editByTypeEl && editByTypeEl.checked) {
            return;
        }

        var row = actionElement && typeof actionElement.closest === 'function'
            ? actionElement.closest('tr[data-cb-row-id]') : null;
        if (!row) {
            return;
        }

        var editCell = row.querySelector('[data-cb-col="edit"]');
        if (!editCell) {
            return;
        }

        if (task === 'form.not_editable') {
            var defaultBadge = editCell.querySelector('.cb-item-type-badge.is-default');
            if (defaultBadge && defaultBadge.parentElement) {
                defaultBadge.parentElement.remove();
            }
            return;
        }

        if (!editCell.querySelector('.cb-item-type-badge')) {
            var wrapper = document.createElement('div');
            wrapper.className = 'mt-1';
            var link = document.createElement('a');
            link.className = 'cb-item-type-badge is-default';
            link.href = 'index.php?option=com_contentbuilderng&view=elementoptions&tmpl=component&element_id='
                + encodeURIComponent(rowId) + '&id=' + encodeURIComponent(cbFormId);
            link.setAttribute('data-bs-toggle', 'modal');
            link.setAttribute('data-bs-target', '#text-type-modal');
            link.textContent = 'Default';
            wrapper.appendChild(link);
            editCell.appendChild(wrapper);
        }
    }

    function cbIsAjaxToggleTask(task) {
        return [
            'form.list_include',
            'form.no_list_include',
            'form.search_include',
            'form.no_search_include',
            'form.linkable',
            'form.not_linkable',
            'form.editable',
            'form.not_editable',
            'form.listpublish',
            'form.listunpublish'
        ].indexOf(task) !== -1;
    }

    function cbNormalizeRowTask(task) {
        switch (task) {
            case 'form.publish':
                return 'form.listpublish';
            case 'form.unpublish':
                return 'form.listunpublish';
            default:
                return task;
        }
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
            actionElement.getAttribute('data-item-task') ||
            actionElement.getAttribute('data-submit-task') ||
            actionElement.getAttribute('data-task') ||
            ''
        ).trim();

        if (dataTask === '') {
            return null;
        }

        return {
            checkboxId: '',
            task: dataTask.indexOf('.') === -1 ? ('form.' + dataTask) : dataTask
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
                onError(cbFormNotFoundMessage);
            }
            return;
        }

        if (cbAjaxBusy) {
            return;
        }

        cbAjaxBusy = true;
        cbRememberViewport(rowId || '');
        cbDismissTransientTooltips();
        cbAnimateSaveButton();

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
                        throw new Error((payload && payload.message) ? payload.message : cbSaveFailedMessage);
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
                    onError(error && error.message ? error.message : cbSaveFailedMessage);
                    return;
                }
                alert(error && error.message ? error.message : cbSaveFailedMessage);
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

        var f = document.adminForm;
        f.limitstart.value = <?php echo Factory::getApplication()->getInput()->getInt('limitstart', 0) ?>;
        var cb = f.elements[id] || document.getElementById(id);

        if (cb) {
            for (var i = 0; true; i++) {
                var cbx = f.elements['cb' + i] || document.getElementById('cb' + i);
                if (!cbx) break;
                cbx.checked = false;
            } // for
            cb.checked = true;
            f.boxchecked.value = 1;
            if (typeof cb.value !== 'undefined' && cb.value !== '') {
                cbLastRowId = String(cb.value);
                cbRememberViewport(cbLastRowId);
            }

            switch (task) {
                case 'form.publish':
                    task = 'form.listpublish';
                    break;
                case 'form.unpublish':
                    task = 'form.listunpublish';
                    break;
                case 'form.orderdown':
                    task = 'form.listorderdown';
                    break;
                case 'form.orderup':
                    task = 'form.listorderup';
                    break;
            }

            if (cbIsAjaxToggleTask(task)) {
                var rowId = (typeof cb.value !== 'undefined' && cb.value !== '') ? String(cb.value) : '';
                var actionElement = null;

                if (cb && typeof cb.closest === 'function') {
                    var row = cb.closest('tr[data-cb-row-id]');
                    if (row) {
                        actionElement = row.querySelector(
                            '[data-item-task="' + task + '"], [data-submit-task="' + task + '"], [data-task="' + task + '"], [onclick*="' + task + '"]'
                        );
                    }
                }

                cbSubmitTaskAjax(task, rowId, function() {
                    cbApplyAjaxToggleState(actionElement, task);
                    cbUpdateEditableBadge(actionElement, task, rowId);
                }, null, actionElement);
                return false;
            }

            submitbutton(task);
        }
        return false;
    }

    function submitbutton(task) {
        const form = document.getElementById('adminForm') || document.adminForm;
        if (!form) return;

        if (!task || task === 'form.display') {
            Joomla.submitform('form.display', form);
            return;
        }

        if (task == 'form.remove') {
            task = 'form.listremove';
        }


        switch (task) {
            case 'form.cancel':
                try {
                    cbRefreshDirtyState();
                } catch (e) {
                    cbSetDirtyState(false);
                }

                if (cbDirtyState && !confirm(cbCloseUnsavedMessage)) {
                    return;
                }

                cbBypassDirtyBeforeUnload();
                cbSubmitFormCancel(form, task);
                break;
            case 'form.publish':
            case 'form.unpublish':
            case 'form.formpublish':
            case 'form.formunpublish':
            case 'form.listpublish':
            case 'form.listunpublish':
            case 'form.listorderdown':
            case 'form.listorderup':
            case 'form.saveorder':
            case 'form.listremove':
            case 'form.list_include':
            case 'form.no_list_include':
            case 'form.search_include':
            case 'form.no_search_include':
            case 'form.linkable':
            case 'form.not_linkable':
            case 'form.editable':
            case 'form.not_editable':
            case 'form.save_labels':
                Joomla.submitform(task, form);
                break;
            case 'form.save':
            case 'form.save2new':
            case 'form.apply':
                cbNormalizeEditableTemplateForEditByType();
                var error = false;
                var nodes = document.adminForm['cid[]'];

                if (document.getElementById('name').value == '') {
                    error = true;
                    alert("<?php echo addslashes(Text::_('COM_CONTENTBUILDERNG_ERROR_ENTER_FORMNAME')); ?>");
                } else if (nodes) {
                    if (typeof nodes.value != 'undefined') {
                        if (nodes.checked && document.adminForm['elementLabels[' + nodes.value + ']'].value == '') {
                            error = true;
                            alert("<?php echo addslashes(Text::_('COM_CONTENTBUILDERNG_ERROR_ENTER_FORMNAME_ALL')); ?>");
                            break;
                        }
                    } else {
                        for (var i = 0; i < nodes.length; i++) {
                            if (nodes[i].checked && document.adminForm['elementLabels[' + nodes[i].value + ']'].value == '') {
                                error = true;
                                alert("<?php echo addslashes(Text::_('COM_CONTENTBUILDERNG_ERROR_ENTER_FORMNAME_ALL')); ?>");
                                break;
                            }
                        }
                    }
                }

                if (!error) {
                    cbSetDirtyState(false);
                    cbAnimateSaveButton();
                    Joomla.submitform(task);
                }

                break;
        }
    }

    function cbSubmitFormCancel(form, task) {
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

    function saveorder(n, task) {
        if (task === 'saveorder') {
            task = 'form.saveorder';
        }
        submitbutton(task);
    }

    function cbHandleItemLabelBlur(input, elementId) {
        if (!input) {
            return;
        }

        var value = String(input.value || '').trim();
        if (value === '') {
            value = cbUnnamedLabel;
        }

        input.value = value;
        input.style.display = 'none';

        var displayNode = document.getElementById('itemLabels_' + elementId);
        if (displayNode) {
            displayNode.style.display = 'block';
            displayNode.innerHTML = '';
            var strong = document.createElement('b');
            strong.textContent = value;
            displayNode.appendChild(strong);
        }

        var lastSaved = String(input.getAttribute('data-cb-last-saved') || '');
        if (lastSaved === value) {
            return;
        }

        cbLastRowId = String(elementId);
        cbSubmitTaskAjax(
            'form.save_labels',
            cbLastRowId,
            function() {
                input.setAttribute('data-cb-last-saved', value);
            },
            function(message) {
                input.setAttribute('data-cb-last-saved', lastSaved);
                alert(message || cbSaveFailedMessage);
            }
        );
    }

    function cbQueueDetailsSampleGeneration(button) {
        var hiddenFlag = document.getElementById('cb_create_sample_flag');
        if (!hiddenFlag) {
            return;
        }

        hiddenFlag.value = '1';

        if (button) {
            button.classList.remove('btn-outline-secondary');
            button.classList.add('btn-success');
        }

        var hint = document.getElementById('cb_create_sample_hint');
        if (hint) {
            hint.classList.remove('d-none');
        }

        cbHandleDirtyInteraction();
    }

    function cbGetJoomlaEditorInstance(fieldName) {
        var editorId = 'jform_' + fieldName;
        var api = window.JoomlaEditor;

        if (api && typeof api.get === 'function') {
            var joomlaInstance = api.get(editorId) || api.get(fieldName) || null;
            if (joomlaInstance) {
                return joomlaInstance;
            }
        }

        var codeMirrorHost = document.querySelector('joomla-editor-codemirror textarea#' + editorId + ', joomla-editor-codemirror textarea[name="jform[' + fieldName + ']"]');
        if (codeMirrorHost && typeof codeMirrorHost.closest === 'function') {
            var codeMirrorElement = codeMirrorHost.closest('joomla-editor-codemirror');
            if (codeMirrorElement && codeMirrorElement.jEditor) {
                return codeMirrorElement.jEditor;
            }
        }

        if (window.tinymce && typeof window.tinymce.get === 'function') {
            var tinyInstance = window.tinymce.get(editorId);
            if (tinyInstance) {
                return tinyInstance;
            }
        }

        return null;
    }

    function cbGetCodeMirrorEditorView(fieldName) {
        var editorId = 'jform_' + fieldName;
        var textarea = document.getElementById(editorId) ||
            document.querySelector('textarea[name="jform[' + fieldName + ']"]');

        if (!textarea || typeof textarea.closest !== 'function') {
            return null;
        }

        var host = textarea.closest('joomla-editor-codemirror');
        if (!host) {
            return null;
        }

        if (host.instance) {
            return host.instance;
        }

        var editorDom = host.querySelector('.cm-editor');
        if (editorDom && editorDom.cmView && editorDom.cmView.view) {
            return editorDom.cmView.view;
        }

        return null;
    }

    function cbBindCodeMirrorViewDirtyTracking(fieldName) {
        var editorView = cbGetCodeMirrorEditorView(fieldName);
        if (!editorView || editorView.__cbDirtyTrackingBound) {
            return 0;
        }

        editorView.__cbDirtyTrackingBound = true;

        var targets = [];
        if (editorView.dom) {
            targets.push(editorView.dom);
        }
        if (editorView.contentDOM && targets.indexOf(editorView.contentDOM) === -1) {
            targets.push(editorView.contentDOM);
        }

        targets.forEach(function(target) {
            if (!target || target.__cbDirtyTrackingListenersBound) {
                return;
            }

            target.__cbDirtyTrackingListenersBound = true;

            ['beforeinput', 'input', 'change', 'keyup', 'keydown', 'paste', 'cut'].forEach(function(eventName) {
                target.addEventListener(eventName, function() {
                    window.requestAnimationFrame(cbHandleDirtyInteraction);
                }, true);
            });
        });

        return 1;
    }

    function cbGetEditorInstancesFromFields(root) {
        var scope = root || document;
        var instances = [];
        var seen = {};

        scope.querySelectorAll('textarea[name^="jform["], input[name^="jform["]').forEach(function(field) {
            var fieldName = cbExtractSimpleJformFieldName(field.name || '');
            if (!fieldName) {
                return;
            }

            var instance = cbGetJoomlaEditorInstance(fieldName);
            if (!instance) {
                return;
            }

            var key = String(fieldName);
            if (seen[key]) {
                return;
            }

            seen[key] = true;
            instances.push(instance);
        });

        return instances;
    }

    function cbGetEditorFieldValue(fieldName) {
        var value = '';
        var instance = cbGetJoomlaEditorInstance(fieldName);

        if (instance) {
            if (typeof instance.getValue === 'function') {
                value = instance.getValue();
            } else if (typeof instance.getContent === 'function') {
                value = instance.getContent();
            }
        }

        if (!value || !String(value).trim()) {
            var input = document.querySelector('textarea[name="jform[' + fieldName + ']"], input[name="jform[' + fieldName + ']"]');
            if (input && typeof input.value === 'string') {
                value = input.value;
            }
        }

        return String(value || '');
    }

    function cbSetEditorFieldValue(fieldName, value) {
        var stringValue = String(value || '');
        var updatedViaEditor = false;
        var instance = cbGetJoomlaEditorInstance(fieldName);

        if (instance) {
            var currentValue = '';
            if (typeof instance.getValue === 'function') {
                currentValue = String(instance.getValue() || '');
            } else if (typeof instance.getContent === 'function') {
                currentValue = String(instance.getContent() || '');
            }

            if (currentValue !== stringValue) {
                if (typeof instance.setValue === 'function') {
                    instance.setValue(stringValue);
                    updatedViaEditor = true;
                } else if (typeof instance.setContent === 'function') {
                    instance.setContent(stringValue);
                    updatedViaEditor = true;
                }
            }
        }

        if (!updatedViaEditor) {
            document.querySelectorAll('textarea[name="jform[' + fieldName + ']"], input[name="jform[' + fieldName + ']"]').forEach(function(input) {
                if (String(input.value || '') !== stringValue) {
                    input.value = stringValue;
                }
            });
        }
    }

    function cbIsBreezingFormsPlaceholder(value) {
        return /^\s*\{BreezingForms\s*:[^}]+\}\s*$/i.test(String(value || ''));
    }

    function cbNormalizeEditableTemplateForEditByType() {
        var checkbox = document.getElementById('edit_by_type');
        if (!checkbox) {
            return;
        }

        if (checkbox.checked) {
            if (cbIsBreezingFormsType && cbBreezingFormsEditableToken.trim() !== '') {
                var currentEditableTemplate = cbGetEditorFieldValue('editable_template');
                if (String(currentEditableTemplate || '') !== String(cbBreezingFormsEditableToken || '')) {
                    cbSetEditorFieldValue('editable_template', cbBreezingFormsEditableToken);
                }
            }
            return;
        }

        var currentTemplate = cbGetEditorFieldValue('editable_template');
        if (cbIsBreezingFormsPlaceholder(currentTemplate)) {
            cbSetEditorFieldValue('editable_template', '');
        }
    }

    function cbHandleEditByTypeToggle(checkbox) {
        if (!checkbox) {
            return;
        }

        if (checkbox.checked && cbIsBreezingFormsType) {
            var confirmed = confirm(cbEditByTypeEnableConfirm);
            if (!confirmed) {
                checkbox.checked = false;
                return;
            }
        }

        cbNormalizeEditableTemplateForEditByType();
    }

    function cbTemplateHasContent(rawValue) {
        if (typeof rawValue !== 'string' || !rawValue.trim()) {
            return false;
        }

        var wrapper = document.createElement('div');
        wrapper.innerHTML = rawValue;

        var text = (wrapper.textContent || wrapper.innerText || '')
            .replace(/\u00a0/g, ' ')
            .trim();

        if (text !== '') {
            return true;
        }

        return /<(img|table|iframe|video|audio|svg|object|embed|canvas|hr)\b/i.test(rawValue);
    }

    function cbQueueEditableSampleGeneration(button) {
        var hiddenFlag = document.getElementById('cb_create_editable_sample_flag');
        if (!hiddenFlag) {
            return;
        }

        var currentTemplate = cbGetEditorFieldValue('editable_template');
        if (cbTemplateHasContent(currentTemplate)) {
            var shouldContinue = confirm("<?php echo addslashes(Text::_('COM_CONTENTBUILDERNG_INITIALISE_OVERWRITE_CONFIRM')); ?>");
            if (!shouldContinue) {
                return;
            }
        }

        hiddenFlag.value = '1';

        if (button) {
            button.classList.remove('btn-outline-secondary');
            button.classList.add('btn-success');
        }

        var hint = document.getElementById('cb_create_editable_sample_hint');
        if (hint) {
            hint.classList.remove('d-none');
        }

        cbHandleDirtyInteraction();
    }

    function cbGetPrepareExamplesModalElement() {
        var modalElement = document.getElementById('cb-prepare-examples-modal');
        if (!modalElement) {
            return null;
        }

        if (modalElement.parentNode !== document.body) {
            document.body.appendChild(modalElement);
        }

        return modalElement;
    }

    function cbOpenPrepareExamples(triggerElement) {
        var modalElement = cbGetPrepareExamplesModalElement();
        if (!modalElement || !window.bootstrap || typeof window.bootstrap.Modal !== 'function') {
            return;
        }

        if (triggerElement && window.bootstrap && typeof window.bootstrap.Tooltip === 'function') {
            var tooltipInstance = window.bootstrap.Tooltip.getInstance(triggerElement);
            if (tooltipInstance && typeof tooltipInstance.hide === 'function') {
                tooltipInstance.hide();
            }
        }

        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }

    function cbQueueEmailAdminSampleGeneration(button) {
        var hiddenFlag = document.getElementById('cb_email_admin_create_sample_flag');
        if (!hiddenFlag) {
            return;
        }

        var currentTemplate = cbGetEditorFieldValue('email_admin_template');
        if (cbTemplateHasContent(currentTemplate)) {
            var shouldContinue = confirm("<?php echo addslashes(Text::_('COM_CONTENTBUILDERNG_INITIALISE_OVERWRITE_CONFIRM')); ?>");
            if (!shouldContinue) {
                return;
            }
        }

        hiddenFlag.value = '1';

        if (button) {
            button.classList.remove('btn-outline-secondary');
            button.classList.add('btn-success');
        }

        var hint = document.getElementById('cb_email_admin_create_sample_hint');
        if (hint) {
            hint.classList.remove('d-none');
        }

        cbHandleDirtyInteraction();
    }

    function cbQueueEmailUserSampleGeneration(button) {
        var hiddenFlag = document.getElementById('cb_email_create_sample_flag');
        if (!hiddenFlag) {
            return;
        }

        var currentTemplate = cbGetEditorFieldValue('email_template');
        if (cbTemplateHasContent(currentTemplate)) {
            var shouldContinue = confirm("<?php echo addslashes(Text::_('COM_CONTENTBUILDERNG_INITIALISE_OVERWRITE_CONFIRM')); ?>");
            if (!shouldContinue) {
                return;
            }
        }

        hiddenFlag.value = '1';

        if (button) {
            button.classList.remove('btn-outline-secondary');
            button.classList.add('btn-success');
        }

        var hint = document.getElementById('cb_email_create_sample_hint');
        if (hint) {
            hint.classList.remove('d-none');
        }

        cbHandleDirtyInteraction();
    }

    function cbAppendLineToEditorField(fieldName, line) {
        var current = cbGetEditorFieldValue(fieldName);
        var next = String(current || '');

        if (next && !/(\r\n|\r|\n)$/.test(next)) {
            next += '\n';
        }

        next += String(line || '');
        cbSetEditorFieldValue(fieldName, next);
    }

    function cbInsertEditablePrepareSnippet() {
        cbInsertPrepareSnippet('editable_prepare', 'cb_editable_prepare_snippet_select', 'cb_editable_prepare_slot', 'cb_editable_prepare_effect_select', 'cb_editable_prepare_snippet_hint');
    }

    function cbInsertDetailsPrepareSnippet() {
        cbInsertPrepareSnippet('details_prepare', 'cb_details_prepare_snippet_select', 'cb_details_prepare_slot', 'cb_details_prepare_effect_select', 'cb_details_prepare_snippet_hint');
    }

    function cbGetPrepareSnippetSlot(radioName) {
        if (!radioName) {
            return 'value';
        }

        var checked = document.querySelector('input[name="' + radioName + '"]:checked');
        if (!checked) {
            return 'value';
        }

        return String(checked.value || '').toLowerCase() === 'label' ? 'label' : 'value';
    }

    function cbBuildPrepareSnippetWithEffect(sourcePath, effectName) {
        var effect = String(effectName || 'none').toLowerCase();
        var expression = sourcePath;

        switch (effect) {
            case 'bold':
                expression = '"<b>".' + sourcePath + '."</b>"';
                break;
            case 'red':
                expression = '"<span style=\\"color:#dc3545\\">".' + sourcePath + '."</span>"';
                break;
            case 'italic':
                expression = '"<i>".' + sourcePath + '."</i>"';
                break;
            case 'gray':
                expression = '"<span style=\\"color:#6c757d\\">".' + sourcePath + '."</span>"';
                break;
            case 'negativered':
                expression = '(is_numeric((string) ' + sourcePath + ') && (float) ' + sourcePath + ' < 0) ? "<span style=\\"color:#dc3545\\">".' + sourcePath + '."</span>" : ' + sourcePath;
                break;
            case 'eurosuffix':
                expression = '((string) ' + sourcePath + ') . " €"';
                break;
            case 'upper':
                expression = 'strtoupper((string) ' + sourcePath + ')';
                break;
            case 'lower':
                expression = 'strtolower((string) ' + sourcePath + ')';
                break;
            case 'blink':
                expression = '"<span class=\\"cb-prepare-blink\\">".' + sourcePath + '."</span>"';
                break;
            case 'truncate10':
                expression = '(mb_strlen((string) ' + sourcePath + ') > 10) ? mb_substr((string) ' + sourcePath + ', 0, 10) . "..." : (string) ' + sourcePath;
                break;
            case 'none':
            default:
                expression = sourcePath;
                break;
        }

        return sourcePath + ' = ' + expression + ';';
    }

    function cbInsertPrepareSnippet(fieldName, selectId, slotRadioName, effectSelectId, hintId) {
        var select = document.getElementById(selectId);
        if (!select) {
            return;
        }

        var baseItemPath = String(select.value || '').trim();
        if (!baseItemPath) {
            return;
        }

        var slot = cbGetPrepareSnippetSlot(slotRadioName);
        var sourcePath = baseItemPath + '["' + slot + '"]';
        var effect = 'none';
        var effectSelect = effectSelectId ? document.getElementById(effectSelectId) : null;
        if (effectSelect) {
            effect = String(effectSelect.value || 'none');
        }

        var snippet = cbBuildPrepareSnippetWithEffect(sourcePath, effect);
        cbAppendLineToEditorField(fieldName, snippet);

        var hint = document.getElementById(hintId);
        if (hint) {
            hint.classList.remove('d-none');
        }
    }

    function cbAutoSizeSelectToContent(selectId) {
        var select = document.getElementById(selectId);
        if (!select || !select.options) {
            return;
        }

        var maxChars = 0;
        Array.prototype.forEach.call(select.options, function(option) {
            var length = String((option && option.text) ? option.text : '').trim().length;
            if (length > maxChars) {
                maxChars = length;
            }
        });

        if (maxChars < 1) {
            return;
        }

        var widthCh = Math.min(Math.max(maxChars + 4, 12), 42);
        select.style.width = widthCh + 'ch';
        select.style.minWidth = '12ch';
        select.style.maxWidth = '42ch';
    }

    document.addEventListener('DOMContentLoaded', function() {
        cbRestoreViewport();

        var form = document.getElementById('adminForm') || document.adminForm;
        if (!form) {
            return;
        }

        cbAutoSizeSelectToContent('cb_details_prepare_snippet_select');
        cbAutoSizeSelectToContent('cb_editable_prepare_snippet_select');

        var editByTypeCheckbox = document.getElementById('edit_by_type');
        if (editByTypeCheckbox) {
            editByTypeCheckbox.addEventListener('change', function() {
                cbHandleEditByTypeToggle(editByTypeCheckbox);
            });
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

            var task = cbNormalizeRowTask(parsed.task);
            if (!cbIsAjaxToggleTask(task)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }

            var rowId = cbResolveRowId(actionElement, parsed.checkboxId);
            if (rowId !== '') {
                cbLastRowId = rowId;
            }

            cbSubmitTaskAjax(task, rowId, function() {
                cbApplyAjaxToggleState(actionElement, task);
                cbUpdateEditableBadge(actionElement, task, rowId);
            }, null, actionElement);
        }, true);

        form.addEventListener('click', function(event) {
            var target = event.target;
            if (!target || typeof target.closest !== 'function') {
                return;
            }

            var row = target.closest('tr[data-cb-row-id]');
            if (row) {
                cbLastRowId = String(row.getAttribute('data-cb-row-id') || '');
            }
        });

        form.addEventListener('submit', function() {
            cbNormalizeEditableTemplateForEditByType();
            cbRememberViewport();
        });
    });

    if (typeof Joomla != 'undefined') {
        Joomla.submitbutton = submitbutton;
        Joomla.listItemTask = listItemTask;
    }

    function contentbuilderng_selectAll(checker, type) {
        var type = type == 'fe' ? 'jform[perms_fe][' : 'jform[perms][';
        for (var i = 0; i < document.adminForm.elements.length; i++) {
            if (typeof document.adminForm.elements[i].name != 'undefined' && document.adminForm.elements[i].name.startsWith(type) && document.adminForm.elements[i].name.endsWith(checker.value + "]")) {
                if (checker.checked) {
                    document.adminForm.elements[i].checked = true;
                } else {
                    document.adminForm.elements[i].checked = false;
                }
            }
        }
    }

    function cbNormalizeColorForPreview(value) {
        if (typeof value !== 'string') {
            return '';
        }

        var hex = value.trim().replace(/^#/, '');

        if (/^[0-9a-fA-F]{3}$/.test(hex)) {
            return (
                hex.charAt(0) + hex.charAt(0) +
                hex.charAt(1) + hex.charAt(1) +
                hex.charAt(2) + hex.charAt(2)
            ).toUpperCase();
        }

        if (/^[0-9a-fA-F]{6}$/.test(hex)) {
            return hex.toUpperCase();
        }

        return '';
    }

    function cbNormalizeColorForNativePicker(value) {
        var hex = cbNormalizeColorForPreview(value);
        return hex ? '#' + hex : '';
    }

    function cbSyncTextInputToColorisFormat(textInput) {
        if (!textInput) {
            return;
        }

        var normalized = cbNormalizeColorForNativePicker(textInput.value);

        if (normalized) {
            textInput.value = normalized.toUpperCase();
        }
    }

    function cbUpdateColorisDefaultFromInput(input) {
        if (!input || typeof window.Coloris !== 'function') {
            return;
        }

        cbSyncTextInputToColorisFormat(input);
        var normalized = cbNormalizeColorForNativePicker(input.value);

        if (!normalized) {
            return;
        }

        window.Coloris({
            defaultColor: normalized
        });
    }

    function cbPreviewTextColor(hex) {
        var red = parseInt(hex.substr(0, 2), 16);
        var green = parseInt(hex.substr(2, 2), 16);
        var blue = parseInt(hex.substr(4, 2), 16);
        var luminance = ((red * 299) + (green * 587) + (blue * 114)) / 1000;
        return luminance >= 160 ? '#000000' : '#FFFFFF';
    }

    function cbApplyListStateColorPreview(input) {
        if (!input) {
            return;
        }

        var hex = cbNormalizeColorForPreview(input.value);

        if (!hex) {
            input.style.backgroundColor = '';
            input.style.color = '';
            return;
        }

        input.style.backgroundColor = '#' + hex;
        input.style.color = cbPreviewTextColor(hex);
    }

    function cbSyncNativePickerFromTextInput(textInput) {
        if (!textInput) {
            return;
        }

        var pickerId = textInput.getAttribute('data-cb-color-picker-target');

        if (!pickerId) {
            return;
        }

        var picker = document.getElementById(pickerId);

        if (!picker) {
            return;
        }

        var normalized = cbNormalizeColorForNativePicker(textInput.value);

        if (normalized) {
            picker.value = normalized;
        }
    }

    function cbSyncTextInputFromNativePicker(pickerInput) {
        if (!pickerInput) {
            return;
        }

        var textId = pickerInput.getAttribute('data-cb-color-target');

        if (!textId) {
            return;
        }

        var textInput = document.getElementById(textId);

        if (!textInput) {
            return;
        }

        textInput.value = pickerInput.value.toUpperCase();
        cbApplyListStateColorPreview(textInput);
    }

    function cbInitListStateColorControls() {
        var inputs = document.querySelectorAll('input[data-cb-color-text="1"]');

        for (var i = 0; i < inputs.length; i++) {
            cbSyncTextInputToColorisFormat(inputs[i]);
            cbApplyListStateColorPreview(inputs[i]);
            cbSyncNativePickerFromTextInput(inputs[i]);
        }
    }

    let cbColorisConfigured = false;
    let cbColorisInitRetries = 0;

    function cbInitColoris() {
        if (cbColorisConfigured) {
            return;
        }

        if (typeof window.Coloris !== 'function') {
            if (cbColorisInitRetries < 12) {
                cbColorisInitRetries++;
                window.setTimeout(cbInitColoris, 250);
            }
            return;
        }

        window.Coloris({
            el: 'input[data-cb-color-text="1"]',
            alpha: false,
            format: 'hex',
            clearButton: false,
            themeMode: 'light',
            defaultColor: '#FFFFFF'
        });
        cbColorisConfigured = true;
    }

    document.addEventListener('DOMContentLoaded', cbInitListStateColorControls);
    document.addEventListener('DOMContentLoaded', cbInitColoris);
    window.addEventListener('load', cbInitListStateColorControls);
    window.addEventListener('load', cbInitColoris);
    document.addEventListener('shown.bs.tab', cbInitListStateColorControls);
    document.addEventListener('shown.bs.tab', cbInitColoris);
    document.addEventListener('pointerdown', function(event) {
        if (event.target && event.target.matches('input[data-cb-color-text="1"]')) {
            cbUpdateColorisDefaultFromInput(event.target);
        }
    }, true);
    document.addEventListener('focusin', function(event) {
        if (event.target && event.target.matches('input[data-cb-color-text="1"]')) {
            cbUpdateColorisDefaultFromInput(event.target);
        }
    });
    document.addEventListener('input', function(event) {
        if (event.target && event.target.matches('input[data-cb-color-text="1"]')) {
            cbApplyListStateColorPreview(event.target);
            cbSyncNativePickerFromTextInput(event.target);
            cbUpdateColorisDefaultFromInput(event.target);
            return;
        }

        if (event.target && event.target.matches('input[data-cb-color-picker="1"]')) {
            cbSyncTextInputFromNativePicker(event.target);
        }
    });
    document.addEventListener('change', function(event) {
        if (event.target && event.target.matches('input[data-cb-color-text="1"]')) {
            cbApplyListStateColorPreview(event.target);
            cbSyncNativePickerFromTextInput(event.target);
            return;
        }

        if (event.target && event.target.matches('input[data-cb-color-picker="1"]')) {
            cbSyncTextInputFromNativePicker(event.target);
        }
    });
    window.setTimeout(cbInitListStateColorControls, 300);
    window.setTimeout(cbInitListStateColorControls, 1200);
    window.setTimeout(cbInitColoris, 300);
    window.setTimeout(cbInitColoris, 1200);

    let cbDirtyState = false;
    let cbDirtySnapshot = '';
    let cbEditorObserver = null;
    let cbEditorPollHandle = null;
    let cbDirtyTrackingInitialized = false;
    let cbDirtyUserInteracted = false;
    let cbDirtyBypassBeforeUnload = false;
    let cbSaveButtonsCache = null;

    function cbShouldIgnoreDirtyField(field) {
        if (!field) {
            return false;
        }

        var name = String(field.name || '');

        if (/^jform\[order\]\[\d+\]$/.test(name)) {
            return true;
        }

        return name === 'limit'
            && String(field.id || '') === 'limit'
            && typeof field.closest === 'function'
            && !!field.closest('.cb-form-elements-pagination');
    }

    function cbShouldTrackField(field) {
        if (!field || field.disabled || cbShouldIgnoreDirtyField(field)) {
            return false;
        }

        var name = String(field.name || '');
        if (name === '' || name === 'cid[]') {
            return false;
        }

        var type = String(field.type || '').toLowerCase();

        if (
            type === 'hidden' &&
            /^(cb_create_sample_flag|cb_create_editable_sample_flag|cb_email_admin_create_sample_flag|cb_email_create_sample_flag)$/.test(String(field.id || ''))
        ) {
            return true;
        }

        if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'reset' || type === 'file') {
            return false;
        }

        return true;
    }

    function cbExtractSimpleJformFieldName(name) {
        var match = String(name || '').match(/^jform\[([^\]]+)\]$/);
        return match && match[1] ? match[1] : '';
    }

    function cbSerializeTrackedFormState(form) {
        if (!form || !form.elements) {
            return '';
        }

        var parts = [];
        var editorKeys = {};

        for (var i = 0; i < form.elements.length; i++) {
            var field = form.elements[i];

            if (!cbShouldTrackField(field)) {
                continue;
            }

            var type = String(field.type || '').toLowerCase();
            var key = String(field.name || '');
            var editorFieldName = cbExtractSimpleJformFieldName(key);

            if (type === 'checkbox' || type === 'radio') {
                parts.push(key + '=' + (field.checked ? '1' : '0'));
                continue;
            }

            if (field.tagName && String(field.tagName).toLowerCase() === 'select' && field.multiple) {
                var selected = [];
                for (var j = 0; j < field.options.length; j++) {
                    if (field.options[j].selected) {
                        selected.push(field.options[j].value);
                    }
                }
                parts.push(key + '=' + selected.join('|'));
                continue;
            }

            if (editorFieldName && !editorKeys[key]) {
                var editorValue = cbGetEditorFieldValue(editorFieldName);
                var textarea = document.getElementById('jform_' + editorFieldName);

                if (
                    textarea &&
                    textarea.tagName &&
                    String(textarea.tagName).toLowerCase() === 'textarea' &&
                    editorValue !== String(field.value || '')
                ) {
                    parts.push(key + '=' + editorValue);
                    editorKeys[key] = true;
                    continue;
                }
            }

            parts.push(key + '=' + String(field.value || ''));
        }

        return parts.join('\n');
    }

    function cbSerializeTrackedEditorState(form) {
        if (!form) {
            return '';
        }

        var parts = [];
        var seen = {};
        var fields = form.querySelectorAll('textarea[name^="jform["], input[name^="jform["]');

        fields.forEach(function(field) {
            var key = String(field.name || '');
            var fieldName = cbExtractSimpleJformFieldName(key);

            if (!fieldName || seen[key]) {
                return;
            }

            var textarea = document.getElementById('jform_' + fieldName);
            var hasEditorInstance = !!cbGetJoomlaEditorInstance(fieldName);
            var looksLikeEditorField = hasEditorInstance ||
                (
                    textarea &&
                    textarea.tagName &&
                    String(textarea.tagName).toLowerCase() === 'textarea' &&
                    (
                        textarea.closest('.tox-tinymce') ||
                        textarea.closest('.editor') ||
                        textarea.dataset.editor === '1'
                    )
                );

            if (!looksLikeEditorField) {
                return;
            }

            parts.push(key + '=' + cbGetEditorFieldValue(fieldName));
            seen[key] = true;
        });

        return parts.join('\n');
    }

    function cbBindEditorDirtyTracking() {
        var boundCount = 0;

        document.querySelectorAll('textarea[name^="jform["], input[name^="jform["]').forEach(function(field) {
            var fieldName = cbExtractSimpleJformFieldName(field.name || '');
            if (!fieldName) {
                return;
            }

            boundCount += cbBindCodeMirrorViewDirtyTracking(fieldName);
        });

        cbGetEditorInstancesFromFields(document).forEach(function(instance) {
            if (!instance || instance.__cbDirtyTrackingBound) {
                return;
            }

            if (typeof instance.on === 'function') {
                instance.__cbDirtyTrackingBound = true;
                ['change', 'input', 'keyup', 'undo', 'redo'].forEach(function(eventName) {
                    try {
                        instance.on(eventName, cbHandleDirtyInteraction);
                    } catch (e) {}
                });
                boundCount++;
                return;
            }

            if (typeof instance.getType === 'function' && instance.getType() === 'codemirror') {
                instance.__cbDirtyTrackingBound = true;

                var rawInstance = typeof instance.getRawInstance === 'function' ? instance.getRawInstance() : null;
                if (rawInstance && !rawInstance.__cbDirtyTrackingBound) {
                    rawInstance.__cbDirtyTrackingBound = true;

                    var targets = [];
                    if (rawInstance.dom) {
                        targets.push(rawInstance.dom);
                    }
                    if (rawInstance.contentDOM && targets.indexOf(rawInstance.contentDOM) === -1) {
                        targets.push(rawInstance.contentDOM);
                    }

                    targets.forEach(function(target) {
                        if (!target || target.__cbDirtyTrackingListenersBound) {
                            return;
                        }

                        target.__cbDirtyTrackingListenersBound = true;

                        ['beforeinput', 'input', 'change', 'keyup', 'keydown', 'paste', 'cut'].forEach(function(eventName) {
                            target.addEventListener(eventName, function() {
                                window.requestAnimationFrame(cbHandleDirtyInteraction);
                            }, true);
                        });
                    });
                }

                boundCount++;
            }
        });

        if (window.tinymce && typeof window.tinymce.get === 'function') {
            document.querySelectorAll('textarea[name^="jform["]').forEach(function(textarea) {
                var editorId = String(textarea.id || '');
                if (!editorId) {
                    return;
                }

                var editor = window.tinymce.get(editorId);
                if (!editor || editor.__cbDirtyTrackingBound) {
                    return;
                }

                editor.__cbDirtyTrackingBound = true;

                ['input', 'change', 'keyup', 'Undo', 'Redo'].forEach(function(eventName) {
                    try {
                        editor.on(eventName, cbHandleDirtyInteraction);
                    } catch (e) {}
                });

                try {
                    var doc = editor.getDoc && editor.getDoc();
                    if (doc && !doc.__cbDirtyTrackingBound) {
                        doc.__cbDirtyTrackingBound = true;
                        ['input', 'keyup', 'paste', 'cut'].forEach(function(eventName) {
                            doc.addEventListener(eventName, cbHandleDirtyInteraction, true);
                        });
                    }
                } catch (e) {}

                try {
                    var body = editor.getBody && editor.getBody();
                    if (body && !body.__cbDirtyTrackingBound) {
                        body.__cbDirtyTrackingBound = true;
                        ['input', 'keyup', 'paste', 'cut'].forEach(function(eventName) {
                            body.addEventListener(eventName, cbHandleDirtyInteraction, true);
                        });
                    }
                } catch (e) {}

                var iframe = document.getElementById(editorId + '_ifr');
                if (iframe && !iframe.__cbDirtyTrackingBound) {
                    iframe.__cbDirtyTrackingBound = true;
                    iframe.addEventListener('load', function() {
                        cbBindEditorDirtyTracking();
                        cbRefreshDirtyState();
                    }, true);
                }

                var container = textarea.nextElementSibling;
                if (container && container.classList && container.classList.contains('tox-tinymce') && !container.__cbDirtyTrackingBound) {
                    container.__cbDirtyTrackingBound = true;
                    ['input', 'keyup', 'paste', 'cut'].forEach(function(eventName) {
                        container.addEventListener(eventName, cbHandleDirtyInteraction, true);
                    });
                }

                boundCount++;
            });
        }

        return boundCount;
    }

    function cbBindEditorFieldDirtyTracking(form) {
        if (!form) {
            return 0;
        }

        var fields = form.querySelectorAll('textarea[name^="jform["], input[name^="jform["]');
        var boundCount = 0;

        fields.forEach(function(field) {
            var name = String(field.name || '');

            if (!/\]$/.test(name) || field.__cbDirtyTrackingBound || cbShouldIgnoreDirtyField(field)) {
                return;
            }

            field.__cbDirtyTrackingBound = true;
            boundCount++;

            ['input', 'change', 'keyup'].forEach(function(eventName) {
                field.addEventListener(eventName, cbHandleDirtyInteraction, true);
            });
        });

        return boundCount;
    }

    function cbEnsureEditorDirtyTracking(form) {
        var editorBindings = cbBindEditorDirtyTracking();
        var fieldBindings = cbBindEditorFieldDirtyTracking(form);

        return editorBindings + fieldBindings;
    }

    function cbNeutralizeHiddenHeaderDropdown() {
        var headerMore = document.getElementById('header-more-items');

        if (!headerMore || !headerMore.classList.contains('d-none')) {
            return;
        }

        var toggle = headerMore.querySelector('.header-more-btn[data-bs-toggle="dropdown"]');
        if (toggle) {
            toggle.removeAttribute('data-bs-toggle');
            toggle.classList.remove('dropdown-toggle');
            toggle.setAttribute('aria-hidden', 'true');
            toggle.tabIndex = -1;
        }

        var menu = headerMore.querySelector('.dropdown-menu');
        if (menu) {
            menu.classList.remove('dropdown-menu');
        }
    }

    function cbGetSaveButtons() {
        if (cbSaveButtonsCache) {
            return cbSaveButtonsCache;
        }

        var tasks = ['form.apply', 'form.save', 'form.save2new'];
        var hostIds = ['save-group-children-apply', 'save-group-children-save', 'save-group-children-save2new'];
        var classNames = ['button-apply', 'button-save', 'button-save-new'];
        var targets = [];

        var collectTarget = function(el) {
            if (!el || targets.indexOf(el) !== -1) {
                return;
            }

            if (el.classList && el.classList.contains('dropdown-toggle-split')) {
                return;
            }

            if (typeof el.closest === 'function' && el.closest('.dropdown-menu')) {
                return;
            }

            targets.push(el);
        };

        var collectToolbarHostButtons = function(host) {
            if (!host) {
                return;
            }

            collectTarget(host);

            if (host.shadowRoot) {
                host.shadowRoot.querySelectorAll('button, a, [role="button"]').forEach(function(el) {
                    collectTarget(el);
                });
            }

            host.querySelectorAll('button, a, [role="button"]').forEach(function(el) {
                collectTarget(el);
            });
        };

        tasks.forEach(function(task) {
            document.querySelectorAll('[data-task="' + task + '"]').forEach(function(el) {
                collectTarget(el);
            });

            document.querySelectorAll('[onclick*="' + task + '"]').forEach(function(el) {
                collectTarget(el);
            });

            document.querySelectorAll('joomla-toolbar-button').forEach(function(host) {
                if (!host || !host.shadowRoot) {
                    return;
                }

                host.shadowRoot.querySelectorAll('[data-task="' + task + '"], [onclick*="' + task + '"]').forEach(function(el) {
                    collectTarget(el);
                });
            });
        });

        hostIds.forEach(function(hostId) {
            collectToolbarHostButtons(document.getElementById(hostId));
            collectToolbarHostButtons(document.querySelector('joomla-toolbar-button#' + hostId));
        });

        classNames.forEach(function(className) {
            document.querySelectorAll('joomla-toolbar-button .' + className + ', #toolbar .' + className).forEach(function(el) {
                collectTarget(el);
            });

            document.querySelectorAll('joomla-toolbar-button').forEach(function(host) {
                if (!host || !host.shadowRoot) {
                    return;
                }

                host.shadowRoot.querySelectorAll('.' + className).forEach(function(el) {
                    collectTarget(el);
                });
            });
        });

        cbSaveButtonsCache = targets;
        return targets;
    }

    function cbSetSaveButtonsEnabled(enabled) {
        cbGetSaveButtons().forEach(function(el) {
            if ('disabled' in el) {
                el.disabled = !enabled;
            } else if (enabled) {
                el.removeAttribute('disabled');
            } else {
                el.setAttribute('disabled', 'disabled');
            }

            el.classList.toggle('cb-save-disabled', !enabled);
            el.setAttribute('aria-disabled', enabled ? 'false' : 'true');

            if (el.parentElement && el.parentElement.classList) {
                el.parentElement.classList.toggle('cb-save-disabled', !enabled);
            }
        });
    }

    function cbSetDirtyState(isDirty) {
        cbDirtyState = !!isDirty;
        cbSetSaveButtonsEnabled(cbDirtyState);
    }

    function cbBypassDirtyBeforeUnload() {
        cbDirtyBypassBeforeUnload = true;
        cbSetDirtyState(false);
    }

    function cbHandleDirtyInteraction(event) {
        if (event && event.target && cbShouldIgnoreDirtyField(event.target)) {
            return;
        }

        cbDirtyUserInteracted = true;
        cbRefreshDirtyState();
    }

    function cbStabilizeDirtySnapshot() {
        if (cbDirtyUserInteracted) {
            cbRefreshDirtyState();
            return;
        }

        cbMarkDirtySnapshot();
    }

    function cbRefreshDirtyState() {
        var form = document.getElementById('adminForm') || document.adminForm;
        if (!form) {
            return;
        }

        var currentState = cbSerializeTrackedFormState(form);

        if (!cbDirtyUserInteracted) {
            if (currentState !== cbDirtySnapshot) {
                cbDirtySnapshot = currentState;
            }

            cbSetDirtyState(false);
            return;
        }

        cbSetDirtyState(currentState !== cbDirtySnapshot);
    }

    function cbMarkDirtySnapshot() {
        var form = document.getElementById('adminForm') || document.adminForm;
        if (!form) {
            return;
        }

        cbDirtySnapshot = cbSerializeTrackedFormState(form);
        cbSetDirtyState(false);
    }

    function cbInitDirtyTracking() {
        cbNeutralizeHiddenHeaderDropdown();

        var form = document.getElementById('adminForm') || document.adminForm;
        if (!form || cbDirtyTrackingInitialized) {
            return;
        }

        cbDirtyTrackingInitialized = true;
        cbMarkDirtySnapshot();

        form.addEventListener('input', cbHandleDirtyInteraction, true);
        form.addEventListener('change', cbHandleDirtyInteraction, true);
        cbEnsureEditorDirtyTracking(form);
        window.setTimeout(function() {
            cbEnsureEditorDirtyTracking(form);
            cbStabilizeDirtySnapshot();
        }, 250);
        window.setTimeout(function() {
            cbEnsureEditorDirtyTracking(form);
            cbStabilizeDirtySnapshot();
        }, 1000);
        window.setTimeout(function() {
            cbEnsureEditorDirtyTracking(form);
            cbStabilizeDirtySnapshot();
        }, 1600);
        window.addEventListener('focus', cbRefreshDirtyState);
        document.addEventListener('visibilitychange', cbRefreshDirtyState);

        if (!cbEditorObserver && typeof MutationObserver === 'function') {
            cbEditorObserver = new MutationObserver(function() {
                cbEnsureEditorDirtyTracking(form);
            });
            cbEditorObserver.observe(form, {
                childList: true,
                subtree: true
            });
        }

        if (!cbEditorPollHandle) {
            cbEditorPollHandle = window.setInterval(function() {
                if (document.visibilityState === 'hidden') {
                    return;
                }

                var editorState = cbSerializeTrackedEditorState(form);
                if (editorState !== '') {
                    cbRefreshDirtyState();
                }
            }, 600);
        }

        window.addEventListener('beforeunload', function(event) {
            if (cbDirtyBypassBeforeUnload) {
                return;
            }

            if (!cbDirtyState) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        });
    }

    document.addEventListener('DOMContentLoaded', cbInitDirtyTracking);

    function cbInitColumnToggle(config) {
        var toggleButton = document.getElementById(config.toggleId);
        var menu = document.querySelector(config.menuSel);
        var countLabel = toggleButton ? toggleButton.querySelector(config.countSel) : null;
        var checkboxes = Array.from(document.querySelectorAll(config.checkboxSel));
        var resetButton = menu ? menu.querySelector(config.resetSel) : null;
        var pendingNodes = config.pendingClass
            ? Array.from(document.querySelectorAll('.' + config.pendingClass)) : [];

        if (!toggleButton || !menu || !countLabel || !checkboxes.length) {
            pendingNodes.forEach(function(node) { node.classList.remove(config.pendingClass); });
            return;
        }

        var defaultState = config.buildDefaultState(checkboxes);
        var totalCount = checkboxes.length;

        var readState = function() {
            try {
                var raw = window.localStorage.getItem(config.storageKey);
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
                window.localStorage.setItem(config.storageKey, JSON.stringify(state));
            } catch (e) {
                // ignore storage failures
            }
        };

        var updateCountLabel = function(state) {
            var visibleCount = Object.keys(defaultState).filter(function(key) {
                return state[key] !== false;
            }).length;
            countLabel.textContent = visibleCount + '/' + totalCount + ' ' + cbElementsColumnsLabel;
        };

        var applyState = function(state) {
            Object.keys(defaultState).forEach(function(key) {
                var visible = state[key] !== false;
                document.querySelectorAll('[' + config.colAttr + '="' + key + '"]').forEach(function(cell) {
                    cell.classList.toggle(config.hiddenClass, !visible);
                });
            });

            checkboxes.forEach(function(input) {
                input.checked = state[String(input.value || '')] !== false;
            });

            updateCountLabel(state);
        };

        var state = readState();
        applyState(state);
        pendingNodes.forEach(function(node) { node.classList.remove(config.pendingClass); });

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

    document.addEventListener('DOMContentLoaded', function() {
        cbInitColumnToggle({
            storageKey:        cbElementsColumnsStateKey,
            toggleId:          'cb-elements-columns-toggle',
            menuSel:           '.cb-elements-columns-menu',
            countSel:          '.cb-elements-columns-count',
            checkboxSel:       '.cb-elements-column-toggle[data-cb-column-toggle="1"]',
            resetSel:          '.cb-elements-columns-reset[data-cb-columns-reset="1"]',
            colAttr:           'data-cb-col',
            hiddenClass:       'cb-elements-col-hidden',
            pendingClass:      'cb-elements-columns-pending',
            buildDefaultState: function(cbs) {
                var s = Object.assign({}, cbElementsColumnsDefaultState);
                cbs.forEach(function(input) {
                    var key = String(input.value || '');
                    if (!Object.prototype.hasOwnProperty.call(s, key)) {
                        s[key] = true;
                    }
                });
                return s;
            }
        });

        cbInitColumnToggle({
            storageKey:        cbPermissionsColumnsStateKey,
            toggleId:          'cb-perm-columns-toggle',
            menuSel:           '.cb-perm-columns-menu',
            countSel:          '.cb-perm-columns-count',
            checkboxSel:       '.cb-perm-column-toggle[data-cb-perm-column-toggle="1"]',
            resetSel:          '.cb-perm-columns-reset[data-cb-perm-columns-reset="1"]',
            colAttr:           'data-cb-perm-col',
            hiddenClass:       'cb-perm-col-hidden',
            pendingClass:      null,
            buildDefaultState: function(cbs) {
                var s = {};
                var showAllColumns = window.matchMedia('(min-width: 1500px)').matches;
                cbs.forEach(function(input) {
                    s[String(input.value || '')] = showAllColumns || input.getAttribute('data-cb-default-visible') !== '0';
                });
                return s;
            }
        });
    });


    document.addEventListener('DOMContentLoaded', function() {
        var paginationRoots = document.querySelectorAll('.cb-form-elements-pagination');
        if (!paginationRoots.length) {
            return;
        }

        paginationRoots.forEach(function(root) {
            root.addEventListener('click', function(event) {
                var target = event.target ? event.target.closest('a') : null;
                if (!target) {
                    return;
                }

                cbBypassDirtyBeforeUnload();
            }, true);

            root.addEventListener('change', function(event) {
                var target = event.target || null;
                if (!target || target.tagName !== 'SELECT') {
                    return;
                }

                cbBypassDirtyBeforeUnload();
            }, true);
        });
    });

    function cbRefreshInheritedPermissionMatrix() {
        const matrixInputs = Array.from(document.querySelectorAll('input[data-cb-perm-matrix="1"]'));

        if (!matrixInputs.length) {
            return;
        }

        const checkedMap = new Map();
        const labelMap = new Map();

        matrixInputs.forEach((input) => {
            const groupId = String(input.dataset.cbGroupId || '');
            const permKey = String(input.dataset.cbPermKey || '');

            if (!groupId || !permKey) {
                return;
            }

            const cellKey = `${groupId}:${permKey}`;

            if (input.checked) {
                checkedMap.set(cellKey, true);
            }

            const row = input.closest('tr');
            const label = row ? row.querySelector('.cb-perm-group-text') : null;
            if (label) {
                labelMap.set(groupId, label.textContent.trim());
            }
        });

        matrixInputs.forEach((input) => {
            const permKey = String(input.dataset.cbPermKey || '');
            const ancestorIds = String(input.dataset.cbAncestorIds || '')
                .split(',')
                .map((value) => value.trim())
                .filter(Boolean);
            const td = input.closest('td');

            input.indeterminate = false;

            if (!td) {
                return;
            }

            td.classList.remove('cb-perm-inherited');
            td.removeAttribute('title');

            if (input.checked) {
                return;
            }

            for (const ancestorId of ancestorIds) {
                if (!checkedMap.has(`${ancestorId}:${permKey}`)) {
                    continue;
                }

                input.indeterminate = true;
                td.classList.add('cb-perm-inherited');

                const ancestorLabel = labelMap.get(ancestorId);
                if (ancestorLabel) {
                    td.setAttribute('title', cbInheritedFromLabel + ' ' + ancestorLabel);
                }

                break;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', cbRefreshInheritedPermissionMatrix);
    document.addEventListener('change', function(event) {
        if (!event.target || !event.target.matches('input[data-cb-perm-matrix="1"]')) {
            return;
        }

        cbRefreshInheritedPermissionMatrix();
    }, true);
</script>
