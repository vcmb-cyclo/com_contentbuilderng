<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use CB\Component\Contentbuilderng\Administrator\Helper\RatingHelper;
use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Site\Helper\NavigationLinkHelper;
use CB\Component\Contentbuilderng\Site\Helper\MenuParamHelper;
use CB\Component\Contentbuilderng\Site\Helper\PreviewLinkHelper;

/** @var SiteApplication $app */
$app = Factory::getApplication();
$frontend = $app->isClient('site');
$permissionService = new PermissionService();
$new_allowed = $frontend ? $permissionService->authorizeFe('new') : $permissionService->authorize('new');
$edit_allowed = $frontend ? $permissionService->authorizeFe('edit') : $permissionService->authorize('edit');
$delete_allowed = $frontend ? $permissionService->authorizeFe('delete') : $permissionService->authorize('delete');
$state_allowed = $frontend ? $permissionService->authorizeFe('state') : $permissionService->authorize('state');
$rating_allowed = $frontend ? $permissionService->authorizeFe('rating') : $permissionService->authorize('rating');
$view_allowed = $frontend ? $permissionService->authorizeFe('view') : $permissionService->authorize('view');
$fullarticle_allowed = $frontend ? $permissionService->authorizeFe('fullarticle') : $permissionService->authorize('fullarticle');
$isAdminPreview = $app->getInput()->getBool('cb_preview_ok', false);
$getStateBadgeStyle = static function ($recordId, array $stateColors): string {
    $color = strtoupper(trim((string) ($stateColors[$recordId] ?? '')));
    $color = ltrim($color, '#');

    if ($color === '' || !preg_match('/^[0-9A-F]{6}$/', $color)) {
        return '';
    }

    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));
    $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    $textColor = $brightness >= 150 ? '#16324F' : '#FFFFFF';

    return 'background-color:#' . $color . ';color:' . $textColor . ';';
};

$input = $app->getInput();
$safeReturn = NavigationLinkHelper::encodeInternalReturn((string) $input->getString('return', ''));
$hasReturn = $safeReturn !== '';
$topBarToggle = MenuParamHelper::resolveInputOrMenuToggle($app, 'cb_show_top_bar', (int) ($this->cb_show_top_bar ?? 1));
$bottomBarToggle = MenuParamHelper::resolveInputOrMenuToggle($app, 'cb_show_bottom_bar', (int) ($this->cb_show_bottom_bar ?? 1));
$showAuthorToggle = MenuParamHelper::resolveInputOrMenuToggle($app, 'cb_show_author', (int) ($this->cb_show_author ?? 1));

$editBackButtonToggle = -1;
if ($frontend) {
    $editBackButtonToggle = MenuParamHelper::resolveInputOrMenuToggle($app, 'cb_show_details_back_button', (int) ($this->back_button ?? 1), 'show_back_button');
    if ($editBackButtonToggle >= 0) {
        $showBack = $editBackButtonToggle === 1 && !$hasReturn;
    }
}

$backToList = $input->getInt('backtolist', 0) === 1;
$jsBack = $input->getInt('jsback', 0) === 1;
$layout = $input->getString('layout', '');
$tmpl = $input->getString('tmpl', '');
$id = $input->getInt('id', 0);
$recordId = $input->getCmd('record_id', 0);
$itemId = $input->getInt('Itemid', 0);
$list = (array) $input->get('list', [], 'array');
$listState = NavigationLinkHelper::resolveListState(
    $app,
    $list,
    $id,
    $layout !== '' ? $layout : 'default',
    $itemId
);
$listStart = (int) $listState['start'];
$listLimit = (int) $listState['limit'];
$listOrdering = (string) $listState['ordering'];
$listDirection = (string) $listState['direction'];
$listQuery = NavigationLinkHelper::buildListQuery($listStart, $listLimit, $listOrdering, $listDirection);
$listHiddenFields = ''
    . '<input type="hidden" name="list[start]" value="' . (int) $listStart . '" />' . "\n"
    . '<input type="hidden" name="list[limit]" value="' . (int) $listLimit . '" />' . "\n"
    . '<input type="hidden" name="list[ordering]" value="' . htmlspecialchars($listOrdering, ENT_QUOTES, 'UTF-8') . '" />' . "\n"
    . '<input type="hidden" name="list[direction]" value="' . htmlspecialchars($listDirection, ENT_QUOTES, 'UTF-8') . '" />';
$previewHiddenFields = '';
$previewEnabled = $input->getBool('cb_preview', false);
$previewUntil = $input->getInt('cb_preview_until', 0);
$previewSig = $input->getString('cb_preview_sig', '');
$previewActorId = $input->getInt('cb_preview_actor_id', 0);
$previewActorName = (string) $input->getString('cb_preview_actor_name', '');
$previewUserId = $input->getInt('cb_preview_user_id', 0);
$currentUser = $app->getIdentity();
$currentSessionLabel = trim((string) ($currentUser->name ?? ''));
if ($currentSessionLabel === '') {
    $currentSessionLabel = trim((string) ($currentUser->username ?? ''));
}
if ($currentSessionLabel === '') {
    $currentSessionLabel = Text::_('COM_CONTENTBUILDERNG_GUEST');
}
$previewActorLabel = trim($previewActorName);
if ($previewActorLabel === '' && $previewActorId > 0) {
    $previewActorLabel = '#' . $previewActorId;
}
$showPreviewSessionBadge = $isAdminPreview && $currentSessionLabel !== '' && $currentSessionLabel !== $previewActorLabel;
$previewQuery = '';
$adminReturnContext = trim((string) $input->getCmd('cb_admin_return', ''));
$adminReturnUrl = Uri::root() . 'administrator/index.php?option=com_contentbuilderng&task=form.edit&id=' . (int) $id;
if ($adminReturnContext === 'forms') {
    $adminReturnUrl = Uri::root() . 'administrator/index.php?option=com_contentbuilderng&view=forms';
}
$previewFormName = trim((string) ($this->form_name ?? ''));
if ($previewFormName === '') {
    $previewFormName = trim((string) ($this->page_title ?? ''));
}
if ($previewFormName === '') {
    $previewFormName = Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
}
$previewFormName = htmlspecialchars($previewFormName, ENT_QUOTES, 'UTF-8');
$previewConfigTabLabel = Text::sprintf('COM_CONTENTBUILDERNG_PREVIEW_CONFIG_TAB', Text::_('COM_CONTENTBUILDERNG_PREVIEW_TAB_EDITABLE_TEMPLATE'));
$editableTemplateMissing = $isAdminPreview && trim((string) ($this->tpl ?? '')) === '';
$previewFrontendPermissionKey = in_array((string) $recordId, ['', '0'], true)
    ? 'COM_CONTENTBUILDERNG_PERM_NEW'
    : 'COM_CONTENTBUILDERNG_PERM_EDIT';
