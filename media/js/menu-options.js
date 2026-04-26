(function () {
    'use strict';

    document.documentElement.dataset.cbMenuOptionsAsset = 'loaded';

    const options = window.Joomla && typeof window.Joomla.getOptions === 'function'
        ? window.Joomla.getOptions('com_contentbuilderng.menuOptions', {})
        : {};

    const defaultsByForm = options.defaultsByForm || {};
    const yesLabel = options.yesLabel || 'Yes';
    const noLabel = options.noLabel || 'No';
    const defaultValueFormat = options.defaultValueFormat || 'Default value: %s';
    const initialFormId = String(options.initialFormId || '');

    function findField(selectors) {
        for (const selector of selectors) {
            const field = document.querySelector(selector);
            if (field) {
                return field;
            }
        }

        return null;
    }

    function styleMenuSectionHeadings() {
        document.querySelectorAll('.alert-info').forEach((note) => {
            const text = String(note.textContent || '').trim();
            if (!/^(List|Edit|Details|Reset|Liste|Édition|Détails|Réinitialisation|Bearbeitung|Zurücksetzen)$/i.test(text)) {
                return;
            }

            const group = note.closest('.control-group, .form-group, .mb-3');
            const row = group || note;
            if (row.classList.contains('cb-menu-section-row')) {
                return;
            }

            row.classList.add('cb-menu-section-row');
            row.querySelectorAll('.control-label, .form-label, label').forEach((label) => {
                if (label !== note) {
                    label.remove();
                }
            });

            note.className = 'cb-menu-section-heading';

            const controls = note.closest('.controls, .col-md-9, .col-sm-9, .field-value');
            if (controls && row && row.insertBefore) {
                row.insertBefore(note, row.firstElementChild);
            }
        });
    }

    function escapeSelectorValue(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(String(value));
        }

        return String(value).replace(/["\\]/g, '\\$&');
    }

    function findDescription(fieldName) {
        const described = findField([
            `#jform_params_settings_${fieldName}-desc`,
            `#jform_params_${fieldName}-desc`,
        ]);
        if (described) {
            return described;
        }

        const input = findField([
            `[name="jform[params][settings][${fieldName}]"]`,
            `[name="jform[params][${fieldName}]"]`,
        ]);
        const group = input ? input.closest('.control-group, .form-group, .mb-3') : null;

        return group ? group.querySelector('.form-text') : null;
    }

    function findInput(fieldName) {
        return findField([
            `#jform_params_settings_${fieldName}`,
            `#jform_params_${fieldName}`,
            `[name="jform[params][settings][${fieldName}]"]`,
            `[name="jform[params][${fieldName}]"]`,
        ]);
    }

    function findBadgeAnchor(fieldName) {
        const input = findInput(fieldName);
        if (!input) {
            return null;
        }

        if (String(input.type || '').toLowerCase() === 'radio') {
            return input.closest('.switcher, .btn-group, fieldset, .radio');
        }

        return input;
    }

    function renderDefaultValue(value) {
        const template = String(defaultValueFormat || 'Default value: %s');
        return template.includes('%s') ? template.replace('%s', String(value)) : `${template} ${value}`;
    }

    function updateDefaultBadge(fieldName, value) {
        const anchor = findBadgeAnchor(fieldName);
        if (!anchor || !anchor.parentNode) {
            return;
        }

        let wrapper = anchor.closest(`.cb-menu-default-wrap[data-cb-default-for="${fieldName}"]`);

        if (!wrapper) {
            wrapper = document.createElement('span');
            wrapper.className = 'cb-menu-default-wrap';
            wrapper.dataset.cbDefaultFor = fieldName;
            anchor.parentNode.insertBefore(wrapper, anchor);
            wrapper.appendChild(anchor);
        }

        let badge = wrapper.querySelector('.cb-menu-default-value');

        if (!value) {
            if (badge) {
                badge.remove();
            }

            if (!wrapper.querySelector('.cb-menu-default-value')) {
                wrapper.replaceWith(anchor);
            }

            return;
        }

        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'cb-menu-default-value';
            wrapper.appendChild(badge);
        }

        const text = renderDefaultValue(value);
        badge.textContent = text.trim() === '' ? String(value) : text;
        badge.title = badge.textContent;
    }

    function updateRadioOptionTooltips(fieldName) {
        const input = findInput(fieldName);
        const description = findDescription(fieldName);
        const tooltipText = description ? String(description.textContent || '').trim() : '';

        if (!input || !tooltipText) {
            return;
        }

        const name = String(input.name || '');
        if (!name) {
            return;
        }

        document.querySelectorAll(`input[type="radio"][name="${escapeSelectorValue(name)}"]`).forEach((radio) => {
            const value = String(radio.value || '');
            if (value !== '0' && value !== '1') {
                return;
            }

            const label = radio.id ? document.querySelector(`label[for="${escapeSelectorValue(radio.id)}"]`) : null;
            const target = label || radio.closest('label');

            if (!target) {
                return;
            }

            target.setAttribute('title', tooltipText);
            target.setAttribute('data-bs-toggle', 'tooltip');
            target.setAttribute('data-bs-placement', 'top');
            target.setAttribute('data-bs-title', tooltipText);

            if (window.bootstrap && typeof window.bootstrap.Tooltip === 'function') {
                const instance = window.bootstrap.Tooltip.getInstance(target);
                if (instance) {
                    instance.dispose();
                }
                new window.bootstrap.Tooltip(target);
            }
        });
    }

    function updateDescription(fieldName, enabled) {
        const description = findDescription(fieldName);
        if (!description) {
            return;
        }

        const suffix = enabled ? yesLabel : noLabel;
        const originalText = description.dataset.cbOriginalText || String(description.textContent || '').trim();
        if (originalText === '') {
            return;
        }

        description.dataset.cbOriginalText = originalText;
        description.textContent = originalText.replace(/\s+[^\s.]+\.?$/, ` ${suffix}.`);
        updateRadioOptionTooltips(fieldName);
    }

    function updateBooleanField(fieldName, enabled) {
        const value = enabled ? yesLabel : noLabel;
        updateDescription(fieldName, enabled);
        updateDefaultBadge(fieldName, value);
    }

    function updateDescriptions(formId) {
        const values = defaultsByForm[String(formId)] || null;
        if (!values) {
            return;
        }

        updateDefaultBadge('form_id', values.form_name || '');
        updateDefaultBadge('cb_category_id', values.default_category_label || '');
        updateDefaultBadge('cb_category_menu_filter', noLabel);
        updateRadioOptionTooltips('cb_category_menu_filter');
        updateBooleanField('cb_show_author', Number(values.cb_show_author) === 1);
        updateBooleanField('cb_show_top_bar', Number(values.cb_show_top_bar) === 1);
        updateBooleanField('cb_show_bottom_bar', Number(values.cb_show_bottom_bar) === 1);
        updateBooleanField('cb_show_details_top_bar', Number(values.cb_show_details_top_bar) === 1);
        updateBooleanField('cb_show_details_bottom_bar', Number(values.cb_show_details_bottom_bar) === 1);
        updateBooleanField('cb_show_details_back_button', Number(values.show_back_button) === 1);
        updateBooleanField('show_back_button', Number(values.show_back_button) === 1);
        updateBooleanField('cb_filter_in_title', Number(values.cb_filter_in_title) === 1);
        updateBooleanField('cb_prefix_in_title', Number(values.cb_prefix_in_title) === 1);
    }

    function trigger(field) {
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function getResetFields(name) {
        return Array.from(document.querySelectorAll(
            `[name="jform[params][settings][${escapeSelectorValue(name)}]"], [name="jform[params][${escapeSelectorValue(name)}]"]`
        ));
    }

    function resetField(name, value) {
        getResetFields(name).forEach((field) => {
            const type = String(field.type || '').toLowerCase();

            if (type === 'radio' || type === 'checkbox') {
                field.checked = String(field.value) === String(value);
                trigger(field);
                return;
            }

            if (field.tagName && String(field.tagName).toLowerCase() === 'select' && field.multiple) {
                Array.from(field.options).forEach((option) => {
                    option.selected = false;
                });
                trigger(field);
                return;
            }

            field.value = String(value ?? '');
            trigger(field);
        });
    }

    function clearFilterUi() {
        document.querySelectorAll("input[id^='element_'][type='text'], input[id^='element_'][type='number']").forEach((input) => {
            input.value = '';
            trigger(input);
        });
    }

    function moveResetPanelToTop(panel) {
        const originalGroup = panel.closest('.control-group, .form-group, .mb-3');
        const fieldset = originalGroup ? originalGroup.closest('fieldset') : panel.closest('fieldset');
        const container = fieldset || (originalGroup ? originalGroup.parentElement : panel.closest('.options-form, form'));

        if (!container) {
            return;
        }

        const legend = container.querySelector(':scope > legend');
        const firstField = Array.from(container.children).find((child) => {
            return child !== originalGroup
                && child !== legend
                && child.matches
                && child.matches('.control-group, .form-group, .mb-3, .joomla-field, .control-wrapper');
        });

        if (legend && legend.nextElementSibling) {
            container.insertBefore(panel, legend.nextElementSibling);
        } else if (legend) {
            container.appendChild(panel);
        } else if (firstField) {
            container.insertBefore(panel, firstField);
        } else {
            container.insertBefore(panel, container.firstElementChild);
        }

        if (originalGroup && originalGroup !== panel && !originalGroup.contains(panel)) {
            originalGroup.style.display = 'none';
        }
    }

    function initResetButtons() {
        document.querySelectorAll('[data-cb-menu-reset-panel]').forEach((panel) => {
            moveResetPanelToTop(panel);
        });

        document.querySelectorAll('[data-cb-menu-reset-button]').forEach((button) => {
            if (button.dataset.cbMenuResetReady === '1') {
                return;
            }

            button.dataset.cbMenuResetReady = '1';

            if (window.bootstrap && typeof window.bootstrap.Tooltip === 'function') {
                window.bootstrap.Tooltip.getOrCreateInstance(button);
            }

            button.addEventListener('click', () => {
                const confirmText = String(button.dataset.cbMenuResetConfirm || '');
                if (confirmText && !window.confirm(confirmText)) {
                    return;
                }

                let defaults = {};
                try {
                    defaults = JSON.parse(button.dataset.cbMenuResetDefaults || '{}');
                } catch (error) {
                    defaults = {};
                }

                Object.entries(defaults).forEach(([name, value]) => {
                    resetField(name, value);
                });

                clearFilterUi();
            });
        });
    }

    window.contentbuilderng_setFormId = function (formId) {
        updateDescriptions(formId);
    };

    function init() {
        styleMenuSectionHeadings();
        initResetButtons();
        updateDescriptions(initialFormId);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}());
