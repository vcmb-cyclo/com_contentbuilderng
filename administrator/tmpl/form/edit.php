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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;
use CB\Component\Contentbuilderng\Administrator\Service\TextUtilityService;
use CB\Component\Contentbuilderng\Site\Helper\PreviewLinkHelper;


/** @var AdministratorApplication $app */
$app = Factory::getApplication();
$session = $app->getSession();
$textUtilityService = new TextUtilityService();
$componentLayoutBase = dirname(__DIR__, 2) . '/layouts';
$wa = $app->getDocument()->getWebAssetManager();
$formEditCssPath = JPATH_ROOT . '/media/com_contentbuilderng/css/form-edit.css';
if (is_file($formEditCssPath) && is_readable($formEditCssPath)) {
    $wa->addInlineStyle((string) file_get_contents($formEditCssPath));
}

$listOrder = (string) ($this->listOrder ?? 'ordering');
$listDirn  = strtolower((string) ($this->listDirn ?? 'asc'));
$listDirn  = ($listDirn === 'desc') ? 'desc' : 'asc';
$formId    = (int) ($this->item->id ?? 0);
$fullOrdering = trim($listOrder . ' ' . strtoupper($listDirn));
?>
<?php
$apiEndpointBase = Uri::root() . 'index.php?option=com_contentbuilderng&task=api.display&id=' . $formId;
$apiPreviewQuery = '';
$apiPreviewUntil = time() + 600;
$apiPreviewActorId = (int) ($app->getIdentity()->id ?? 0);
$apiPreviewActorName = trim((string) ($app->getIdentity()->name ?? ''));
$apiPreviewUserId = (int) ($app->getIdentity()->id ?? 0);
if ($apiPreviewActorName === '') {
    $apiPreviewActorName = trim((string) ($app->getIdentity()->username ?? ''));
}
if ($apiPreviewActorName !== '' && $apiPreviewUserId > 0) {
    $apiPreviewPayload = PreviewLinkHelper::buildPayload((string) $formId, $apiPreviewUntil, $apiPreviewActorId, $apiPreviewActorName, $apiPreviewUserId);
    $apiPreviewSig = hash_hmac('sha256', $apiPreviewPayload, (string) $app->get('secret'));
    $apiPreviewQuery = PreviewLinkHelper::buildQuery($apiPreviewUntil, $apiPreviewActorId, $apiPreviewActorName, $apiPreviewUserId, $apiPreviewSig);
}
$apiExampleRecordId = 123;
$apiExampleFields = [
    'Email' => 'john@example.com',
    'Amount' => '125.50',
];

