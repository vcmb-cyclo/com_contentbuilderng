<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
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
use CB\Component\Contentbuilderng\Administrator\Service\ElementSettingsStateService;
use CB\Component\Contentbuilderng\Administrator\Service\FormAuditService;
use CB\Component\Contentbuilderng\Administrator\Service\TextUtilityService;
use CB\Component\Contentbuilderng\Site\Helper\PreviewLinkHelper;


/** @var AdministratorApplication $app */
$app = \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication();
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
        $db = \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getDatabase();
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
$apiExampleFilteredStatsDisplayUrl = $apiExampleStatsDisplayUrl
    . '&filter[field]=NomDuChamp&filter[value]=200%20km*&fields[records]=total';
$apiExampleFilteredStatsUrl = $apiExampleFilteredStatsDisplayUrl . $apiPreviewQuery;
$apiExampleVerboseDisplayUrl = $apiExampleDetailDisplayUrl . '&verbose=1';
$apiExampleVerboseUrl = $apiExampleVerboseDisplayUrl . $apiPreviewQuery;
$apiSparseFieldNames = array_slice(array_keys($apiExampleFields), 0, 2);
$apiSparseFieldList = implode(',', array_map('rawurlencode', $apiSparseFieldNames));
$apiExampleSparseListDisplayUrl = $apiExampleListDisplayUrl . '&fields[items]=record_id,' . $apiSparseFieldList;
$apiExampleSparseDetailDisplayUrl = $apiExampleDetailDisplayUrl . '&fields[fields]=' . $apiSparseFieldList;
$apiExampleSparseStatsDisplayUrl = $apiExampleStatsDisplayUrl . '&fields[records]=total,published';
$apiExampleSparseListUrl = $apiExampleSparseListDisplayUrl . $apiPreviewQuery;
$apiExampleSparseDetailUrl = $apiExampleSparseDetailDisplayUrl . $apiPreviewQuery;
$apiExampleSparseStatsUrl = $apiExampleSparseStatsDisplayUrl . $apiPreviewQuery;
$isBreezingFormsType = in_array(
    (string) ($this->item->type ?? ''),
    ['com_breezingformsng'],
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

$viewTabLabel = static function (
    string $iconClass,
    string $labelKey,
    ?string $tipKey = null,
    string $badgesHtml = '',
    bool $tipIsTranslated = false
): string {
    $label = '<span class="' . htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></span> '
        . htmlspecialchars(Text::_($labelKey), ENT_QUOTES, 'UTF-8')
        . $badgesHtml;

    if ($tipKey === null) {
        return $label;
    }

    $tip = $tipIsTranslated ? $tipKey : Text::_($tipKey);

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

$sourceEditableTypes = [];
if (is_object($this->item->form ?? null) && method_exists($this->item->form, 'getEditableElementTypes')) {
    try {
        $sourceEditableTypes = (array) $this->item->form->getEditableElementTypes();
    } catch (\Throwable) {
        $sourceEditableTypes = [];
    }
}
$isModifiedElementSettings = static function ($row) use ($sourceEditableTypes): bool {

    $referenceId = (string) ($row->reference_id ?? '');
    $sourceType = (string) ($sourceEditableTypes[$referenceId] ?? 'text');
    return ElementSettingsStateService::isModified($row, $sourceType);
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
    'com_breezingformsng' => 'BreezingForms',
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
        $debugModeEnabled = !empty($this->item->debug_mode);
        $frontendPermissionConfig = is_array($this->item->config ?? null) ? $this->item->config : [];
        $hasFrontendPermission = static function (string $action) use ($frontendPermissionConfig): bool {
            if (!empty($frontendPermissionConfig['own_fe'][$action])) {
                return true;
            }

            foreach ((array) ($frontendPermissionConfig['permissions_fe'] ?? []) as $groupPermissions) {
                if (is_array($groupPermissions) && !empty($groupPermissions[$action])) {
                    return true;
                }
            }

            return false;
        };
        $hasPublishedDetailElement = false;
        $hasPublishedEditableElement = false;
        $hasPublishedLinkableElement = false;
        foreach ((array) ($this->all_elements ?? []) as $viewElement) {
            if (empty($viewElement->published)) {
                continue;
            }

            if (!empty($viewElement->detail_include)) {
                $hasPublishedDetailElement = true;
            }
            if (!empty($viewElement->editable)) {
                $hasPublishedEditableElement = true;
            }
            if (!empty($viewElement->linkable)) {
                $hasPublishedLinkableElement = true;
            }
        }
        $detailsTemplateRequired = $hasPublishedDetailElement && $hasFrontendPermission('view');
        // This tab describes modification only; creation keeps its own access.
        $detailsEntryPointEnabled = $detailsTemplateRequired && $hasPublishedLinkableElement;
        $editableTemplateRequired = $hasFrontendPermission('edit')
            && (!empty($this->item->edit_button) || $detailsEntryPointEnabled);
        $editableEntryPointEnabled = $hasPublishedEditableElement && $editableTemplateRequired;
        // At-a-glance state of the two template tabs. An empty template is only
        // surfaced when frontend permissions make the corresponding screen useful.
        $templateAuditReferences = [
            'details' => [
                'CBNG-AUDIT-TEMPLATES-EMPTY',
                'CBNG-AUDIT-DETAILS-TEMPLATE-EMPTY',
                'CBNG-AUDIT-FIELD-MISSING-IN-DETAILS',
                'CBNG-AUDIT-SYSTEM-FIELD-MISSING-IN-DETAILS',
                'CBNG-AUDIT-UNKNOWN-MARKER-DETAILS',
            ],
            'edit' => [
                'CBNG-AUDIT-TEMPLATES-EMPTY',
                'CBNG-AUDIT-EDITABLE-TEMPLATE-EMPTY',
                'CBNG-AUDIT-FIELD-MISSING-IN-EDIT',
                'CBNG-AUDIT-SYSTEM-FIELD-MISSING-IN-EDIT',
                'CBNG-AUDIT-UNKNOWN-MARKER-EDIT',
                'CBNG-AUDIT-EDITABLE-FIELD-WITHOUT-ITEM',
                'CBNG-AUDIT-NONEDITABLE-FIELD-WITH-ITEM',
            ],
        ];
        $templateAuditChecks = array_values(array_filter(
            (array) ($this->audit['checks'] ?? []),
            static fn($check): bool => is_array($check)
                && in_array((string) ($check['status'] ?? ''), [FormAuditService::STATUS_WARNING, FormAuditService::STATUS_ERROR], true)
        ));
        $hasTemplateAuditIssue = static function (array $references) use ($templateAuditChecks): bool {
            foreach ($templateAuditChecks as $check) {
                if (in_array((string) ($check['reference'] ?? ''), $references, true)) {
                    return true;
                }
            }

            return false;
        };
        $templateTabState = static function (
            bool $filled,
            bool $hasAuditIssue,
            bool $required,
            bool $entryPointEnabled,
            bool $locked = false
        ): array {
            if ($hasAuditIssue || ($required && !$filled)) {
                $stateClass = 'cb-template-state is-inconsistent';
                $stateTipKey = 'COM_CONTENTBUILDERNG_TAB_TEMPLATE_STATUS_INVALID';
            } elseif ($entryPointEnabled && !$hasAuditIssue) {
                $stateClass = 'cb-template-state is-filled';
                $stateTipKey = 'COM_CONTENTBUILDERNG_TAB_TEMPLATE_STATUS_ACTIVE';
            } elseif (!$required && !$entryPointEnabled) {
                $badge = '';
                if ($locked) {
                    $lockTip = Text::_('COM_CONTENTBUILDERNG_TAB_TEMPLATE_LOCKED');
                    $badge = ' <span class="cb-template-state is-locked ms-1" aria-hidden="true" title="'
                        . htmlspecialchars($lockTip, ENT_QUOTES, 'UTF-8') . '"></span>'
                        . '<span class="visually-hidden">' . htmlspecialchars($lockTip, ENT_QUOTES, 'UTF-8') . '</span>';
                }

                return [
                    'badge' => $badge,
                    'tipKey' => 'COM_CONTENTBUILDERNG_TAB_TEMPLATE_STATUS_INACTIVE_EMPTY',
                    'locked' => $locked,
                ];
            } elseif ($filled || $required) {
                $stateClass = 'cb-template-state is-incomplete';
                $stateTipKey = 'COM_CONTENTBUILDERNG_TAB_TEMPLATE_STATUS_INCOMPLETE';
            } else {
                $badge = '';
                if ($locked) {
                    $lockTip = Text::_('COM_CONTENTBUILDERNG_TAB_TEMPLATE_LOCKED');
                    $badge = ' <span class="cb-template-state is-locked ms-1" aria-hidden="true" title="'
                        . htmlspecialchars($lockTip, ENT_QUOTES, 'UTF-8') . '"></span>'
                        . '<span class="visually-hidden">' . htmlspecialchars($lockTip, ENT_QUOTES, 'UTF-8') . '</span>';
                }

                return [
                    'badge' => $badge,
                    'tipKey' => 'COM_CONTENTBUILDERNG_TAB_TEMPLATE_STATUS_INACTIVE_EMPTY',
                    'locked' => $locked,
                ];
            }

            $stateTip = Text::_($stateTipKey);
            if ($locked) {
                $lockTip = Text::_('COM_CONTENTBUILDERNG_TAB_TEMPLATE_LOCKED');
                $badge = ' <span class="cb-template-lock-stack ms-1" aria-hidden="true">'
                    . '<span class="' . $stateClass . ' cb-template-state-inline" title="'
                    . htmlspecialchars($stateTip, ENT_QUOTES, 'UTF-8') . '"></span>'
                    . '<span class="cb-template-lock-inline" title="'
                    . htmlspecialchars($lockTip, ENT_QUOTES, 'UTF-8') . '"></span></span>'
                    . '<span class="visually-hidden">' . htmlspecialchars($stateTip, ENT_QUOTES, 'UTF-8') . '</span>'
                    . '<span class="visually-hidden">' . htmlspecialchars($lockTip, ENT_QUOTES, 'UTF-8') . '</span>';
            } else {
                $badge = ' <span class="' . $stateClass
                    . ' ms-1" aria-hidden="true" title="' . htmlspecialchars($stateTip, ENT_QUOTES, 'UTF-8') . '"></span>'
                    . '<span class="visually-hidden">' . htmlspecialchars($stateTip, ENT_QUOTES, 'UTF-8') . '</span>';
            }

            return ['badge' => $badge, 'tipKey' => $stateTipKey, 'locked' => $locked];
        };
        $neutralTabBadge = static function (bool $hasContent): string {
            return $hasContent
                ? ' <span class="cb-template-state is-neutral ms-1" aria-hidden="true"></span>'
                : '';
        };
        $detailsTemplateState = $templateTabState(
            trim((string) ($this->item->details_template ?? '')) !== '',
            $hasTemplateAuditIssue($templateAuditReferences['details']),
            $detailsTemplateRequired,
            $detailsEntryPointEnabled,
            !empty($this->item->details_template_locked)
        );
        $editableTemplateState = $templateTabState(
            trim((string) ($this->item->editable_template ?? '')) !== '',
            $editableTemplateRequired && $hasTemplateAuditIssue($templateAuditReferences['edit']),
            $editableTemplateRequired,
            $editableEntryPointEnabled,
            !empty($this->item->editable_template_locked)
        );
        $buildTemplateTabTip = static function (string $baseKey, array $state): string {
            $parts = [Text::_($baseKey), Text::_((string) $state['tipKey'])];
            if (!empty($state['locked'])) {
                $parts[] = Text::_('COM_CONTENTBUILDERNG_TAB_TEMPLATE_LOCKED_EXPLANATION');
            }
            return implode(' ', $parts);
        };
        $detailsTemplateBadge = (string) $detailsTemplateState['badge'];
        $editableTemplateBadge = (string) $editableTemplateState['badge'];
        $detailsTemplateTabTip = $buildTemplateTabTip('COM_CONTENTBUILDERNG_TAB_TIP_DETAILS_TEMPLATE', $detailsTemplateState);
        $editableTemplateTabTip = $buildTemplateTabTip('COM_CONTENTBUILDERNG_TAB_TIP_EDITABLE_TEMPLATE', $editableTemplateState);
        $emailTemplateBadge = $neutralTabBadge(
            trim((string) ($this->item->email_admin_template ?? '')) !== ''
                || trim((string) ($this->item->email_template ?? '')) !== ''
        );
        $configuredListStates = (array) ($this->item->list_states ?? []);
        if ($configuredListStates === [] && $this->form) {
            $configuredListStates = (array) $this->form->getValue('list_states');
        }
        $hasPublishedListState = false;
        foreach ($configuredListStates as $configuredListState) {
            $published = is_array($configuredListState)
                ? ($configuredListState['published'] ?? 0)
                : ($configuredListState->published ?? 0);

            if ((int) $published === 1) {
                $hasPublishedListState = true;
                break;
            }
        }
        $showsListStates = !empty($this->item->list_state);
        $showsStateFilter = !empty($this->item->show_state_filter);
        $hasListStatePermission = $hasFrontendPermission('state');
        $listStatesBadge = '';
        $listStatesTabTipKey = 'COM_CONTENTBUILDERNG_TAB_TIP_LIST_STATES';

        if ($hasPublishedListState || $showsListStates || $showsStateFilter || $hasListStatePermission) {
            if ($hasPublishedListState) {
                $listStatesBadgeClass = 'cb-template-state is-filled';
                $listStatesBadgeTip = Text::_('COM_CONTENTBUILDERNG_LIST_STATES_BADGE_COHERENT');
                $listStatesTabTipKey = 'COM_CONTENTBUILDERNG_TAB_TIP_LIST_STATES_GREEN';
            } elseif ($showsListStates || $hasListStatePermission) {
                if ($showsListStates && $hasListStatePermission) {
                    $listStatesBadgeClass = 'cb-template-state is-inconsistent';
                    $listStatesTabTipKey = 'COM_CONTENTBUILDERNG_TAB_TIP_LIST_STATES_RED_NO_PUBLISHED_FULL';
                } elseif ($showsListStates) {
                    $listStatesBadgeClass = 'cb-template-state is-inconsistent';
                    $listStatesTabTipKey = 'COM_CONTENTBUILDERNG_TAB_TIP_LIST_STATES_RED_NO_PUBLISHED_DISPLAY';
                } else {
                    $listStatesBadgeClass = 'cb-template-state is-inconsistent';
                    $listStatesTabTipKey = 'COM_CONTENTBUILDERNG_TAB_TIP_LIST_STATES_RED_NO_PUBLISHED_PERMISSION';
                }
                $listStatesBadgeTip = Text::_($listStatesTabTipKey);
            } else {
                $listStatesBadgeClass = 'cb-template-state is-incomplete';
                $listStatesTabTipKey = 'COM_CONTENTBUILDERNG_TAB_TIP_LIST_STATES_ORANGE_FILTER_NO_STATE';
                $listStatesBadgeTip = Text::_($listStatesTabTipKey);
            }

            $listStatesBadge = ' <span class="' . $listStatesBadgeClass
                . ' ms-1" aria-hidden="true" title="' . htmlspecialchars($listStatesBadgeTip, ENT_QUOTES, 'UTF-8') . '"></span>'
                . '<span class="visually-hidden">' . htmlspecialchars($listStatesBadgeTip, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        $hasListIntro = trim((string) ($this->item->intro_text ?? '')) !== '';
        if (!$hasListIntro && $this->form) {
            $hasListIntro = trim((string) $this->form->getValue('intro_text')) !== '';
        }
        $listIntroBadge = $hasListIntro
            ? ' <span class="cb-template-state is-filled ms-1" aria-hidden="true"></span>'
            : '';
        $listIntroTabTipKey = $hasListIntro
            ? 'COM_CONTENTBUILDERNG_TAB_TIP_LIST_INTRO_ACTIVE'
            : 'COM_CONTENTBUILDERNG_TAB_TIP_LIST_INTRO_EMPTY';
        if ($formId > 0 && $debugModeEnabled) {
            $allowedViewTabs[] = 'tab12';
            $allowedViewTabs[] = 'tab13';
            $allowedViewTabs[] = 'tab14';
        }
        if ($debugModeEnabled) {
            $allowedViewTabs[] = 'tab11';
        }
        if (!in_array($activeViewTab, $allowedViewTabs, true)) {
            $activeViewTab = 'tab0';
        }
        echo HTMLHelper::_('uitab.startTabSet', 'view-pane', ['active' => $activeViewTab]);
        ?>
        <div id="cb-form-tab-actions" class="cb-form-tab-actions" aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_VIEW'), ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($formId > 0) : ?>
                <div class="d-inline-flex align-items-center gap-2">
                    <?php
                    $publishedToggleHtml = HTMLHelper::_(
                        'jgrid.published',
                        !empty($this->item->published) ? 1 : 0,
                        0,
                        'form.form',
                        true,
                        'cbformstate'
                    );
                    $publishedTooltip = htmlspecialchars(
                        Text::_(!empty($this->item->published) ? 'COM_CONTENTBUILDERNG_PUBLISHED' : 'COM_CONTENTBUILDERNG_UNPUBLISHED'),
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    $publishedToggleHtml = preg_replace('/\saria-labelledby="[^"]*"/', '', (string) $publishedToggleHtml) ?? (string) $publishedToggleHtml;
                    $publishedToggleHtml = preg_replace('#<div role="tooltip"[^>]*>.*?</div>#s', '', (string) $publishedToggleHtml) ?? (string) $publishedToggleHtml;
                    $publishedToggleHtml = preg_replace('/<a\b/', '<a data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="' . $publishedTooltip . '" title="' . $publishedTooltip . '"', (string) $publishedToggleHtml, 1) ?? (string) $publishedToggleHtml;
                    echo $publishedToggleHtml;
                    ?>
                    <input type="checkbox" name="cid[]" id="cbformstate0" value="<?php echo $formId; ?>" style="display:none" />
                </div>
            <?php endif; ?>
            <div class="d-inline-flex align-items-center gap-2">
                <?php if ($formId > 0) : ?>
                    <?php
                    $debugEnabled = !empty($this->item->debug_mode);
                    $debugStates = [
                        1 => ['debug_off', 'COM_CONTENTBUILDERNG_DEBUG_MODE', '', '', false, 'publish', 'publish'],
                        0 => ['debug_on',  'COM_CONTENTBUILDERNG_DEBUG_MODE', '', '', false, 'unpublish', 'unpublish'],
                    ];
                    $debugToggleHtml = HTMLHelper::_('jgrid.state', $debugStates, $debugEnabled ? 1 : 0, 0, 'form.', true, true, 'cbdebugstate');
                    $debugToggleHtml = preg_replace('/\saria-labelledby="[^"]*"/', '', (string) $debugToggleHtml) ?? (string) $debugToggleHtml;
                    $debugToggleHtml = preg_replace('#<div role="tooltip"[^>]*>.*?</div>#s', '', (string) $debugToggleHtml) ?? (string) $debugToggleHtml;
                    $debugTooltip = htmlspecialchars(
                        Text::_($debugEnabled ? 'COM_CONTENTBUILDERNG_DEBUG_MODE_ENABLED' : 'COM_CONTENTBUILDERNG_DEBUG_MODE_DISABLED'),
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    $debugToggleHtml = preg_replace('/<a\b/', '<a data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="' . $debugTooltip . '" title="' . $debugTooltip . '"', (string) $debugToggleHtml, 1) ?? (string) $debugToggleHtml;
                    $debugToggleHtml = str_replace('icon-unpublish', 'fa-solid fa-bug text-muted', (string) $debugToggleHtml);
                    if ($debugEnabled) {
                        // Active debug mode is a state the admin must not miss: render it as a
                        // filled danger pill, matching the frontend debug badge, instead of a
                        // tinted icon that reads the same as the inactive one at a glance.
                        $debugToggleHtml = str_replace('icon-publish', 'fa-solid fa-bug', $debugToggleHtml);
                        $debugToggleHtml = preg_replace(
                            '/class="([^"]*\btbody-icon\b[^"]*)"/',
                            'class="$1 cb-debug-toggle is-active"',
                            $debugToggleHtml,
                            1
                        ) ?? $debugToggleHtml;
                    }
                    echo $debugToggleHtml;
                    ?>
                    <input type="checkbox" name="cid[]" id="cbdebugstate0" value="<?php echo $formId; ?>" style="display:none" />
                <?php else : ?>
                    <input type="hidden" name="jform[debug_mode]" id="debug_mode" value="0" />
                    <a href="#"
                       class="tbody-icon"
                       data-bs-toggle="tooltip"
                       data-bs-placement="bottom"
                       data-bs-title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_MODE_DISABLED'); ?>"
                       title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_MODE_DISABLED'); ?>"
                       onclick="this.classList.toggle('active'); document.getElementById('debug_mode').value = this.classList.contains('active') ? '1' : '0'; return false;">
                        <span class="fa-solid fa-bug text-muted" aria-hidden="true"></span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
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

                        $debugElementNames = [];
                        if ($debugModeEnabled) {
                            $debugSourceForm = FormSourceFactory::getForm(
                                (string) ($this->item->type ?? ''),
                                (int) ($this->item->reference_id ?? 0)
                            );
                            if (is_object($debugSourceForm) && method_exists($debugSourceForm, 'getElementNames')) {
                                $debugElementNames = (array) $debugSourceForm->getElementNames();
                            }
                        }

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
                                'debugModeEnabled' => $debugModeEnabled,
                                'debugElementNames' => $debugElementNames,
                            ],
                            $componentLayoutBase
                        );

                        $allBfSystemFields = [];
                        // System fields are added to a saved form only: a new one has no id for them yet.
                        if (
                            $isBreezingFormsType
                            && (int) ($this->item->id ?? 0) > 0
                            && is_object($this->item->form ?? null)
                            && method_exists($this->item->form, 'getSystemFieldDefinitions')
                        ) {
                            // Build map: reference_id => element_id (for already-added system fields)
                            $addedByRef = [];
                            foreach ((array) ($this->all_elements ?? []) as $elementRow) {
                                $refId = (int) ($elementRow->reference_id ?? 0);
                                if ($refId < 0) {
                                    $addedByRef[$refId] = (int) ($elementRow->id ?? 0);
                                }
                            }

                            foreach ($this->item->form::getSystemFieldDefinitions() as $systemReferenceId => $systemDefinition) {
                                $refId = (int) $systemReferenceId;
                                $allBfSystemFields[$refId] = [
                                    'label'       => (string) ($systemDefinition['label'] ?? $systemReferenceId),
                                    'name'        => (string) ($systemDefinition['name'] ?? ''),
                                    'type'        => (string) ($systemDefinition['type'] ?? ''),
                                    'description' => (string) ($systemDefinition['description'] ?? ''),
                                    'added'       => isset($addedByRef[$refId]),
                                    'element_id'  => $addedByRef[$refId] ?? 0,
                                ];
                            }

                            uasort($allBfSystemFields, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
                        }
                        ?>
        <?php
        echo LayoutHelper::render(
            'form.view_tab',
            [
                'item'              => $this->item,
                'themePlugins'      => $this->theme_plugins,
                'formatTypeDisplay' => $formatTypeDisplay,
                'elementsTableHtml' => $elementsTableHtml,
                'allBfSystemFields' => $allBfSystemFields,
                'isBreezingFormsType' => $isBreezingFormsType,
            ],
            $componentLayoutBase
        );
        ?>

        <?php
        echo HTMLHelper::_('uitab.endTab');
        $auditChecks = array_values(array_filter(
            (array) ($this->audit['checks'] ?? []),
            static fn($check): bool => is_array($check)
        ));
        $auditErrorCount = count(array_filter(
            $auditChecks,
            static fn(array $check): bool => (string) ($check['status'] ?? '') === FormAuditService::STATUS_ERROR
        ));
        $auditWarningCount = count(array_filter(
            $auditChecks,
            static fn(array $check): bool => (string) ($check['status'] ?? '') === FormAuditService::STATUS_WARNING
        ));
        $auditTabBadges = '';
        if ($auditErrorCount > 0 || $auditWarningCount > 0) {
            $auditBadgeLabels = [];
            if ($auditErrorCount > 0) {
                $auditBadgeLabels[] = Text::plural('COM_CONTENTBUILDERNG_AUDIT_N_ERRORS', $auditErrorCount);
            }
            if ($auditWarningCount > 0) {
                $auditBadgeLabels[] = Text::plural('COM_CONTENTBUILDERNG_AUDIT_N_WARNINGS', $auditWarningCount);
            }
            $auditBadgeLabel = implode(' · ', $auditBadgeLabels);
            $auditBadgeClass = $auditErrorCount > 0 ? 'cb-audit-state-dot is-error' : 'cb-audit-state-dot is-warning';
            $auditTabBadges = ' <span class="' . $auditBadgeClass . ' ms-1" aria-hidden="true" title="'
                . htmlspecialchars($auditBadgeLabel, ENT_QUOTES, 'UTF-8')
                . '"></span><span class="visually-hidden">'
                . htmlspecialchars($auditBadgeLabel, ENT_QUOTES, 'UTF-8')
                . '</span>';
        }
        if ($formId > 0 && $debugModeEnabled) :
            echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab12', $viewTabLabel('fa-solid fa-stethoscope', 'COM_CONTENTBUILDERNG_AUDIT_TRAIL', 'COM_CONTENTBUILDERNG_TAB_TIP_AUDIT_TRAIL', $auditTabBadges));
            echo LayoutHelper::render(
                'form.audit_tab',
                ['audit' => $this->audit],
                $componentLayoutBase
            );
            echo HTMLHelper::_('uitab.endTab');
            echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab13', $viewTabLabel('fa-solid fa-gauge-high', 'COM_CONTENTBUILDERNG_TAB_PERFORMANCE', 'COM_CONTENTBUILDERNG_TAB_TIP_PERFORMANCE'));
            echo LayoutHelper::render(
                'form.performance_tab',
                ['audit' => $this->audit],
                $componentLayoutBase
            );
            echo HTMLHelper::_('uitab.endTab');
            echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab14', $viewTabLabel('fa-solid fa-database', 'COM_CONTENTBUILDERNG_TAB_DATA', 'COM_CONTENTBUILDERNG_TAB_TIP_DATA'));
            echo LayoutHelper::render(
                'form.data_tab',
                ['audit' => $this->audit],
                $componentLayoutBase
            );
            echo HTMLHelper::_('uitab.endTab');
        endif;
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab9', $viewTabLabel('fa-solid fa-sliders', 'COM_CONTENTBUILDERNG_ADVANCED_OPTIONS', 'COM_CONTENTBUILDERNG_TAB_TIP_ADVANCED_OPTIONS'));
        echo $advancedOptionsContent;
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab2', $viewTabLabel('fa-regular fa-file-lines', 'COM_CONTENTBUILDERNG_LIST_INTRO_TEXT', $listIntroTabTipKey, $listIntroBadge));
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
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab1', $viewTabLabel('fa-solid fa-list-check', 'COM_CONTENTBUILDERNG_LIST_STATES', $listStatesTabTipKey, $listStatesBadge));
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
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab3', $viewTabLabel('fa-regular fa-id-card', 'COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY', $detailsTemplateTabTip, $detailsTemplateBadge, true));

        ?>
        <?php
        echo LayoutHelper::render(
            'form.details_display',
            [
                'item' => $this->item,
                'form' => $this->form,
                'templateAuditChecks' => $templateAuditChecks,
                'templateAuditReferences' => $templateAuditReferences['details'],
                'renderCheckbox' => $renderCheckbox,
                'editablePrepareSnippetOptions' => $editablePrepareSnippetOptions,
                'prepareEffectOptions' => $prepareEffectOptions,
            ],
            $componentLayoutBase
        );
        ?>
        <?php
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab5', $viewTabLabel('fa-regular fa-pen-to-square', 'COM_CONTENTBUILDERNG_TAB_EDIT_DISPLAY', $editableTemplateTabTip, $editableTemplateBadge, true));
        ?>
        <?php
        echo LayoutHelper::render(
            'form.edit_display',
            [
                'item' => $this->item,
                'form' => $this->form,
                'templateAuditChecks' => $templateAuditChecks,
                'templateAuditReferences' => $templateAuditReferences['edit'],
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
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab6', $viewTabLabel('fa-solid fa-plug', 'COM_CONTENTBUILDERNG_API_TAB_TITLE', 'COM_CONTENTBUILDERNG_TAB_TIP_API'));
        echo LayoutHelper::render(
            'form.api_tab',
            [
                'apiExampleDetailUrl' => $apiExampleDetailUrl,
                'apiExampleListUrl' => $apiExampleListUrl,
                'apiExampleUpdateUrl' => $apiExampleUpdateUrl,
                'apiExampleStatsUrl' => $apiExampleStatsUrl,
                'apiExampleFilteredStatsUrl' => $apiExampleFilteredStatsUrl,
                'apiExampleVerboseUrl' => $apiExampleVerboseUrl,
                'apiExampleDetailDisplayUrl' => $apiExampleDetailDisplayUrl,
                'apiExampleListDisplayUrl' => $apiExampleListDisplayUrl,
                'apiExampleStatsDisplayUrl' => $apiExampleStatsDisplayUrl,
                'apiExampleFilteredStatsDisplayUrl' => $apiExampleFilteredStatsDisplayUrl,
                'apiExampleVerboseDisplayUrl' => $apiExampleVerboseDisplayUrl,
                'apiExampleSparseListUrl' => $apiExampleSparseListUrl,
                'apiExampleSparseDetailUrl' => $apiExampleSparseDetailUrl,
                'apiExampleSparseStatsUrl' => $apiExampleSparseStatsUrl,
                'apiExampleSparseListDisplayUrl' => $apiExampleSparseListDisplayUrl,
                'apiExampleSparseDetailDisplayUrl' => $apiExampleSparseDetailDisplayUrl,
                'apiExampleSparseStatsDisplayUrl' => $apiExampleSparseStatsDisplayUrl,
                'apiExamplePayloadJson' => $apiExamplePayloadJson,
                'formId' => $formId,
            ],
            $componentLayoutBase
        );
        ?>
        <?php
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab7', $viewTabLabel('fa-regular fa-envelope', 'COM_CONTENTBUILDERNG_EMAIL_TEMPLATES', 'COM_CONTENTBUILDERNG_TAB_TIP_EMAIL_TEMPLATES', $emailTemplateBadge));
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
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab11', $viewTabLabel('fa-solid fa-bug', 'COM_CONTENTBUILDERNG_TAB_DEBUG', 'COM_CONTENTBUILDERNG_TAB_DEBUG_TIP'));
        echo LayoutHelper::render(
            'form.debug_tab',
            [
                'item' => $this->item,
                'renderCheckbox' => $renderCheckbox,
            ],
            $componentLayoutBase
        );
        echo HTMLHelper::_('uitab.endTab');
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
    <?php if ($this->wizardReturnUrl !== '') : ?>
        <input type="hidden" name="return" value="<?php echo htmlspecialchars($this->wizardReturnUrl, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="wizard" value="1" />
    <?php endif; ?>
    <input type="hidden" name="limitstart" value="<?php echo (int) \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->getInt('limitstart', 0); ?>" />
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
        value="<?php echo htmlspecialchars((string) $session->get('email_users', 'none', 'com_contentbuilderng'), ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="jform[email_admins]"
        value="<?php echo htmlspecialchars((string) $session->get('email_admins', '', 'com_contentbuilderng'), ENT_QUOTES, 'UTF-8'); ?>" />

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
//$wa->useScript('bootstrap.tab');

$viewTabTooltips = [
    'tab0' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_VIEW'),
    'tab9' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_ADVANCED_OPTIONS'),
    'tab10' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_ARTICLE'),
    'tab2' => Text::_($listIntroTabTipKey),
    'tab1' => Text::_($listStatesTabTipKey),
    'tab3' => $detailsTemplateTabTip,
    'tab5' => $editableTemplateTabTip,
    'tab6' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_API'),
    'tab7' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_EMAIL_TEMPLATES'),
    'tab8' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_PERMISSIONS'),
    'tab12' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_AUDIT_TRAIL'),
    'tab13' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_PERFORMANCE'),
    'tab14' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_DATA'),
];

$permTabTooltips = [
    'permtab1' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_PERMISSIONS_FRONTEND'),
    'permtab2' => Text::_('COM_CONTENTBUILDERNG_TAB_TIP_PERMISSIONS_USERS'),
];

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
?>
<?php require __DIR__ . '/edit_footer_scripts.php'; ?>
