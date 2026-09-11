<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Site\Field;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\ListLimitHelper;
use CB\Component\Contentbuilderng\Site\Helper\MenuViewDefaultsHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

final class MenulistbuilderField extends FormField
{
    protected $type = 'Menulistbuilder';

    protected function getInput(): string
    {
        $document = RuntimeContextHelper::getApplication()->getDocument();
        if ($document instanceof HtmlDocument) {
            ListLimitHelper::registerFieldAssets($document);
        }

        $formId = (int) ($this->form?->getValue('form_id', 'params.settings', 0) ?? 0);
        if ($formId < 1) {
            $formId = (int) ($this->form?->getValue('form_id', 'params', 0) ?? 0);
        }
        $viewDefaults = MenuViewDefaultsHelper::get($formId);
        $viewMaximumRecords = max(0, (int) ($viewDefaults['cb_maximum_records'] ?? 0));

        $config = json_decode((string) $this->value, true);
        $config = is_array($config) ? $config : [];
        $elements = $this->getElements($formId);
        if ((string) ($config['columnsMode'] ?? 'default') === 'custom') {
            $storedOrder = is_array($config['columnOrder'] ?? null)
                ? $config['columnOrder']
                : (is_array($config['columns'] ?? null) ? $config['columns'] : []);
            $columnOrder = array_flip(array_map('strval', $storedOrder));
            usort($elements, static function (array $left, array $right) use ($columnOrder): int {
                $leftOrder = $columnOrder[(string) $left['reference_id']] ?? PHP_INT_MAX;
                $rightOrder = $columnOrder[(string) $right['reference_id']] ?? PHP_INT_MAX;

                return $leftOrder <=> $rightOrder ?: ((int) $left['ordering'] <=> (int) $right['ordering']);
            });
        }
        $id = $this->id;
        $encodedConfig = htmlspecialchars(
            $config === []
                ? '{}'
                : json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ENT_QUOTES,
            'UTF-8'
        );

        $options = static function (array $values, string $selected): string {
            $html = '';
            foreach ($values as $value => $label) {
                $translatedLabel = preg_match('/^(?:COM_|J[A-Z_]+$)/', (string) $label) === 1
                    ? Text::_((string) $label)
                    : (string) $label;
                $html .= '<option value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"'
                    . ((string) $value === $selected ? ' selected' : '') . '>'
                    . htmlspecialchars($translatedLabel, ENT_QUOTES, 'UTF-8') . '</option>';
            }
            return $html;
        };

        $modeOptions = [
            'default' => 'COM_CONTENTBUILDERNG_MENU_NEW_DEFAULT_VIEW',
            'custom' => 'COM_CONTENTBUILDERNG_MENU_NEW_CUSTOM',
        ];
        $toggleOptions = [
            'default' => 'COM_CONTENTBUILDERNG_MENU_NEW_USE_DEFAULT',
            'yes' => 'JYES',
            'no' => 'JNO',
        ];
        $visibilityOptions = [
            'default' => 'COM_CONTENTBUILDERNG_MENU_NEW_USE_DEFAULT',
            'no' => 'COM_CONTENTBUILDERNG_MENU_NEW_HIDE',
        ];
        $out = '<input type="hidden" name="' . $this->name . '" id="' . $id . '" value="' . $encodedConfig
            . '" data-cb-new-list-config data-cb-menu-override="true">';
        $out .= '<div id="' . $id . '_builder" class="cb-new-list-builder" data-cb-new-list-builder data-cb-config-input="'
            . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" data-cb-inherited-control-title="'
            . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_MENU_NEW_FIELD_INHERITED_TIP'), ENT_QUOTES, 'UTF-8')
            . '" data-cb-unavailable-control-title="'
            . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_MENU_NEW_FIELD_UNAVAILABLE_TIP'), ENT_QUOTES, 'UTF-8') . '">';
        $introductionHtml = '<div class="cb-menu-introduction-settings">'
            . '<div class="cb-menu-horizontal-control"><label class="form-label">'
            . Text::_('COM_CONTENTBUILDERNG_MENU_NEW_TITLE_MODE') . '</label><div><select class="form-select" data-cb-key="titleMode" data-cb-reset-value="default" data-cb-view-default-key="show_title_breadcrumb">'
            . $options([
                'default' => 'COM_CONTENTBUILDERNG_MENU_NEW_USE_DEFAULT',
                'custom' => 'COM_CONTENTBUILDERNG_MENU_NEW_CUSTOM',
                'hidden' => 'COM_CONTENTBUILDERNG_MENU_NEW_HIDDEN',
            ], (string) ($config['titleMode'] ?? 'default')) . '</select>'
            . $this->inlineHelp('COM_CONTENTBUILDERNG_MENU_NEW_TITLE_MODE_DESC') . '</div></div>'
            . '<div class="cb-menu-horizontal-control" data-cb-show-when="titleMode:custom"><label class="form-label">'
            . Text::_('COM_CONTENTBUILDERNG_MENU_NEW_CUSTOM_TITLE') . '</label><div><div class="input-group align-items-start"><textarea rows="2" class="form-control cb-menu-introduction-textarea" data-cb-key="title" data-cb-reset-value="">'
            . htmlspecialchars((string) ($config['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</textarea><span class="input-group-text" data-cb-title-character-count data-cb-line-label-one="'
            . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_MENU_NEW_LINE'), ENT_QUOTES, 'UTF-8') . '" data-cb-line-label-many="'
            . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_MENU_NEW_LINES'), ENT_QUOTES, 'UTF-8') . '" aria-live="polite" title="'
            . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_MENU_NEW_CHARACTER_COUNT'), ENT_QUOTES, 'UTF-8') . '">0/255 · 1 '
            . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_MENU_NEW_LINE'), ENT_QUOTES, 'UTF-8') . '</span></div></div></div></div>';

        $sortRows = is_array($config['sort'] ?? null) ? array_values($config['sort']) : [];
        $storedMaximumRecords = array_key_exists('maximumRecords', $config)
            ? max(-1, min(5000, (int) $config['maximumRecords']))
            : -1;
        $topHtml = '<div class="cb-menu-top-settings">'
            . '<div class="cb-menu-top-native-field" data-cb-native-field-slot="cb_list_limit"></div>'
            . '<div class="cb-menu-top-control"><label class="form-label">'
            . Text::_('COM_CONTENTBUILDERNG_MENU_NEW_MAX_RECORDS') . '</label><div>'
            . $this->listLimitControl(
                $id . '_maximum_records',
                $storedMaximumRecords,
                $viewMaximumRecords,
                'maximumRecords',
                'cb_maximum_records'
            ) . $this->inlineHelp('COM_CONTENTBUILDERNG_MENU_NEW_MAX_RECORDS_DESC') . '</div></div>'
            . '<div class="cb-menu-top-control"><label class="form-label">'
            . Text::_('COM_CONTENTBUILDERNG_MENU_NEW_SORT_MODE') . '</label><div><select class="form-select" data-cb-key="sortMode" data-cb-reset-value="default">'
            . $options($modeOptions, (string) ($config['sortMode'] ?? 'default')) . '</select></div></div>'
            . '<div class="cb-menu-custom-sort" data-cb-show-when="sortMode:custom">';
        for ($index = 0; $index < 3; $index++) {
            $row = is_array($sortRows[$index] ?? null) ? $sortRows[$index] : [];
            $sortField = (string) ($row['field'] ?? '');
            $sortDirection = $sortField === '' ? 'asc' : (string) ($row['dir'] ?? 'asc');
            $topHtml .= '<div class="d-flex flex-wrap align-items-center gap-2 mb-2 cb-menu-sort-row"><select class="form-select cb-menu-sort-field" data-cb-sort-field="' . $index . '">'
                . '<option value="">' . Text::_('COM_CONTENTBUILDERNG_NONE') . '</option>'
                . '<option value="ID"' . ($sortField === 'ID' ? ' selected' : '') . '>ID</option>';
            foreach ($elements as $element) {
                $reference = (string) $element['reference_id'];
                $topHtml .= '<option value="' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '"'
                    . ($sortField === $reference ? ' selected' : '') . '>'
                    . htmlspecialchars((string) $element['label'], ENT_QUOTES, 'UTF-8') . '</option>';
            }
            $topHtml .= '</select><select class="form-select cb-menu-sort-direction" data-cb-sort-dir="' . $index . '">'
                . '<option value="asc"' . ($sortDirection === 'asc' ? ' selected' : '') . '>ASC</option>'
                . '<option value="desc"' . ($sortDirection === 'desc' ? ' selected' : '') . '>DESC</option>'
                . '</select></div>';
        }
        $out .= $topHtml . '</div>' . $this->inlineHelp('COM_CONTENTBUILDERNG_MENU_NEW_SORT_DESC')
            . $introductionHtml . '</div>';

        $actions = is_array($config['action'] ?? null) ? $config['action'] : [];
        $exportFilenameMode = (string) ($config['exportFilenameMode'] ?? 'default');
        if (!in_array($exportFilenameMode, ['default', 'custom'], true)) {
            $exportFilenameMode = 'default';
        }
        $exportFilenameOptions = [
            'default' => 'COM_CONTENTBUILDERNG_MENU_EXPORT_FILENAME_DEFAULT',
            'custom' => 'COM_CONTENTBUILDERNG_MENU_EXPORT_FILENAME_CUSTOM',
        ];
        $displayHtml = '<div class="cb-menu-native-display-fields">'
            . '<div class="row g-3 mb-4 cb-menu-display-grid">'
            . $this->selectControl(
                'action.export',
                Text::_('COM_CONTENTBUILDERNG_MENU_NEW_ACTION_EXPORT'),
                $toggleOptions,
                (string) ($actions['export'] ?? 'default'),
                $options,
                'export_xls',
                true
            )
            . '<div class="col-12 col-lg-4" data-cb-native-field-slot="cb_show_details_back_button"></div>'
            . $this->selectControl(
                'action.rating',
                Text::_('COM_CONTENTBUILDERNG_MENU_NEW_ACTION_RATING'),
                $toggleOptions,
                (string) ($actions['rating'] ?? 'default'),
                $options,
                'list_rating',
                true
            )
            . '</div><div class="row g-3 mb-4 cb-menu-display-grid">'
            . '<div class="col-12 col-lg-4" data-cb-native-field-slot="cb_show_details_top_bar"></div>'
            . '<div class="col-12 col-lg-4" data-cb-native-field-slot="cb_show_details_bottom_bar"></div>'
            . $this->selectControl(
                'action.print',
                Text::_('COM_CONTENTBUILDERNG_MENU_NEW_ACTION_PRINT'),
                $toggleOptions,
                (string) ($actions['print'] ?? 'default'),
                $options,
                'print_button',
                true
            )
            . '</div><div class="row g-3 cb-menu-display-grid">'
            . '<div class="col-12 col-lg-4" data-cb-native-field-slot="cb_show_top_bar"></div>'
            . '<div class="col-12 col-lg-4" data-cb-native-field-slot="cb_show_bottom_bar"></div>'
            . $this->selectControl(
                'editListButton',
                Text::_('COM_CONTENTBUILDERNG_MENU_NEW_EDIT_LIST_BUTTON'),
                $visibilityOptions,
                (string) ($config['editListButton'] ?? 'default'),
                $options,
                'edit_button',
                true,
                'COM_CONTENTBUILDERNG_MENU_NEW_EDIT_LIST_BUTTON_DESC'
            )
            . '</div><div class="row g-3 mt-1">'
            . $this->selectControl(
                'exportFilenameMode',
                Text::_('COM_CONTENTBUILDERNG_MENU_EXPORT_FILENAME_MODE'),
                $exportFilenameOptions,
                $exportFilenameMode,
                $options,
                '',
                false,
                'COM_CONTENTBUILDERNG_MENU_EXPORT_FILENAME_DESC'
            )
            . '<div class="col-12 col-lg-4" data-cb-show-when="exportFilenameMode:custom"><label class="form-label">'
            . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_MENU_EXPORT_FILENAME_CUSTOM_LABEL'), ENT_QUOTES, 'UTF-8')
            . '</label><input type="text" class="form-control" maxlength="150" autocomplete="off" data-cb-key="exportFilename" data-cb-reset-value="" value="'
            . htmlspecialchars((string) ($config['exportFilename'] ?? ''), ENT_QUOTES, 'UTF-8') . '">'
            . $this->inlineHelp('COM_CONTENTBUILDERNG_MENU_EXPORT_FILENAME_CUSTOM_DESC') . '</div>'
            . '</div></div>' . $this->inlineHelp('COM_CONTENTBUILDERNG_MENU_NEW_ACTIONS_DESC', 'mt-2');
        $out .= $this->section(Text::_('COM_CONTENTBUILDERNG_MENU_NEW_DISPLAY'), $displayHtml);

        $searchHtml = '<div class="row g-3">'
            . $this->selectControl('searchShow', Text::_('COM_CONTENTBUILDERNG_MENU_NEW_SHOW_SEARCH'), $toggleOptions, (string) ($config['searchShow'] ?? 'default'), $options, 'show_filter', true, 'COM_CONTENTBUILDERNG_MENU_NEW_SHOW_SEARCH_DESC')
            . $this->selectControl('stateShow', Text::_('COM_CONTENTBUILDERNG_MENU_NEW_SHOW_STATE'), $toggleOptions, (string) ($config['stateShow'] ?? 'default'), $options, 'list_state', true, 'COM_CONTENTBUILDERNG_MENU_NEW_SHOW_STATE_DESC')
            . $this->selectControl('stateBulkShow', Text::_('COM_CONTENTBUILDERNG_MENU_NEW_SHOW_STATE_BULK'), $toggleOptions, (string) ($config['stateBulkShow'] ?? 'default'), $options, 'list_state_bulk', true, 'COM_CONTENTBUILDERNG_MENU_NEW_SHOW_STATE_BULK_DESC')
            . $this->selectControl('stateFilterShow', Text::_('COM_CONTENTBUILDERNG_MENU_NEW_SHOW_STATE_FILTER'), $toggleOptions, (string) ($config['stateFilterShow'] ?? 'default'), $options, 'show_state_filter', true, 'COM_CONTENTBUILDERNG_MENU_NEW_SHOW_STATE_FILTER_DESC')
            . '</div>';
        $out .= $this->section(Text::_('COM_CONTENTBUILDERNG_MENU_NEW_SEARCH_STATE'), $searchHtml);

        $security = is_array($config['security'] ?? null) ? $config['security'] : [];
        $additionalDisplayHtml = '<div class="row g-3">';
        $securityLabels = [
            'new' => 'COM_CONTENTBUILDERNG_MENU_NEW_ACTION_CREATE',
            'detail' => 'COM_CONTENTBUILDERNG_MENU_NEW_ACTION_DETAIL',
            'edit' => 'COM_CONTENTBUILDERNG_MENU_NEW_ACTION_EDIT',
            'delete' => 'COM_CONTENTBUILDERNG_MENU_NEW_ACTION_DELETE',
            'publish' => 'COM_CONTENTBUILDERNG_MENU_NEW_ACTION_PUBLISH',
            'state' => 'COM_CONTENTBUILDERNG_MENU_NEW_ACTION_STATE',
        ];
        foreach ($securityLabels as $key => $label) {
            $securityOptions = [
                'inherit' => 'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_PERMISSIONS_VALUE',
                'disabled' => 'JDISABLED',
            ];
            $additionalDisplayHtml .= $this->selectControl(
                'security.' . $key,
                Text::_($label),
                $securityOptions,
                (string) ($security[$key] ?? 'inherit'),
                $options,
                '',
                true,
                'COM_CONTENTBUILDERNG_MENU_NEW_ADDITIONAL_' . strtoupper($key) . '_DESC'
            );
        }
        $additionalDisplayHtml .= '</div>';
        $out .= $this->section(
            Text::_('COM_CONTENTBUILDERNG_MENU_NEW_ADDITIONAL_DISPLAY'),
            $additionalDisplayHtml,
            Text::_('COM_CONTENTBUILDERNG_MENU_NEW_ADDITIONAL_DISPLAY_DESC')
        );

        $columnMode = (string) ($config['columnsMode'] ?? 'default');
        $selectedColumns = array_map('strval', is_array($config['columns'] ?? null) ? $config['columns'] : []);
        $selectedSearch = array_map('strval', is_array($config['searchFields'] ?? null) ? $config['searchFields'] : []);
        $selectedLinks = array_map('strval', is_array($config['linkFields'] ?? null) ? $config['linkFields'] : []);
        $selectedDetails = array_map('strval', is_array($config['detailFields'] ?? null) ? $config['detailFields'] : []);
        $selectedEdits = array_map('strval', is_array($config['editFields'] ?? null) ? $config['editFields'] : []);
        $selectedExports = array_map('strval', is_array($config['exportFields'] ?? null) ? $config['exportFields'] : []);
        $selectedPublished = array_map('strval', is_array($config['publishedFields'] ?? null) ? $config['publishedFields'] : []);
        $hasSelectedDetails = is_array($config['detailFields'] ?? null);
        $hasSelectedEdits = is_array($config['editFields'] ?? null);
        $hasSelectedExports = is_array($config['exportFields'] ?? null);
        $hasSelectedPublished = is_array($config['publishedFields'] ?? null);
        $filters = is_array($config['filters'] ?? null) ? $config['filters'] : [];
        $columnsHtml = '<div class="row g-3 mb-3">'
            . $this->selectControl('columnsMode', Text::_('COM_CONTENTBUILDERNG_MENU_NEW_COLUMNS_MODE'), $modeOptions, $columnMode, $options)
            . '<div class="col-12 col-lg-8"><label class="form-label">' . Text::_('JSEARCH_FILTER') . '</label><input type="search" class="form-control" data-cb-field-search></div></div>'
            . '<div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th class="w-1"></th>'
            . $this->tableHeading('COM_CONTENTBUILDERNG_MENU_NEW_FIELD', 'COM_CONTENTBUILDERNG_MENU_NEW_FIELD_DESC')
            . $this->tableHeading('COM_CONTENTBUILDERNG_MENU_NEW_VIEW_DISPLAY', 'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_DISPLAY_DESC', true)
            . $this->tableHeading('COM_CONTENTBUILDERNG_MENU_NEW_VIEW_SEARCH', 'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_SEARCH_DESC', true)
            . $this->tableHeading('COM_CONTENTBUILDERNG_MENU_NEW_VIEW_LINK', 'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_LINK_DESC', true)
            . $this->tableHeading('COM_CONTENTBUILDERNG_MENU_NEW_VIEW_DETAIL', 'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_DETAIL_DESC', true)
            . $this->tableHeading('COM_CONTENTBUILDERNG_MENU_NEW_VIEW_EDIT', 'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_EDIT_DESC', true)
            . $this->tableHeading('COM_CONTENTBUILDERNG_MENU_NEW_VIEW_EXPORT', 'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_EXPORT_DESC', true)
            . $this->tableHeading('COM_CONTENTBUILDERNG_MENU_NEW_VIEW_PUBLISHED', 'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_PUBLISHED_DESC', true)
            . $this->tableHeading('COM_CONTENTBUILDERNG_MENU_NEW_FIXED_FILTER', 'COM_CONTENTBUILDERNG_MENU_NEW_FIXED_FILTER_DESC')
            . '</tr></thead><tbody data-cb-column-rows>';
        foreach ($elements as $element) {
            $reference = (string) $element['reference_id'];
            $isPublished = (int) $element['published'] === 1;
            $viewList = (int) $element['list_include'] === 1;
            $viewSearch = (int) $element['search_include'] === 1;
            $viewLink = (int) $element['linkable'] === 1;
            $viewDetail = (int) $element['detail_include'] === 1;
            $viewEdit = (int) $element['editable'] === 1;
            $viewExport = (int) $element['export_include'] === 1;
            $checked = $viewList && ($columnMode === 'custom' ? in_array($reference, $selectedColumns, true) : true);
            $fieldStateTip = !$isPublished
                ? Text::_('COM_CONTENTBUILDERNG_MENU_NEW_FIELD_UNPUBLISHED_TIP')
                : (!$viewList ? Text::_('COM_CONTENTBUILDERNG_MENU_NEW_FIELD_NOT_LISTED_TIP') : '');
            $fieldLabel = htmlspecialchars((string) $element['label'], ENT_QUOTES, 'UTF-8');
            if ($fieldStateTip !== '') {
                $fieldLabel = '<span class="cb-menu-field-state" tabindex="0" title="'
                    . htmlspecialchars($fieldStateTip, ENT_QUOTES, 'UTF-8') . '">' . $fieldLabel . '</span>';
            }
            $columnsHtml .= '<tr data-cb-column-row data-reference="' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '" data-view-order="'
                . (int) $element['ordering'] . '" data-can-list="'
                . ($isPublished && $viewList ? '1' : '0') . '" data-can-search="' . ($isPublished && $viewSearch ? '1' : '0')
                . '" data-can-link="' . ($isPublished && $viewLink ? '1' : '0') . '" data-can-detail="'
                . ($isPublished && $viewDetail ? '1' : '0') . '" data-can-edit="'
                . ($isPublished && $viewEdit ? '1' : '0') . '" data-can-export="'
                . ($isPublished && $viewExport ? '1' : '0') . '" data-can-published="'
                . ($isPublished ? '1' : '0') . '" data-label="'
                . htmlspecialchars(mb_strtolower((string) $element['label'], 'UTF-8'), ENT_QUOTES, 'UTF-8') . '"><td><div class="btn-group btn-group-sm" role="group"><button type="button" class="btn btn-outline-secondary" data-cb-move="up" aria-label="↑">↑</button><button type="button" class="btn btn-outline-secondary" data-cb-move="down" aria-label="↓">↓</button></div></td><td>'
                . $fieldLabel . '</td>'
                . '<td class="text-center cb-menu-capability-cell"><input type="checkbox" class="form-check-input" data-cb-column value="'
                . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '" data-view-default="' . ($viewList ? '1' : '0') . '"'
                . ($checked ? ' checked' : '') . (!$isPublished || !$viewList || $columnMode !== 'custom' ? ' disabled' : '')
                . ' aria-label="' . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_MENU_NEW_VIEW_DISPLAY'), ENT_QUOTES, 'UTF-8') . '"></td>'
                . '<td class="text-center cb-menu-capability-cell"><input type="checkbox" class="form-check-input" data-cb-search-field value="'
                . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '" data-view-default="' . ($viewSearch ? '1' : '0') . '"'
                . (($viewSearch && ($columnMode === 'custom' ? in_array($reference, $selectedSearch, true) : true)) ? ' checked' : '')
                . (!$isPublished || !$viewSearch || $columnMode !== 'custom' ? ' disabled' : '')
                . ' aria-label="' . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_MENU_NEW_VIEW_SEARCH'), ENT_QUOTES, 'UTF-8') . '"></td>'
                . '<td class="text-center cb-menu-capability-cell"><input type="checkbox" class="form-check-input" data-cb-link-field value="'
                . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '" data-view-default="' . ($viewLink ? '1' : '0') . '"'
                . (($viewLink && ($columnMode === 'custom' ? in_array($reference, $selectedLinks, true) : true)) ? ' checked' : '')
                . (!$isPublished || !$viewLink || $columnMode !== 'custom' ? ' disabled' : '')
                . ' aria-label="' . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_MENU_NEW_VIEW_LINK'), ENT_QUOTES, 'UTF-8') . '"></td>'
                . $this->capabilityCheckbox('detail', $reference, $viewDetail, $isPublished && $viewDetail && ($columnMode !== 'custom' || !$hasSelectedDetails || in_array($reference, $selectedDetails, true)), $isPublished && $viewDetail && $columnMode === 'custom', 'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_DETAIL')
                . $this->capabilityCheckbox('edit', $reference, $viewEdit, $isPublished && $viewEdit && ($columnMode !== 'custom' || !$hasSelectedEdits || in_array($reference, $selectedEdits, true)), $isPublished && $viewEdit && $columnMode === 'custom', 'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_EDIT')
                . $this->capabilityCheckbox('export', $reference, $viewExport, $isPublished && $viewExport && ($columnMode !== 'custom' || !$hasSelectedExports || in_array($reference, $selectedExports, true)), $isPublished && $viewExport && $columnMode === 'custom', 'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_EXPORT')
                . $this->capabilityCheckbox('published', $reference, $isPublished, $isPublished && ($columnMode !== 'custom' || !$hasSelectedPublished || in_array($reference, $selectedPublished, true)), $isPublished && $columnMode === 'custom', 'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_PUBLISHED')
                . '<td><input type="text" class="form-control form-control-sm cb-menu-filter-input" data-cb-filter value="'
                . htmlspecialchars((string) ($filters[$reference] ?? ''), ENT_QUOTES, 'UTF-8') . '" placeholder="'
                . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_MENU_NEW_FILTER_PLACEHOLDER'), ENT_QUOTES, 'UTF-8') . '"'
                . (!$isPublished ? ' disabled' : '') . '></td></tr>';
        }
        $columnsHtml .= '</tbody></table></div><div class="form-text">' . Text::_('COM_CONTENTBUILDERNG_MENU_NEW_FILTER_HELP') . '</div>';
        $out .= $this->section(Text::_('COM_CONTENTBUILDERNG_MENU_NEW_COLUMNS_FILTERS'), $columnsHtml);
        $out .= '</div>';

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function getElements(int $formId): array
    {
        if ($formId < 1) {
            return [];
        }

        $db = RuntimeContextHelper::getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['reference_id', 'label', 'published', 'detail_include', 'editable', 'export_include', 'list_include', 'search_include', 'linkable', 'order_type', 'ordering']))
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('form_id') . ' = ' . $formId)
            ->where($db->quoteName('reference_id') . ' >= 0')
            ->order($db->quoteName('ordering') . ' ASC');
        $db->setQuery($query);

        return array_values((array) $db->loadAssocList());
    }

    private function section(string $title, string $content, string $description = ''): string
    {
        $tooltip = $description === ''
            ? ''
            : ' hasTip" tabindex="0" title="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

        return '<fieldset class="cb-menu-builder-section mb-4"><legend><span class="cb-menu-section-heading' . $tooltip . '">'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span></legend>' . $content . '</fieldset>';
    }

    /** @param callable(array<string, string>, string): string $options */
    private function selectControl(
        string $key,
        string $label,
        array $values,
        string $selected,
        callable $options,
        string $defaultKey = '',
        bool $colourState = false,
        string $descriptionKey = '',
        ?bool $inheritedBoolean = null
    ): string {
        $defaultAttribute = $defaultKey !== ''
            ? ' data-cb-view-default-key="' . htmlspecialchars($defaultKey, ENT_QUOTES, 'UTF-8') . '"'
            : '';

        $resetValue = str_starts_with($key, 'security.') ? 'inherit' : 'default';
        $inheritedAttribute = $inheritedBoolean === null
            ? ''
            : ' data-cb-inherited-boolean="' . ($inheritedBoolean ? 'yes' : 'no') . '"';

        return '<div class="col-12 col-lg-4"><label class="form-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</label><select class="form-select' . ($colourState ? ' form-select-color-state' : '') . '" data-cb-key="'
            . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" data-cb-reset-value="' . $resetValue . '"'
            . $defaultAttribute . $inheritedAttribute . '>'
            . $options($values, $selected) . '</select>'
            . ($descriptionKey !== '' ? $this->inlineHelp($descriptionKey) : '')
            . '</div>';
    }

    private function inlineHelp(string $languageKey, string $additionalClass = ''): string
    {
        $classes = trim('form-text hide-aware-inline-help d-none ' . $additionalClass);

        return '<div class="' . $classes . '">' . Text::_($languageKey) . '</div>';
    }

    private function capabilityCheckbox(string $capability, string $reference, bool $viewDefault, bool $checked, bool $enabled, string $labelKey): string
    {
        return '<td class="text-center cb-menu-capability-cell"><input type="checkbox" class="form-check-input" data-cb-' . $capability . '-field value="'
            . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '" data-view-default="' . ($viewDefault ? '1' : '0') . '"'
            . ($checked ? ' checked' : '') . ($enabled ? '' : ' disabled') . ' aria-label="'
            . htmlspecialchars(Text::_($labelKey), ENT_QUOTES, 'UTF-8') . '"></td>';
    }

    private function tableHeading(string $labelKey, string $descriptionKey, bool $centred = false): string
    {
        return '<th' . ($centred ? ' class="text-center"' : '') . '><span class="hasTip cb-menu-table-heading" tabindex="0" title="'
            . htmlspecialchars(Text::_($descriptionKey), ENT_QUOTES, 'UTF-8') . '">'
            . Text::_($labelKey) . '</span></th>';
    }

    private function listLimitControl(
        string $id,
        int $value,
        int $inherited,
        string $configKey,
        string $defaultKey
    ): string {
        $value = max(-1, min(5000, $value));
        $allLabel = Text::_('JALL');
        $choices = ListLimitHelper::getPaginationChoices();
        $inheritedLabel = $inherited === ListLimitHelper::ALL ? $allLabel : (string) $inherited;
        $defaultLabel = Text::sprintf('COM_CONTENTBUILDERNG_USE_DEFAULT_VALUE', $inheritedLabel);
        $display = $value < 0
            ? $defaultLabel
            : ($value === 0
            ? $allLabel
            : (in_array($value, $choices, true)
                ? (string) $value
                : Text::sprintf('COM_CONTENTBUILDERNG_LIST_LIMIT_CUSTOM_VALUE', $value)));
        $items = '<li><button type="button" class="dropdown-item" data-cb-list-limit-choice="-1">'
            . htmlspecialchars($defaultLabel, ENT_QUOTES, 'UTF-8') . '</button></li>';

        foreach ($choices as $choice) {
            $label = $choice === ListLimitHelper::ALL ? $allLabel : (string) $choice;
            $items .= '<li><button type="button" class="dropdown-item" data-cb-list-limit-choice="' . (int) $choice . '">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</button></li>';
        }

        return '<div class="input-group cb-list-limit-control" data-cb-list-limit-control data-mode="menu" data-inherit-value="-1" data-inherited="'
            . $inherited . '" data-default-label="' . htmlspecialchars($defaultLabel, ENT_QUOTES, 'UTF-8') . '" data-custom-format="'
            . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_LIST_LIMIT_CUSTOM_VALUE'), ENT_QUOTES, 'UTF-8') . '" data-all-label="'
            . htmlspecialchars($allLabel, ENT_QUOTES, 'UTF-8') . '"><input type="text" class="form-control" id="'
            . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($display, ENT_QUOTES, 'UTF-8')
            . '" inputmode="numeric" autocomplete="off"><button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="'
            . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_LIST_LIMIT_OPEN_CHOICES'), ENT_QUOTES, 'UTF-8')
            . '"></button><ul class="dropdown-menu dropdown-menu-end">' . $items . '</ul><input type="hidden" id="'
            . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '-value" value="' . $value
            . '" data-cb-list-limit-storage data-cb-key="' . htmlspecialchars($configKey, ENT_QUOTES, 'UTF-8')
            . '" data-cb-reset-value="-1" data-cb-menu-default-key="' . htmlspecialchars($defaultKey, ENT_QUOTES, 'UTF-8') . '"></div>';
    }
}