try {
    $formType = trim((string) ($this->item->type ?? ''));
    $formReferenceId = trim((string) ($this->item->reference_id ?? ''));

    if ($formId > 0 && $formType !== '' && $formReferenceId !== '') {
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('record_id'))
            ->from($db->quoteName('#__contentbuilderng_records'))
            ->where($db->quoteName('type') . ' = ' . $db->quote($formType))
            ->where($db->quoteName('reference_id') . ' = ' . $db->quote($formReferenceId))
            ->order($db->quoteName('record_id') . ' DESC');
        $db->setQuery($query, 0, 30);
        $candidateRecordIds = array_map('intval', (array) $db->loadColumn());

        $apiForm = $this->item->form ?? null;
        if (!is_object($apiForm)) {
            $apiForm = FormSourceFactory::getForm($formType, $formReferenceId);
        }

        if (is_object($apiForm) && method_exists($apiForm, 'getRecord')) {
            $bestExampleRecordId = 0;
            $bestExampleFields = [];
            $bestExampleScore = -1;

            foreach ($candidateRecordIds as $candidateRecordId) {
                if ($candidateRecordId < 1) {
                    continue;
                }

                $recordItems = $apiForm->getRecord($candidateRecordId, false, -1, true);
                if (!is_array($recordItems) || empty($recordItems)) {
                    continue;
                }

                $detectedFields = [];
                $nonEmptyValues = 0;

                foreach ($recordItems as $recordItem) {
                    if (!is_object($recordItem)) {
                        continue;
                    }

                    $fieldName = trim((string) ($recordItem->recName ?? ''));
                    if ($fieldName === '' || isset($detectedFields[$fieldName])) {
                        continue;
                    }

                    $fieldValue = (string) ($recordItem->recValue ?? '');
                    if (trim($fieldValue) !== '') {
                        $nonEmptyValues++;
                    }

                    $detectedFields[$fieldName] = $fieldValue;
                    if (count($detectedFields) >= 4) {
                        break;
                    }
                }

                if (empty($detectedFields)) {
                    continue;
                }

                // Prefer rows with real non-empty values and more available fields.
                $candidateScore = ($nonEmptyValues * 100) + count($detectedFields);
                if ($candidateScore <= $bestExampleScore) {
                    continue;
                }

                $bestExampleRecordId = $candidateRecordId;
                $bestExampleFields = $detectedFields;
                $bestExampleScore = $candidateScore;
            }

            if ($bestExampleRecordId > 0 && !empty($bestExampleFields)) {
                $apiExampleRecordId = $bestExampleRecordId;
                $apiExampleFields = $bestExampleFields;
            }
        }
    }
} catch (\Throwable $e) {
    // Keep static fallback examples when dynamic extraction is not possible.
}

