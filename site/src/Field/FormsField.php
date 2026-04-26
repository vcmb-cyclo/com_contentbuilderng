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
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

class FormsField extends FormField
{
    protected $type = 'Forms';

    private const MENU_OPTIONS_STYLE = 'com_contentbuilderng.menu-options.direct.css';
    private const MENU_OPTIONS_SCRIPT = 'com_contentbuilderng.menu-options.direct.js';

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

    private const FORM_EXTRA_DEFAULT_COLUMNS = [
        'default_category',
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

            foreach (array_merge(array_keys(self::FORM_BOOLEAN_DEFAULTS), self::FORM_EXTRA_DEFAULT_COLUMNS) as $columnName) {
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
        $defaultCategoryIds = [];

        foreach ($status as $form) {
            $formId = (string) ($form->id ?? '');
            if ($formId === '') {
                continue;
            }

            $defaultCategoryId = (int) ($form->default_category ?? 0);
            if ($defaultCategoryId > 0) {
                $defaultCategoryIds[$defaultCategoryId] = $defaultCategoryId;
            }

            $defaultsByForm[$formId] = [
                'form_name' => (string) ($form->name ?? ''),
                'default_category_id' => $defaultCategoryId,
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

        $categoryTitles = [];

        if ($defaultCategoryIds !== []) {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title']))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $defaultCategoryIds)) . ')');
            $db->setQuery($query);

            foreach ((array) $db->loadObjectList() as $category) {
                $categoryTitles[(int) ($category->id ?? 0)] = (string) ($category->title ?? '');
            }
        }

        foreach ($defaultsByForm as $formId => $defaults) {
            $categoryId = (int) ($defaults['default_category_id'] ?? 0);
            $defaultsByForm[$formId]['default_category_label'] = $categoryId > 0
                ? ($categoryTitles[$categoryId] ?? ('#' . $categoryId))
                : Text::_('COM_CONTENTBUILDERNG_INHERIT');
            $defaultsByForm[$formId]['cb_category_menu_filter'] = 0;
        }

        $select = '<select id="' . htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8') . '"'
            . ' name="' . htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8') . '"'
            . ' onchange="if(typeof contentbuilderng_setFormId != \'undefined\') { contentbuilderng_setFormId(this.options[this.selectedIndex].value); }"'
            . ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">';

        foreach ($status as $form) {
            $value = (string) ($form->id ?? '');
            $selected = $value === (string) $this->value ? ' selected="selected"' : '';
            $select .= '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>'
                . htmlspecialchars((string) ($form->name ?? ''), ENT_QUOTES, 'UTF-8')
                . '</option>';
        }

        $select .= '</select>';

        $yesLabel = Text::_('COM_CONTENTBUILDERNG_YES');
        $noLabel = Text::_('COM_CONTENTBUILDERNG_NO');
        $defaultValueFormat = Text::_('COM_CONTENTBUILDERNG_MENU_DEFAULT_VALUE');
        if ($defaultValueFormat === 'COM_CONTENTBUILDERNG_MENU_DEFAULT_VALUE') {
            $defaultValueFormat = 'Default value: %s';
        }

        $document = Factory::getApplication()->getDocument();
        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
        if (!$wa->assetExists('style', self::MENU_OPTIONS_STYLE)) {
            $wa->registerStyle(
                self::MENU_OPTIONS_STYLE,
                'media/com_contentbuilderng/css/menu-options.css',
                [],
                ['media' => 'all']
            );
        }
        if (!$wa->assetExists('script', self::MENU_OPTIONS_SCRIPT)) {
            $wa->registerScript(
                self::MENU_OPTIONS_SCRIPT,
                'media/com_contentbuilderng/js/menu-options.js',
                [],
                ['defer' => true],
                ['core']
            );
        }
        $wa->useStyle(self::MENU_OPTIONS_STYLE);
        $wa->useScript(self::MENU_OPTIONS_SCRIPT);
        $document->addScriptOptions(
            'com_contentbuilderng.menuOptions',
            [
                'defaultsByForm' => $defaultsByForm,
                'yesLabel' => $yesLabel,
                'noLabel' => $noLabel,
                'defaultValueFormat' => $defaultValueFormat,
                'initialFormId' => (string) $selectedFormId,
            ]
        );

        return $select;
    }
}