$previewFrontendPermissionHint = Text::sprintf(
    'COM_CONTENTBUILDERNG_PREVIEW_FRONTEND_PERMISSION_HINT',
    Text::_($previewFrontendPermissionKey),
    Text::_('COM_CONTENTBUILDERNG_DISPLAY_FRONTEND')
);
$editScreenAdminUrl = Uri::root() . 'administrator/index.php?option=com_contentbuilderng&view=form&layout=edit&id=' . (int) $id . '&tab=tab5&force_view_tab=tab5';
if ($previewEnabled && $previewUntil > 0 && $previewSig !== '') {
    $previewQuery = PreviewLinkHelper::buildQuery(
        (int) $previewUntil,
        (int) $previewActorId,
        (string) $previewActorName,
        (int) $previewUserId,
        (string) $previewSig,
        (string) $adminReturnContext
    );
    $previewHiddenFields = PreviewLinkHelper::buildHiddenFields(
        (int) $previewUntil,
        (int) $previewActorId,
        (string) $previewActorName,
        (int) $previewUserId,
        (string) $previewSig,
        (string) $adminReturnContext
    );
}

$detailsHref = Route::_(
    'index.php?option=com_contentbuilderng&task=details.display'
        . ($layout !== '' ? '&layout=' . $layout : '')
        . '&id=' . $id
        . '&record_id=' . $recordId
        . ($tmpl !== '' ? '&tmpl=' . $tmpl : '')
        . '&Itemid=' . $itemId
        . ($listQuery !== '' ? '&' . $listQuery : '')
        . $previewQuery
);

$listHref = Route::_(
    'index.php?option=com_contentbuilderng&task=list.display'
        . ($layout !== '' ? '&layout=' . $layout : '')
        . '&id=' . $id
        . ($listQuery !== '' ? '&' . $listQuery : '')
        . ($tmpl !== '' ? '&tmpl=' . $tmpl : '')
        . '&Itemid=' . $itemId
        . $previewQuery
);

$hasRecord = !in_array((string) $recordId, ['', '0'], true);
$currentRecordLabel = trim((string) $recordId);
$showCurrentRecordLabel = !in_array($currentRecordLabel, ['', '0'], true);
$showCurrentRecordLabel = $showCurrentRecordLabel && (int) ($this->show_id_column ?? 0) === 1;
$stateOptions = (array) ($this->states ?? []);
$currentStateId = (int) (($this->state_ids ?? [])[$recordId] ?? 0);
$currentStateTitle = trim((string) (($this->state_titles ?? [])[$recordId] ?? ''));
$currentStateBadgeStyle = $getStateBadgeStyle((string) $recordId, (array) ($this->state_colors ?? []));
$showStateControl = (int) ($this->list_state ?? 0) === 1 && count($stateOptions) > 0 && $hasRecord;
$showRatingControl = (int) ($this->list_rating ?? 0) === 1 && (int) ($this->rating_slots ?? 0) > 0 && $hasRecord;
$backHref = ($backToList || !$hasRecord) ? $listHref : $detailsHref;
$showBack = $this->back_button && !$hasReturn;
$prevRecordId = property_exists($this, 'prev_record_id') ? (int) $this->prev_record_id : 0;
$nextRecordId = property_exists($this, 'next_record_id') ? (int) $this->next_record_id : 0;
$prevRecordStart = property_exists($this, 'prev_record_start') ? (int) $this->prev_record_start : 0;
$nextRecordStart = property_exists($this, 'next_record_start') ? (int) $this->next_record_start : 0;
$editBaseParams = [
    'option' => 'com_contentbuilderng',
    'task' => 'edit.display',
    'id' => $id,
    'Itemid' => $itemId,
];
if ($layout !== '') {
    $editBaseParams['layout'] = $layout;
}
if ($tmpl !== '') {
    $editBaseParams['tmpl'] = $tmpl;
}
if ($backToList) {
    $editBaseParams['backtolist'] = 1;
}
if ($jsBack) {
    $editBaseParams['jsback'] = 1;
}
if ($hasReturn) {
    $editBaseParams['return'] = $safeReturn;
}
$editNavBaseLink = NavigationLinkHelper::buildRouteLink($editBaseParams + [
    'list' => [
        'start' => $listStart,
        'limit' => $listLimit,
        'ordering' => $listOrdering,
        'direction' => $listDirection,
    ],
], $previewQuery);
$editPrevHref = $prevRecordId > 0
    ? Route::_(
        NavigationLinkHelper::buildHref(
            NavigationLinkHelper::buildRouteLink($editBaseParams, $previewQuery),
            $prevRecordId,
            $prevRecordStart,
            $listLimit,
            $listOrdering,
            $listDirection
        )
    )
    : '';
$editNextHref = $nextRecordId > 0
    ? Route::_(
        NavigationLinkHelper::buildHref(
            NavigationLinkHelper::buildRouteLink($editBaseParams, $previewQuery),
            $nextRecordId,
            $nextRecordStart,
            $listLimit,
            $listOrdering,
            $listDirection
        )
    )
    : '';
$showColumnHeader = $input->getInt('cb_show_column_header', 1) === 1;
$columnHeaderHtml = '';
$showAuditTrail = $showAuthorToggle === 1;

