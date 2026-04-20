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
$wa->addInlineStyle(
    '.saveorder.btn{background-color:var(--bs-body-bg);border-color:var(--bs-border-color);color:var(--bs-body-color)}'
        . '.saveorder.btn:hover{background-color:var(--bs-secondary-bg)}'
        . '.cb-display-in-row{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}'
        . '.cb-order-slot{display:inline-block;width:24px;text-align:center}'
        . '.cb-order-placeholder{visibility:hidden}'
        . '.cb-order-input{margin-left:6px}'
        . '.cb-order-head{vertical-align:middle;white-space:nowrap}'
        . '.cb-order-head .saveorder{float:none!important;margin-left:6px}'
        . '.cb-item-label-cell{display:flex;flex-direction:column;gap:4px}'
        . '.cb-item-label-display{cursor:pointer;width:100%;display:block}'
        . '.cb-item-type-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.22rem .5rem;border-radius:999px;border:1px solid transparent;font-size:.72rem;font-weight:700;line-height:1.2}'
        . '.cb-item-type-badge a{text-decoration:none;color:inherit}'
        . '.cb-item-type-badge.is-default{color:var(--bs-secondary-color);background:var(--bs-secondary-bg);border-color:var(--bs-border-color)}'
        . '.cb-item-type-badge.is-modified{color:#842029;background:#f8d7da;border-color:#f1aeb5}'
        . '.cb-item-order-type-select{align-self:flex-start;width:auto!important;max-width:100%}'
        . '.cb-wordwrap-input{width:8ch!important;min-width:8ch!important;max-width:8ch!important;text-align:center}'
        . '.cb-prepare-tools{row-gap:.5rem}'
        . '.cb-prepare-tools .btn{text-wrap:nowrap}'
        . '.cb-prepare-tools .cb-snippet-select{display:inline-block;width:auto;min-width:12ch;max-width:42ch;flex:0 0 auto}'
        . '.cb-prepare-tools .cb-effect-select{min-width:170px;max-width:240px}'
        . '.cb-upload-box{margin:0 0 1rem;padding:.85rem .95rem;border:1px solid var(--bs-border-color);border-radius:12px;background:linear-gradient(180deg,var(--bs-tertiary-bg),var(--bs-body-bg))}'
        . '.cb-save-animate,.cb-save-animate button,.cb-save-animate .btn,button.cb-save-animate,.btn.cb-save-animate{background-color:var(--alert-heading-bg,var(--bs-success,#198754))!important;background-image:none!important;border-color:var(--bs-success,#198754)!important;color:var(--bs-white)!important;filter:brightness(1.2)!important;box-shadow:0 0 0 .38rem rgba(25,135,84,.36)!important;transition:none!important;opacity:1!important}'
        . '.cb-save-animate .fa-check,.cb-save-animate .fa-xmark,.cb-save-animate .fa-xmark-new,.cb-save-animate [class*="icon-"],.cb-save-animate svg{color:var(--bs-white)!important;fill:currentColor!important;stroke:currentColor!important}'
        . '.cb-save-disabled{filter:none!important;opacity:1!important}'
        . '.cb-save-disabled button,.cb-save-disabled .btn{background:linear-gradient(180deg,#fcfcfd,#f4f6f8)!important;border-color:#d8dee4!important;color:#8a949e!important;box-shadow:none!important;text-shadow:none!important}'
        . '.cb-save-disabled button .icon-save,.cb-save-disabled .btn .icon-save,.cb-save-disabled button .fa-check,.cb-save-disabled .btn .fa-check,.cb-save-disabled button [class*="icon-"],.cb-save-disabled .btn [class*="icon-"]{color:#8a949e!important}'
        . '.cb-save-disabled button svg,.cb-save-disabled .btn svg{color:#8a949e!important;fill:currentColor!important;stroke:currentColor!important}'
        . '.cb-save-disabled,[aria-disabled="true"].cb-save-disabled{pointer-events:none!important;cursor:not-allowed!important}'
        . 'joomla-tab#view-pane > div[role="tablist"],joomla-tab#perm-pane > div[role="tablist"]{display:flex;gap:0;flex-wrap:wrap;padding:0!important;margin-bottom:1rem;background:transparent;white-space:normal;border-block-end:var(--joomla-tablist-border-bottom)}'
        . 'joomla-tab#view-pane > div[role="tablist"] > button[role="tab"],joomla-tab#perm-pane > div[role="tablist"] > button[role="tab"]{position:relative;border:0!important;border-radius:0!important;padding:.6rem 1rem!important;font-weight:500;color:var(--bs-secondary-color,#6c757d)!important;background:var(--body-bg)!important;transition:color .16s ease,background-color .16s ease;display:inline-flex;align-items:center;box-shadow:none!important}'
        . 'joomla-tab#view-pane > div[role="tablist"] > button[role="tab"] > span[class*="fa-"],joomla-tab#perm-pane > div[role="tablist"] > button[role="tab"] > span[class*="fa-"]{margin-inline-end:.45rem}'
        . 'joomla-tab#view-pane > div[role="tablist"] > button[role="tab"] + button[role="tab"],joomla-tab#perm-pane > div[role="tablist"] > button[role="tab"] + button[role="tab"]{border-inline-start:1px solid #d7dde5!important}'
        . 'joomla-tab#view-pane > div[role="tablist"] > button[role="tab"]:hover,joomla-tab#view-pane > div[role="tablist"] > button[role="tab"]:focus,joomla-tab#perm-pane > div[role="tablist"] > button[role="tab"]:hover,joomla-tab#perm-pane > div[role="tablist"] > button[role="tab"]:focus{background:var(--body-bg)!important;border-radius:0!important;color:var(--body-color)!important;box-shadow:none!important}'
        . 'joomla-tab#view-pane > div[role="tablist"] > button[role="tab"]:focus-visible,joomla-tab#perm-pane > div[role="tablist"] > button[role="tab"]:focus-visible{outline:2px solid var(--bs-primary);outline-offset:1px}'
        . 'joomla-tab#view-pane > div[role="tablist"] > button[role="tab"][aria-selected="true"],joomla-tab#perm-pane > div[role="tablist"] > button[role="tab"][aria-selected="true"]{font-weight:600;color:var(--joomla-tab-btn-hvr)!important;background:var(--joomla-tab-btn-aria-exp-bg)!important;box-shadow:none!important}'
        . 'joomla-tab#view-pane > div[role="tablist"] > button[role="tab"][aria-selected="true"]::after,joomla-tab#perm-pane > div[role="tablist"] > button[role="tab"][aria-selected="true"]::after{content:"";position:absolute;left:0;right:0;bottom:0;height:3px;border-radius:0;background:var(--btn-primary-bg)}'
        . '.cb-perm-group-label{display:inline-flex;align-items:center;gap:.35rem}'
        . '.cb-perm-group-tree{display:inline-flex;align-items:center;gap:0;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace}'
        . '.cb-perm-group-text{font-family:var(--body-font-family,inherit)}'
        . '.cb-perm-group-branch{color:#8a94a6;line-height:1;display:inline-block}'
        . '.cb-perm-group-branch-guide{width:.85rem;text-align:center}'
        . '.cb-perm-group-branch-node{margin-inline-end:.1rem}'
        . '.cb-perm-inherited{background:#eef1f4}'
        . '.cb-perm-inherited .form-check-input{background-color:#dce2e8;border-color:#b8c2cc}'
        . '.cb-perm-inherited .form-check-input:not(:checked){box-shadow:none}'
        . '.cb-perm-inherited .form-check-input:indeterminate{background-color:#c8d1db!important;border-color:#9eacba!important;background-image:url("data:image/svg+xml,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 20 20%27%3e%3cpath fill=%27none%27 stroke=%27%23ffffff%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%273%27 d=%27M5 10.5l3 3 7-7%27/%3e%3c/svg%3e")!important;background-size:1rem 1rem!important}'
        . '.cb-perm-users-grid{display:grid;grid-template-columns:minmax(280px,340px) minmax(0,1fr);gap:1rem;align-items:start}'
        . '.cb-perm-users-card{border:1px solid var(--bs-border-color);border-radius:10px;background:var(--bs-body-bg);padding:.65rem 1rem .9rem}'
        . '.cb-perm-users-card-wide{grid-column:1 / -1}'
        . '.cb-perm-users-title{display:flex;align-items:center;gap:.45rem;margin:0 0 .9rem;padding-bottom:.6rem;border-bottom:1px solid var(--bs-border-color);font-size:1rem;font-weight:600;color:var(--bs-emphasis-color)}'
        . '.cb-perm-users-title > .fa-solid{font-size:.95rem;color:var(--bs-secondary-color)}'
        . '.cb-perm-users-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.9rem 1rem}'
        . '.cb-perm-users-field{display:flex;flex-direction:column;gap:.38rem;min-width:0;padding:.7rem .8rem;border:1px solid var(--bs-border-color-translucent,rgba(0,0,0,.08));border-radius:8px;background:var(--bs-body-bg)}'
        . '.cb-perm-users-field-wide{grid-column:1 / -1}'
        . '.cb-perm-users-field-grow{min-width:0}'
        . '.cb-perm-users-label{display:flex;align-items:flex-start;min-height:2.2rem;font-size:.82rem;font-weight:600;line-height:1.35;color:var(--bs-secondary-color)}'
        . '.cb-perm-users-field .btn{align-self:flex-start}'
        . '.cb-perm-verify-stack{display:flex;flex-direction:column;gap:.85rem}'
        . '.cb-perm-verify-row{padding:.85rem .9rem 1rem;border:1px solid var(--bs-border-color-translucent,rgba(0,0,0,.08));border-radius:8px;background:var(--bs-body-bg)}'
        . '.cb-perm-verify-row:last-child{padding-bottom:0;border-bottom:0}'
        . '.cb-perm-verify-head{margin-bottom:.6rem}'
        . '.cb-perm-verify-badge{display:inline-flex;align-items:center;padding:0;font-size:.85rem;font-weight:600;color:var(--bs-emphasis-color)}'
        . '.cb-perm-verify-controls{display:grid;grid-template-columns:minmax(220px,260px) 96px minmax(220px,1fr);gap:.75rem;align-items:end}'
        . '.cb-perm-verify-toggle{display:flex;align-items:center;gap:.55rem;min-height:2.375rem;padding:0;border:0;background:transparent}'
        . '.cb-perm-verify-toggle .cb-perm-users-label{margin:0}'
        . '.cb-perm-users-card .form-control,.cb-perm-users-card .form-select{min-height:2.375rem}'
        . '#cb_perm_users_manage{min-height:2.75rem;padding:.55rem 1rem;font-size:1rem;font-weight:600}'
        . '@media (max-width:1199.98px){.cb-perm-users-grid{grid-template-columns:1fr}.cb-perm-verify-controls{grid-template-columns:minmax(220px,260px) 96px minmax(220px,1fr)}}'
        . '@media (max-width:991.98px){.cb-perm-users-fields{grid-template-columns:1fr}.cb-perm-verify-controls{grid-template-columns:1fr}.cb-perm-users-field-wide{grid-column:auto}}'
        . '@media (max-width:991.98px){joomla-tab#view-pane > div[role="tablist"],joomla-tab#perm-pane > div[role="tablist"]{flex-wrap:nowrap;overflow:auto;-webkit-overflow-scrolling:touch}joomla-tab#view-pane > div[role="tablist"] > button[role="tab"],joomla-tab#perm-pane > div[role="tablist"] > button[role="tab"]{white-space:nowrap}}'
);

