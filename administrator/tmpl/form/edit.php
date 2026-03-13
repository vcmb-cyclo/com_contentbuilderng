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

use Joomla\CMS\Factory;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Editor\Editor;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;
use CB\Component\Contentbuilderng\Administrator\Service\TextUtilityService;
?>
<?php
/** @var AdministratorApplication $app */
$app = Factory::getApplication();
$session = $app->getSession();
$textUtilityService = new TextUtilityService();
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
        . '.cb-item-order-type-select{align-self:flex-start;width:auto!important;max-width:100%}'
        . '.cb-wordwrap-input{width:8ch!important;min-width:8ch!important;max-width:8ch!important;text-align:center}'
        . '.cb-prepare-tools{row-gap:.5rem}'
        . '.cb-prepare-tools .btn{text-wrap:nowrap}'
        . '.cb-prepare-tools .cb-snippet-select{display:inline-block;width:auto;min-width:12ch;max-width:42ch;flex:0 0 auto}'
        . '.cb-prepare-tools .cb-effect-select{min-width:170px;max-width:240px}'
        . '.cb-upload-box{margin:0 0 1rem;padding:.85rem .95rem;border:1px solid var(--bs-border-color);border-radius:12px;background:linear-gradient(180deg,var(--bs-tertiary-bg),var(--bs-body-bg))}'
        . '.cb-save-animate{background-color:var(--alert-heading-bg,var(--bs-success,#198754))!important;background-image:none!important;border-color:var(--bs-success,#198754)!important;color:var(--bs-white)!important;filter:brightness(1.2)!important;box-shadow:0 0 0 .38rem rgba(25,135,84,.36)!important;transition:none!important}'
        . '.cb-save-animate .fa-check,.cb-save-animate .fa-xmark,.cb-save-animate .fa-xmark-new{color:var(--bs-white)!important}'
        . '#view-pane .nav-tabs,#perm-pane .nav-tabs{display:flex;gap:.4rem;flex-wrap:wrap;padding:.42rem;margin-bottom:.9rem;border:1px solid var(--bs-border-color);border-bottom:1px solid var(--bs-border-color);border-radius:14px;background:linear-gradient(180deg,var(--bs-tertiary-bg),var(--bs-secondary-bg))}'
        . '#view-pane .nav-tabs .nav-link,#view-pane .nav-tabs [role="tab"],#perm-pane .nav-tabs .nav-link,#perm-pane .nav-tabs [role="tab"]{border:1px solid transparent;border-radius:10px;padding:.45rem .8rem;font-weight:600;color:var(--bs-secondary-color);background:transparent;transition:all .18s ease}'
        . '#view-pane .nav-tabs .nav-link:hover,#view-pane .nav-tabs [role="tab"]:hover,#perm-pane .nav-tabs .nav-link:hover,#perm-pane .nav-tabs [role="tab"]:hover{background:var(--bs-body-bg);border-color:var(--bs-border-color);color:var(--bs-emphasis-color);transform:translateY(-1px)}'
        . '#view-pane .nav-tabs .nav-link:focus-visible,#view-pane .nav-tabs [role="tab"]:focus-visible,#perm-pane .nav-tabs .nav-link:focus-visible,#perm-pane .nav-tabs [role="tab"]:focus-visible{outline:2px solid var(--bs-primary);outline-offset:1px}'
        . '#view-pane .nav-tabs .nav-link.active,#view-pane .nav-tabs [role="tab"][aria-selected="true"],#perm-pane .nav-tabs .nav-link.active,#perm-pane .nav-tabs [role="tab"][aria-selected="true"]{color:var(--bs-white);background:var(--bs-primary);border-color:var(--bs-primary);box-shadow:0 4px 12px rgba(13,110,253,.28)}'
        . '@media (max-width:991.98px){#view-pane .nav-tabs,#perm-pane .nav-tabs{flex-wrap:nowrap;overflow:auto;-webkit-overflow-scrolling:touch}#view-pane .nav-tabs .nav-link,#view-pane .nav-tabs [role="tab"],#perm-pane .nav-tabs .nav-link,#perm-pane .nav-tabs [role="tab"]{white-space:nowrap}}'
        . '@keyframes cb-blink{50%{opacity:0}}'
);

$listOrder = (string) ($this->listOrder ?? 'ordering');
$listDirn  = strtolower((string) ($this->listDirn ?? 'asc'));
$listDirn  = ($listDirn === 'desc') ? 'desc' : 'asc';
$formId    = (int) ($this->item->id ?? 0);
$apiEndpointBase = Uri::root() . 'index.php?option=com_contentbuilderng&task=api.display&id=' . $formId;
$apiPreviewQuery = '';
$apiPreviewUntil = time() + 600;
$apiPreviewActorId = (int) ($app->getIdentity()->id ?? 0);
$apiPreviewActorName = trim((string) ($app->getIdentity()->name ?? ''));
if ($apiPreviewActorName === '') {
    $apiPreviewActorName = trim((string) ($app->getIdentity()->username ?? ''));
}
if ($apiPreviewActorName !== '') {
    $apiPreviewPayload = $formId . '|' . $apiPreviewUntil . '|' . $apiPreviewActorId . '|' . $apiPreviewActorName;
    $apiPreviewSig = hash_hmac('sha256', $apiPreviewPayload, (string) $app->get('secret'));
    if ($apiPreviewSig !== '') {
        $apiPreviewQuery = '&cb_preview=1'
            . '&cb_preview_until=' . $apiPreviewUntil
            . '&cb_preview_actor_id=' . $apiPreviewActorId
            . '&cb_preview_actor_name=' . rawurlencode($apiPreviewActorName)
            . '&cb_preview_sig=' . $apiPreviewSig;
    }
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
    $isActive = ($listOrder === $field);
    $nextDir = ($isActive && $listDirn === 'asc') ? 'desc' : 'asc';
    $indicator = $isActive
        ? ($listDirn === 'asc'
            ? ' <span class="ms-1 fa-solid fa-sort fa-solid fa-sort-up" aria-hidden="true"></span>'
            : ' <span class="ms-1 fa-solid fa-sort fa-solid fa-sort-down" aria-hidden="true"></span>')
        : '';
    $url = Route::_(
        'index.php?option=com_contentbuilderng&task=form.display&layout=edit&id=' . $formId
            . '&list[ordering]=' . $field . '&list[direction]=' . $nextDir
    );

    return '<a href="' . $url . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . $indicator . '</a>';
};