$apiExamplePayloadJson = (string) json_encode(
    ['fields' => $apiExampleFields],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
$apiExampleDetailDisplayUrl = $apiEndpointBase . '&record_id=' . (int) $apiExampleRecordId;
$apiExampleListDisplayUrl = $apiEndpointBase . '&list[limit]=20&list[start]=0';
$apiExampleDetailUrl = $apiExampleDetailDisplayUrl . $apiPreviewQuery;
$apiExampleListUrl = $apiExampleListDisplayUrl . $apiPreviewQuery;
$apiExampleUpdateUrl = $apiEndpointBase . '&record_id=' . (int) $apiExampleRecordId;
$apiExampleStatsDisplayUrl = $apiEndpointBase . '&action=stats';
$apiExampleStatsUrl = $apiExampleStatsDisplayUrl . $apiPreviewQuery;
$apiExampleVerboseDisplayUrl = $apiExampleDetailDisplayUrl . '&verbose=1';
$apiExampleVerboseUrl = $apiExampleVerboseDisplayUrl . $apiPreviewQuery;
$isBreezingFormsType = in_array(
    (string) ($this->item->type ?? ''),
    ['com_breezingforms', 'com_breezingforms_ng'],
    true
);
$canEditByType = (string) ($this->item->type ?? '') !== 'com_contentbuilderng';
$breezingFormsEditableToken = '{BreezingForms: ' . (isset($this->item->type_name) ? (string) $this->item->type_name : '') . '}';
$breezingFormsProvidedMessage = '<div class="alert alert-success d-inline-flex align-items-center py-2 px-3 mb-2" role="status">'
    . '<span class="badge bg-success me-2">&#10003;</span>'
    . '<span>' . htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_EDITABLE_TEMPLATE_PROVIDED_BY_BREEZINGFORMS'), ENT_QUOTES, 'UTF-8') . '</span>'
    . '</div>';

$sortLink = function (string $label, string $field) use ($listOrder, $listDirn, $formId): string {
    return HTMLHelper::_('searchtools.sort', $label, $field, $listDirn, $listOrder);
};

$permHeaderLabel = static function (string $labelKey, string $tipKey): string {
	$label = Text::_($labelKey);
	$tip = Text::_($tipKey);

	return '<span class="cb-perm-header-tip" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="'
	    . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '">'
	    . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
};

$permGroupLabel = static function (string $groupText, int $groupId = 0, string $groupPath = '', string $groupTitle = ''): string {
    $visibleLabel = trim(html_entity_decode(strip_tags($groupText), ENT_QUOTES, 'UTF-8'));
    $titleLabel = trim($groupTitle) !== '' ? trim($groupTitle) : $visibleLabel;
    $tooltipLines = [$titleLabel];

    if ($groupId > 0) {
        $tooltipLines[] = 'ID: ' . $groupId;
    }

    $groupPath = trim($groupPath);
    if ($groupPath !== '') {
        $tooltipLines[] = 'Path: ' . $groupPath;
    }

    $tooltip = implode(' | ', $tooltipLines);

    return '<span class="cb-perm-header-tip" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="'
        . htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') . '">'
        . '<span class="cb-perm-group-text">' . htmlspecialchars($titleLabel, ENT_QUOTES, 'UTF-8') . '</span></span>';
};

$viewTabLabel = static function (string $iconClass, string $labelKey, ?string $tipKey = null): string {
    $label = '<span class="' . htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></span> '
        . htmlspecialchars(Text::_($labelKey), ENT_QUOTES, 'UTF-8');

    if ($tipKey === null) {
        return $label;
    }

    $tip = Text::_($tipKey);

    return '<span class="cb-perm-header-tip" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="'
        . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '">'
        . $label
        . '</span>';
};

$permissionColumns = [
    ['key' => 'listaccess', 'label' => 'COM_CONTENTBUILDERNG_PERM_LIST_ACCESS', 'tip' => 'COM_CONTENTBUILDERNG_PERM_LIST_ACCESS_TIP'],
    ['key' => 'view', 'label' => 'COM_CONTENTBUILDERNG_PERM_VIEW', 'tip' => 'COM_CONTENTBUILDERNG_PERM_VIEW_TIP'],
    ['key' => 'new', 'label' => 'COM_CONTENTBUILDERNG_PERM_NEW', 'tip' => 'COM_CONTENTBUILDERNG_PERM_NEW_TIP'],
    ['key' => 'edit', 'label' => 'COM_CONTENTBUILDERNG_PERM_EDIT', 'tip' => 'COM_CONTENTBUILDERNG_PERM_EDIT_TIP'],
    ['key' => 'delete', 'label' => 'COM_CONTENTBUILDERNG_PERM_DELETE', 'tip' => 'COM_CONTENTBUILDERNG_PERM_DELETE_TIP'],
    ['key' => 'state', 'label' => 'COM_CONTENTBUILDERNG_PERM_STATE', 'tip' => 'COM_CONTENTBUILDERNG_PERM_STATE_TIP'],
    ['key' => 'publish', 'label' => 'COM_CONTENTBUILDERNG_PUBLISH', 'tip' => 'COM_CONTENTBUILDERNG_PUBLISH_TIP'],
    ['key' => 'api', 'label' => 'COM_CONTENTBUILDERNG_PERM_API', 'tip' => 'COM_CONTENTBUILDERNG_PERM_API_TIP'],
    ['key' => 'stats', 'label' => 'COM_CONTENTBUILDERNG_PERM_STATS', 'tip' => 'COM_CONTENTBUILDERNG_PERM_STATS_TIP'],
    ['key' => 'fullarticle', 'label' => 'COM_CONTENTBUILDERNG_PERM_FULL_ARTICLE', 'tip' => 'COM_CONTENTBUILDERNG_PERM_FULL_ARTICLE_TIP'],
    ['key' => 'language', 'label' => 'COM_CONTENTBUILDERNG_PERM_CHANGE_LANGUAGE', 'tip' => 'COM_CONTENTBUILDERNG_PERM_CHANGE_LANGUAGE_TIP'],
    ['key' => 'rating', 'label' => 'COM_CONTENTBUILDERNG_PERM_RATING', 'tip' => 'COM_CONTENTBUILDERNG_PERM_RATING_TIP'],
];

$defaultCheckedForNewPermissions = ['listaccess' => true, 'view' => true, 'new' => true];

$editablePrepareSnippetOptions = [];
$availableEditablePrepareElements = is_array($this->all_elements ?? null) ? $this->all_elements : [];
$sourceElementNamesByReference = [];

if (is_object($this->item->form ?? null) && method_exists($this->item->form, 'getElementNames')) {
    try {
        $sourceElementNames = (array) $this->item->form->getElementNames();
    } catch (\Throwable $e) {
        $sourceElementNames = [];
    }

    foreach ($sourceElementNames as $referenceId => $itemName) {
        $itemName = trim((string) $itemName);

        if ($itemName !== '') {
            $sourceElementNamesByReference[(string) $referenceId] = $itemName;
        }
    }
}

$isModifiedElementSettings = static function ($row): bool {
    $type = trim((string) ($row->type ?? ''));
    if ($type !== '' && $type !== 'text') {
        return true;
    }

    if (trim((string) ($row->item_wrapper ?? '')) !== '') {
        return true;
    }

    foreach (
        [
            'hint',
            'default_value',
            'validations',
            'custom_init_script',
            'custom_action_script',
            'custom_validation_script',
            'validation_message',
        ] as $field
    ) {
        if (trim((string) ($row->{$field} ?? '')) !== '') {
            return true;
        }
    }

    $options = PackedDataHelper::decodePackedData((string) ($row->options ?? ''), null);
    if (is_object($options)) {
        $options = (array) $options;
    }
    if (!is_array($options)) {
        $options = [];
    }

    $ignoreDefaults = [
        'length' => '',
        'maxlength' => '',
        'password' => 0,
        'readonly' => 0,
        'seperator' => ',',
        'class' => '',
        'allow_raw' => false,
        'allow_html' => false,
    ];

    foreach ($options as $key => $value) {
        if (is_string($value)) {
            $value = trim($value);
        }

        if (array_key_exists((string) $key, $ignoreDefaults) && $ignoreDefaults[(string) $key] === $value) {
            continue;
        }

        if ($value === '' || $value === null || $value === false || $value === 0 || $value === '0') {
            continue;
        }

        return true;
    }

    return false;
};

$hasPublishedEditableElements = false;
foreach ($availableEditablePrepareElements as $elementRow) {
    if (!is_object($elementRow)) {
        continue;
    }

    if ((int) ($elementRow->published ?? 0) !== 1) {
        continue;
    }

    if (strtolower((string) ($elementRow->type ?? '')) === 'hidden') {
        continue;
    }

    if ((int) ($elementRow->editable ?? 0) === 1) {
        $hasPublishedEditableElements = true;
        break;
    }
}

$seenPrepareItemNames = [];
foreach ($availableEditablePrepareElements as $elementRow) {
    if (!is_object($elementRow)) {
        continue;
    }

    if ((int) ($elementRow->published ?? 0) !== 1) {
        continue;
    }

    if (strtolower((string) ($elementRow->type ?? '')) === 'hidden') {
        continue;
    }

    if ($hasPublishedEditableElements && (int) ($elementRow->editable ?? 0) !== 1) {
        continue;
    }

    $referenceId = (string) ($elementRow->reference_id ?? '');
    if ($referenceId === '') {
        continue;
    }

    $itemName = trim((string) ($sourceElementNamesByReference[$referenceId] ?? $referenceId));
    if ($itemName === '' || isset($seenPrepareItemNames[$itemName])) {
        continue;
    }
    $seenPrepareItemNames[$itemName] = true;

    $itemNameEscaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $itemName);
    $itemPath = '$items["' . $itemNameEscaped . '"]';
    $editablePrepareSnippetOptions[] = [
        'text' => $itemName,
        'item_path' => $itemPath,
    ];
}