$listOrder = (string) ($this->listOrder ?? 'ordering');
$listDirn  = strtolower((string) ($this->listDirn ?? 'asc'));
$listDirn  = ($listDirn === 'desc') ? 'desc' : 'asc';
$formId    = (int) ($this->item->id ?? 0);
$fullOrdering = trim($listOrder . ' ' . strtoupper($listDirn));
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
    ['key' => 'fullarticle', 'label' => 'COM_CONTENTBUILDERNG_PERM_FULL_ARTICLE', 'tip' => 'COM_CONTENTBUILDERNG_PERM_FULL_ARTICLE_TIP'],
    ['key' => 'language', 'label' => 'COM_CONTENTBUILDERNG_PERM_CHANGE_LANGUAGE', 'tip' => 'COM_CONTENTBUILDERNG_PERM_CHANGE_LANGUAGE_TIP'],
    ['key' => 'rating', 'label' => 'COM_CONTENTBUILDERNG_PERM_RATING', 'tip' => 'COM_CONTENTBUILDERNG_PERM_RATING_TIP'],
    ['key' => 'api', 'label' => 'COM_CONTENTBUILDERNG_PERM_API', 'tip' => 'COM_CONTENTBUILDERNG_PERM_API_TIP'],
    ['key' => 'stats', 'label' => 'COM_CONTENTBUILDERNG_PERM_STATS', 'tip' => 'COM_CONTENTBUILDERNG_PERM_STATS_TIP'],
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

