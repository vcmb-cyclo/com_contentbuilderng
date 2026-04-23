<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>

<script>
    let textTypeModal = document.getElementById('text-type-modal');
    textTypeModal.addEventListener('shown.bs.modal', function(event) {
        const modal = jQuery('#text-type-modal');
        const body = modal.find('.modal-body');
        body.css('display', 'none');
        modal.find('iframe').attr('src', event.relatedTarget.href);
        body.css('display', '');
    });

    let editModal = document.getElementById('edit-modal');
    editModal.addEventListener('shown.bs.modal', function(event) {
        const modal = jQuery('#edit-modal');
        const body = modal.find('.modal-body');
        body.css('display', 'none');
        modal.find('iframe').attr('src', event.relatedTarget.href);
        body.css('display', '');
    });

    window.addEventListener('message', function(event) {
        if (!event.data || event.data.type !== 'cbElementOptionsView') {
            return;
        }

        const elementId = parseInt(event.data.elementId, 10);
        const isModified = !!event.data.isModified;

        if (!elementId) {
            return;
        }

        const editByTypeEl = document.getElementById('edit_by_type');
        if (editByTypeEl && editByTypeEl.checked) {
            return;
        }

        const row = document.querySelector('tr[data-cb-row-id="' + elementId + '"]');
        if (!row) {
            return;
        }

        const editCell = row.querySelector('[data-cb-col="edit"]');
        if (!editCell) {
            return;
        }

        const badge = editCell.querySelector('.cb-item-type-badge');

        if (!badge && isModified) {
            const wrapper = document.createElement('div');
            wrapper.className = 'mt-1';
            const link = document.createElement('a');
            link.className = 'cb-item-type-badge is-modified';
            link.href = 'index.php?option=com_contentbuilderng&view=elementoptions&tmpl=component&element_id='
                + encodeURIComponent(elementId) + '&id=' + encodeURIComponent(cbFormId);
            link.setAttribute('data-bs-toggle', 'modal');
            link.setAttribute('data-bs-target', '#text-type-modal');
            link.setAttribute('title', 'Element settings changed from default');
            link.textContent = 'Modified';
            wrapper.appendChild(link);
            editCell.appendChild(wrapper);
            return;
        }

        if (badge && isModified && !badge.classList.contains('is-modified')) {
            badge.classList.remove('is-default');
            badge.classList.add('is-modified');
            badge.textContent = 'Modified';
            badge.setAttribute('title', 'Element settings changed from default');
        } else if (badge && !isModified && !badge.classList.contains('is-default')) {
            badge.classList.remove('is-modified');
            badge.classList.add('is-default');
            badge.textContent = 'Default';
            badge.removeAttribute('title');
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        var typeSelect = document.getElementById('cb_form_type_select');
        if (typeSelect) {
            var isBreezingFormsType = function (value) {
                return value === 'com_breezingforms' || value === 'com_breezingforms_ng';
            };

            var updateTypeTitle = function () {
                var option = typeSelect.options[typeSelect.selectedIndex];
                typeSelect.title = option ? (option.getAttribute('data-full') || option.value || '') : '';
            };

            var syncAutoPublishDefault = function () {
                var field = document.querySelector('input[type="checkbox"][name="jform[auto_publish]"]');
                if (!field) {
                    return;
                }

                field.checked = isBreezingFormsType(typeSelect.value || '');
                field.dispatchEvent(new Event('change', { bubbles: true }));
            };

            typeSelect.addEventListener('change', updateTypeTitle);
            typeSelect.addEventListener('change', syncAutoPublishDefault);
            updateTypeTitle();
            syncAutoPublishDefault();
        }

        var resetButton = document.getElementById('cb-reset-list-intro');
        if (resetButton) {
            resetButton.addEventListener('click', function () {
                var confirmMessage = resetButton.getAttribute('data-confirm') || '';

                if (confirmMessage && !window.confirm(confirmMessage)) {
                    return;
                }

                if (typeof cbSetEditorFieldValue === 'function') {
                    cbSetEditorFieldValue('intro_text', '');
                }
            });
        }
    });

    (() => {
        const adminUi = window.ContentBuilderNgAdmin || null;
        const KEY_PERM = 'cb_active_perm_tab';
        const viewTabTooltips = <?php echo json_encode($viewTabTooltips, $jsonFlags); ?>;
        const permTabTooltips = <?php echo json_encode($permTabTooltips, $jsonFlags); ?>;
        if (!adminUi) {
            return;
        }

        adminUi.persistJoomlaTabset('perm-pane', KEY_PERM, (id) => {
            adminUi.setHiddenInputValue('slideStartOffset', id);
        });

        adminUi.applyTabTooltips('view-pane', viewTabTooltips);
        adminUi.applyTabTooltips('perm-pane', permTabTooltips);
        adminUi.initBootstrapTooltips(document);

    })();
</script>