$createdOnText = '';
if (!empty($this->created)) {
    $createdOnText = Text::_('COM_CONTENTBUILDERNG_CREATED_ON') . ' ' . HTMLHelper::_('date', $this->created, Text::_('DATE_FORMAT_LC2'));
}

$createdByText = '';
if (!empty($this->created_by)) {
    $createdByText = Text::_('COM_CONTENTBUILDERNG_BY') . ' ' . htmlspecialchars((string) $this->created_by, ENT_QUOTES, 'UTF-8');
}

$modifiedOnText = '';
if (!empty($this->modified)) {
    $modifiedOnText = Text::_('COM_CONTENTBUILDERNG_LAST_UPDATED_ON') . ' ' . HTMLHelper::_('date', $this->modified, Text::_('DATE_FORMAT_LC2'));
}

$modifiedByText = '';
if (!empty($this->modified_by)) {
    $modifiedByText = Text::_('COM_CONTENTBUILDERNG_BY') . ' ' . htmlspecialchars((string) $this->modified_by, ENT_QUOTES, 'UTF-8');
}

$createdTrailText = trim($createdOnText . (($createdOnText !== '' && $createdByText !== '') ? ' ' : '') . $createdByText);
$modifiedTrailText = trim($modifiedOnText . (($modifiedOnText !== '' && $modifiedByText !== '') ? ' ' : '') . $modifiedByText);

$auditTrailHtml = '';
if ($showAuditTrail && ($createdTrailText !== '' || $modifiedTrailText !== '')) {
    ob_start();
    ?>
    <div class="cbAuditTrail mt-2 mb-2">
        <?php if ($createdTrailText !== '') : ?>
            <span class="small created-by"><?php echo $createdTrailText; ?></span>
        <?php endif; ?>
        <?php if ($modifiedTrailText !== '') : ?>
            <span class="small created-by"><?php echo $modifiedTrailText; ?></span>
        <?php endif; ?>
    </div>
    <?php
    $auditTrailHtml = ob_get_clean();
}