if (!empty($editablePrepareSnippetOptions)) {
    usort(
        $editablePrepareSnippetOptions,
        static fn(array $a, array $b): int => strnatcasecmp((string) ($a['text'] ?? ''), (string) ($b['text'] ?? ''))
    );
}

$prepareEffectOptions = [
    ['value' => 'none', 'text' => Text::_('COM_CONTENTBUILDERNG_NONE')],
    ['value' => 'bold', 'text' => Text::_('COM_CONTENTBUILDERNG_PREPARE_EFFECT_BOLD')],
    ['value' => 'red', 'text' => Text::_('COM_CONTENTBUILDERNG_PREPARE_EFFECT_RED')],
    ['value' => 'italic', 'text' => Text::_('COM_CONTENTBUILDERNG_PREPARE_EFFECT_ITALIC')],
    ['value' => 'gray', 'text' => Text::_('COM_CONTENTBUILDERNG_PREPARE_EFFECT_GRAY')],
    ['value' => 'negativeRed', 'text' => Text::_('COM_CONTENTBUILDERNG_PREPARE_EFFECT_NEGATIVE_RED')],
    ['value' => 'euroSuffix', 'text' => Text::_('COM_CONTENTBUILDERNG_PREPARE_EFFECT_EURO_SUFFIX')],
    ['value' => 'upper', 'text' => Text::_('COM_CONTENTBUILDERNG_PREPARE_EFFECT_UPPER')],
    ['value' => 'lower', 'text' => Text::_('COM_CONTENTBUILDERNG_PREPARE_EFFECT_LOWER')],
    ['value' => 'truncate10', 'text' => Text::_('COM_CONTENTBUILDERNG_PREPARE_EFFECT_TRUNCATE_10')],
    ['value' => 'blink', 'text' => Text::_('COM_CONTENTBUILDERNG_PREPARE_EFFECT_BLINK')],
];

