<?php

/**
 * @package     BreezingCommerce
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Field;

\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

class FormsField extends FormField
{
    protected $type = 'Forms';

    private const FORM_BOOLEAN_DEFAULTS = [
        'cb_show_author' => 1,
        'cb_show_top_bar' => 1,
        'cb_show_bottom_bar' => 0,
        'cb_show_details_top_bar' => 1,
        'cb_show_details_bottom_bar' => 0,
        'show_back_button' => 1,
        'cb_filter_in_title' => 0,
        'cb_prefix_in_title' => 0,
    ];

    private function getSelectedFormId(): int
    {
        $selectedFormId = (int) ($this->form?->getValue('form_id', 'params.settings', 0) ?? 0);

        if ($selectedFormId <= 0) {
            $selectedFormId = (int) ($this->form?->getValue('form_id', 'params', 0) ?? 0);
        }

        if ($selectedFormId <= 0 && method_exists($this->form, 'getData')) {
            $data = $this->form->getData();

            if (is_object($data) && method_exists($data, 'get')) {
                $selectedFormId = (int) $data->get('params.settings.form_id', 0);

                if ($selectedFormId <= 0) {
                    $selectedFormId = (int) $data->get('params.form_id', 0);
                }
            }
        }

        if ($selectedFormId <= 0) {
            $selectedFormId = (int) $this->value;
        }

        return $selectedFormId;
    }

    protected function getInput()
    {
        $class = (string) ($this->element['class'] ?: '');
        $selectedFormId = $this->getSelectedFormId();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $tableName = $db->getPrefix() . 'contentbuilderng_forms';
        $optionalColumns = [];

        try {
            $tableColumns = $db->getTableColumns($tableName, true);
            $knownColumns = [];

            foreach ((array) $tableColumns as $columnName => $_type) {
                $knownColumns[strtolower((string) $columnName)] = true;
            }

            foreach (array_keys(self::FORM_BOOLEAN_DEFAULTS) as $columnName) {
                if (isset($knownColumns[$columnName])) {
                    $optionalColumns[] = $columnName;
                }
            }
        } catch (\Throwable $e) {
            $optionalColumns = [];
        }

        $selectColumns = ['id', '`name`'];
        foreach ($optionalColumns as $columnName) {
            $selectColumns[] = $columnName;
        }

        $db->setQuery(
            'Select ' . implode(',', $selectColumns)
            . ' From #__contentbuilderng_forms'
            . ' Where published = 1'
            . ' Order By `name` ASC, `id` ASC'
        );
        $status = $db->loadObjectList();

        $defaultsByForm = [];
        foreach ($status as $form) {
            $formId = (string) ($form->id ?? '');
            if ($formId === '') {
                continue;
            }

            $defaultsByForm[$formId] = [
                'cb_show_author' => (int) ($form->cb_show_author ?? self::FORM_BOOLEAN_DEFAULTS['cb_show_author']),
                'cb_show_top_bar' => (int) ($form->cb_show_top_bar ?? self::FORM_BOOLEAN_DEFAULTS['cb_show_top_bar']),
                'cb_show_bottom_bar' => (int) ($form->cb_show_bottom_bar ?? self::FORM_BOOLEAN_DEFAULTS['cb_show_bottom_bar']),
                'cb_show_details_top_bar' => (int) ($form->cb_show_details_top_bar ?? self::FORM_BOOLEAN_DEFAULTS['cb_show_details_top_bar']),
                'cb_show_details_bottom_bar' => (int) ($form->cb_show_details_bottom_bar ?? self::FORM_BOOLEAN_DEFAULTS['cb_show_details_bottom_bar']),
                'show_back_button' => (int) ($form->show_back_button ?? self::FORM_BOOLEAN_DEFAULTS['show_back_button']),
                'cb_filter_in_title' => (int) ($form->cb_filter_in_title ?? self::FORM_BOOLEAN_DEFAULTS['cb_filter_in_title']),
                'cb_prefix_in_title' => (int) ($form->cb_prefix_in_title ?? self::FORM_BOOLEAN_DEFAULTS['cb_prefix_in_title']),
            ];
        }

        $select = HTMLHelper::_(
            'select.genericlist',
            $status,
            $this->name,
            'onchange="if(typeof contentbuilderng_setFormId != \'undefined\') { contentbuilderng_setFormId(this.options[this.selectedIndex].value); }" class="' . $class . '"',
            'id',
            'name',
            $this->value
        );

        $yesLabel = Text::_('COM_CONTENTBUILDERNG_YES');
        $noLabel = Text::_('COM_CONTENTBUILDERNG_NO');
        $defaultsJson = json_encode(
            $defaultsByForm,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );
        $yesJson = json_encode($yesLabel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $noJson = json_encode($noLabel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $selectedJson = json_encode((string) $selectedFormId, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        $script = <<<'JS'
            <script>
            (() => {
                const defaultsByForm = __DEFAULTS_JSON__;
                const yesLabel = __YES_JSON__;
                const noLabel = __NO_JSON__;
                const initialFormId = __SELECTED_JSON__;

                function findField(selectors) {
                    for (const selector of selectors) {
                        const field = document.querySelector(selector);
                        if (field) {
                            return field;
                        }
                    }

                    return null;
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
                    description.textContent = originalText.replace(/\s+[^\s.]+\.?$/, ' ' + suffix + '.');
                }

                function updateDescriptions(formId) {
                    const values = defaultsByForm[String(formId)] || null;
                    if (!values) {
                        return;
                    }

                    updateDescription('cb_show_author', Number(values.cb_show_author) === 1);
                    updateDescription('cb_show_top_bar', Number(values.cb_show_top_bar) === 1);
                    updateDescription('cb_show_bottom_bar', Number(values.cb_show_bottom_bar) === 1);
                    updateDescription('cb_show_details_top_bar', Number(values.cb_show_details_top_bar) === 1);
                    updateDescription('cb_show_details_bottom_bar', Number(values.cb_show_details_bottom_bar) === 1);
                    updateDescription('cb_show_details_back_button', Number(values.show_back_button) === 1);
                    updateDescription('show_back_button', Number(values.show_back_button) === 1);
                    updateDescription('cb_filter_in_title', Number(values.cb_filter_in_title) === 1);
                    updateDescription('cb_prefix_in_title', Number(values.cb_prefix_in_title) === 1);
                }

                window.contentbuilderng_setFormId = function(formId) {
                    updateDescriptions(formId);
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', () => {
                        updateDescriptions(initialFormId);
                    }, { once: true });
                } else {
                    updateDescriptions(initialFormId);
                }
            })();
            </script>
JS;

        return $select . str_replace(
            ['__DEFAULTS_JSON__', '__YES_JSON__', '__NO_JSON__', '__SELECTED_JSON__'],
            [
                $defaultsJson ?: '{}',
                $yesJson ?: '""',
                $noJson ?: '""',
                $selectedJson ?: '""',
            ],
            $script
        );
    }
}