<script type="text/javascript">
    const cbViewportStateKey = 'cbng.form.viewport.<?php echo (int) ($this->item->id ?? 0); ?>';
    const cbSaveAnimationDurationMs = 500;
    const cbIsBreezingFormsType = <?php echo $isBreezingFormsType ? 'true' : 'false'; ?>;
    const cbBreezingFormsEditableToken = <?php echo json_encode($breezingFormsEditableToken, JSON_UNESCAPED_UNICODE); ?>;
    const cbEditByTypeEnableConfirm = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_TYPE_EDIT_ENABLE_BF_CONFIRM'), JSON_UNESCAPED_UNICODE); ?>;
    const cbFormNotFoundMessage = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'), JSON_UNESCAPED_UNICODE); ?>;
    const cbSaveFailedMessage = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_SAVE_FAILED'), JSON_UNESCAPED_UNICODE); ?>;
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
            clearTimeout(cbSaveButtonTimer);
            cbSaveButtonTimer = null;
        }

        cbSaveButtonTimer = setTimeout(function() {
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
        // Hide Bootstrap tooltips that may remain visible after intercepted AJAX clicks.
        if (window.bootstrap && typeof window.bootstrap.Tooltip === 'function') {
            document.querySelectorAll('[data-bs-toggle="tooltip"], .hasTip, .editlinktip, .js-grid-item-action').forEach(function(el) {
                var instance = window.bootstrap.Tooltip.getInstance(el);
                if (instance && typeof instance.hide === 'function') {
                    instance.hide();
                }
            });
        }

        // Defensive cleanup for any visible tooltip containers left in the DOM.
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
        f.limitstart.value = <?php echo Factory::getApplication()->input->getInt('limitstart', 0) ?>;
        cb = eval('f.' + id);

        if (cb) {
            for (i = 0; true; i++) {
                cbx = eval('f.cb' + i);
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
                Joomla.submitform(task);
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

    var cbColorisConfigured = false;
    var cbColorisInitRetries = 0;

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

    var cbDirtyState = false;
    var cbDirtySnapshot = '';
    var cbEditorObserver = null;
    var cbEditorPollHandle = null;
    var cbDirtyTrackingInitialized = false;
    var cbDirtyUserInteracted = false;
    var cbDirtyBypassBeforeUnload = false;

    function cbShouldIgnoreDirtyField(field) {
        if (!field) {
            return false;
        }

        var name = String(field.name || '');

        return /^jform\[order\]\[\d+\]$/.test(name);
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

    function cbHandleDirtyInteraction() {
        var target = arguments.length > 0 ? arguments[0] : null;

        if (target && target.target && cbShouldIgnoreDirtyField(target.target)) {
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
    window.setTimeout(cbStabilizeDirtySnapshot, 200);
    window.setTimeout(cbStabilizeDirtySnapshot, 900);
</script>
<form action="index.php" method="post" name="adminForm" id="adminForm">
    <div class="w-100 row g-0" style="max-width: 100%; overflow-x: auto;">
        <?php
        $advancedOptionsContent = '';
        // Démarrer les onglets
        $activeViewTab = trim((string) $app->input->getCmd('tab', ''));
        $allowedViewTabs = ['tab0', 'tab1', 'tab2', 'tab3', 'tab5', 'tab6', 'tab7', 'tab8', 'tab9', 'tab10'];
        if (!in_array($activeViewTab, $allowedViewTabs, true)) {
            $activeViewTab = 'tab0';
        }
        echo HTMLHelper::_('uitab.startTabSet', 'view-pane', ['active' => $activeViewTab]);
        // Premier onglet
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab0', $viewTabLabel('fa-regular fa-window-maximize', 'COM_CONTENTBUILDERNG_VIEW', 'COM_CONTENTBUILDERNG_TAB_TIP_VIEW'));
        ?>

        <table width="100%">
            <tr>
                <td class="align-top">

                    <fieldset id="cb-form-view-general" class="border rounded p-3 mb-3">

                        <div class="row g-3 align-items-end mb-2">
                            <div class="col-12 col-lg-3">
                                <label for="name">
                                    <span class="editlinktip hasTip"
                                        title="<?php echo Text::_('COM_CONTENTBUILDERNG_VIEW_NAME_TIP'); ?>"><b>
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_NAME'); ?>:
                                        </b></span>
                                </label>
                                <input class="form-control form-control-sm" type="text" name="jform[name]" id="name" size="32"
                                    style="max-width: 280px;" maxlength="255"
                                    value="<?php echo htmlentities($this->item->name ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="col-12 col-lg-3">
                                <label for="tag">
                                    <span class="editlinktip hasTip"
                                        title="<?php echo Text::_('COM_CONTENTBUILDERNG_VIEW_TAG_TIP'); ?>"><b>
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_TAG'); ?>:
                                        </b></span>
                                </label>
                                <input class="form-control form-control-sm" type="text" name="jform[tag]" id="tag" size="32"
                                    style="max-width: 280px;" maxlength="255"
                                    value="<?php echo htmlentities($this->item->tag ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="col-12 col-lg-3">
                                <div class="d-flex align-items-center gap-2 flex-nowrap">
                                    <label for="theme_plugin" class="mb-0">
                                        <span class="editlinktip hasTip"
                                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_THEME_PLUGIN_TIP'); ?>"><b>
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_THEME_PLUGIN'); ?>:
                                            </b></span>
                                    </label>
                                    <select class="form-select-sm w-auto" name="jform[theme_plugin]" id="theme_plugin">
                                        <?php
                                        foreach ($this->theme_plugins as $theme_plugin) {
                                        ?>
                                            <option value="<?php echo htmlspecialchars((string) $theme_plugin, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $theme_plugin == $this->item->theme_plugin ? ' selected="selected"' : ''; ?>>
                                                <?php echo htmlspecialchars((string) $theme_plugin, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12 col-lg-3">
                                <?php if ((int) ($this->item->id ?? 0) > 0) : ?>
                                    <div class="d-inline-flex align-items-center gap-2 ms-sm-4 ps-sm-2">
                                        <span class="fw-semibold editlinktip hasTip"
                                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_PUBLISH_TIP'); ?>">
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_PUBLISHED'); ?> :
                                        </span>
                                        <?php
                                        $publishedToggleHtml = HTMLHelper::_(
                                            'jgrid.published',
                                            !empty($this->item->published) ? 1 : 0,
                                            0,
                                            'form.form',
                                            true,
                                            'cbformstate'
                                        );
                                        $publishedToggleHtml = preg_replace('/\saria-labelledby="[^"]*"/', '', (string) $publishedToggleHtml) ?? (string) $publishedToggleHtml;
                                        $publishedToggleHtml = preg_replace('#<div role="tooltip"[^>]*>.*?</div>#s', '', (string) $publishedToggleHtml) ?? (string) $publishedToggleHtml;
                                        echo $publishedToggleHtml;
                                        ?>
                                        <input type="checkbox"
                                            name="cid[]"
                                            id="cbformstate0"
                                            value="<?php echo (int) $this->item->id; ?>"
                                            style="display:none" />
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                        if ($this->item->id < 1) {
                        ?>
                            <label for="cb_form_type_select">
                                <span class="editlinktip hasTip"
                                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_TYPE_TIP'); ?>"><b>
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_TYPE'); ?>:
                                    </b></span>
                            </label>
                            <select class="form-select-sm" name="jform[type]" id="cb_form_type_select">
                                <?php
                                foreach ($this->item->types as $type) {
                                    if (trim($type)) {
                                        $typeValue = (string) $type;
                                        $typeDisplay = $formatTypeDisplay($typeValue);
                                ?>
                                        <option value="<?php echo htmlspecialchars($typeValue, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-full="<?php echo htmlspecialchars((string) ($typeDisplay['full'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            title="<?php echo htmlspecialchars((string) ($typeDisplay['full'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars((string) ($typeDisplay['short'] ?? $typeValue), ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                <?php
                                    }
                                }
                                ?>
                            </select>
                            <script>
                                (function() {
                                    var typeSelect = document.getElementById('cb_form_type_select');
                                    if (!typeSelect) {
                                        return;
                                    }

                                    var updateTypeTitle = function() {
                                        var option = typeSelect.options[typeSelect.selectedIndex];
                                        typeSelect.title = option ? (option.getAttribute('data-full') || option.value || '') : '';
                                    };

                                    typeSelect.addEventListener('change', updateTypeTitle);
                                    updateTypeTitle();
                                })();
                            </script>

                        <?php
                        } else {
                        ?>
                            <div></div>

                            <div class="alert">
                                <label<?php echo !$this->item->reference_id ? ' for="cb_form_reference_select"' : ''; ?>>
                                    <b>
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_FORM_SOURCE'); ?>:
                                    </b>
                                    </label>
                                    <?php

                                    if (!$this->item->reference_id) {
                                    ?>
                                        <select class="form-select-sm" name="jform[reference_id]" id="cb_form_reference_select" style="max-width: 200px;">
                                            <option value="0" selected="selected">
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_CHOOSE'); ?>
                                            </option>
                                            <?php
                                            foreach ($this->item->forms as $reference_id => $title) {
                                            ?>
                                                <option value="<?php echo $reference_id ?>">
                                                    <?php echo htmlentities($title ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php
                                            }
                                            ?>
                                        </select>
                                    <?php
                                    } else {
                                        $sourceTitle = (string) ($this->item->form->getTitle() ?? '');
                                        $sourceReferenceId = (int) $this->item->form->getReferenceId();
                                        $sourceType = (string) ($this->item->type ?? '');
                                        $sourceTypeName = trim((string) ($this->item->type_name ?? ''));
                                        $sourceEditLink = '';

                                        if ($sourceType === 'com_breezingforms' && $sourceReferenceId > 0 && $sourceTypeName !== '') {
                                            $sourceEditLink = Route::_(
                                                'index.php?option=com_breezingforms&act=quickmode&formName=' . rawurlencode($sourceTypeName) . '&form=' . $sourceReferenceId,
                                                false
                                            );
                                        } elseif ($sourceType === 'com_contentbuilderng' && $sourceReferenceId > 0) {
                                            $sourceEditLink = Route::_(
                                                'index.php?option=com_contentbuilderng&view=storage&layout=edit&id=' . $sourceReferenceId,
                                                false
                                            );
                                        }
                                    ?>
                                        <?php if ($sourceEditLink !== '') : ?>
                                            <a href="<?php echo htmlspecialchars($sourceEditLink, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlentities($sourceTitle, ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        <?php else : ?>
                                            <?php echo htmlentities($sourceTitle, ENT_QUOTES, 'UTF-8'); ?>
                                        <?php endif; ?>
                                        <input type="hidden" name="jform[reference_id]"
                                            value="<?php echo $sourceReferenceId; ?>" />
                                    <?php
                                    }
                                    ?>

                                    <label>
                                        <span class="editlinktip hasTip"
                                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_TYPE_TIP'); ?>"><b>
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_TYPE'); ?>:
                                            </b></span>
                                    </label>
                                    <?php $typeDisplay = $formatTypeDisplay((string) ($this->item->type ?? '')); ?>
                                    <span class="editlinktip hasTip"
                                        title="<?php echo htmlspecialchars((string) ($typeDisplay['full'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars((string) ($typeDisplay['short'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <input type="hidden" name="jform[type]" value="<?php echo $this->item->type ?>" />
                                    <input type="hidden" name="jform[type_name]"
                                        value="<?php echo isset($this->item->type_name) ? $this->item->type_name : ''; ?>" />
                            </div>

                            <div></div>

                        <?php
                        }
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
                        ?>

                    </fieldset>

                </td>
            </tr>
        </table>
        </fieldset>
        </td>
        </tr>
        <tr>
            <td class="align-top">
                <?php
                echo '<div id="cb-form-view-elements">';
                echo LayoutHelper::render(
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
                echo '</div>';
                ?>

            </td>
        </tr>

        </table>

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
        <h3 id="cb-form-list-intro-text" class="mb-3">
            <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_INTRO_MODE_TITLE'); ?>
        </h3>
        <p class="text-muted mb-3">
            <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_INTRO_MODE_INTRO'); ?>
        </p>
        <?php
        echo $this->form->renderField('intro_text');
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
    <input type="hidden" name="limitstart" value="<?php echo (int) Factory::getApplication()->input->getInt('limitstart', 0); ?>" />
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