$permHeaderLabel = static function (string $labelKey, string $tipKey): string {
    $label = Text::_($labelKey);
    $tip = Text::_($tipKey);

    return '<span class="cb-perm-header-tip" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="'
        . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
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
    ['value' => 'none', 'text' => Text::_('COM_CONTENTBUILDERNG_PREPARE_EFFECT_NONE')],
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
    $html .= '<input class="form-check-input" type="checkbox"';

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
        var selectors = [
            'joomla-toolbar-button#save-group-children-apply button',
            '#save-group-children-apply button',
            '#toolbar .button-apply'
        ];

        var targets = [];
        selectors.forEach(function(selector) {
            document.querySelectorAll(selector).forEach(function(el) {
                if (!el) {
                    return;
                }
                if (el.classList && el.classList.contains('dropdown-toggle-split')) {
                    return;
                }
                if (typeof el.closest === 'function' && el.closest('.dropdown-menu')) {
                    return;
                }
                if (targets.indexOf(el) === -1) {
                    targets.push(el);
                }
            });
        });

        if (!targets.length) {
            return;
        }

        targets.forEach(function(el) {
            el.classList.remove('cb-save-animate');
            void el.offsetWidth;
            el.classList.add('cb-save-animate');
        });

        if (cbSaveButtonTimer) {
            clearTimeout(cbSaveButtonTimer);
            cbSaveButtonTimer = null;
        }

        cbSaveButtonTimer = setTimeout(function() {
            targets.forEach(function(el) {
                el.classList.remove('cb-save-animate');
            });
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
                onError("Form not found.");
            }
            return;
        }

        if (cbAjaxBusy) {
            return;
        }

        cbAjaxBusy = true;
        cbRememberViewport(rowId || '');
        cbDismissTransientTooltips();

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
                        throw new Error((payload && payload.message) ? payload.message : 'Save failed');
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
                    onError(error && error.message ? error.message : 'Save failed');
                    return;
                }
                alert(error && error.message ? error.message : 'Save failed');
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
            value = 'Unnamed';
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
                alert(message || 'Save failed');
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
    }

    function cbGetEditorFieldValue(fieldName) {
        var value = '';
        var editorId = 'jform_' + fieldName;

        if (window.Joomla && Joomla.editors && Joomla.editors.instances) {
            var instance = Joomla.editors.instances[editorId] || Joomla.editors.instances[fieldName];

            if (instance) {
                if (typeof instance.getValue === 'function') {
                    value = instance.getValue();
                } else if (typeof instance.getContent === 'function') {
                    value = instance.getContent();
                }
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
        var editorId = 'jform_' + fieldName;
        var updatedViaEditor = false;

        if (window.Joomla && Joomla.editors && Joomla.editors.instances) {
            var instance = Joomla.editors.instances[editorId] || Joomla.editors.instances[fieldName];

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
    }

    function cbOpenPrepareExamples() {
        var modalElement = document.getElementById('cb-prepare-examples-modal');
        if (!modalElement || !window.bootstrap || typeof window.bootstrap.Modal !== 'function') {
            return;
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
</script>
<form action="index.php" method="post" name="adminForm" id="adminForm">
    <div class="w-100 row g-0" style="max-width: 100%; overflow-x: auto;">

        <?php
        $advancedOptionsContent = '';
        // Démarrer les onglets
        $activeViewTab = trim((string) $app->input->getCmd('tab', ''));
        if ($activeViewTab === '') {
            $activeViewTab = trim((string) $session->get('tabStartOffset', 'tab0', 'com_contentbuilderng'));
        }
        $allowedViewTabs = ['tab0', 'tab1', 'tab2', 'tab3', 'tab5', 'tab7', 'tab8', 'tab9'];
        if (!in_array($activeViewTab, $allowedViewTabs, true)) {
            $activeViewTab = 'tab0';
        }
        echo HTMLHelper::_('uitab.startTabSet', 'view-pane', ['active' => $activeViewTab]);
        // Premier onglet
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab0', Text::_('COM_CONTENTBUILDERNG_VIEW'));
        ?>

        <table width="100%">
            <tr>
                <td valign="top">

                    <fieldset class="border rounded p-3 mb-3">

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
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_PUBLISHED'); ?> :
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
                            <label for="types">
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
                                <label for="name">
                                    <b>
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_FORM_SOURCE'); ?>:
                                    </b>
                                </label>
                                <?php

                                if (!$this->item->reference_id) {
                                ?>
                                    <select class="form-select-sm" name="jform[reference_id]" style="max-width: 200px;">
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
                                ?>
                                    <?php echo htmlentities($this->item->form->getTitle() ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    <input type="hidden" name="jform[reference_id]"
                                        value="<?php echo $this->item->form->getReferenceId(); ?>" />
                                <?php
                                }
                                ?>

                                <label for="types">
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

                        <?php ob_start(); ?>
                        <div class="bg-body-tertiary p-3" id="advancedOptions">

                            <fieldset>
                                <legend>
                                    <h3 class="editlinktip hasTip"
                                        title="<?php echo Text::_('COM_CONTENTBUILDERNG_DISPLAY_TIP'); ?>">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_DISPLAY'); ?>
                                    </h3>
                                </legend>


                                <div class="cb-display-in-row">
                                    <select class="form-select-sm" name="jform[display_in]">
                                        <option value="0" <?php echo $this->item->display_in == 0 ? ' selected="selected"' : '' ?>>
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_DISPLAY_FRONTEND') ?>
                                        </option>
                                        <option value="1" <?php echo $this->item->display_in == 1 ? ' selected="selected"' : '' ?>>
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_DISPLAY_BACKEND') ?>
                                        </option>
                                        <option value="2" <?php echo $this->item->display_in == 2 ? ' selected="selected"' : '' ?>>
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_DISPLAY_BOTH') ?>
                                        </option>
                                    </select>
                                </div>

                            </fieldset>

                            <hr />

                            <fieldset>
                                <legend>
                                    <h3 class="editlinktip hasTip"
                                        title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_COLUMNS_TIP'); ?>">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW'); ?>
                                    </h3>
                                </legend>


                                <div class="row gx-3 gy-1 mt-0">
                                    <div class="col-12 col-xl-4">
                                        <div class="border rounded bg-body p-3 h-100">
                                            <h4 class="h6 text-body-secondary mb-2">
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_DATA_OPTIONS'); ?>
                                            </h4>
                                            <div class="d-flex flex-wrap align-items-center gap-3">
                                                <div>
                                                    <input type="hidden" name="jform[show_id_column]" value="0" />
                                                    <?php echo $renderCheckbox('jform[show_id_column]', 'show_id_column', (bool) $this->item->show_id_column); ?>
                                                    <label class="form-check-label" for="show_id_column">
                                                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_ID_COLUMN_TIP'); ?>">
                                                            <?php echo Text::_('COM_CONTENTBUILDERNG_ID_COLUMN'); ?>
                                                        </span>
                                                    </label>
                                                </div>
                                                <div>
                                                    <input type="hidden" name="jform[select_column]" value="0" />
                                                    <?php echo $renderCheckbox('jform[select_column]', 'select_column', (bool) $this->item->select_column); ?>
                                                    <label class="form-check-label" for="select_column">
                                                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_SELECT_COLUMN_TIP'); ?>">
                                                            <?php echo Text::_('COM_CONTENTBUILDERNG_SELECT_COLUMN'); ?>
                                                        </span>
                                                    </label>
                                                </div>
                                                <div>
                                                    <input type="hidden" name="jform[list_state]" value="0" />
                                                    <?php echo $renderCheckbox('jform[list_state]', 'list_state', (bool) $this->item->list_state); ?>
                                                    <label class="form-check-label" for="list_state">
                                                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_STATE_COLUMN_TIP'); ?>">
                                                            <?php echo Text::_('COM_CONTENTBUILDERNG_EDIT_STATE'); ?>
                                                        </span>
                                                    </label>
                                                </div>
                                                <div>
                                                    <input type="hidden" name="jform[list_publish]" value="0" />
                                                    <?php echo $renderCheckbox('jform[list_publish]', 'list_publish', (bool) $this->item->list_publish); ?>
                                                    <label class="form-check-label" for="list_publish">
                                                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_PUBLISH_COLUMN_TIP'); ?>">
                                                            <?php echo Text::_('COM_CONTENTBUILDERNG_PUBLISH'); ?>
                                                        </span>
                                                    </label>
                                                </div>
                                                <div>
                                                    <input type="hidden" name="jform[list_language]" value="0" />
                                                    <?php echo $renderCheckbox('jform[list_language]', 'list_language', (bool) $this->item->list_language); ?>
                                                    <label class="form-check-label" for="list_language">
                                                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_LANGUAGE_COLUMN_TIP'); ?>">
                                                            <?php echo Text::_('COM_CONTENTBUILDERNG_LANGUAGE'); ?>
                                                        </span>
                                                    </label>
                                                </div>
                                                <div>
                                                    <input type="hidden" name="jform[list_article]" value="0" />
                                                    <?php echo $renderCheckbox('jform[list_article]', 'list_article', (bool) $this->item->list_article); ?>
                                                    <label class="form-check-label" for="list_article">
                                                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_ARTICLE_COLUMN_TIP'); ?>">
                                                            <?php echo Text::_('COM_CONTENTBUILDERNG_ARTICLE'); ?>
                                                        </span>
                                                    </label>
                                                </div>
                                                <div>
                                                    <input type="hidden" name="jform[list_author]" value="0" />
                                                    <?php echo $renderCheckbox('jform[list_author]', 'list_author', (bool) $this->item->list_author); ?>
                                                    <label class="form-check-label" for="list_author">
                                                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_AUTHOR_COLUMN_TIP'); ?>">
                                                            <?php echo Text::_('COM_CONTENTBUILDERNG_AUTHOR'); ?>
                                                        </span>
                                                    </label>
                                                </div>
                                                <div>
                                                    <input type="hidden" name="jform[metadata]" value="0" />
                                                    <?php echo $renderCheckbox('jform[metadata]', 'metadata', (bool) $this->item->metadata); ?>
                                                    <label class="form-check-label" for="metadata">
                                                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_METADATA_TIP'); ?>">
                                                            <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_METADATA'); ?>
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-xl-4">
                                        <div class="border rounded bg-body p-3 h-100">
                                            <h4 class="h6 text-body-secondary mb-2">
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_BUTTON_OPTIONS'); ?>
                                            </h4>
                                            <div class="d-flex flex-wrap align-items-center gap-3">
                                                <div>
                                                    <input type="hidden" name="jform[edit_button]" value="0" />
                                                    <?php echo $renderCheckbox('jform[edit_button]', 'edit_button', (bool) $this->item->edit_button); ?>
                                                    <label class="form-check-label" for="edit_button">
                                                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_EDIT_BUTTON_TIP'); ?>">
                                                            <?php echo Text::_('COM_CONTENTBUILDERNG_EDIT_BUTTON'); ?>
                                                        </span>
                                                    </label>
                                                </div>
                                                <div>
                                                    <input type="hidden" name="jform[new_button]" value="0" />
                                                    <?php echo $renderCheckbox('jform[new_button]', 'new_button', (bool) ($this->item->new_button ?? 0)); ?>
                                                    <label class="form-check-label" for="new_button">
                                                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_NEW_BUTTON_TIP'); ?>">
                                                            <?php echo Text::_('COM_CONTENTBUILDERNG_NEW'); ?>
                                                        </span>
                                                    </label>
                                                </div>
                                                <div>
                                                    <input type="hidden" name="jform[export_xls]" value="0" />
                                                    <?php echo $renderCheckbox('jform[export_xls]', 'export_xls', (bool) $this->item->export_xls); ?>
                                                    <label class="form-check-label" for="export_xls">
                                                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_XLSEXPORT_TIP'); ?>">
                                                            <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_XLSEXPORT'); ?>
                                                        </span>
                                                    </label>
                                                </div>
                                                <div>
                                                    <input type="hidden" name="jform[print_button]" value="0" />
                                                    <?php echo $renderCheckbox('jform[print_button]', 'print_button', (bool) $this->item->print_button); ?>
                                                    <label class="form-check-label" for="print_button">
                                                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_PRINTBUTTON_TIP'); ?>">
                                                            <?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_PRINTBUTTON'); ?>
                                                        </span>
                                                    </label>
                                                </div>
                                                <div class="w-100"></div>
                                                <div>
                                                    <input type="hidden" name="jform[button_bar_sticky]" value="0" />
                                                    <?php echo $renderCheckbox('jform[button_bar_sticky]', 'button_bar_sticky', (bool) ($this->item->button_bar_sticky ?? 0)); ?>
                                                    <label class="form-check-label" for="button_bar_sticky">
                                                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_BUTTON_BAR_STICKY_TIP'); ?>">
                                                            <?php echo Text::_('COM_CONTENTBUILDERNG_BUTTON_BAR_STICKY'); ?>
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-xl-4">
                                        <div class="border rounded bg-body p-3 h-100">
                                            <h4 class="h6 text-body-secondary mb-2">
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_DISPLAY_OPTIONS'); ?>
                                            </h4>
                                            <div class="d-flex flex-wrap align-items-center gap-3">
                                                <div>
                                                    <input type="hidden" name="jform[show_filter]" value="0" />
                                                    <?php echo $renderCheckbox('jform[show_filter]', 'show_filter', (bool) $this->item->show_filter); ?>
                                                    <label class="form-check-label" for="show_filter">
                                                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_FILTER_TIP'); ?>">
                                                            <?php echo Text::_('COM_CONTENTBUILDERNG_FILTER'); ?>
                                                        </span>
                                                    </label>
                                                </div>
                                                <div>
                                                    <input type="hidden" name="jform[show_records_per_page]" value="0" />
                                                    <?php echo $renderCheckbox('jform[show_records_per_page]', 'show_records_per_page', (bool) $this->item->show_records_per_page); ?>
                                                    <label class="form-check-label" for="show_records_per_page">
                                                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_RECORDS_PER_PAGE_TIP'); ?>">
                                                            <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_LIMIT_LABEL'); ?>
                                                        </span>
                                                    </label>
                                                </div>
                                                <div>
                                                    <input type="hidden" name="jform[show_preview_link]" value="0" />
                                                    <?php echo $renderCheckbox('jform[show_preview_link]', 'show_preview_link', (bool) ($this->item->show_preview_link ?? 0)); ?>
                                                    <label class="form-check-label" for="show_preview_link">
                                                        <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_PREVIEW_LINK_TIP'); ?>">
                                                            <span class="fa-solid fa-eye" aria-hidden="true"></span>
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </fieldset>

                            <hr />

                            <fieldset>
                                <legend>
                                    <h3>
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_RATING'); ?>
                                    </h3>
                                </legend>
                                <div class="alert">
                                    <input type="hidden" name="jform[list_rating]" value="0" />
                                    <?php echo $renderCheckbox('jform[list_rating]', 'list_rating', (bool) $this->item->list_rating); ?>
                                    <label class="form-check-label" for="list_rating">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_RATING'); ?>
                                    </label>

                                    <select class="form-select-sm" name="jform[rating_slots]" id="rating_slots">
                                        <option value="1" <?php echo $this->item->rating_slots == 1 ? ' selected="selected"' : ''; ?>>1</option>
                                        <option value="2" <?php echo $this->item->rating_slots == 2 ? ' selected="selected"' : ''; ?>>2</option>
                                        <option value="3" <?php echo $this->item->rating_slots == 3 ? ' selected="selected"' : ''; ?>>3</option>
                                        <option value="4" <?php echo $this->item->rating_slots == 4 ? ' selected="selected"' : ''; ?>>4</option>
                                        <option value="5" <?php echo $this->item->rating_slots == 5 ? ' selected="selected"' : ''; ?>>5</option>
                                    </select>
                                    <label for="rating_slots">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_RATING_SLOTS'); ?>
                                    </label>
                                </div>
                            </fieldset>

                            <hr />

                            <fieldset>
                                <legend>
                                    <h3>
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_SORTING'); ?>
                                    </h3>
                                </legend>
                                <div class="alert">
                                    <label for="initial_sort_order">
                                        <span class="editlinktip hasTip"
                                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_INITIAL_SORT_ORDER_TIP'); ?>"><b>
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_INITIAL_SORT_ORDER'); ?>:
                                            </b></span>
                                    </label>
                                    <select class="form-select-sm"
                                        onchange="if(this.selectedIndex == 3) { document.getElementById('randUpdate').style.display='block'; } else { document.getElementById('randUpdate').style.display='none'; } "
                                        name="jform[initial_sort_order]" id="initial_sort_order" style="max-width: 200px;">
                                        <option value="-1">
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_INITIAL_SORT_ORDER_BY_ID'); ?>
                                        </option>
                                        <option value="Rating" <?php echo $this->item->initial_sort_order == 'Rating' ? ' selected="selected"' : ''; ?>>
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_RATING'); ?>
                                        </option>
                                        <option value="RatingCount" <?php echo $this->item->initial_sort_order == 'RatingCount' ? ' selected="selected"' : ''; ?>>
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_RATING_COUNT'); ?>
                                        </option>
                                        <option value="Rand" <?php echo $this->item->initial_sort_order == 'Rand' ? ' selected="selected"' : ''; ?>>
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_INITIAL_SORT_ORDER_RAND'); ?>
                                        </option>
                                        <?php
                                        foreach ($this->elements as $sortable) {
                                        ?>
                                            <option value="<?php echo $sortable->reference_id; ?>" <?php echo $this->item->initial_sort_order == $sortable->reference_id ? ' selected="selected"' : ''; ?>>
                                                <?php echo htmlentities($sortable->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                                </value>
                                            <?php
                                        }
                                            ?>
                                    </select>
                                    <span id="randUpdate"
                                        style="display: <?php echo $this->item->initial_sort_order == 'Rand' ? 'block' : 'none' ?>;">
                                        <b>
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_RAND_UPDATE'); ?>:
                                        </b>
                                        <input class="form-control form-control-sm" type="text" name="jform[rand_update]"
                                            value="<?php echo $this->item->rand_update; ?>" />
                                    </span>
                                    <select class="form-select-sm" name="jform[initial_sort_order2]" id="initial_sort_order2"
                                        style="max-width: 200px;">
                                        <option value="-1">
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_NONE'); ?>
                                        </option>
                                        <?php
                                        foreach ($this->elements as $sortable) {
                                        ?>
                                            <option value="<?php echo $sortable->reference_id; ?>" <?php echo $this->item->initial_sort_order2 == $sortable->reference_id ? ' selected="selected"' : ''; ?>>
                                                <?php echo htmlentities($sortable->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                                </value>
                                            <?php
                                        }
                                            ?>
                                    </select>
                                    <select class="form-select-sm" name="jform[initial_sort_order3]" id="initial_sort_order3"
                                        style="max-width: 200px;">
                                        <option value="-1">
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_NONE'); ?>
                                        </option>
                                        <?php
                                        foreach ($this->elements as $sortable) {
                                        ?>
                                            <option value="<?php echo $sortable->reference_id; ?>" <?php echo $this->item->initial_sort_order3 == $sortable->reference_id ? ' selected="selected"' : ''; ?>>
                                                <?php echo htmlentities($sortable->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                                </value>
                                            <?php
                                        }
                                            ?>
                                    </select>
                                    <div></div>
                                    <input class="form-check-input" type="radio" name="jform[initial_order_dir]"
                                        id="initial_order_dir" value="asc" <?php echo $this->item->initial_order_dir == 'asc' ? ' checked="checked"' : ''; ?> /> <label
                                        for="initial_order_dir">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_INITIAL_SORT_ORDER_ASC'); ?>
                                    </label>
                                    <input class="form-check-input" type="radio" name="jform[initial_order_dir]"
                                        id="initial_order_dir_desc" value="desc" <?php echo $this->item->initial_order_dir == 'desc' ? ' checked="checked"' : ''; ?> /> <label
                                        for="initial_order_dir_desc">
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_INITIAL_SORT_ORDER_DESC'); ?>
                                    </label>
                                </div>
                            </fieldset>

                            <hr />

                            <fieldset>
                                <legend>
                                    <h3>
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_BUTTONS'); ?>
                                    </h3>
                                </legend>
                                <div class="alert">
                                    <label for="save_button_title">
                                        <span class="editlinktip hasTip"
                                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_SAVE_BUTTON_TITLE_TIP'); ?>"><b>
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_SAVE_BUTTON_TITLE'); ?>:
                                            </b></span>
                                    </label>
                                    <input class="form-control form-control-sm" type="text" id="save_button_title"
                                        name="jform[save_button_title]"
                                        value="<?php echo htmlentities($this->item->save_button_title ?? '', ENT_QUOTES, 'UTF-8'); ?>" />

                                    <label for="apply_button_title">
                                        <span class="editlinktip hasTip"
                                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_APPLY_BUTTON_TITLE_TIP'); ?>"><b>
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_APPLY_BUTTON_TITLE'); ?>:
                                            </b></span>
                                    </label>
                                    <input class="form-control form-control-sm" type="text" id="apply_button_title"
                                        name="jform[apply_button_title]"
                                        value="<?php echo htmlentities($this->item->apply_button_title ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                </div>
                            </fieldset>

                            <hr />

                            <fieldset>
                                <legend>
                                    <h3>
                                        <?php echo Text::_('COM_CONTENTBUILDERNG_MISC'); ?>
                                    </h3>
                                </legend>
                                <div class="alert">
                                    <input type="hidden" name="jform[filter_exact_match]" value="0" />
                                    <?php echo $renderCheckbox('jform[filter_exact_match]', 'filter_exact_match', (bool) $this->item->filter_exact_match); ?>
                                    <label class="form-check-label" for="filter_exact_match">
                                        <span class="editlinktip hasTip"
                                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_FILTER_EXACT_MATCH_TIP'); ?>">
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_FILTER_EXACT_MATCH'); ?>
                                        </span>
                                    </label>

                                    <input type="hidden" name="jform[use_view_name_as_title]" value="0" />
                                    <?php echo $renderCheckbox('jform[use_view_name_as_title]', 'use_view_name_as_title', (bool) $this->item->use_view_name_as_title); ?>
                                    <label class="form-check-label" for="use_view_name_as_title">
                                        <span class="editlinktip hasTip"
                                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_USE_VIEW_NAME_AS_TITLE_TIP'); ?>">
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_USE_VIEW_NAME_AS_TITLE'); ?>
                                        </span>
                                    </label>

                                    <input type="hidden" name="jform[published_only]" value="0" />
                                    <?php echo $renderCheckbox('jform[published_only]', 'published_only', (bool) $this->item->published_only); ?>
                                    <label class="form-check-label" for="published_only">
                                        <span class="editlinktip hasTip"
                                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_PUBLISHED_ONLY_TIP'); ?>">
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_PUBLISHED_ONLY'); ?>
                                        </span>
                                    </label>

                                    <input type="hidden" name="jform[allow_external_filter]" value="0" />
                                    <?php echo $renderCheckbox('jform[allow_external_filter]', 'allow_external_filter', (bool) $this->item->allow_external_filter); ?>
                                    <label class="form-check-label" for="allow_external_filter">
                                        <span class="editlinktip hasTip"
                                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_ALLOW_EXTERNAL_FILTER_TIP'); ?>">
                                            <?php echo Text::_('COM_CONTENTBUILDERNG_ALLOW_EXTERNAL_FILTER'); ?>
                                        </span>
                                    </label>
                                </div>
                            </fieldset>

                        </div>
                        <?php $advancedOptionsContent = ob_get_clean(); ?>

                    </fieldset>

                </td>
            </tr>
        </table>
        </fieldset>
        </td>
        </tr>
        <tr>
            <td valign="top">
                <table class="table table-striped cb-elements-table">
                    <thead>
                        <tr>
                            <th width="5">
                                <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_ID'), 'id'); ?>
                            </th>
                            <th width="20">
                                <?php echo HTMLHelper::_('grid.checkall'); ?>
                            </th>
                            <th>
                                <span class="editlinktip hasTip"
                                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_LABEL_TIP'); ?>">
                                    <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_LABEL'), 'label'); ?>
                                </span>
                            </th>
                            <th>
                                <span class="editlinktip hasTip"
                                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_LIST_INCLUDE_TIP'); ?>">
                                    <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_LIST_INCLUDE'), 'list_include'); ?>
                                </span>
                            </th>
                            <th>
                                <span class="editlinktip hasTip"
                                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_SEARCH_INCLUDE_TIP'); ?>">
                                    <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_SEARCH_INCLUDE'), 'search_include'); ?>
                                </span>
                            </th>
                            <th>
                                <span class="editlinktip hasTip"
                                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_LINKABLE_TIP'); ?>">
                                    <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_LINKABLE'), 'linkable'); ?>
                                </span>
                            </th>
                            <th>
                                <span class="editlinktip hasTip"
                                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_EDITABLE_TIP'); ?>">
                                    <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_EDITABLE'), 'editable'); ?>
                                </span>
                            </th>
                            <th>
                                <span class="editlinktip hasTip"
                                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_LIST_WORDWRAP_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_WORDWRAP'); ?>
                                </span>
                            </th>
                            <th width="150">
                                <span class="editlinktip hasTip"
                                    title="<?php echo $textUtilityService->allhtmlentities(Text::_('COM_CONTENTBUILDERNG_LIST_ITEM_WRAPPER_TIP')); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_ITEM_WRAPPER'); ?>
                                </span>
                            </th>
                            <th>
                                <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_PUBLISHED'), 'published'); ?>
                            </th>
                            <th width="120" class="cb-order-head">
                                <?php if (!empty($this->elements) && is_array($this->elements)) : ?>
                                    <?php echo $sortLink(Text::_('COM_CONTENTBUILDERNG_ORDERBY'), 'ordering'); ?>
                                    <?php //TODO: dragndrop if ($this->ordering) echo HTMLHelper::_('grid.order',  $this->elements );   
                                    ?>
                                    <?php echo HTMLHelper::_('grid.order', $this->elements); ?>
                                <?php endif; ?>
                            </th>

                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $k = 0;
                        $n = count($this->elements);
                        for ($i = 0; $i < $n; $i++) {
                            $row = $this->elements[$i];
                            $checked = HTMLHelper::_('grid.id', $i, $row->id);
                            $published = ContentbuilderngHelper::listPublish('form', $row, $i);
                            $list_include = ContentbuilderngHelper::listIncludeInList('form', $row, $i);
                            $search_include = ContentbuilderngHelper::listIncludeInSearch('form', $row, $i);
                            $linkable = ContentbuilderngHelper::listLinkable('form', $row, $i);
                            $editable = ContentbuilderngHelper::listEditable('form', $row, $i);
                        ?>
                            <tr id="cb-row-<?php echo (int) $row->id; ?>" class="<?php echo "row$k"; ?>" data-cb-row-id="<?php echo (int) $row->id; ?>">
                                <td valign="top">
                                    <?php echo $row->id; ?>
                                </td>
                                <td valign="top">
                                    <?php echo $checked; ?>
                                </td>
                                <td valign="top">
                                    <div class="cb-item-label-cell">
                                        <div class="cb-item-label-display"
                                            id="itemLabels_<?php echo $row->id ?>"
                                            onclick="document.getElementById('itemLabels<?php echo $row->id ?>').style.display='block';this.style.display='none';document.getElementById('itemLabels<?php echo $row->id ?>').focus();">
                                            <b>
                                                <?php echo htmlentities($row->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </b>
                                        </div>
                                        <input class="form-control form-control-sm"
                                            onblur="cbHandleItemLabelBlur(this, <?php echo (int) $row->id; ?>);"
                                            onkeydown="if (event.key === 'Enter') { event.preventDefault(); this.blur(); }"
                                            id="itemLabels<?php echo $row->id ?>" type="text" style="display:none; width: 100%;"
                                            name="jform[itemLabels][<?php echo $row->id ?>]"
                                            data-cb-last-saved="<?php echo htmlentities($row->label ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            value="<?php echo htmlentities($row->label ?? '', ENT_QUOTES, 'UTF-8') ?>" />

                                        <select class="form-select form-select-sm d-inline-block w-auto cb-item-order-type-select"
                                            id="itemOrderTypes<?php echo $row->id ?>" name="jform[itemOrderTypes][<?php echo $row->id ?>]">
                                            <option value=""> -
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_ORDER_TYPES'); ?> -
                                            </option>
                                            <option value="CHAR" <?php echo $row->order_type == 'CHAR' ? ' selected="selected"' : '' ?>>
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_ORDER_TYPES_TEXT'); ?>
                                            </option>
                                            <option value="DATETIME" <?php echo $row->order_type == 'DATETIME' ? ' selected="selected"' : '' ?>>
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_ORDER_TYPES_DATETIME'); ?>
                                            </option>
                                            <option value="DATE" <?php echo $row->order_type == 'DATE' ? ' selected="selected"' : '' ?>>
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_ORDER_TYPES_DATE'); ?>
                                            </option>
                                            <option value="TIME" <?php echo $row->order_type == 'TIME' ? ' selected="selected"' : '' ?>>
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_ORDER_TYPES_TIME'); ?>
                                            </option>
                                            <option value="UNSIGNED" <?php echo $row->order_type == 'UNSIGNED' ? ' selected="selected"' : '' ?>>
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_ORDER_TYPES_INTEGER'); ?>
                                            </option>
                                            <option value="DECIMAL" <?php echo $row->order_type == 'DECIMAL' ? ' selected="selected"' : '' ?>>
                                                <?php echo Text::_('COM_CONTENTBUILDERNG_ORDER_TYPES_DECIMAL'); ?>
                                            </option>
                                        </select>
                                    </div>

                                </td>
                                <td valign="top">
                                    <?php echo $list_include; ?>
                                </td>
                                <td valign="top">
                                    <?php echo $search_include; ?>
                                </td>
                                <td valign="top">
                                    <?php echo $linkable; ?>
                                </td>
                                <td valign="top">
                                    <?php echo $editable; ?>
                                    <?php
                                    if ($row->editable && !$this->item->edit_by_type) {
                                        echo '<div class="mt-1">[<a href="index.php?option=com_contentbuilderng&amp;view=elementoptions&amp;tmpl=component&amp;element_id=' . $row->id . '&amp;id=' . $this->item->id . '" title="" data-bs-toggle="modal" data-bs-target="#text-type-modal">' . $row->type . '</a>]</div>';
                                    }
                                    ?>
                                </td>
                                <td valign="top">
                                    <input class="form-control form-control-sm cb-wordwrap-input" type="text" size="4" maxlength="4" inputmode="numeric" pattern="[0-9]{0,4}" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,4);"
                                        name="jform[itemWordwrap][<?php echo $row->id ?>]"
                                        value="<?php echo htmlentities($row->wordwrap ?? '', ENT_QUOTES, 'UTF-8') ?>" />
                                </td>
                                <td valign="top">
                                    <input class="form-control form-control-sm w-100" style="width: 150px;" type="text"
                                        name="jform[itemWrapper][<?php echo $row->id ?>]"
                                        value="<?php echo htmlentities($row->item_wrapper ?? '', ENT_QUOTES, 'UTF-8') ?>" />
                                </td>
                                <td valign="top">
                                    <?php echo $published; ?>
                                </td>
                                <td class="order" width="150" valign="top">
                                    <?php
                                    $orderUp = '';
                                    $orderDown = '';
                                    if ($this->pagination) {
                                        $orderUp = (string) $this->pagination->orderUpIcon($i, true, 'form.orderup', 'Move Up', $this->ordering);
                                        $orderDown = (string) $this->pagination->orderDownIcon($i, $n, true, 'form.orderdown', 'Move Down', $this->ordering);
                                    }
                                    ?>
                                    <span class="cb-order-slot">
                                        <?php echo $orderUp !== '' ? $orderUp : '<span class="cb-order-placeholder">•</span>'; ?>
                                    </span>
                                    <span class="cb-order-slot">
                                        <?php echo $orderDown !== '' ? $orderDown : '<span class="cb-order-placeholder">•</span>'; ?>
                                    </span>
                                    <?php $disabled = $this->ordering ? '' : 'disabled="disabled"'; ?>
                                    <input
                                        type="text"
                                        name="jform[order][<?php echo (int) $row->id; ?>]"
                                        size="3"
                                        style="width:30px;text-align:center;margin-left:20px"
                                        value="<?php echo (int) $row->ordering; ?>"
                                        <?php echo $disabled; ?>
                                        class="text_area" />
                                </td>
                            </tr>
                        <?php
                            $k = 1 - $k;
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="11">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">

                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <?php echo $this->pagination ? $this->pagination->getPagesCounter() : ''; ?>
                                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_DISPLAY_NUM'); ?>&nbsp;</span>
                                        <span class="d-inline-block">
                                            <?php echo $this->pagination ? $this->pagination->getLimitBox() : ''; ?>
                                        </span>
                                    </div>

                                    <div>
                                        <?php echo $this->pagination ? $this->pagination->getPagesLinks() : ''; ?>
                                    </div>

                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>

            </td>
        </tr>

        </table>

        <?php
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab9', Text::_('COM_CONTENTBUILDERNG_ADVANCED_OPTIONS'));
        echo $advancedOptionsContent;
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab2', Text::_('COM_CONTENTBUILDERNG_LIST_INTRO_TEXT'));
        ?>
        <h3 class="mb-3">
            <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_INTRO_MODE_TITLE'); ?>
        </h3>
        <?php
        echo $this->form->renderField('intro_text');
        ?>

        <?php
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab1', Text::_('COM_CONTENTBUILDERNG_LIST_STATES'));
        ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_PUBLISHED') ?>
                    </th>
                    <th>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_TITLE') ?>
                    </th>
                    <th>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_COLOR') ?>
                    </th>
                    <th>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_ACTION') ?>
                    </th>
                </tr>
            </thead>
            <?php
            foreach ($this->item->list_states as $state) {
                $k = 0;
                $stateRawColor = (string) ($state['color'] ?? '');
                $previewHex = strtoupper(ltrim(trim($stateRawColor), '#'));
                if (preg_match('/^[0-9A-F]{3}$/', $previewHex)) {
                    $previewHex = $previewHex[0] . $previewHex[0]
                        . $previewHex[1] . $previewHex[1]
                        . $previewHex[2] . $previewHex[2];
                }
                $stateColorStyle = '';
                if (preg_match('/^[0-9A-F]{6}$/', $previewHex)) {
                    $red = hexdec(substr($previewHex, 0, 2));
                    $green = hexdec(substr($previewHex, 2, 2));
                    $blue = hexdec(substr($previewHex, 4, 2));
                    $textColor = ((($red * 299) + ($green * 587) + ($blue * 114)) / 1000) >= 160 ? '#000000' : '#FFFFFF';
                    $stateColorStyle = 'background-color:#' . $previewHex . ';color:' . $textColor . ';';
                }
                $stateColorInputId = 'list_state_color_' . (int) $state['id'];
                $stateColorPickerId = 'list_state_color_picker_' . (int) $state['id'];
                $stateNativePickerValue = preg_match('/^[0-9A-F]{6}$/', $previewHex) ? '#' . $previewHex : '#FFFFFF';
            ?>
                <tr class="<?php echo "row$k"; ?>">
                    <td>
                        <?php echo $renderCheckbox('jform[list_states][' . $state['id'] . '][published]', 'list_state_published_' . $state['id'], (bool) $state['published']); ?>
                    </td>
                    <td>
                        <input class="form-control form-control-sm w-100" type="text"
                            name="jform[list_states][<?php echo $state['id']; ?>][title]"
                            value="<?php echo htmlentities($state['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <input
                                class="form-control form-control-sm w-100"
                                type="text"
                                id="<?php echo $stateColorInputId; ?>"
                                data-cb-color-text="1"
                                data-cb-color-picker-target="<?php echo $stateColorPickerId; ?>"
                                value="<?php echo htmlentities($stateRawColor, ENT_QUOTES, 'UTF-8'); ?>"
                                style="<?php echo $stateColorStyle; ?>"
                                name="jform[list_states][<?php echo $state['id']; ?>][color]" />
                            <input
                                class="form-control form-control-color form-control-sm"
                                type="color"
                                id="<?php echo $stateColorPickerId; ?>"
                                data-cb-color-picker="1"
                                data-cb-color-target="<?php echo $stateColorInputId; ?>"
                                value="<?php echo $stateNativePickerValue; ?>"
                                title="<?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_COLOR'); ?>"
                                aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_COLOR'); ?>"
                                style="width: 3rem; min-width: 3rem; padding: 0.2rem;" />
                        </div>
                    </td>
                    <td>
                        <select class="form-select-sm" name="jform[list_states][<?php echo $state['id']; ?>][action]">
                            <option value=""> -
                                <?php echo Text::_('COM_CONTENTBUILDERNG_NONE'); ?> -
                            </option>
                            <?php
                            foreach ($this->list_states_action_plugins as $list_state_action_plugin) {
                            ?>
                                <option value="<?php echo $list_state_action_plugin; ?>" <?php echo $list_state_action_plugin == $state['action'] ? ' selected="selected"' : ''; ?>>
                                    <?php echo $list_state_action_plugin; ?>
                                </option>
                            <?php
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            <?php
                $k = 1 - $k;
            }
            ?>
        </table>
        <?php
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab3', Text::_('COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY'));

        ?>
        <h3 class="mb-3">
            <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY'); ?>
        </h3>
        <p class="text-muted mb-3">
            <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY_INTRO'); ?>
        </p>
        <div class="alert alert-info mb-3">
            <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_DETAILS_DISPLAY_PERMISSION_HINT'); ?>
        </div>
        <table width="100%" class="table table-striped">
            <tr>
                <td width="20%">
                    <label for="create_sample"><span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE'); ?><span></label>
                </td>
                <td>
                    <input type="hidden" name="jform[create_sample]" id="cb_create_sample_flag" value="0" />
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-1" id="create_sample"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE_TIP'); ?>"
                            aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE_TIP'); ?>"
                            onclick="cbQueueDetailsSampleGeneration(this);">
                            <span class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></span>
                            <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE'); ?>
                        </button>
                        <small id="cb_create_sample_hint" class="text-success d-none">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_INITIALISE_WILL_APPLY_ON_SAVE'); ?>
                        </small>
                    </div>
                </td>
                <td width="20%">
                    <div class="mb-2">
                        <label for="create_articles_yes"><span class="editlinktip hasTip"
                                title="<?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TIP'); ?>">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_ARTICLES_LABEL'); ?>
                            </span></label>
                    </div>
                    <div class="mb-1">
                        <label for="delete_articles"><span class="editlinktip hasTip"
                                title="<?php echo Text::_('COM_CONTENTBUILDERNG_DELETE_ARTICLES_TIP'); ?>">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_DELETE_ARTICLES'); ?>
                            </span></label>
                    </div>
                </td>
                <td>
                    <div class="mb-2">
                        <input class="form-check-input" type="radio" value="1" name="jform[create_articles]" id="create_articles_yes"
                            <?php echo (int) $this->item->create_articles === 1 ? ' checked="checked"' : ''; ?> />
                        <label for="create_articles_yes">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_YES'); ?>
                        </label>
                        <input class="form-check-input" type="radio" value="0" name="jform[create_articles]" id="create_articles_no"
                            <?php echo (int) $this->item->create_articles !== 1 ? ' checked="checked"' : ''; ?> />
                        <label for="create_articles_no">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_NO'); ?>
                        </label>
                    </div>
                    <input class="form-check-input" type="radio" value="1" name="jform[delete_articles]" id="delete_articles"
                        <?php echo $this->item->delete_articles ? ' checked="checked"' : '' ?> /> <label
                        for="delete_articles">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_YES'); ?>
                    </label>
                    <input class="form-check-input" type="radio" value="0" name="jform[delete_articles]"
                        id="delete_articles_no" <?php echo !$this->item->delete_articles ? ' checked="checked"' : '' ?> /> <label for="delete_articles_no">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_NO'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <td width="20%">
                    <label for="title_field"><span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_TITLE_FIELD_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_TITLE_FIELD'); ?>
                        </span></label>
                </td>
                <td>
                    <select class="form-select-sm" name="jform[title_field]" id="title_field">
                        <option value="0">
                            - <?php echo Text::_('COM_CONTENTBUILDERNG_NONE'); ?> -
                        </option>
                        <?php
                        foreach ($this->all_elements as $sortable) {
                        ?>
                            <option value="<?php echo $sortable->reference_id; ?>" <?php echo $this->item->title_field == $sortable->reference_id ? ' selected="selected"' : ''; ?>>
                                <?php echo htmlentities($sortable->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php
                        }
                        ?>
                    </select>
                </td>
                <td width="20%">
                    <label for="default_category"><span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_CATEGORY_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_CATEGORY'); ?>
                        </span></label>
                </td>
                <td>
                    <?php
                    ?>
                    <select class="form-select-sm" id="default_category" name="jform[sectioncategories]">
                        <?php
                        foreach ($this->item->sectioncategories as $category) {
                        ?>
                            <option <?php echo $this->item->default_category == $category->value ? ' selected="selected"' : '' ?>value="<?php echo $category->value; ?>">
                                <?php echo htmlentities($category->text ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php
                        }
                        ?>
                    </select>
                    <?php
                    ?>
                </td>
            </tr>
            <tr>
                <td width="20%" valign="top">
                    <label for="default_lang_code"><span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_LANG_CODE_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_LANG_CODE'); ?>
                        </span></label>
                </td>
                <td valign="top">
                    <select class="form-select-sm" name="jform[default_lang_code]" id="default_lang_code">
                        <option value="*">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_ANY'); ?>
                        </option>
                        <?php
                        foreach ($this->item->language_codes as $lang_code) {
                        ?>
                            <option value="<?php echo $lang_code ?>" <?php echo $lang_code == $this->item->default_lang_code ? ' selected="selected"' : ''; ?>>
                                <?php echo $lang_code; ?>
                            </option>
                        <?php
                        }
                        ?>
                    </select>
                    <br /><br />
                    <label for="article_record_impact_language"><span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_ARTICLE_RECORD_IMPACT_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_ARTICLE_RECORD_IMPACT'); ?>
                        </span></label>
                    <input class="form-check-input" <?php echo $this->item->article_record_impact_language ? 'checked="checked" ' : '' ?>type="radio" name="jform[article_record_impact_language]"
                        id="article_record_impact_language" value="1" />
                    <label for="article_record_impact_language_yes">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_YES'); ?>
                    </label>
                    <input class="form-check-input" <?php echo !$this->item->article_record_impact_language ? 'checked="checked" ' : '' ?>type="radio" name="jform[article_record_impact_language]"
                        id="article_record_impact_language_no" value="0" />
                    <label for="article_record_impact_language_no">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_NO'); ?>
                    </label>
                </td>
                <td width="20%" valign="top">
                    <label for="default_lang_code_ignore_yes"><span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_LANG_CODE_IGNORE_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_LANG_CODE_IGNORE'); ?>
                        </span></label>
                </td>
                <td valign="top">
                    <input class="form-check-input" <?php echo $this->item->default_lang_code_ignore ? 'checked="checked" ' : '' ?>type="radio" name="jform[default_lang_code_ignore]"
                        id="default_lang_code_ignore_yes" value="1" />
                    <label for="default_lang_code_ignore_yes">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_YES'); ?>
                    </label>

                    <input class="form-check-input" <?php echo !$this->item->default_lang_code_ignore ? 'checked="checked" ' : '' ?>type="radio" name="jform[default_lang_code_ignore]"
                        id="default_lang_code_ignore_no" value="0" />
                    <label for="default_lang_code_ignore_no">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_NO'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <td width="20%" valign="top">
                    <label for="default_publish_up_days"><span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_PUBLISH_UP_DAYS_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_PUBLISH_UP_DAYS'); ?>
                        </span></label>
                </td>
                <td valign="top">
                    <input class="form-control form-control-sm w-100" type="text" name="jform[default_publish_up_days]"
                        id="default_publish_up_days" value="<?php echo $this->item->default_publish_up_days; ?>" />
                    <br /><br />
                    <label for="article_record_impact_publish"><span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_ARTICLE_RECORD_PUBLISH_IMPACT_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_ARTICLE_RECORD_PUBLISH_IMPACT'); ?>
                        </span></label>
                    <input class="form-check-input" <?php echo $this->item->article_record_impact_publish ? 'checked="checked" ' : '' ?>type="radio" name="jform[article_record_impact_publish]"
                        id="article_record_impact_publish" value="1" />
                    <label for="article_record_impact_publish_yes">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_YES'); ?>
                    </label>
                    <input class="form-check-input" <?php echo !$this->item->article_record_impact_publish ? 'checked="checked" ' : '' ?>type="radio" name="jform[article_record_impact_publish]"
                        id="article_record_impact_publish_no" value="0" />
                    <label for="article_record_impact_publish_no">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_NO'); ?>
                    </label>

                </td>
                <td width="20%" valign="top">
                    <label for="default_publish_down_days"><span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_PUBLISH_DOWN_DAYS_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_PUBLISH_DOWN_DAYS'); ?>
                        </span></label>
                </td>
                <td valign="top">
                    <input class="form-control form-control-sm w-100" type="text" name="jform[default_publish_down_days]"
                        id="default_publish_down_days" value="<?php echo $this->item->default_publish_down_days; ?>" />
                </td>

            </tr>
            <tr>
                <td width="20%">
                    <label for="default_access"><span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_ACCESS_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_ACCESS'); ?>
                        </span></label>
                </td>
                <td>
                    <?php
                    ?>
                    <?php echo HTMLHelper::_('access.level', 'default_access', $this->item->default_access, '', array(), 'default_access'); ?>
                    <?php
                    ?>
                </td>
                <td width="20%">
                    <label for="default_featured"><span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_FEATURED_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_DEFAULT_FEATURED'); ?>
                        </span></label>
                </td>
                <td>
                    <input class="form-check-input" class="form-check-input" <?php echo $this->item->default_featured ? 'checked="checked" ' : '' ?>type="radio" name="jform[default_featured]" id="default_featured"
                        value="1" />
                    <label for="default_featured">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_YES'); ?>
                    </label>

                    <input class="form-check-input" class="form-check-input" <?php echo !$this->item->default_featured ? 'checked="checked" ' : '' ?>type="radio" name="jform[default_featured]" id="default_featured_no"
                        value="0" />
                    <label for="default_featured_no">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_NO'); ?>
                    </label>

                </td>
            </tr>
            <tr>
                <td width="20%">
                    <label for="auto_publish"><span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_AUTO_PUBLISH_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_AUTO_PUBLISH'); ?>
                        </span></label>
                </td>
                <td>
                    <input type="hidden" name="jform[auto_publish]" value="0" />
                    <?php echo $renderCheckbox('jform[auto_publish]', 'auto_publish', (int) $this->item->auto_publish === 1); ?>
                </td>
                <td width="20%">
                    <?php
                    if ($this->item->edit_by_type && $isBreezingFormsType) {
                    ?>
                        <label for="protect_upload_directory"><span class="editlinktip hasTip"
                                title="<?php echo Text::_('COM_CONTENTBUILDERNG_UPLOAD_DIRECTORY_TYPE_TIP'); ?>">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_PROTECT_UPLOAD_DIRECTORY'); ?>
                            </span></label>
                    <?php
                    }
                    ?>
                </td>
                <td>
                    <?php
                    if ($this->item->edit_by_type && $isBreezingFormsType) {
                    ?>
                        <input type="hidden" name="jform[protect_upload_directory]" value="0" />
                        <?php echo $renderCheckbox('jform[protect_upload_directory]', 'protect_upload_directory', trim((string) $this->item->protect_upload_directory) !== ''); ?>
                    <?php
                    }
                    ?>
                </td>
            </tr>
        </table>

        <?php
        echo $this->form->renderField('details_template');
        //        $editor = Editor::getInstance(Factory::getApplication()->get('editor'));
        //        echo $editor->display('details_template', $this->item->details_template, '100%', '550', '75', '20', true, 'details_template');
        ?>
        <hr />
        <h3 class="mb-3">
            <?php echo Text::_('COM_CONTENTBUILDERNG_DETAILS_PREPARE_MODE_TITLE'); ?>
        </h3>
        <?php
        if (trim($this->item->details_prepare ?? '') == '') {
            $this->item->details_prepare = '// Ici, vous pouvez modifier les libellés et les valeurs de chaque élément avant le rendu du template détail.' . "\n";
        }
        $prepareExamplesText = <<<'TXT'
// Ici, vous pouvez modifier les libellés et les valeurs de chaque élément avant le rendu du template d'édition.

// Adaptez la valeur et le libellé avec du code PHP.
// Les données sont stockées dans le tableau $items.

// Exemple : la valeur du champ "NAME" sera affichée en majuscules, en gras et en rouge.
$items["NAME"]["value"] = strtoupper((string) $items["NAME"]["value"]);
$items["NAME"]["value"] = "<b>" . $items["NAME"]["value"] . "</b>";
$items["NAME"]["value"] = "<span style=\"color:#dc3545\">" . $items["NAME"]["value"] . "</span>";

// Exemple : la valeur du champ "COUNT" sera affichée en rouge si elle est < 0.
$items["COUNT"]["value"] = (is_numeric((string) $items["COUNT"]["value"]) && (float) $items["COUNT"]["value"] < 0)
    ? "<span style=\"color:#dc3545\">" . $items["COUNT"]["value"] . "</span>"
    : $items["COUNT"]["value"];

// Exemple : ajouter la date courante à un champ de libellé.
$items["DATE_LABEL"]["label"] = (string) $items["DATE_LABEL"]["label"] . " (" . date("Y-m-d") . ")";
TXT;

        ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3 cb-prepare-tools">
            <label class="form-label mb-0" for="cb_details_prepare_snippet_select">
                <?php echo Text::_('COM_CONTENTBUILDERNG_EDITABLE_PREPARE_SNIPPET_LABEL'); ?>
            </label>
            <select class="form-select form-select-sm cb-snippet-select" id="cb_details_prepare_snippet_select">
                <?php if (!empty($editablePrepareSnippetOptions)) : ?>
                    <option value=""><?php echo Text::_('COM_CONTENTBUILDERNG_EDITABLE_PREPARE_SNIPPET_PLACEHOLDER'); ?></option>
                    <?php foreach ($editablePrepareSnippetOptions as $snippetOption) : ?>
                        <option value="<?php echo htmlspecialchars((string) ($snippetOption['item_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars((string) ($snippetOption['text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                <?php else : ?>
                    <option value=""><?php echo Text::_('COM_CONTENTBUILDERNG_EDITABLE_PREPARE_SNIPPET_EMPTY'); ?></option>
                <?php endif; ?>
            </select>
            <span class="d-inline-flex align-items-center gap-2">
                <span class="form-check form-check-inline mb-0">
                    <input class="form-check-input" type="radio" name="cb_details_prepare_slot" id="cb_details_prepare_slot_value" value="value" checked="checked" <?php echo empty($editablePrepareSnippetOptions) ? 'disabled="disabled"' : ''; ?> />
                    <label class="form-check-label" for="cb_details_prepare_slot_value"><?php echo Text::_('COM_CONTENTBUILDERNG_PREPARE_TARGET_VALUE_OPTION'); ?></label>
                </span>
                <span class="form-check form-check-inline mb-0">
                    <input class="form-check-input" type="radio" name="cb_details_prepare_slot" id="cb_details_prepare_slot_label" value="label" <?php echo empty($editablePrepareSnippetOptions) ? 'disabled="disabled"' : ''; ?> />
                    <label class="form-check-label" for="cb_details_prepare_slot_label"><?php echo Text::_('COM_CONTENTBUILDERNG_PREPARE_TARGET_LABEL_OPTION'); ?></label>
                </span>
            </span>
            <label class="form-label mb-0" for="cb_details_prepare_effect_select">
                <?php echo Text::_('COM_CONTENTBUILDERNG_PREPARE_EFFECT_LABEL'); ?>
            </label>
            <select class="form-select form-select-sm cb-effect-select" id="cb_details_prepare_effect_select" <?php echo empty($editablePrepareSnippetOptions) ? 'disabled="disabled"' : ''; ?>>
                <?php foreach ($prepareEffectOptions as $effectOption) : ?>
                    <option value="<?php echo htmlspecialchars((string) ($effectOption['value'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string) ($effectOption['text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary text-nowrap"
                id="cb_add_details_prepare_snippet"
                onclick="cbInsertDetailsPrepareSnippet();"
                <?php echo empty($editablePrepareSnippetOptions) ? 'disabled="disabled"' : ''; ?>>
                <?php echo Text::_('COM_CONTENTBUILDERNG_DETAILS_PREPARE_SNIPPET_ADD'); ?>
            </button>
            <button
                type="button"
                class="btn btn-sm px-2"
                data-bs-toggle="tooltip"
                data-bs-placement="top"
                data-bs-title="<?php echo Text::_('COM_CONTENTBUILDERNG_PREPARE_EXAMPLES_BUTTON_TIP'); ?>"
                aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_PREPARE_EXAMPLES_BUTTON_TIP'); ?>"
                onclick="cbOpenPrepareExamples();">
                <span class="fa-solid fa-circle-question" aria-hidden="true"></span>
            </button>
            <small id="cb_details_prepare_snippet_hint" class="text-success d-none">
                <?php echo Text::_('COM_CONTENTBUILDERNG_EDITABLE_PREPARE_SNIPPET_HINT'); ?>
            </small>
        </div>
        <div class="modal fade" id="cb-prepare-examples-modal" tabindex="-1" aria-labelledby="cb-prepare-examples-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cb-prepare-examples-modal-label"><?php echo Text::_('COM_CONTENTBUILDERNG_PREPARE_EXAMPLES_TITLE'); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
                    </div>
                    <div class="modal-body">
                        <pre class="mb-0"><code><?php echo htmlspecialchars($prepareExamplesText, ENT_QUOTES, 'UTF-8'); ?></code></pre>
                    </div>
                </div>
            </div>
        </div>
        <?php

        $params = array('syntax' => 'php');
        $editor = Editor::getInstance('codemirror');
        echo $editor->display(
            'jform[details_prepare]',
            (string) ($this->item->details_prepare ?? ''),
            '100%',
            '550',
            '75',
            '20',
            false,
            'jform_details_prepare',
            null,
            null,
            $params
        );

        //echo '<textarea name="jform[details_prepare]" style="width:100%;height: 500px;">'.htmlentities($this->item->details_prepare, ENT_QUOTES, 'UTF-8').'</textarea>';
        ?>
        <?php
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab5', Text::_('COM_CONTENTBUILDERNG_TAB_EDIT_DISPLAY'));
        ?>
        <h3 class="mb-3">
            <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_EDIT_DISPLAY'); ?>
        </h3>
        <p class="text-muted mb-3">
            <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_EDIT_DISPLAY_INTRO'); ?>
        </p>
        <div class="alert alert-info mb-3">
            <?php echo Text::_('COM_CONTENTBUILDERNG_TAB_EDIT_DISPLAY_PERMISSION_HINT'); ?>
        </div>
        <input type="hidden" name="jform[edit_by_type]" value="0" />
        <?php if ($canEditByType) : ?>
            <div class="form-check mb-3">
                <?php echo $renderCheckbox('jform[edit_by_type]', 'edit_by_type', (bool) $this->item->edit_by_type); ?>
                <label class="form-check-label" for="edit_by_type">
                    <span class="editlinktip hasTip" title="<?php echo Text::_('COM_CONTENTBUILDERNG_TYPE_EDIT_TIP'); ?>">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_TYPE_EDIT'); ?>
                    </span>
                </label>
            </div>
        <?php endif; ?>
        <?php

        if ($this->item->edit_by_type && $isBreezingFormsType) {
            echo $breezingFormsProvidedMessage;
            echo '<input type="hidden" name="jform[editable_template]" value="' . htmlspecialchars($breezingFormsEditableToken, ENT_QUOTES, 'UTF-8') . '"/>';
            //echo '<input type="hidden" name="jform[protect_upload_directory]" value="'.(trim($this->item->protect_upload_directory) ? 1 : 0).'"/>'; 
            echo '<input type="hidden" name="jform[upload_directory]" value="' . (trim($this->item->upload_directory) ? trim($this->item->upload_directory) : JPATH_SITE . '/media/com_contentbuilderng/upload') . '"/>';
        } else {
        ?>

            <input type="hidden" name="jform[protect_upload_directory]" value="0" />
            <div class="cb-upload-box">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-8">
                        <label for="upload_directory" class="form-label mb-2"><span class="editlinktip hasTip"
                                title="<?php echo Text::_('COM_CONTENTBUILDERNG_UPLOAD_DIRECTORY_TIP'); ?>">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_UPLOAD_DIRECTORY'); ?>
                            </span></label>
                        <input class="form-control form-control-sm" type="text"
                            value="<?php echo trim($this->item->upload_directory) ? trim($this->item->upload_directory) : JPATH_SITE . '/media/com_contentbuilderng/upload'; ?>"
                            name="jform[upload_directory]" id="upload_directory" />
                    </div>
                    <div class="col-lg-auto">
                        <div class="form-check mb-1">
                            <?php echo $renderCheckbox('jform[protect_upload_directory]', 'protect_upload_directory', trim((string) $this->item->protect_upload_directory) !== ''); ?>
                            <label class="form-check-label" for="protect_upload_directory">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_PROTECT_UPLOAD_DIRECTORY'); ?>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <input type="hidden" name="jform[create_editable_sample]" id="cb_create_editable_sample_flag" value="0" />
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-1" id="create_editable_sample"
                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE_TIP'); ?>"
                    aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE_TIP'); ?>"
                    onclick="cbQueueEditableSampleGeneration(this);">
                    <span class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></span>
                    <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_TEMPLATE'); ?>
                </button>
                <small id="cb_create_editable_sample_hint" class="text-success d-none">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_INITIALISE_WILL_APPLY_ON_SAVE'); ?>
                </small>
            </div>
            <br />
            <br />
        <?php
            echo $this->form->renderField('editable_template');
            //      $editor = Editor::getInstance(Factory::getApplication()->get('editor'));
            //      echo $editor->display('editable_template', $this->item->editable_template, '100%', '550', '75', '20', true, 'editable_template');
        }
        ?>
        <hr />
        <h3 class="mb-3">
            <?php echo Text::_('COM_CONTENTBUILDERNG_DETAILS_PREPARE_MODE_TITLE'); ?>
        </h3>
        <?php

        if ($this->item->edit_by_type) {
            echo $breezingFormsProvidedMessage;
            echo '<input type="hidden" name="jform[editable_prepare]" value="' . htmlentities($this->item->editable_prepare ?? '', ENT_QUOTES, 'UTF-8') . '"/>';
        } else {
            if (trim($this->item->editable_prepare ?? '') == '') {
                $this->item->editable_prepare = '// Ici, vous pouvez modifier les libellés et les valeurs de chaque élément avant le rendu du template d\'édition.' . "\n";
            }

        ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3 cb-prepare-tools">
                <label class="form-label mb-0" for="cb_editable_prepare_snippet_select">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_EDITABLE_PREPARE_SNIPPET_LABEL'); ?>
                </label>
                <select class="form-select form-select-sm cb-snippet-select" id="cb_editable_prepare_snippet_select">
                    <?php if (!empty($editablePrepareSnippetOptions)) : ?>
                        <option value=""><?php echo Text::_('COM_CONTENTBUILDERNG_EDITABLE_PREPARE_SNIPPET_PLACEHOLDER'); ?></option>
                        <?php foreach ($editablePrepareSnippetOptions as $snippetOption) : ?>
                            <option value="<?php echo htmlspecialchars((string) ($snippetOption['item_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars((string) ($snippetOption['text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <option value=""><?php echo Text::_('COM_CONTENTBUILDERNG_EDITABLE_PREPARE_SNIPPET_EMPTY'); ?></option>
                    <?php endif; ?>
                </select>
                <span class="d-inline-flex align-items-center gap-2">
                    <span class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="radio" name="cb_editable_prepare_slot" id="cb_editable_prepare_slot_value" value="value" checked="checked" <?php echo empty($editablePrepareSnippetOptions) ? 'disabled="disabled"' : ''; ?> />
                        <label class="form-check-label" for="cb_editable_prepare_slot_value"><?php echo Text::_('COM_CONTENTBUILDERNG_PREPARE_TARGET_VALUE_OPTION'); ?></label>
                    </span>
                    <span class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="radio" name="cb_editable_prepare_slot" id="cb_editable_prepare_slot_label" value="label" <?php echo empty($editablePrepareSnippetOptions) ? 'disabled="disabled"' : ''; ?> />
                        <label class="form-check-label" for="cb_editable_prepare_slot_label"><?php echo Text::_('COM_CONTENTBUILDERNG_PREPARE_TARGET_LABEL_OPTION'); ?></label>
                    </span>
                </span>
                <label class="form-label mb-0" for="cb_editable_prepare_effect_select">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_PREPARE_EFFECT_LABEL'); ?>
                </label>
                <select class="form-select form-select-sm cb-effect-select" id="cb_editable_prepare_effect_select" <?php echo empty($editablePrepareSnippetOptions) ? 'disabled="disabled"' : ''; ?>>
                    <?php foreach ($prepareEffectOptions as $effectOption) : ?>
                        <option value="<?php echo htmlspecialchars((string) ($effectOption['value'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars((string) ($effectOption['text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary text-nowrap"
                    id="cb_add_editable_prepare_snippet"
                    onclick="cbInsertEditablePrepareSnippet();"
                    <?php echo empty($editablePrepareSnippetOptions) ? 'disabled="disabled"' : ''; ?>>
                    <?php echo Text::_('COM_CONTENTBUILDERNG_EDITABLE_PREPARE_SNIPPET_ADD'); ?>
                </button>
                <button
                    type="button"
                    class="btn btn-sm px-2"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    data-bs-title="<?php echo Text::_('COM_CONTENTBUILDERNG_PREPARE_EXAMPLES_BUTTON_TIP'); ?>"
                    aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_PREPARE_EXAMPLES_BUTTON_TIP'); ?>"
                    onclick="cbOpenPrepareExamples();">
                    <span class="fa-solid fa-circle-question" aria-hidden="true"></span>
                </button>
                <small id="cb_editable_prepare_snippet_hint" class="text-success d-none">
                    <?php echo Text::_('COM_CONTENTBUILDERNG_EDITABLE_PREPARE_SNIPPET_HINT'); ?>
                </small>
            </div>
        <?php

            $params = array('syntax' => 'php');
            $editor = Editor::getInstance('codemirror');
            echo $editor->display(
                'jform[editable_prepare]',
                (string) ($this->item->editable_prepare ?? ''),
                '100%',
                '550',
                '75',
                '20',
                false,
                'jform_editable_prepare',
                null,
                null,
                $params
            );
        }

        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab6', Text::_('COM_CONTENTBUILDERNG_API_TAB_TITLE'));
        ?>
        <h3 class="mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_API_TAB_TITLE'); ?></h3>
        <p class="text-muted mb-3">
            <?php echo Text::_('COM_CONTENTBUILDERNG_API_TAB_INTRO'); ?>
        </p>
        <div class="alert alert-info mb-3">
            <?php echo Text::_('COM_CONTENTBUILDERNG_API_TAB_PERMISSION_HINT'); ?>
        </div>
        <table class="table table-striped">
            <tr>
                <th style="width:180px;"><?php echo Text::_('COM_CONTENTBUILDERNG_API_METHOD'); ?></th>
                <th><?php echo Text::_('COM_CONTENTBUILDERNG_API_ENDPOINT'); ?></th>
                <th><?php echo Text::_('COM_CONTENTBUILDERNG_API_DESCRIPTION'); ?></th>
            </tr>
            <tr>
                <td><code>GET</code></td>
                <td>
                    <a href="<?php echo htmlspecialchars($apiExampleDetailUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                        <code><?php echo htmlspecialchars($apiExampleDetailDisplayUrl, ENT_QUOTES, 'UTF-8'); ?></code>
                    </a>
                </td>
                <td><?php echo Text::_('COM_CONTENTBUILDERNG_API_GET_DETAIL_DESC'); ?></td>
            </tr>
            <tr>
                <td><code>GET</code></td>
                <td>
                    <a href="<?php echo htmlspecialchars($apiExampleListUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                        <code><?php echo htmlspecialchars($apiExampleListDisplayUrl, ENT_QUOTES, 'UTF-8'); ?></code>
                    </a>
                </td>
                <td><?php echo Text::_('COM_CONTENTBUILDERNG_API_GET_LIST_DESC'); ?></td>
            </tr>
            <tr>
                <td><code>PUT</code> / <code>PATCH</code> / <code>POST</code></td>
                <td>
                    <a href="<?php echo htmlspecialchars($apiExampleUpdateUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                        <code><?php echo htmlspecialchars($apiExampleUpdateUrl, ENT_QUOTES, 'UTF-8'); ?></code>
                    </a>
                </td>
                <td><?php echo Text::_('COM_CONTENTBUILDERNG_API_UPDATE_DESC'); ?></td>
            </tr>
        </table>
        <div class="alert alert-secondary py-2 mb-3">
            <strong><?php echo Text::_('COM_CONTENTBUILDERNG_API_VERBOSE_OPTION_TITLE'); ?></strong>
            <?php echo Text::_('COM_CONTENTBUILDERNG_API_VERBOSE_OPTION_TEXT'); ?>
            <a href="<?php echo htmlspecialchars($apiExampleVerboseUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                <code><?php echo htmlspecialchars($apiExampleVerboseDisplayUrl, ENT_QUOTES, 'UTF-8'); ?></code>
            </a>
        </div>
        <label for="cb_api_example_payload" class="form-label"><strong><?php echo Text::_('COM_CONTENTBUILDERNG_API_JSON_LABEL'); ?></strong></label>
        <textarea id="cb_api_example_payload" class="form-control" rows="7" readonly="readonly"><?php echo htmlspecialchars($apiExamplePayloadJson, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <?php
        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab7', Text::_('COM_CONTENTBUILDERNG_EMAIL_TEMPLATES'));
        ?>
        <h3 class="mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_TEMPLATES'); ?></h3>
        <p class="text-muted mb-3">
            <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_TAB_INTRO'); ?>
        </p>
        <div class="alert alert-info mb-3">
            <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_TAB_PERMISSION_HINT'); ?>
        </div>
        <div class="border rounded-3 p-3 mb-3 bg-body-tertiary">
            <div class="row g-3 align-items-start">
                <div class="col-lg-4 d-flex align-items-start gap-2">
                    <input type="hidden" name="jform[email_notifications]" value="0" />
                    <?php echo $renderCheckbox('jform[email_notifications]', 'email_notifications', (bool) $this->item->email_notifications); ?>
                    <label class="form-check-label" for="email_notifications">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_TYPE_EMAIL_NOTIFICATIONS'); ?>
                    </label>
                </div>
                <div class="col-lg-8">
                    <small class="text-muted"><?php echo Text::_('COM_CONTENTBUILDERNG_TYPE_EMAIL_NOTIFICATIONS_DESC'); ?></small>
                </div>
                <div class="col-lg-4 d-flex align-items-start gap-2">
                    <input type="hidden" name="jform[email_update_notifications]" value="0" />
                    <?php echo $renderCheckbox('jform[email_update_notifications]', 'email_update_notifications', (bool) $this->item->email_update_notifications); ?>
                    <label class="form-check-label" for="email_update_notifications">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_TYPE_EMAIL_UPDATE_NOTIFICATIONS'); ?>
                    </label>
                </div>
                <div class="col-lg-8">
                    <small class="text-muted"><?php echo Text::_('COM_CONTENTBUILDERNG_TYPE_EMAIL_UPDATE_NOTIFICATIONS_DESC'); ?></small>
                </div>
            </div>
        </div>
        <?php

        if ($this->item->edit_by_type) {
            echo $breezingFormsProvidedMessage;
            echo '<input type="hidden" name="jform[email_admin_template]" value="' . htmlentities($this->item->email_admin_template ?? '', ENT_QUOTES, 'UTF-8') . '"/>';
            echo '<input type="hidden" name="jform[email_template]" value="' . htmlentities($this->item->email_template ?? '', ENT_QUOTES, 'UTF-8') . '"/>';
            echo '<input type="hidden" name="jform[email_admin_subject]" value="' . htmlentities($this->item->email_admin_subject ?? '', ENT_QUOTES, 'UTF-8') . '"/>';
            echo '<input type="hidden" name="jform[email_admin_alternative_from]" value="' . htmlentities($this->item->email_admin_alternative_from ?? '', ENT_QUOTES, 'UTF-8') . '"/>';
            echo '<input type="hidden" name="jform[email_admin_alternative_fromname]" value="' . htmlentities($this->item->email_admin_alternative_fromname ?? '', ENT_QUOTES, 'UTF-8') . '"/>';
            echo '<input type="hidden" name="jform[email_admin_recipients]" value="' . htmlentities($this->item->email_admin_recipients ?? '', ENT_QUOTES, 'UTF-8') . '"/>';
            echo '<input type="hidden" name="jform[email_admin_recipients_attach_uploads]" value="' . htmlentities($this->item->email_admin_recipients_attach_uploads ?? '', ENT_QUOTES, 'UTF-8') . '"/>';
            echo '<input type="hidden" name="jform[email_admin_html]" value="' . htmlentities($this->item->email_admin_html ?? '', ENT_QUOTES, 'UTF-8') . '"/>';

            echo '<input type="hidden" name="jform[email_subject]" value="' . htmlentities($this->item->email_subject ?? '', ENT_QUOTES, 'UTF-8') . '"/>';
            echo '<input type="hidden" name="jform[email_alternative_from]" value="' . htmlentities($this->item->email_alternative_from ?? '', ENT_QUOTES, 'UTF-8') . '"/>';
            echo '<input type="hidden" name="jform[email_alternative_fromname]" value="' . htmlentities($this->item->email_alternative_fromname ?? '', ENT_QUOTES, 'UTF-8') . '"/>';
            echo '<input type="hidden" name="jform[email_recipients]" value="' . htmlentities($this->item->email_recipients ?? '', ENT_QUOTES, 'UTF-8') . '"/>';
            echo '<input type="hidden" name="jform[email_recipients_attach_uploads]" value="' . htmlentities($this->item->email_recipients_attach_uploads ?? '', ENT_QUOTES, 'UTF-8') . '"/>';
            echo '<input type="hidden" name="jform[email_html]" value="' . htmlentities($this->item->email_html ?? '', ENT_QUOTES, 'UTF-8') . '"/>';
        } else {

            $title = Text::_('COM_CONTENTBUILDERNG_EMAIL_ADMINS');

        ?>
            <div id="email_admins" style="cursor:pointer; width: 100%; background-color: var(--bs-body-bg);"
                onclick="if(document.adminForm.email_admins.value=='none'){document.adminForm.email_admins.value='';document.getElementById('email_admins_div').style.display='';}else{document.adminForm.email_admins.value='none';document.getElementById('email_admins_div').style.display='none';}">
                <h3>
                    <?php echo $title; ?>
                </h3>
            </div>
            <div id="email_admins_div"
                style="display:<?php echo $session->get('email_admins', '', 'com_contentbuilderng'); ?>">
                <table width="100%" class="table table-striped">
                    <tr>
                        <td width="20%">
                            <label for="email_admin_subject"><span class="editlinktip hasTip"
                                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_SUBJECT_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_SUBJECT'); ?>
                                </span></label>
                        </td>
                        <td>
                            <input class="form-control form-control-sm w-100" id="email_admin_subject" type="text"
                                name="jform[email_admin_subject]"
                                value="<?php echo htmlentities($this->item->email_admin_subject ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                        </td>
                        <td width="20%">
                            <label for="email_admin_alternative_from">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_ALTERNATIVE_FROM'); ?>
                            </label>
                        </td>
                        <td>
                            <input class="form-control form-control-sm w-100" id="email_admin_alternative_from" type="text"
                                name="jform[email_admin_alternative_from]"
                                value="<?php echo htmlentities($this->item->email_admin_alternative_from ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <td width="20%">
                            <label for="email_admin_alternative_fromname">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_ALTERNATIVE_FROMNAME'); ?>
                            </label>
                        </td>
                        <td>
                            <input class="form-control form-control-sm w-100" id="email_admin_alternative_fromname"
                                type="text" name="jform[email_admin_alternative_fromname]"
                                value="<?php echo htmlentities($this->item->email_admin_alternative_fromname ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                        </td>
                        <td width="20%">
                            <label for="email_admin_recipients"><span class="editlinktip hasTip"
                                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_RECIPIENTS_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_RECIPIENTS'); ?>
                                </span></label>
                        </td>
                        <td>
                            <input class="form-control form-control-sm w-100" id="email_admin_recipients" type="text"
                                name="jform[email_admin_recipients]"
                                value="<?php echo htmlentities($this->item->email_admin_recipients ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <td width="20%">
                            <label for="email_admin_recipients_attach_uploads"><span class="editlinktip hasTip"
                                    title="<?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_ATTACH_UPLOADS_TIP'); ?>">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_ATTACH_UPLOADS'); ?>
                                </span></label>
                        </td>
                        <td>
                            <input class="form-control form-control-sm w-100" id="email_admin_recipients_attach_uploads"
                                type="text" name="jform[email_admin_recipients_attach_uploads]"
                                value="<?php echo htmlentities($this->item->email_admin_recipients_attach_uploads ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                        </td>
                        <td width="20%">
                            <label for="email_admin_html">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_HTML'); ?>
                            </label>
                        </td>
                        <td>
                            <input type="hidden" name="jform[email_admin_html]" value="0" />
                            <?php echo $renderCheckbox('jform[email_admin_html]', 'email_admin_html', (bool) $this->item->email_admin_html); ?>
                        </td>
                    </tr>
                    <tr>
                        <td width="20%">
                            <label for="email_admin_create_sample_button">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_EMAIL_TEMPLATE'); ?>
                            </label>
                        </td>
                        <td>
                            <input type="hidden" name="jform[email_admin_create_sample]" id="cb_email_admin_create_sample_flag" value="0" />
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="email_admin_create_sample_button"
                                    onclick="cbQueueEmailAdminSampleGeneration(this);">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_EMAIL_TEMPLATE'); ?>
                                </button>
                                <small id="cb_email_admin_create_sample_hint" class="text-success d-none">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_INITIALISE_WILL_APPLY_ON_SAVE'); ?>
                                </small>
                            </div>
                        </td>
                        <td width="20%">
                        </td>
                        <td>
                        </td>
                    </tr>
                </table>

                <?php
                $params = array('syntax' => 'html');
                echo $this->form->renderField('email_admin_template');
                ?>
            </div>
            <?php

            $title = Text::_('COM_CONTENTBUILDERNG_EMAIL_USERS');

            ?>
            <div id="email_users" style="cursor:pointer; width: 100%; background-color: var(--bs-body-bg);">
                <h3>
                    <?php echo $title; ?>
                </h3>
            </div>
            <div id="email_users_div">
                <table width="100%" class="table table-striped">
                    <tr>
                        <td width="20%">
                            <label for="email_subject">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_SUBJECT'); ?>
                            </label>
                        </td>
                        <td>
                            <input class="form-control form-control-sm w-100" id="email_subject" type="text"
                                name="jform[email_subject]"
                                value="<?php echo htmlentities($this->item->email_subject ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                        </td>
                        <td width="20%">
                            <label for="email_alternative_from">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_ALTERNATIVE_FROM'); ?>
                            </label>
                        </td>
                        <td>
                            <input class="form-control form-control-sm w-100" id="email_alternative_from" type="text"
                                name="jform[email_alternative_from]"
                                value="<?php echo htmlentities($this->item->email_alternative_from ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <td width="20%">
                            <label for="email_alternative_fromname">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_ALTERNATIVE_FROMNAME'); ?>
                            </label>
                        </td>
                        <td>
                            <input class="form-control form-control-sm w-100" id="email_alternative_fromname" type="text"
                                name="jform[email_alternative_fromname]"
                                value="<?php echo htmlentities($this->item->email_alternative_fromname ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                        </td>
                        <td width="20%">
                            <label for="email_recipients">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_RECIPIENTS'); ?>
                            </label>
                        </td>
                        <td>
                            <input class="form-control form-control-sm w-100" id="email_recipients" type="text"
                                name="jform[email_recipients]"
                                value="<?php echo htmlentities($this->item->email_recipients ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <td width="20%">
                            <label for="email_recipients_attach_uploads">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_ATTACH_UPLOADS'); ?>
                            </label>
                        </td>
                        <td>
                            <input class="form-control form-control-sm w-100" id="email_recipients_attach_uploads"
                                type="text" name="jform[email_recipients_attach_uploads]"
                                value="<?php echo htmlentities($this->item->email_recipients_attach_uploads ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                        </td>
                        <td width="20%">
                            <label for="email_html">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_EMAIL_HTML'); ?>
                            </label>
                        </td>
                        <td>
                            <input type="hidden" name="jform[email_html]" value="0" />
                            <?php echo $renderCheckbox('jform[email_html]', 'email_html', (bool) $this->item->email_html); ?>
                        </td>
                    </tr>
                    <tr>
                        <td width="20%">
                            <label for="email_create_sample_button">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_EMAIL_TEMPLATE'); ?>
                            </label>
                        </td>
                        <td>
                            <input type="hidden" name="jform[email_create_sample]" id="cb_email_create_sample_flag" value="0" />
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="email_create_sample_button"
                                    onclick="cbQueueEmailUserSampleGeneration(this);">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_CREATE_EMAIL_TEMPLATE'); ?>
                                </button>
                                <small id="cb_email_create_sample_hint" class="text-success d-none">
                                    <?php echo Text::_('COM_CONTENTBUILDERNG_INITIALISE_WILL_APPLY_ON_SAVE'); ?>
                                </small>
                            </div>
                        </td>
                        <td width="20%">
                        </td>
                        <td>
                        </td>
                    </tr>
                </table>

                <?php
                $params = array('syntax' => 'html');
                echo $this->form->renderField('email_template');
                ?>
            </div>
        <?php
        }

        echo HTMLHelper::_('uitab.endTab');
        echo HTMLHelper::_('uitab.addTab', 'view-pane', 'tab8', Text::_('COM_CONTENTBUILDERNG_PERMISSIONS'));

        // Démarrer les onglets
        $activePermTab = $session->get('slideStartOffset', 'permtab1', 'com_contentbuilderng');
        echo HTMLHelper::_('uitab.startTabSet', 'perm-pane', ['active' => $activePermTab]);


        // Premier onglet
        echo HTMLHelper::_('uitab.addTab', 'perm-pane', 'permtab1', Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_FRONTEND'));
        ?>
        <table class="table table-striped">
            <tr class="row0">
                <td width="20%" align="right" class="key">
                    <label for="own_only_fe">
                        <span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_PERM_OWN_OWNLY_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_OWN_OWNLY'); ?>
                        </span>:
                    </label>
                </td>
                <td>
                    <input type="hidden" name="jform[own_only_fe]" value="0" />
                    <?php echo $renderCheckbox('jform[own_only_fe]', 'own_only_fe', (bool) $this->item->own_only_fe); ?>
                </td>
            </tr>
            <tr class="row0">
                <td width="20%" align="right" class="key">
                    <label for="limited_article_options_fe">
                        <span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_PERM_LIMITED_ARTICLE_OPTIONS_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_LIMITED_ARTICLE_OPTIONS'); ?>
                        </span>:
                    </label>
                </td>
                <td>
                    <input type="hidden" name="jform[limited_article_options_fe]" value="0" />
                    <?php echo $renderCheckbox('jform[limited_article_options_fe]', 'limited_article_options_fe', (bool) $this->item->limited_article_options_fe); ?>
                </td>
            </tr>
            <tr class="row0">
                <td width="20%" align="right" class="key">
                    <label for="own_fe_view">
                        <span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_PERM_OWN_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_OWN'); ?>
                        </span>:
                    </label>
                </td>
                <td>
                    <?php foreach ($permissionColumns as $permissionColumn) : ?>
                        <?php
                        $permKey = $permissionColumn['key'];
                        $permId = 'own_fe_' . $permKey;
                        $permName = 'jform[own_fe][' . $permKey . ']';
                        $isChecked = !empty($this->item->config['own_fe'][$permKey]);
                        ?>
                        <?php echo $renderCheckbox($permName, $permId, $isChecked); ?>
                        <label class="form-check-label me-2" for="<?php echo $permId; ?>">
                            <?php echo Text::_($permissionColumn['label']); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr class="row0">
                <td width="20%" align="right" class="key">
                    <label for="show_all_languages_fe">
                        <span class="editlinktip hasTip"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_PERM_SHOW_ALL_LANGUAGES_TIP'); ?>">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_SHOW_ALL_LANGUAGES'); ?>
                        </span>:
                    </label>
                </td>
                <td>
                    <input type="hidden" name="jform[show_all_languages_fe]" value="0" />
                    <?php echo $renderCheckbox('jform[show_all_languages_fe]', 'show_all_languages_fe', (bool) $this->item->show_all_languages_fe); ?>
                </td>
            </tr>
            <?php
            if ($this->item->edit_by_type) {
            ?>
                <tr class="row0">
                    <td width="20%" align="right" class="key">
                        <label for="force_login">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_FORCE_LOGIN'); ?>
                        </label>
                    </td>
                    <td>
                        <input type="hidden" name="jform[force_login]" value="0" />
                        <?php echo $renderCheckbox('jform[force_login]', 'force_login', (bool) $this->item->force_login); ?>
                    </td>
                </tr>
                <tr class="row0">
                    <td width="20%" align="right" class="key">
                        <label for="force_url">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_FORCE_URL'); ?>
                        </label>
                    </td>
                    <td>
                        <input style="width: 100%;" id="force_url" name="jform[force_url]" type="text"
                            value="<?php echo htmlentities($this->item->force_url ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                    </td>
                </tr>
            <?php
            }
            ?>
        </table>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>
                        <?php echo $permHeaderLabel('COM_CONTENTBUILDERNG_PERM_GROUP', 'COM_CONTENTBUILDERNG_PERM_GROUP_TIP'); ?>
                    </th>
                    <?php foreach ($permissionColumns as $permissionColumn) : ?>
                        <th>
                            <?php echo $permHeaderLabel($permissionColumn['label'], $permissionColumn['tip']); ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tr>
                <td class="bg-body-tertiary"></td>
                <?php foreach ($permissionColumns as $permissionColumn) : ?>
                    <?php
                    $permKey = $permissionColumn['key'];
                    $permId = 'perms_fe_select_' . $permKey;
                    ?>
                    <td class="bg-body-tertiary">
                        <?php echo $renderCheckbox('', $permId, false, $permKey, ['onclick' => "contentbuilderng_selectAll(this,'fe')"]); ?>
                    </td>
                <?php endforeach; ?>
            </tr>

            <?php
            foreach ($this->gmap as $entry) {
                $k = 0;
            ?>
                <tr class="<?php echo "row$k"; ?>">
                    <td>
                        <?php echo $entry->text; ?>
                    </td>
                    <?php
                    $groupPermissions = $this->item->config['permissions_fe'][$entry->value] ?? [];
                    foreach ($permissionColumns as $permissionColumn) {
                        $permKey = $permissionColumn['key'];
                        $permName = 'jform[perms_fe][' . $entry->value . '][' . $permKey . ']';
                        $permId = 'perms_fe_' . $entry->value . '_' . $permKey;
                        $isChecked = !$this->item->id && !empty($defaultCheckedForNewPermissions[$permKey]);

                        if (!$isChecked) {
                            $isChecked = !empty($groupPermissions[$permKey]);
                        }

                        echo '<td>' . $renderCheckbox($permName, $permId, $isChecked) . '</td>';
                    }
                    ?>
                </tr>
            <?php
                $k = 1 - $k;
            }
            ?>
        </table>
        <?php
        echo HTMLHelper::_('uitab.endTab');
        // The old backend permissions block was removed in favor of Joomla 6 frontend permissions UI.


        echo HTMLHelper::_('uitab.addTab', 'perm-pane', 'permtab2', Text::_('COM_CONTENTBUILDERNG_PERMISSIONS_USERS'));
        ?>

        <table class="table table-striped">
            <tr class="row0">
                <td width="20%" align="right" class="key">
                    <label for="limit_add">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_LIMIT_ADD'); ?>:
                    </label>
                </td>
                <td>
                    <input class="form-control form-control-sm w-100" id="limit_add" name="jform[limit_add]" type="text"
                        value="<?php echo $this->item->limit_add; ?>" />
                </td>
            </tr>
            <tr class="row0">
                <td width="20%" align="right" class="key">
                    <label for="limit_edit">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_LIMIT_EDIT'); ?>:
                    </label>
                </td>
                <td>
                    <input class="form-control form-control-sm w-100" id="limit_edit" name="jform[limit_edit]" type="text"
                        value="<?php echo $this->item->limit_edit; ?>" />
                </td>
            </tr>
            <tr class="row0">
                <td width="20%" align="right" class="key">
                    <label for="verification_required_view">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VIEW'); ?>:
                    </label>
                </td>
                <td>
                    <input type="hidden" name="jform[verification_required_view]" value="0" />
                    <?php echo $renderCheckbox('jform[verification_required_view]', 'verification_required_view', (bool) $this->item->verification_required_view); ?><label class="form-check-label" for="verification_required_view">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_REQUIRED'); ?>
                    </label>
                    <input class="form-control form-control-sm" style="width: 50px;" id="verification_days_view"
                        name="jform[verification_days_view]" type="text"
                        value="<?php echo $this->item->verification_days_view; ?>" /> <label
                        for="verification_days_view">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_DAYS'); ?>
                    </label>
                    <input class="form-control form-control-sm" style="width: 300px;" id="verification_url_view"
                        name="jform[verification_url_view]" type="text"
                        value="<?php echo htmlentities($this->item->verification_url_view ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                    <label for="verification_url_view">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_URL'); ?>
                    </label>
                </td>
            </tr>
            <tr class="row0">
                <td width="20%" align="right" class="key">
                    <label for="verification_required_new">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_NEW'); ?>:
                    </label>
                </td>
                <td>
                    <input type="hidden" name="jform[verification_required_new]" value="0" />
                    <?php echo $renderCheckbox('jform[verification_required_new]', 'verification_required_new', (bool) $this->item->verification_required_new); ?><label class="form-check-label" for="verification_required_new">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_REQUIRED'); ?>
                    </label>
                    <input class="form-control form-control-sm" style="width: 50px;" id="verification_days_new"
                        name="jform[verification_days_new]" type="text"
                        value="<?php echo $this->item->verification_days_new; ?>" /> <label for="verification_days_new">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_DAYS'); ?>
                    </label>
                    <input class="form-control form-control-sm" style="width: 300px;" id="verification_url_new"
                        name="jform[verification_url_new]" type="text"
                        value="<?php echo htmlentities($this->item->verification_url_new ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                    <label for="verification_url_new">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_URL'); ?>
                    </label>
                </td>
            </tr>
            <tr class="row0">
                <td width="20%" align="right" class="key">
                    <label for="verification_required_edit">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_EDIT'); ?>:
                    </label>
                </td>
                <td>
                    <input type="hidden" name="jform[verification_required_edit]" value="0" />
                    <?php echo $renderCheckbox('jform[verification_required_edit]', 'verification_required_edit', (bool) $this->item->verification_required_edit); ?><label class="form-check-label" for="verification_required_edit">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_REQUIRED'); ?>
                    </label>
                    <input class="form-control form-control-sm" style="width: 50px;" id="verification_days_edit"
                        name="jform[verification_days_edit]" type="text"
                        value="<?php echo $this->item->verification_days_edit; ?>" /> <label
                        for="verification_days_edit">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_DAYS'); ?>
                    </label>
                    <input class="form-control form-control-sm" style="width: 300px;" id="verification_url_new"
                        name="jform[verification_url_edit]" type="text"
                        value="<?php echo htmlentities($this->item->verification_url_edit ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                    <label for="verification_url_edit">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_VERIFICATION_URL'); ?>
                    </label>
                </td>
            </tr>
            <tr class="row0">
                <td width="20%" align="right" class="key">
                    <label>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_USERS'); ?>:
                    </label>
                </td>
                <td>
                    <?php echo '[<a href="index.php?option=com_contentbuilderng&amp;view=users&amp;tmpl=component&amp;form_id=' . $this->item->id . '" title="" data-bs-toggle="modal" data-bs-target="#edit-modal">' . Text::_('COM_CONTENTBUILDERNG_EDIT') . '</a>]'; ?>

                </td>
            </tr>
            <?php
            if (!$this->item->edit_by_type) {
            ?>
                <tr class="row0">
                    <td width="20%" align="right" class="key" valign="top">
                        <label for="act_as_registration">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION'); ?>:
                        </label>
                    </td>
                    <td>
                        <input type="hidden" name="jform[act_as_registration]" value="0" />
                        <?php echo $renderCheckbox('jform[act_as_registration]', 'act_as_registration', (bool) $this->item->act_as_registration); ?>
                        <br />
                        <br />
                        <select class="form-select-sm" name="jform[registration_name_field]" id="registration_name_field"
                            style="max-width: 200px;">
                            <option value=""> -
                                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION_NAME_FIELD'); ?> -
                            </option>
                            <?php
                            foreach ($this->elements as $the_element) {
                            ?>
                                <option value="<?php echo $the_element->reference_id; ?>" <?php echo $this->item->registration_name_field == $the_element->reference_id ? ' selected="selected"' : ''; ?>>
                                    <?php echo htmlentities($the_element->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </value>
                                <?php
                            }
                                ?>
                        </select>
                        <br />
                        <br />
                        <select class="form-select-sm" name="jform[registration_username_field]" id="registration_username_field"
                            style="max-width: 200px;">
                            <option value=""> -
                                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION_USERNAME_FIELD'); ?> -
                            </option>
                            <?php
                            foreach ($this->elements as $the_element) {
                            ?>
                                <option value="<?php echo $the_element->reference_id; ?>" <?php echo $this->item->registration_username_field == $the_element->reference_id ? ' selected="selected"' : ''; ?>>
                                    <?php echo htmlentities($the_element->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </value>
                                <?php
                            }
                                ?>
                        </select>
                        <br />
                        <br />
                        <select class="form-select-sm" name="jform[registration_email_field]" id="registration_email_field"
                            style="max-width: 200px;">
                            <option value=""> -
                                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION_EMAIL_FIELD'); ?> -
                            </option>
                            <?php
                            foreach ($this->elements as $the_element) {
                            ?>
                                <option value="<?php echo $the_element->reference_id; ?>" <?php echo $this->item->registration_email_field == $the_element->reference_id ? ' selected="selected"' : ''; ?>>
                                    <?php echo htmlentities($the_element->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </value>
                                <?php
                            }
                                ?>
                        </select>
                        <br />
                        <br />
                        <select class="form-select-sm" name="jform[registration_email_repeat_field]"
                            id="registration_email_repeat_field" style="max-width: 200px;">
                            <option value=""> -
                                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION_EMAIL_REPEAT_FIELD'); ?> -
                            </option>
                            <?php
                            foreach ($this->elements as $the_element) {
                            ?>
                                <option value="<?php echo $the_element->reference_id; ?>" <?php echo $this->item->registration_email_repeat_field == $the_element->reference_id ? ' selected="selected"' : ''; ?>>
                                    <?php echo htmlentities($the_element->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </value>
                                <?php
                            }
                                ?>
                        </select>
                        <br />
                        <br />
                        <select class="form-select-sm" name="jform[registration_password_field]" id="registration_password_field"
                            style="max-width: 200px;">
                            <option value=""> -
                                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION_PASSWORD_FIELD'); ?> -
                            </option>
                            <?php
                            foreach ($this->elements as $the_element) {
                            ?>
                                <option value="<?php echo $the_element->reference_id; ?>" <?php echo $this->item->registration_password_field == $the_element->reference_id ? ' selected="selected"' : ''; ?>>
                                    <?php echo htmlentities($the_element->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </value>
                                <?php
                            }
                                ?>
                        </select>
                        <br />
                        <br />
                        <select class="form-select-sm" name="jform[registration_password_repeat_field]"
                            id="registration_password_repeat_field" style="max-width: 200px;">
                            <option value=""> -
                                <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_ACT_AS_REGISTRATION_PASSWORD_REPEAT_FIELD'); ?>
                                -
                            </option>
                            <?php
                            foreach ($this->elements as $the_element) {
                            ?>
                                <option value="<?php echo $the_element->reference_id; ?>" <?php echo $this->item->registration_password_repeat_field == $the_element->reference_id ? ' selected="selected"' : ''; ?>>
                                    <?php echo htmlentities($the_element->label ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </value>
                                <?php
                            }
                                ?>
                        </select>
                        <br />
                        <br />
                        <label for="force_login">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_FORCE_LOGIN'); ?>
                        </label>
                        <br />
                        <input type="hidden" name="jform[force_login]" value="0" />
                        <?php echo $renderCheckbox('jform[force_login]', 'force_login', (bool) $this->item->force_login); ?>
                        <br />
                        <br />
                        <label for="force_url">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_FORCE_URL'); ?>
                        </label>
                        <br />
                        <input class="form-control form-control-sm" id="force_url" name="jform[force_url]" type="text"
                            value="<?php echo htmlentities($this->item->force_url ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                        <br />
                        <br />
                        <label for="registration_bypass_plugin">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_REGISTRATION_BYPASS_PLUGIN'); ?>
                        </label>
                        <br />
                        <select class="form-select-sm" name="jform[registration_bypass_plugin]" id="registration_bypass_plugin">
                            <option value=""> -
                                <?php echo Text::_('COM_CONTENTBUILDERNG_NONE'); ?> -
                            </option>
                            <?php
                            foreach ($this->verification_plugins as $registration_bypass_plugin) {
                            ?>
                                <option value="<?php echo $registration_bypass_plugin; ?>" <?php echo $registration_bypass_plugin == $this->item->registration_bypass_plugin ? ' selected="selected"' : ''; ?>>
                                    <?php echo $registration_bypass_plugin; ?>
                                </option>
                            <?php
                            }
                            ?>
                        </select>
                        <br />
                        <br />
                        <label for="registration_bypass_verification_name">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_REGISTRATION_BYPASS_VERIFICATION_NAME'); ?>
                        </label>
                        <br />
                        <input class="form-control form-control-sm" type="text" name="jform[registration_bypass_verification_name]"
                            id="registration_bypass_verification_name"
                            value="<?php echo htmlentities($this->item->registration_bypass_verification_name ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                        <br />
                        <br />
                        <label for="registration_bypass_verify_view">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_REGISTRATION_BYPASS_VERIFICATION_VIEW'); ?>
                        </label>
                        <br />
                        <input class="form-control form-control-sm" type="text" name="jform[registration_bypass_verify_view]"
                            id="registration_bypass_verify_view"
                            value="<?php echo htmlentities($this->item->registration_bypass_verify_view ?? '', ENT_QUOTES, 'UTF-8'); ?>" />

                        <br />
                        <br />
                        <label for="registration_bypass_plugin_params">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_PERM_REGISTRATION_BYPASS_PLUGIN_PARAMS'); ?>
                        </label>
                        <br />
                        <textarea class="form-control form-control-sm" style="width: 100%;height: 80px;"
                            name="jform[registration_bypass_plugin_params]"
                            id="registration_bypass_plugin_params"><?php echo htmlentities($this->item->registration_bypass_plugin_params ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </td>
                </tr>
            <?php
            } else {
            ?>
                <input type="hidden" name="jform[act_as_registration]"
                    value="<?php echo htmlentities($this->item->act_as_registration ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="jform[registration_name_field]"
                    value="<?php echo htmlentities($this->item->registration_name_field ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="jform[registration_username_field]"
                    value="<?php echo htmlentities($this->item->registration_username_field ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="jform[registration_email_field]"
                    value="<?php echo htmlentities($this->item->registration_email_field ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="jform[registration_email_repeat_field]"
                    value="<?php echo htmlentities($this->item->registration_email_repeat_field ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="jform[registration_password_field]"
                    value="<?php echo htmlentities($this->item->registration_password_field ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="jform[registration_password_repeat_field]"
                    value="<?php echo htmlentities($this->item->registration_password_repeat_field ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="jform[registration_bypass_plugin]"
                    value="<?php echo htmlentities($this->item->registration_bypass_plugin ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="jform[registration_bypass_verification_name]"
                    value="<?php echo htmlentities($this->item->registration_bypass_verification_name ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="jform[registration_bypass_verify_view]"
                    value="<?php echo htmlentities($this->item->registration_bypass_verify_view ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="jform[registration_bypass_plugin_params]"
                    value="<?php echo htmlentities($this->item->registration_bypass_plugin_params ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            <?php
            }
            ?>
        </table>

        <?php
        echo HTMLHelper::_('uitab.endTab'); // ✅ ferme permtab2
        echo HTMLHelper::_('uitab.endTabSet'); // ✅ ferme perm-pane

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
    <input type="hidden" name="list[ordering]" value="<?php echo htmlspecialchars($listOrder, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="list[direction]" value="<?php echo htmlspecialchars($listDirn, ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="hidemainmenu" value="0" />
    <input type="hidden" name="tabStartOffset" value="<?php echo htmlspecialchars((string) $session->get('tabStartOffset', 'tab0', 'com_contentbuilderng'), ENT_QUOTES, 'UTF-8'); ?>" />
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
        // Clés de stockage
        const KEY_VIEW = 'cb_active_view_tab';
        const KEY_PERM = 'cb_active_perm_tab';
        const urlParams = new URLSearchParams(window.location.search);
        const forcedViewTab = urlParams.get('force_view_tab') || urlParams.get('tab');
        const tooltipSelector = '[data-bs-toggle="tooltip"]';
        const viewTabTooltips = <?php echo json_encode($viewTabTooltips, $jsonFlags); ?>;
        const permTabTooltips = <?php echo json_encode($permTabTooltips, $jsonFlags); ?>;

        // Helpers
        const $ = (sel, root = document) => root.querySelector(sel);

        function getTabTargetId(el) {
            if (!el || typeof el.getAttribute !== 'function') {
                return null;
            }

            return (
                el.getAttribute('aria-controls') ||
                el.getAttribute('data-tab') ||
                (el.getAttribute('href') && el.getAttribute('href').startsWith('#') ? el.getAttribute('href').slice(1) : null) ||
                (el.getAttribute('data-target') && el.getAttribute('data-target').startsWith('#') ? el.getAttribute('data-target').slice(1) : null)
            );
        }

        function initBootstrapTooltips(root = document) {
            if (!window.bootstrap || typeof window.bootstrap.Tooltip !== 'function') {
                return;
            }

            root.querySelectorAll(tooltipSelector).forEach((el) => {
                if (!window.bootstrap.Tooltip.getInstance(el)) {
                    new window.bootstrap.Tooltip(el);
                }
            });
        }

        function applyTabTooltips(tabsetId, tips, attempt = 0) {
            const tabset = document.getElementById(tabsetId);
            if (!tabset || !tips) {
                return;
            }

            const jTab = tabset.matches('joomla-tab') ? tabset : tabset.querySelector('joomla-tab');
            if (!jTab) {
                return;
            }

            const selector = 'button[aria-controls],button[data-tab],button[data-target],a[aria-controls],a[data-tab],a[data-target],a[href^="#"]';
            const roots = [jTab];
            if (jTab.shadowRoot) {
                roots.push(jTab.shadowRoot);
            }

            let applied = 0;

            roots.forEach((root) => {
                root.querySelectorAll(selector).forEach((trigger) => {
                    const id = getTabTargetId(trigger);
                    const tip = id ? tips[id] : null;

                    if (!tip) {
                        return;
                    }

                    trigger.setAttribute('title', String(tip));
                    trigger.setAttribute('data-bs-toggle', 'tooltip');
                    trigger.setAttribute('data-bs-placement', 'top');
                    trigger.setAttribute('data-bs-title', String(tip));
                    applied++;
                });

                initBootstrapTooltips(root);
            });

            if (applied === 0 && attempt < 12) {
                window.setTimeout(() => applyTabTooltips(tabsetId, tips, attempt + 1), 120);
            }
        }

        function setHidden(name, value) {
            const el = document.querySelector(`input[name="jform[${name}]"]`);
            if (el) el.value = value;
        }

        /**
         * Persistance d'un joomla-tab (uitab)
         * @param {string} tabsetId  ex: 'view-pane' ou 'perm-pane'
         * @param {string} storageKey
         * @param {(value:string)=>void} onSave  callback optionnel (ex: hidden input)
         */
        function persistJoomlaTabset(tabsetId, storageKey, onSave) {
            const tabset = document.getElementById(tabsetId);
            if (!tabset) return;

            // Joomla génère souvent un <joomla-tab> avec des <button> ou des liens internes.
            const jTab = tabset.matches('joomla-tab') ? tabset : tabset.querySelector('joomla-tab');
            if (!jTab) return;

            // Restauration : si on a une valeur stockée, on tente d'activer cet onglet
            const saved = localStorage.getItem(storageKey);
            if (saved) {
                // 1) tente via API si dispo
                if (typeof jTab.show === 'function') {
                    try {
                        jTab.show(saved);
                    } catch (e) {}
                }

                // 2) fallback : cliquer un bouton correspondant
                // Les boutons ont souvent aria-controls ou data-tab / data-target
                const btn =
                    jTab.querySelector(`button[aria-controls="${saved}"]`) ||
                    jTab.querySelector(`button[data-tab="${saved}"]`) ||
                    jTab.querySelector(`button[data-target="#${saved}"]`) ||
                    jTab.querySelector(`a[aria-controls="${saved}"]`) ||
                    jTab.querySelector(`a[href="#${saved}"]`);

                if (btn) {
                    btn.click();
                    btn.blur?.();
                }
            }

            // Sauvegarde : écouter les clics sur onglets
            jTab.addEventListener('click', (ev) => {
                const trigger = ev.target?.closest?.('button,a') || ev.target;
                const id = getTabTargetId(trigger);

                if (!id) return;

                localStorage.setItem(storageKey, id);
                if (typeof onSave === 'function') onSave(id);
            }, {
                passive: true
            });
        }

        // Force requested view tab from URL and override persisted tab selection.
        if (forcedViewTab && /^tab\d+$/.test(forcedViewTab)) {
            localStorage.setItem(KEY_VIEW, forcedViewTab);
            setHidden('tabStartOffset', forcedViewTab);
        }

        // 1) onglets principaux view-pane (tab0, tab1, tab2…)
        persistJoomlaTabset('view-pane', KEY_VIEW, (id) => {
            // Optionnel : si tu veux continuer avec tabStartOffset
            // Ici je stocke l'id, si tu veux l'index, dis-moi et je te donne la variante.
            setHidden('tabStartOffset', id);
        });

        // 2) onglets internes permissions perm-pane (permtab1, permtab2…)
        persistJoomlaTabset('perm-pane', KEY_PERM, (id) => {
            setHidden('slideStartOffset', id);
        });

        applyTabTooltips('view-pane', viewTabTooltips);
        applyTabTooltips('perm-pane', permTabTooltips);

        initBootstrapTooltips();

    })();
</script>