$stateControlHtml = '';
if ($showStateControl) {
    ob_start();
    ?>
    <div class="cbEditStateControl mb-3">
        <label class="form-label fw-semibold" for="cb-edit-state-select-<?php echo (int) $this->id; ?>">
            <?php echo Text::_('COM_CONTENTBUILDERNG_EDIT_STATE'); ?>
        </label>
        <?php if ($state_allowed) : ?>
            <div class="cbEditStateControlRow">
                <select
                    id="cb-edit-state-select-<?php echo (int) $this->id; ?>"
                    class="form-select"
                    name="list_state"
                    data-record-id="<?php echo (int) $recordId; ?>"
                    data-original-value="<?php echo (int) $currentStateId; ?>"
                    onchange="contentbuilderng_edit_state_single(this);">
                    <option value="0"></option>
                    <?php foreach ($stateOptions as $state) : ?>
                        <option
                            value="<?php echo (int) ($state['id'] ?? 0); ?>"
                            data-state-title="<?php echo htmlspecialchars((string) ($state['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            data-state-color="<?php echo htmlspecialchars((string) ($state['color'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo (int) ($state['id'] ?? 0) === $currentStateId ? ' selected="selected"' : ''; ?>>
                            <?php echo htmlspecialchars((string) ($state['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span
                    id="cb-edit-state-badge-<?php echo (int) $this->id; ?>"
                    class="badge rounded-pill cbEditStateBadge"
                    style="<?php echo htmlspecialchars($currentStateBadgeStyle, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($currentStateTitle, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </div>
        <?php else : ?>
            <div class="pt-1">
                <?php if ($currentStateTitle !== '') : ?>
                    <span class="badge rounded-pill" style="<?php echo htmlspecialchars($currentStateBadgeStyle, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($currentStateTitle, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php else : ?>
                    <span class="text-muted"><?php echo Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE'); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    $stateControlHtml = ob_get_clean();
}

$ratingControlHtml = '';
if ($showRatingControl) {
    ob_start();
    ?>
    <div class="cbEditRatingControl mb-3">
        <div class="form-label fw-semibold"><?php echo Text::_('COM_CONTENTBUILDERNG_PERM_RATING'); ?></div>
        <?php echo RatingHelper::getRating($id, (int) $recordId, (float) ($this->rating ?? 0), (int) ($this->rating_slots ?? 0), $input->getCmd('lang', ''), $rating_allowed, (int) ($this->rating_count ?? 0), (int) ($this->rating_sum ?? 0)); ?>
    </div>
    <?php
    $ratingControlHtml = ob_get_clean();
}

if ($showColumnHeader) {
    $columnHeaderHtml = '<div class="cbColumnHeader d-none d-md-grid" aria-hidden="true">'
        . '<div class="cbColumnHeaderLabel">' . Text::_('COM_CONTENTBUILDERNG_COLUMN_HEADER_FIELD') . '</div>'
        . '<div class="cbColumnHeaderValue">' . Text::_('COM_CONTENTBUILDERNG_COLUMN_HEADER_VALUE') . '</div>'
        . '</div>';
}

if (!empty($this->theme_css) || !empty($this->theme_js)) {
    $wa = $app->getDocument()->getWebAssetManager();
    $themeCss = trim((string) ($this->theme_css ?? ''));
    if ($themeCss !== '') {
        $wa->addInlineStyle($themeCss);
    }

    $themeJs = trim((string) ($this->theme_js ?? ''));
    if ($themeJs !== '') {
        $wa->addInlineScript($themeJs);
    }
}
$wa = $app->getDocument()->getWebAssetManager();
$wa->addInlineStyle(
    <<<'CSS'
.cb-preview-config-help{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:1.55rem;
    height:1.55rem;
    margin-left:.25rem;
    border-radius:999px;
    color:#7a4c07;
    background:rgba(255,255,255,.45);
    text-decoration:none;
    vertical-align:middle;
}
.cb-preview-config-help:hover,
.cb-preview-config-help:focus{
    color:#5f3b00;
    background:rgba(255,255,255,.62);
    outline:none;
}
.cbEditStateControlRow{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:.75rem;
}
.cbEditStateControlRow .form-select{
    width:auto;
    min-width:14rem;
    max-width:22rem;
}
.cbEditStateBadge:empty{
    display:none;
}
@media (prefers-color-scheme: dark){
    .cb-preview-config-help{
        color:#f5d38f;
        background:rgba(255,255,255,.08);
    }
    .cb-preview-config-help:hover,
    .cb-preview-config-help:focus{
        color:#ffe8b3;
        background:rgba(255,255,255,.14);
    }
}
@media (max-width:575.98px){
    .cbEditStateControlRow{
        align-items:stretch;
    }
    .cbEditStateControlRow .form-select{
        width:100%;
        max-width:none;
    }
}
CSS
);
?>
<a name="article_up"></a>
<script type="text/javascript">
    function contentbuilderng_delete() {
        var confirmed = confirm('<?php echo Text::_('COM_CONTENTBUILDERNG_CONFIRM_DELETE_MESSAGE'); ?>');
        if (confirmed) {
            location.href = '<?php echo Uri::root() . ltrim(Route::_('index.php?option=com_contentbuilderng&task=edit.delete' . (Factory::getApplication()->getInput()->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->getInput()->get('tmpl', '', 'string') : '') . (Factory::getApplication()->getInput()->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->getInput()->get('layout', '', 'string') : '') . '&id=' . Factory::getApplication()->getInput()->getInt('id', 0) . '&cid[]=' . Factory::getApplication()->getInput()->getCmd('record_id', 0) . '&Itemid=' . Factory::getApplication()->getInput()->getInt('Itemid', 0) . ($listQuery !== '' ? '&' . $listQuery : ''), false), '/'); ?>';
        }
    }

    if (typeof FF_SELECTED_DEBUG === "undefined") {
        var FF_SELECTED_DEBUG = false;
    }

    function ff_setSelected(name, value, checked) {
        if (checked === undefined) checked = true;
        if (value === undefined || value === null) value = "";
        value = String(value).trim();

        var el = null;
        if (typeof ff_getElementByName === "function") {
            try {
                el = ff_getElementByName(name);
            } catch (e) {
                el = null;
            }
        }
        if (!el) {
            var nodes = document.getElementsByName(name);
            if (nodes && nodes.length) el = nodes[0];
        }

        if (el && el.tagName === "SELECT") {
            return ff_setSelected_listNode(el, name, value);
        }
        if (el && el.element && el.element.tagName === "SELECT") {
            return ff_setSelected_listNode(el.element, name, value);
        }
        if (el && el[0] && el[0].tagName === "SELECT") {
            return ff_setSelected_listNode(el[0], name, value);
        }

        return ff_setSelected_groupBF(name, value, checked);
    }

    function ff_setSelected_listNode(selectEl, name, value) {
        var found = false;
        for (var i = 0; i < selectEl.options.length; i++) {
            if (String(selectEl.options[i].value) == value) {
                selectEl.selectedIndex = i;
                found = true;
                break;
            }
        }
        if (FF_SELECTED_DEBUG) {
            console.log("[BF] ff_setSelected SELECT name=" + name + " value=" + value + " found=" + found);
        }
        if (!found) return false;
        try {
            if (typeof selectEl.onchange === "function") selectEl.onchange();
            selectEl.dispatchEvent(new Event("change", {
                bubbles: true
            }));
        } catch (e) {}
        return true;
    }

    function ff_setSelected_groupBF(name, value, checked) {
        var htmlName = name;

        try {
            if (typeof ff_elements !== "undefined") {
                for (var i = 0; i < ff_elements.length; i++) {
                    if (ff_elements[i][2] == name) {
                        var e = typeof ff_getElementByIndex === "function" ? ff_getElementByIndex(i) : null;
                        if (e && e.name) htmlName = e.name;
                        break;
                    }
                }
            }
        } catch (e) {}

        var nodes = document.getElementsByName(htmlName);
        if ((!nodes || nodes.length === 0) && htmlName.slice(-2) !== "[]") {
            nodes = document.getElementsByName(htmlName + "[]");
            if (nodes && nodes.length > 0) htmlName = htmlName + "[]";
        }

        if (!nodes || nodes.length === 0) {
            if (FF_SELECTED_DEBUG) console.warn("[BF] ff_setSelected GROUP not found name=" + name + " htmlName=" + htmlName);
            return false;
        }

        var values = [value];
        if (value.indexOf(";") >= 0) values = value.split(/\s*;\s*/);
        else if (value.indexOf(",") >= 0) values = value.split(/\s*,\s*/);

        var done = false;

        for (var n = 0; n < nodes.length; n++) {
            var input = nodes[n];
            var v = String(input.value);

            if (input.type === "radio") {
                if (v == value) {
                    input.checked = checked;
                    done = true;
                    break;
                }
            } else if (input.type === "checkbox") {
                for (var k = 0; k < values.length; k++) {
                    if (v == String(values[k])) {
                        input.checked = checked;
                        done = true;
                        break;
                    }
                }
            }
        }

        if (FF_SELECTED_DEBUG) {
            console.log("[BF] ff_setSelected GROUP name=" + name + " htmlName=" + htmlName + " value=" + value + " done=" + done);
        }

        return done;
    }

    function ff_setChecked(name, value, checked) {
        var missingInputs = (name === undefined || name === null || value === undefined || value === null);
        if (checked === undefined) checked = true;
        if (value === undefined || value === null) value = "";
        if (name === undefined || name === null) name = "";
        name = String(name).trim();
        value = String(value).trim();

        var result = ff_setSelected_groupBF(name, value, checked);

        if (missingInputs) {
            console.warn("[BF] ff_setChecked called with undefined inputs", {
                name: name,
                value: value,
                checked: checked
            });
        } else if (!result) {
            console.warn("[BF] ff_setChecked element not found", {
                name: name,
                value: value
            });
        }

        return result;
    }

    var cbInitialEditFormState = "";
    var cbUnsavedChangesWarning = <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_UNSAVED_CHANGES_WARNING')); ?>;

    function cbShouldTrackFieldForDirtyState(field) {
        if (!field || field.disabled) {
            return false;
        }

        var type = (field.type || "").toLowerCase();
        if (type === "hidden" || type === "submit" || type === "button" || type === "reset" || type === "image") {
            return false;
        }

        var name = field.name || field.id || "";
        if (name === "") {
            return false;
        }

        return true;
    }

    function cbCaptureEditFormState(root) {
        if (!root) {
            return "";
        }

        var parts = [];
        var fields = root.querySelectorAll("input, select, textarea");
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            if (!cbShouldTrackFieldForDirtyState(field)) {
                continue;
            }

            var name = field.name || field.id || "";
            var tag = (field.tagName || "").toLowerCase();
            var type = (field.type || "").toLowerCase();

            if (type === "checkbox" || type === "radio") {
                parts.push(name + "=" + (field.checked ? "1" : "0"));
                continue;
            }

            if (tag === "select" && field.multiple) {
                var selected = [];
                for (var j = 0; j < field.options.length; j++) {
                    if (field.options[j].selected) {
                        selected.push(field.options[j].value);
                    }
                }
                parts.push(name + "=" + selected.join(","));
                continue;
            }

            parts.push(name + "=" + field.value);
        }

        return parts.join("&");
    }

    function cbHasUnsavedEditChanges(root) {
        return cbCaptureEditFormState(root) !== cbInitialEditFormState;
    }

    function cbAttachEditNavigationWarning() {
        var form = document.getElementById("adminForm");
        var wrapper = document.getElementById("cbEditableWrapper<?php echo (int) $this->id; ?>");
        var stateRoot = form || wrapper;
        if (!stateRoot) {
            return;
        }

        cbInitialEditFormState = cbCaptureEditFormState(stateRoot);
        window.addEventListener("load", function() {
            cbInitialEditFormState = cbCaptureEditFormState(stateRoot);
        });

        var navButtons = document.querySelectorAll(".cbPrevButton, .cbNextButton");
        for (var i = 0; i < navButtons.length; i++) {
            navButtons[i].addEventListener("click", function(event) {
                if (!cbHasUnsavedEditChanges(stateRoot)) {
                    return;
                }

                if (!confirm(cbUnsavedChangesWarning)) {
                    event.preventDefault();
                }
            });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", cbAttachEditNavigationWarning);
    } else {
        cbAttachEditNavigationWarning();
    }

    function contentbuilderng_edit_state_single(select) {
        var form = document.getElementById("adminForm");
        if (!form || !select) {
            return;
        }

        var recordId = String(select.getAttribute("data-record-id") || "");
        if (recordId === "" || recordId === "0") {
            return;
        }

        var formData = new FormData(form);
        formData.set("task", "edit.state");
        formData.set("cb_ajax", "1");
        formData.set("list_state", String(select.value || "0"));
        formData.delete("cid[]");
        formData.append("cid[]", recordId);
        formData.set("boxchecked", "1");

        var originalValue = String(select.getAttribute("data-original-value") || "");
        var badge = document.getElementById("cb-edit-state-badge-<?php echo (int) $this->id; ?>");
        select.disabled = true;

        fetch(form.getAttribute("action") || window.location.href, {
            method: "POST",
            body: formData,
            credentials: "same-origin",
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        })
            .then(function(response) {
                return response.text().then(function(text) {
                    var payload = null;

                    try {
                        payload = JSON.parse(text);
                    } catch (error) {
                        payload = null;
                    }

                    if (!response.ok || !payload || payload.success !== true) {
                        throw new Error(payload && payload.message ? payload.message : <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_ERROR')); ?>);
                    }

                    select.setAttribute("data-original-value", String(select.value || "0"));
                    if (badge) {
                        var selectedOption = select.options[select.selectedIndex] || null;
                        var stateTitle = selectedOption ? String(selectedOption.getAttribute("data-state-title") || "") : "";
                        var stateColor = selectedOption ? String(selectedOption.getAttribute("data-state-color") || "") : "";
                        badge.textContent = stateTitle;
                        badge.style.cssText = "";

                        if (/^[0-9a-f]{6}$/i.test(stateColor)) {
                            var color = stateColor.replace(/^#/, "");
                            var r = parseInt(color.substring(0, 2), 16);
                            var g = parseInt(color.substring(2, 4), 16);
                            var b = parseInt(color.substring(4, 6), 16);
                            var brightness = ((r * 299) + (g * 587) + (b * 114)) / 1000;
                            var textColor = brightness >= 150 ? "#16324F" : "#FFFFFF";
                            badge.style.backgroundColor = "#" + color;
                            badge.style.color = textColor;
                        }
                    }
                    cbInitialEditFormState = cbCaptureEditFormState(form);
                });
            })
            .catch(function(error) {
                select.value = originalValue;
                window.alert(error && error.message ? error.message : <?php echo json_encode(Text::_('COM_CONTENTBUILDERNG_ERROR')); ?>);
            })
            .finally(function() {
                select.disabled = false;
            });
    }
</script>
<div class="cbEditableWrapper" id="cbEditableWrapper<?php echo $this->id; ?>">
    <?php if ($isAdminPreview): ?>
        <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <span>
                <?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW_MODE') . ' - ' . Text::sprintf('COM_CONTENTBUILDERNG_PREVIEW_CURRENT_FORM', $previewFormName); ?>
                <?php if ($previewActorLabel !== ''): ?>
                    <span class="badge text-bg-secondary ms-2">Preview actor: <?php echo htmlspecialchars($previewActorLabel, ENT_QUOTES, 'UTF-8'); ?><?php echo $previewActorId > 0 ? ' (#' . (int) $previewActorId . ')' : ''; ?></span>
                <?php endif; ?>
                <?php if ($showPreviewSessionBadge): ?>
                    <span class="badge text-bg-secondary ms-1">Session: <?php echo htmlspecialchars($currentSessionLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
                <span class="cb-preview-config-help" title="<?php echo htmlspecialchars($previewConfigTabLabel, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($previewConfigTabLabel, ENT_QUOTES, 'UTF-8'); ?>" tabindex="0">
                    <span class="fa-solid fa-circle-question" aria-hidden="true"></span>
                </span>
                <br />
                <small class="d-inline-block mt-1">
                    <?php echo htmlspecialchars($previewFrontendPermissionHint, ENT_QUOTES, 'UTF-8'); ?>
                </small>
                <?php if ($editableTemplateMissing): ?>
                    <br />
                    <strong><?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW_EDITABLE_TEMPLATE_MISSING'); ?></strong>
                <?php endif; ?>
            </span>
            <span class="d-inline-flex flex-wrap align-items-center gap-2">
                <?php if ($editableTemplateMissing): ?>
                    <a class="btn btn-sm btn-outline-warning" href="<?php echo htmlspecialchars($editScreenAdminUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW_OPEN_EDIT_SCREEN'); ?>
                    </a>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $adminReturnUrl; ?>">
                    <span class="fa-solid fa-arrow-left me-1" aria-hidden="true"></span>
                    <?php echo Text::_('COM_CONTENTBUILDERNG_BACK_TO_ADMIN'); ?>
                </a>
            </span>
        </div>
    <?php endif; ?>
    <?php
    if ($this->show_page_heading && $this->page_title) {
    ?>
        <h1 class="display-6 mb-4">
            <?php echo $this->page_title; ?>
        </h1>
    <?php
    }
    ?>
    <?php echo  $this->event->afterDisplayTitle; ?>
    <?php
    ob_start();
    if ($this->record_id && $edit_allowed && $this->create_articles && $fullarticle_allowed) {
    ?>
        <button class="btn btn-sm btn-primary cbButton cbArticleSettingsButton" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_EDIT_ARTICLE_SETTINGS_TOOLTIP'), ENT_QUOTES, 'UTF-8'); ?>" onclick="if(document.getElementById('cbArticleOptions').style.display == 'none'){document.getElementById('cbArticleOptions').style.display='block'}else{document.getElementById('cbArticleOptions').style.display='none'};"><?php echo Text::_('COM_CONTENTBUILDERNG_SHOW_ARTICLE_SETTINGS'); ?></button>
    <?php
    }
    if (($edit_allowed || $new_allowed) && !$this->edit_by_type) {
    ?>
        <button class="btn btn-sm btn-primary cbButton cbSaveButton" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_EDIT_SAVE_TOOLTIP'), ENT_QUOTES, 'UTF-8'); ?>" onclick="document.getElementById('contentbuilderng_task').value='edit.apply';contentbuilderng.onSubmit();">
            <span class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></span>
            <?php echo trim($this->save_button_title) != '' ? htmlspecialchars($this->save_button_title, ENT_QUOTES, 'UTF-8') : Text::_('COM_CONTENTBUILDERNG_SAVE'); ?>
        </button>
    <?php
    }
    if ($this->record_id && $edit_allowed && $this->create_articles && $this->edit_by_type && $fullarticle_allowed) {
    ?>
        <button class="btn btn-sm btn-primary cbButton cbArticleSettingsButton" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_EDIT_APPLY_ARTICLE_SETTINGS_TOOLTIP'), ENT_QUOTES, 'UTF-8'); ?>" onclick="document.getElementById('contentbuilderng_task').value='edit.apply';contentbuilderng.onSubmit();">
            <span class="fa-solid fa-check me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_CONTENTBUILDERNG_APPLY_ARTICLE_SETTINGS'); ?>
        </button>
    <?php
    }
    $editToolbarExtraHtml = ob_get_clean();
    $buttons = LayoutHelper::render(
        'contentbuilderng.action_toolbar',
        [
            'toolbarClass' => 'mb-5',
            'currentRecordLabel' => $currentRecordLabel,
            'showCurrentRecordLabel' => $showCurrentRecordLabel,
            'prevRecordId' => $prevRecordId,
            'nextRecordId' => $nextRecordId,
            'prevHref' => $editPrevHref,
            'nextHref' => $editNextHref,
            'navBaseLink' => $editNavBaseLink,
            'prevTooltip' => Text::_('COM_CONTENTBUILDERNG_EDIT_PREVIOUS_TOOLTIP'),
            'nextTooltip' => Text::_('COM_CONTENTBUILDERNG_EDIT_NEXT_TOOLTIP'),
            'extraHtml' => $editToolbarExtraHtml,
            'showDelete' => $this->record_id && $delete_allowed,
            'deleteTitle' => Text::_('COM_CONTENTBUILDERNG_DELETE'),
            'deleteTooltip' => Text::_('COM_CONTENTBUILDERNG_EDIT_DELETE_TOOLTIP'),
            'showClose' => $showBack,
            'closeTitle' => Text::_('COM_CONTENTBUILDERNG_BACK'),
            'closeTooltip' => Text::_('COM_CONTENTBUILDERNG_EDIT_BACK_TOOLTIP'),
            'closeHref' => $jsBack ? '' : $backHref,
            'closeOnclick' => $jsBack ? 'history.back(-1);void(0);' : '',
        ],
        JPATH_COMPONENT_SITE . '/layouts'
    );

    if ($topBarToggle === 1) {
    ?>
    <?php
        echo $buttons;
    }

    if ($this->create_articles && $fullarticle_allowed) {

        ?>
        <?php
        if (!$this->edit_by_type) {
        ?>
            <form class="form-horizontal mt-5 mb-5" name="adminForm" id="adminForm" onsubmit="return false;" action="<?php echo Route::_('index.php?option=com_contentbuilderng&task=edit.display' . (Factory::getApplication()->getInput()->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->getInput()->get('layout', '', 'string') : '') . '&id=' . Factory::getApplication()->getInput()->getInt('id', 0) . '&record_id=' . Factory::getApplication()->getInput()->getCmd('record_id',  '') . (Factory::getApplication()->getInput()->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->getInput()->get('tmpl', '', 'string') : '') . '&Itemid=' . Factory::getApplication()->getInput()->getInt('Itemid', 0) . ($listQuery !== '' ? '&' . $listQuery : '')); ?>" method="post" enctype="multipart/form-data">
            <?php
        }
            ?>
            <?php
            if ($this->edit_by_type) {
            ?>
                <form class="mt-5 mb-5" name="adminForm" id="adminForm" onsubmit="return false;" action="<?php echo Route::_('index.php?option=com_contentbuilderng&task=edit.display' . (Factory::getApplication()->getInput()->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->getInput()->get('layout', '', 'string') : '') . '&id=' . Factory::getApplication()->getInput()->getInt('id', 0) . '&record_id=' . Factory::getApplication()->getInput()->getCmd('record_id',  '') . (Factory::getApplication()->getInput()->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->getInput()->get('tmpl', '', 'string') : '') . '&Itemid=' . Factory::getApplication()->getInput()->getInt('Itemid', 0) . ($listQuery !== '' ? '&' . $listQuery : '')); ?>" method="post" enctype="multipart/form-data">
                <?php
            }
                ?>

                <div id="cbArticleOptions" style="display:none;">

                    <fieldset class="border rounded p-3 mb-3">
                        <ul class="list-unstyled mb-0">
                            <li><?php echo $this->article_options->getLabel('alias'); ?>
                                <?php echo $this->article_options->getInput('alias'); ?></li>

                            <li><?php echo $this->article_options->getLabel('catid'); ?>
                                <?php echo $this->article_options->getInput('catid'); ?></li>

                            <!--<li><?php echo $this->article_options->getLabel('state'); ?>
	<?php echo $this->article_options->getInput('state'); ?></li>-->

                            <li><?php echo $this->article_options->getLabel('access'); ?>
                                <?php echo $this->article_options->getInput('access'); ?></li>

                            <li><?php echo $this->article_options->getLabel('featured'); ?>
                                <?php echo $this->article_options->getInput('featured'); ?></li>

                            <li><?php echo $this->article_options->getLabel('language'); ?>
                                <?php echo $this->article_options->getInput('language'); ?></li>
                            <?php
                            if (!$this->limited_options) {
                            ?>
                                <li><?php echo $this->article_options->getLabel('id'); ?>
                                    <?php echo $this->article_options->getInput('id'); ?></li>
                            <?php
                            }
                            ?>
                        </ul>
                        <div class="clr"></div>
                    </fieldset>

                    <fieldset class="border rounded p-3 mb-3">
                        <ul class="list-unstyled mb-0">

                            <?php
                            if (!$this->limited_options && Factory::getApplication()->isClient('administrator')) {
                            ?>
                                <li><?php echo $this->article_options->getLabel('created_by'); ?>
                                    <?php echo $this->article_options->getInput('created_by'); ?></li>

                            <?php
                            }
                            ?>
                            <li><?php echo $this->article_options->getLabel('created_by_alias'); ?>
                                <?php echo $this->article_options->getInput('created_by_alias'); ?></li>

                            <?php
                            if (!$this->limited_options) {
                            ?>
                                <li><?php echo $this->article_options->getLabel('created'); ?>
                                    <?php echo $this->article_options->getInput('created'); ?></li>
                            <?php
                            }
                            ?>

                            <li><?php echo $this->article_options->getLabel('publish_up'); ?>
                                <?php echo $this->article_options->getInput('publish_up'); ?></li>

                            <li><?php echo $this->article_options->getLabel('publish_down'); ?>
                                <?php echo $this->article_options->getInput('publish_down'); ?></li>
                            <?php
                            if (!$this->limited_options) {
                            ?>
                                <?php if ($this->article_settings->modified_by) : ?>
                                    <li><?php echo $this->article_options->getLabel('modified_by'); ?>
                                        <?php echo $this->article_options->getInput('modified_by'); ?></li>

                                    <li><?php echo $this->article_options->getLabel('modified'); ?>
                                        <?php echo $this->article_options->getInput('modified'); ?></li>
                                <?php endif; ?>

                                <?php if ($this->article_settings->version) : ?>
                                    <li><?php echo $this->article_options->getLabel('version'); ?>
                                        <?php echo $this->article_options->getInput('version'); ?></li>
                                <?php endif; ?>

                                <?php if ($this->article_settings->hits) : ?>
                                    <li><?php echo $this->article_options->getLabel('hits'); ?>
                                        <?php echo $this->article_options->getInput('hits'); ?></li>
                                <?php endif; ?>
                            <?php
                            }
                            ?>
                        </ul>
                    </fieldset>

                    <?php
                    if (!$this->limited_options) {
                    ?>
                        <?php $fieldSets = $this->article_options->getFieldsets('attribs'); ?>
                        <?php foreach ($fieldSets as $name => $fieldSet) : ?>
                            <?php if (!in_array($name, array('editorConfig', 'basic-limited'))) : ?>

                                <?php if (isset($fieldSet->description) && trim($fieldSet->description)) : ?>
                                    <p class="tip"><?php echo $this->escape(Text::_($fieldSet->description)); ?></p>
                                <?php endif; ?>
                                <fieldset class="border rounded p-3 mb-3">
                                    <ul class="list-unstyled mb-0">
                                        <?php foreach ($this->article_options->getFieldset($name) as $field) : ?>
                                            <li><?php echo $field->label; ?><?php echo $field->input; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </fieldset>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php
                    }
                    ?>
                    <fieldset class="border rounded p-3 mb-3">
                        <?php echo $this->article_options->getLabel('metadesc'); ?>
                        <?php echo $this->article_options->getInput('metadesc'); ?>

                        <?php echo $this->article_options->getLabel('metakey'); ?>
                        <?php echo $this->article_options->getInput('metakey'); ?>
                        <?php
                        if (!$this->limited_options) {
                        ?>
                            <?php foreach ($this->article_options->getGroup('metadata') as $field): ?>
                                <?php if ($field->hidden): ?>
                                    <?php echo $field->input; ?>
                                <?php else: ?>
                                    <?php echo $field->label; ?>
                                    <?php echo $field->input; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php
                        }
                        ?>
                    </fieldset>

                </div>
                <?php

                if (Factory::getApplication()->getInput()->get('tmpl', '', 'string') != '') {
                ?>
                    <input type="hidden" name="tmpl" value="<?php echo Factory::getApplication()->getInput()->get('tmpl', '', 'string'); ?>" />
                <?php
                }
                ?>
                <input type="hidden" name="Itemid" value="<?php echo Factory::getApplication()->getInput()->getInt('Itemid', 0); ?>" />
                <input type="hidden" name="task" id="contentbuilderng_task" value="edit.save" />
                <input type="hidden" name="backtolist" value="<?php echo Factory::getApplication()->getInput()->getInt('backtolist', 0); ?>" />
                <input type="hidden" name="return" value="<?php echo htmlspecialchars($safeReturn, ENT_QUOTES, 'UTF-8'); ?>" />
                <?php echo $listHiddenFields; ?>
                <?php echo $previewHiddenFields; ?>
                <?php echo HTMLHelper::_('form.token'); ?>
                <?php
                if ($this->edit_by_type) {
                ?>
                </form>
            <?php
                }
            ?>
            <?php echo $this->event->beforeDisplayContent; ?>
            <?php echo $this->toc ?>
            <?php echo $stateControlHtml; ?>
            <?php echo $ratingControlHtml; ?>
            <div class="cbEditableBody">
                <?php echo $columnHeaderHtml; ?>
                <?php echo $this->tpl ?>
            </div>
            <?php echo $this->event->afterDisplayContent; ?>
            <?php echo $auditTrailHtml; ?>
            <br />
            <?php
            if ($bottomBarToggle === 1) {

                echo $buttons;
            ?>
            <?php
            }
            ?>
            <?php
            if (!$this->edit_by_type) {
            ?>
            </form>
        <?php
            }
        ?>
        <?php
    } else {
        if ($this->edit_by_type) {
        ?>
            <form class="mt-5" name="adminForm" id="adminForm" onsubmit="return false;" action="<?php echo Route::_('index.php?option=com_contentbuilderng&task=edit.display' . (Factory::getApplication()->getInput()->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->getInput()->get('layout', '', 'string') : '') . '&id=' . Factory::getApplication()->getInput()->getInt('id', 0) . '&record_id=' . Factory::getApplication()->getInput()->getCmd('record_id',  '') . (Factory::getApplication()->getInput()->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->getInput()->get('tmpl', '', 'string') : '') . '&Itemid=' . Factory::getApplication()->getInput()->getInt('Itemid', 0) . ($listQuery !== '' ? '&' . $listQuery : '')); ?>" method="post" enctype="multipart/form-data">
                <?php
                if (Factory::getApplication()->getInput()->get('tmpl', '', 'string') != '') {
                ?>
                    <input type="hidden" name="tmpl" value="<?php echo Factory::getApplication()->getInput()->get('tmpl', '', 'string'); ?>" />
                <?php
                }
                ?>
                <input type="hidden" name="Itemid" value="<?php echo Factory::getApplication()->getInput()->getInt('Itemid', 0); ?>" />
                <input type="hidden" name="task" id="contentbuilderng_task" value="edit.save" />
                <input type="hidden" name="backtolist" value="<?php echo Factory::getApplication()->getInput()->getInt('backtolist', 0); ?>" />
                <input type="hidden" name="return" value="<?php echo htmlspecialchars($safeReturn, ENT_QUOTES, 'UTF-8'); ?>" />
                <?php echo $listHiddenFields; ?>
                <?php echo $previewHiddenFields; ?>
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
            <?php echo $this->event->beforeDisplayContent; ?>
            <?php echo $this->toc ?>
            <?php echo $stateControlHtml; ?>
            <?php echo $ratingControlHtml; ?>
            <div class="cbEditableBody">
                <?php echo $columnHeaderHtml; ?>
                <?php echo $this->tpl ?>
            </div>
            <?php echo $this->event->afterDisplayContent; ?>
            <?php echo $auditTrailHtml; ?>
            <br />
            <?php
            if ($bottomBarToggle === 1) {

                echo $buttons;
            ?>
            <?php
            }
            ?>
        <?php
        } else {
        ?>
            <form class="form-horizontal" name="adminForm" id="adminForm" onsubmit="return false;" action="<?php echo Route::_('index.php?option=com_contentbuilderng&task=edit.display' . (Factory::getApplication()->getInput()->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->getInput()->get('layout', '', 'string') : '') . '&id=' . Factory::getApplication()->getInput()->getInt('id', 0) . '&record_id=' . Factory::getApplication()->getInput()->getCmd('record_id',  '') . (Factory::getApplication()->getInput()->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->getInput()->get('tmpl', '', 'string') : '') . '&Itemid=' . Factory::getApplication()->getInput()->getInt('Itemid', 0) . ($listQuery !== '' ? '&' . $listQuery : '')); ?>" method="post" enctype="multipart/form-data">
                <?php echo $this->event->beforeDisplayContent; ?>
                <?php echo $this->toc ?>
                <?php echo $stateControlHtml; ?>
                <?php echo $ratingControlHtml; ?>
                <div class="cbEditableBody">
                    <?php echo $columnHeaderHtml; ?>
                    <?php echo $this->tpl ?>
                </div>
                <?php echo $this->event->afterDisplayContent; ?>
                <?php echo $auditTrailHtml; ?>
                <?php
                if (Factory::getApplication()->getInput()->get('tmpl', '', 'string') != '') {
                ?>
                    <input type="hidden" name="tmpl" value="<?php echo Factory::getApplication()->getInput()->get('tmpl', '', 'string'); ?>" />
                <?php
                }
                ?>
                <input type="hidden" name="Itemid" value="<?php echo Factory::getApplication()->getInput()->getInt('Itemid', 0); ?>" />
                <input type="hidden" name="task" id="contentbuilderng_task" value="edit.save" />
                <input type="hidden" name="backtolist" value="<?php echo Factory::getApplication()->getInput()->getInt('backtolist', 0); ?>" />
                <input type="hidden" name="return" value="<?php echo htmlspecialchars($safeReturn, ENT_QUOTES, 'UTF-8'); ?>" />
                <?php echo $listHiddenFields; ?>
                <?php echo $previewHiddenFields; ?>
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
            <?php
            if ($bottomBarToggle === 1) {

                echo $buttons;
            ?>
            <?php
            }
            ?>
    <?php
        }
    }
    ?>
</div>