$typeDisplayAliases = [
    'com_breezingforms' => 'BreezingForms',
    'com_breezingforms_ng' => 'BreezingForms',
    'com_contentbuilderng' => 'ContentBuilder',
];

$formatTypeDisplay = static function (string $type) use ($typeDisplayAliases): array {
    $normalizedType = trim($type);

    if ($normalizedType === '') {
        return ['short' => '', 'full' => ''];
    }

    $short = $typeDisplayAliases[$normalizedType] ?? $normalizedType;

    return [
        'short' => $short,
        'full' => $normalizedType,
    ];
};

$renderCheckbox = static function (string $name, string $id, bool $checked = false, string $value = '1', array $attributes = []): string {
    $html = '<span class="form-check d-inline-block mb-0">';
    $inputClass = 'form-check-input';

    if (!empty($attributes['class'])) {
        $inputClass .= ' ' . trim((string) $attributes['class']);
        unset($attributes['class']);
    }

    $html .= '<input class="' . htmlspecialchars($inputClass, ENT_QUOTES, 'UTF-8') . '" type="checkbox"';

    if ($name !== '') {
        $html .= ' name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"';
    }

    $html .= ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"';
    $html .= ' value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';

    if ($checked) {
        $html .= ' checked="checked"';
    }

    foreach ($attributes as $attr => $attrValue) {
        if ($attrValue === null || $attrValue === false) {
            continue;
        }

        $html .= ' ' . htmlspecialchars((string) $attr, ENT_QUOTES, 'UTF-8');

        if ($attrValue !== true) {
            $html .= '="' . htmlspecialchars((string) $attrValue, ENT_QUOTES, 'UTF-8') . '"';
        }
    }

    $html .= ' />';
    $html .= '</span>';

    return $html;
};
?>

<?php require __DIR__ . '/edit_init_scripts.php'; ?>

<form action="index.php" method="post" name="adminForm" id="adminForm">
    <div class="w-100 row g-0" style="max-width: 100%; overflow-x: auto;">
        <?php
        $advancedOptionsContent = '';
        // Démarrer les onglets
        $activeViewTab = trim((string) $app->getInput()->getCmd('tab', ''));
        $allowedViewTabs = ['tab0', 'tab1', 'tab2', 'tab3', 'tab5', 'tab6', 'tab7', 'tab8', 'tab9', 'tab10'];
        if (!in_array($activeViewTab, $allowedViewTabs, true)) {
            $activeViewTab = 'tab0';
        }
        echo HTMLHelper::_('uitab.startTabSet', 'view-pane', ['active' => $activeViewTab]);
        // Premier onglet
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab0', $viewTabLabel('fa-regular fa-window-maximize', 'COM_CONTENTBUILDERNG_VIEW', 'COM_CONTENTBUILDERNG_TAB_TIP_VIEW'));
        ?>

        <?php
                        $advancedOptionsContent = LayoutHelper::render(
                            'form.advanced_options',
                            [
                                'item' => $this->item,
                                'elements' => $this->all_elements,
                                'renderCheckbox' => $renderCheckbox,
                                'referencingMenuItems' => $this->referencingMenuItems ?? [],
                            ],
                            $componentLayoutBase
                        );

                        $elementsTableHtml = LayoutHelper::render(
                            'form.elements_table',
                            [
                                'elements' => $this->elements,
                                'pagination' => $this->pagination,
                                'ordering' => $this->ordering,
                                'item' => $this->item,
                                'sortLink' => $sortLink,
                                'textUtilityService' => $textUtilityService,
                                'isModifiedElementSettings' => $isModifiedElementSettings,
                            ],
                            $componentLayoutBase
                        );
                        ?>
        <?php
        echo LayoutHelper::render(
            'form.view_tab',
            [
                'item' => $this->item,
                'themePlugins' => $this->theme_plugins,
                'formatTypeDisplay' => $formatTypeDisplay,
                'elementsTableHtml' => $elementsTableHtml,
            ],
            $componentLayoutBase
        );
        ?>

        <?php
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab9', $viewTabLabel('fa-solid fa-sliders', 'COM_CONTENTBUILDERNG_ADVANCED_OPTIONS', 'COM_CONTENTBUILDERNG_TAB_TIP_ADVANCED_OPTIONS'));
        echo $advancedOptionsContent;
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab10', $viewTabLabel('fa-regular fa-newspaper', 'COM_CONTENTBUILDERNG_ARTICLE', 'COM_CONTENTBUILDERNG_TAB_TIP_ARTICLE'));
        echo LayoutHelper::render(
            'form.article_tab',
            [
                'item' => $this->item,
                'allElements' => $this->all_elements,
                'renderCheckbox' => $renderCheckbox,
                'isBreezingFormsType' => $isBreezingFormsType,
            ],
            $componentLayoutBase
        );
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab2', $viewTabLabel('fa-regular fa-file-lines', 'COM_CONTENTBUILDERNG_LIST_INTRO_TEXT', 'COM_CONTENTBUILDERNG_TAB_TIP_LIST_INTRO_TEXT'));
        ?>
        <?php
        echo LayoutHelper::render(
            'form.list_intro_tab',
            [
                'form' => $this->form,
            ],
            $componentLayoutBase
        );
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab1', $viewTabLabel('fa-solid fa-list-check', 'COM_CONTENTBUILDERNG_LIST_STATES', 'COM_CONTENTBUILDERNG_TAB_TIP_LIST_STATES'));
        ?>
        <?php
        echo LayoutHelper::render(
            'form.list_states',
            [
                'item' => $this->item,
                'renderCheckbox' => $renderCheckbox,
                'listStatesActionPlugins' => $this->list_states_action_plugins,
            ],
            $componentLayoutBase
        );
        ?>
        <?php
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab3', $viewTabLabel('fa-regular fa-id-card', 'COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY', 'COM_CONTENTBUILDERNG_TAB_TIP_DETAILS_TEMPLATE'));

        ?>
        <?php
        echo LayoutHelper::render(
            'form.details_display',
            [
                'item' => $this->item,
                'form' => $this->form,
                'renderCheckbox' => $renderCheckbox,
                'editablePrepareSnippetOptions' => $editablePrepareSnippetOptions,
                'prepareEffectOptions' => $prepareEffectOptions,
            ],
            $componentLayoutBase
        );
        ?>
        <?php
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab5', $viewTabLabel('fa-regular fa-pen-to-square', 'COM_CONTENTBUILDERNG_TAB_EDIT_DISPLAY', 'COM_CONTENTBUILDERNG_TAB_TIP_EDITABLE_TEMPLATE'));
        ?>
        <?php
        echo LayoutHelper::render(
            'form.edit_display',
            [
                'item' => $this->item,
                'form' => $this->form,
                'renderCheckbox' => $renderCheckbox,
                'canEditByType' => $canEditByType,
                'isBreezingFormsType' => $isBreezingFormsType,
                'breezingFormsProvidedMessage' => $breezingFormsProvidedMessage,
                'breezingFormsEditableToken' => $breezingFormsEditableToken,
                'editablePrepareSnippetOptions' => $editablePrepareSnippetOptions,
                'prepareEffectOptions' => $prepareEffectOptions,
            ],
            $componentLayoutBase
        );
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab6', $viewTabLabel('fa-solid fa-plug', 'COM_CONTENTBUILDERNG_API_TAB_TITLE', 'COM_CONTENTBUILDERNG_TAB_TIP_API'));
        echo LayoutHelper::render(
            'form.api_tab',
            [
                'apiExampleDetailUrl' => $apiExampleDetailUrl,
                'apiExampleListUrl' => $apiExampleListUrl,
                'apiExampleUpdateUrl' => $apiExampleUpdateUrl,
                'apiExampleStatsUrl' => $apiExampleStatsUrl,
                'apiExampleVerboseUrl' => $apiExampleVerboseUrl,
                'apiExampleDetailDisplayUrl' => $apiExampleDetailDisplayUrl,
                'apiExampleListDisplayUrl' => $apiExampleListDisplayUrl,
                'apiExampleStatsDisplayUrl' => $apiExampleStatsDisplayUrl,
                'apiExampleVerboseDisplayUrl' => $apiExampleVerboseDisplayUrl,
                'apiExamplePayloadJson' => $apiExamplePayloadJson,
            ],
            $componentLayoutBase
        );
        ?>
        <?php
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab7', $viewTabLabel('fa-regular fa-envelope', 'COM_CONTENTBUILDERNG_EMAIL_TEMPLATES', 'COM_CONTENTBUILDERNG_TAB_TIP_EMAIL_TEMPLATES'));
        ?>
        <?php
        echo LayoutHelper::render(
            'form.email_tab',
            [
                'item' => $this->item,
                'form' => $this->form,
                'session' => $session,
                'renderCheckbox' => $renderCheckbox,
                'breezingFormsProvidedMessage' => $breezingFormsProvidedMessage,
            ],
            $componentLayoutBase
        );

        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab8', $viewTabLabel('fa-solid fa-shield-halved', 'COM_CONTENTBUILDERNG_PERMISSIONS', 'COM_CONTENTBUILDERNG_TAB_TIP_PERMISSIONS'));
        ?>
        <?php
        echo LayoutHelper::render(
            'form.permissions_tab',
            [
                'item' => $this->item,
                'session' => $session,
                'gmap' => $this->gmap,
                'elements' => $this->elements,
                'verificationPlugins' => $this->verification_plugins,
                'permissionColumns' => $permissionColumns,
                'defaultCheckedForNewPermissions' => $defaultCheckedForNewPermissions,
                'renderCheckbox' => $renderCheckbox,
                'permHeaderLabel' => $permHeaderLabel,
                'permGroupLabel' => $permGroupLabel,
            ],
            $componentLayoutBase
        );

        echo HTMLHelper::_('uitab.endTab');     // ferme tab8 (Permissions)
        echo HTMLHelper::_('uitab.endTabSet');  // ferme view-pane
        ?>

    </div>

    <div class="clr"></div>

    <input type="hidden" name="option" value="com_contentbuilderng" />
    <input type="hidden" name="view" value="form" />
    <input type="hidden" name="layout" value="edit" />
    <input type="hidden" name="id" value="<?php echo (int) $this->item->id; ?>" />
    <input type="hidden" name="jform[id]" value="<?php echo (int) $this->item->id; ?>" />
    <input type="hidden" name="task" value="form.display" />
    <input type="hidden" name="limitstart" value="<?php echo (int) Factory::getApplication()->getInput()->getInt('limitstart', 0); ?>" />
    <input type="hidden" name="jform[ordering]" value="<?php echo $this->item->ordering; ?>" />
    <input type="hidden" name="jform[published]" value="<?php echo $this->item->published; ?>" />
    <input type="hidden" name="filter_order" value="<?php echo htmlspecialchars($listOrder, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="filter_order_Dir" value="<?php echo htmlspecialchars($listDirn, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="list[ordering]" value="<?php echo htmlspecialchars($listOrder, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="list[direction]" value="<?php echo htmlspecialchars($listDirn, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" id="list_fullordering" name="list[fullordering]" value="<?php echo htmlspecialchars($fullOrdering, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="hidemainmenu" value="0" />
    <input type="hidden" name="tabStartOffset" value="tab0" />
    <input type="hidden" name="slideStartOffset"
        value="<?php echo htmlspecialchars((string) $session->get('slideStartOffset', 'permtab1', 'com_contentbuilderng'), ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="jform[email_users]"
        value="<?php echo $session->get('email_users', 'none', 'com_contentbuilderng'); ?>" />
    <input type="hidden" name="jform[email_admins]"
        value="<?php echo $session->get('email_admins', '', 'com_contentbuilderng'); ?>" />

    <?php echo HTMLHelper::_('form.token'); ?>

</form>
<?php
$textTypeModalParams = [
    'title' => Text::_('COM_CONTENTBUILDERNG_EDIT'),
    'url' => '#',
    'height' => '100%',
    'width' => '100%',
    'bodyHeight' => 80,
    'modalWidth' => 90,
];
echo HTMLHelper::_('bootstrap.renderModal', 'text-type-modal', $textTypeModalParams);

$editModalParams = [
    'title' => Text::_('COM_CONTENTBUILDERNG_EDIT'),
    'url' => '#',
    'height' => '400',
    'width' => '800',
    'bodyHeight' => 60,
    'modalWidth' => 80,
];
echo HTMLHelper::_('bootstrap.renderModal', 'edit-modal', $editModalParams);

$wa = $app->getDocument()->getWebAssetManager();
$wa->useScript('jquery');
//$wa->useScript('bootstrap.tab');

$viewTabTooltips = [
    'tab0' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_VIEW'),
    'tab9' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_ADVANCED_OPTIONS'),
    'tab10' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_ARTICLE'),
    'tab2' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_LIST_INTRO_TEXT'),
    'tab1' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_LIST_STATES'),
    'tab3' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_DETAILS_TEMPLATE') . ' + ' . Text::_('COM_CONTENTBUILDERNG_TAB_TIP_DETAILS_PREPARE'),
    'tab5' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_EDITABLE_TEMPLATE'),
    'tab6' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_API'),
    'tab7' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_EMAIL_TEMPLATES'),
    'tab8' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_PERMISSIONS'),
];

$permTabTooltips = [
    'permtab1' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_PERMISSIONS_FRONTEND'),
    'permtab2' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_PERMISSIONS_USERS'),
];

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
?>
<?php require __DIR__ . '/edit_footer_scripts.php'; ?>
