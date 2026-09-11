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
\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\ListLimitHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;
use CB\Component\Contentbuilderng\Administrator\Helper\RatingHelper;
use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Site\Helper\DebugPermissionHelper;
use CB\Component\Contentbuilderng\Site\Helper\NavigationLinkHelper;
use CB\Component\Contentbuilderng\Site\Helper\MenuParamHelper;
use CB\Component\Contentbuilderng\Site\Helper\PreviewColorModeHelper;
use CB\Component\Contentbuilderng\Site\Helper\PreviewLinkHelper;
use CB\Component\Contentbuilderng\Site\Helper\PreviewThemeHelper;
use CB\Component\Contentbuilderng\Site\Service\EmbeddedListActionFilterService;
use CB\Component\Contentbuilderng\Site\Service\EmbeddedListContextService;
use CB\Component\Contentbuilderng\Site\Service\EmbeddedListFieldFilterService;
use CB\Component\Contentbuilderng\Site\Service\MenuDataFilterService;

/** @var SiteApplication $app */
$app = \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication();
$cbListTemplateVariant = isset($cbListTemplateVariant) && is_string($cbListTemplateVariant)
	? trim(strtolower($cbListTemplateVariant))
	: 'default';
$isCardsVariant = $cbListTemplateVariant === 'cards';
$isCompactVariant = $cbListTemplateVariant === 'compact';
$isTilesVariant = $cbListTemplateVariant === 'tiles';
$usesCardLayout = $isCardsVariant || $isTilesVariant;
$frontend = $app->isClient('site');
$permissionService = PermissionService::createFromRuntimeContext();
$language_allowed = $permissionService->authorizeFe('language');
$edit_allowed = $frontend ? $permissionService->authorizeFe('edit') : $permissionService->authorize('edit');
$delete_allowed = $frontend ? $permissionService->authorizeFe('delete') : $permissionService->authorize('delete');
$view_allowed = $frontend ? $permissionService->authorizeFe('view') : $permissionService->authorize('view');
$new_allowed = $frontend ? $permissionService->authorizeFe('new') : $permissionService->authorize('new');
$state_allowed = $frontend ? $permissionService->authorizeFe('state') : $permissionService->authorize('state');
$publish_allowed = $frontend ? $permissionService->authorizeFe('publish') : $permissionService->authorize('publish');
$rating_allowed = $frontend ? $permissionService->authorizeFe('rating') : $permissionService->authorize('rating');
$wordwrapLabel = static function (string $label): string {
	return (string) ContentbuilderngHelper::contentbuilderng_wordwrap($label, 20, "\n", true);
							};
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

	// The custom properties let theme CSS reapply the state colour where
	// dark-mode table rules override the inline background with !important.
	return 'background-color:#' . $color . ';color:' . $textColor . ';'
		. '--cb-state-bg:#' . $color . ';--cb-state-fg:' . $textColor . ';';
							};

$input = $app->getInput();
$requestList = (array) $input->get('list', [], 'array');
$previewQuery = '';
$previewEnabled = $input->getBool('cb_preview', false);
$previewUntil = $input->getInt('cb_preview_until', 0);
$previewSig = (string) $input->getString('cb_preview_sig', '');
$previewActorId = $input->getInt('cb_preview_actor_id', 0);
$previewActorName = (string) $input->getString('cb_preview_actor_name', '');
$previewUserId = $input->getInt('cb_preview_user_id', 0);
$isAdminPreview = $input->getBool('cb_preview_ok', false);
$isBfLinked     = !empty($this->debug_mode) && !empty($this->debug_show_bf_id)
    && ($this->source_type ?? '') === 'com_breezingformsng'
    && ($this->source_reference_id ?? 0) > 0;
$currentUser = $app->getIdentity();
$currentSessionLabel = trim((string) ($currentUser->name ?? ''));
if ($currentSessionLabel === '') {
    $currentSessionLabel = trim((string) ($currentUser->username ?? ''));
							}
if ($currentSessionLabel === '') {
    $currentSessionLabel = Text::_('COM_CONTENTBUILDERNG_GUEST');
							}
$currentUserId = (int) ($currentUser->id ?? 0);
$ownerUserId = $isAdminPreview && $previewActorId > 0
    ? $previewActorId
    : $currentUserId;
$formInstance = $this->form ?? null;
$ownerPermissionMatrix = $permissionService->getStoredPermissions(null, '_fe');
$ownerRuleSet = (array) ($ownerPermissionMatrix['own_fe'] ?? []);
$hasOwnerViewRule = !empty($ownerRuleSet['view']);
$hasOwnerEditRule = !empty($ownerRuleSet['edit']);
$ownerPermissionCache = [];
$canAccessOwnedRecord = static function (string $action, $recordId) use (&$ownerPermissionCache, $ownerUserId, $formInstance, $ownerRuleSet): bool {
    $recordId = (string) $recordId;

    if (
        $recordId === ''
        || $recordId === '0'
        || $ownerUserId <= 0
        || empty($ownerRuleSet[$action])
        || !is_object($formInstance)
        || !method_exists($formInstance, 'isOwner')
    ) {
        return false;
    }

    $cacheKey = $action . ':' . $recordId;

    if (array_key_exists($cacheKey, $ownerPermissionCache)) {
        return $ownerPermissionCache[$cacheKey];
    }

    $ownerPermissionCache[$cacheKey] = (bool) $formInstance->isOwner($ownerUserId, $recordId);

    return $ownerPermissionCache[$cacheKey];
							};
$showEditAction = !empty($this->edit_button) && $edit_allowed;
if (!$showEditAction && !empty($this->edit_button) && $hasOwnerEditRule) {
    foreach ((array) $this->items as $item) {
        if ($canAccessOwnedRecord('edit', $item->colRecord ?? 0)) {
            $showEditAction = true;
            break;
        }
    }
							}
$previewActorLabel = trim($previewActorName);
if ($previewActorLabel === '' && $previewActorId > 0) {
    $previewActorLabel = '#' . $previewActorId;
							}
$showPreviewSessionBadge = $isAdminPreview && $currentSessionLabel !== '' && $currentSessionLabel !== $previewActorLabel;
$adminReturnContext = trim((string) $input->getCmd('cb_admin_return', ''));
$adminReturnUrl = Uri::root() . 'administrator/index.php?option=com_contentbuilderng&task=form.edit&id=' . (int) $input->getInt('id', 0);
if ($adminReturnContext === 'forms') {
    $adminReturnUrl = Uri::root() . 'administrator/index.php?option=com_contentbuilderng&view=forms';
							} elseif ($adminReturnContext === 'storages') {
    $adminReturnUrl = Uri::root() . 'administrator/index.php?option=com_contentbuilderng&view=storages';
							}
$previewFormName = trim((string) ($this->form_name ?? ''));
if ($previewFormName === '') {
    $previewFormName = trim((string) ($this->page_title ?? ''));
							}
if ($previewFormName === '') {
    $previewFormName = Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
							}
$previewFormName = htmlspecialchars($previewFormName, ENT_QUOTES, 'UTF-8');

// Item wrappers encode reserved characters before the template receives them.
// Decode once before the final HTML escape to avoid displaying entities such
// as &#40; and &#41; literally while keeping the rendered output escaped.
$escapeListValue = static function (mixed $value): string {
	return htmlspecialchars(
		html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
		ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
		'UTF-8'
	);
};
$previewConfigTabLabel = Text::sprintf('COM_CONTENTBUILDERNG_PREVIEW_CONFIG_TAB', Text::_('COM_CONTENTBUILDERNG_FORM'));
$previewFrontendPermissionHint = Text::sprintf(
    'COM_CONTENTBUILDERNG_PREVIEW_FRONTEND_PERMISSION_HINT',
    Text::_('COM_CONTENTBUILDERNG_PERM_LIST_ACCESS'),
    Text::_('COM_CONTENTBUILDERNG_DISPLAY_FRONTEND')
);
$currentListLayout = trim((string) $input->getCmd('layout', 'default'));
if ($currentListLayout === '') {
    $currentListLayout = 'default';
							}
$currentListLayoutQuery = $currentListLayout !== 'default' ? '&layout=' . rawurlencode($currentListLayout) : '';
$embeddedListContext = (string) $input->getCmd('cblist_embed', '');
$isEmbeddedListRequest = EmbeddedListFieldFilterService::isEmbeddedRequest($embeddedListContext);
$embeddedListFields = $isEmbeddedListRequest
    ? trim((string) $input->getString('cblist_fields', ''))
    : '';
$embeddedListRawActions = $isEmbeddedListRequest
    ? trim((string) $input->getString('cblist_actions', ''))
    : '';
$embeddedListSort = $isEmbeddedListRequest ? trim((string) $input->getString('cblist_sort', '')) : '';
$embeddedListDir = $isEmbeddedListRequest ? trim((string) $input->getString('cblist_dir', '')) : '';
$embeddedListLimit = $isEmbeddedListRequest ? (string) $input->getInt('cblist_limit', 0) : '';
$embeddedListHidePagination = $isEmbeddedListRequest && $input->getBool('cblist_hide_pagination', false);
try {
    $cbListAllowedActions = EmbeddedListActionFilterService::parseActions($embeddedListRawActions);
							} catch (\InvalidArgumentException) {
    // cblist_actions is plugin-generated and already validated before this
    // ever renders — this only fires for a hand-crafted request bypassing
    // the plugin. Fail open to "unrestricted" rather than a fatal error.
    $cbListAllowedActions = [];
							}
$cbListActionAllowed = static fn(string $action): bool
    => EmbeddedListActionFilterService::isAllowed($action, $cbListAllowedActions);
$resolveMenuVisibility = static function (string $value, bool $default): bool {
    return match ($value) {
        'yes' => true,
        'no' => false,
        default => $default,
    };
};
$showStateControl = $resolveMenuVisibility(
    (string) $input->getCmd('cb_new_show_state', 'default'),
    !empty($this->list_state)
);
$showStateBulkControl = $resolveMenuVisibility(
    (string) $input->getCmd('cb_new_show_state_bulk', 'default'),
    !empty($this->list_state_bulk)
);
$showStateFilter = $resolveMenuVisibility(
    (string) $input->getCmd('cb_new_show_state_filter', 'default'),
    !empty($this->show_state_filter)
);
$showEditAction = $resolveMenuVisibility(
    (string) $input->getCmd('cb_new_show_list_edit', 'default'),
    $showEditAction
) && $cbListActionAllowed('edit');
$embeddedListParams = EmbeddedListContextService::parameters(
    $embeddedListContext,
    $embeddedListFields,
    $embeddedListRawActions,
    $embeddedListLimit,
    $embeddedListHidePagination ? '1' : ''
);
$embeddedListQuery = EmbeddedListContextService::buildQuery(
    $embeddedListContext,
    $embeddedListFields,
    $embeddedListRawActions,
    $embeddedListLimit,
    $embeddedListHidePagination ? '1' : ''
);
$embeddedListTitle = $isEmbeddedListRequest
    ? trim((string) $input->getString('cblist_title', ''))
    : '';
$embeddedListTitleProvided = $isEmbeddedListRequest && $input->getBool('cblist_title_set', false);
$embeddedListTitleQuery = $embeddedListTitleProvided
    ? '&cblist_title=' . rawurlencode($embeddedListTitle) . '&cblist_title_set=1'
    : '';
$previewLayoutOptions = [
    'default' => Text::_('COM_CONTENTBUILDERNG_PREVIEW_LIST_LAYOUT_DEFAULT'),
    'listcard' => Text::_('COM_CONTENTBUILDERNG_PREVIEW_LIST_LAYOUT_LISTCARD'),
    'listcompact' => Text::_('COM_CONTENTBUILDERNG_PREVIEW_LIST_LAYOUT_LISTCOMPACT'),
    'listtiles' => Text::_('COM_CONTENTBUILDERNG_PREVIEW_LIST_LAYOUT_LISTTILES'),
];
if (!isset($previewLayoutOptions[$currentListLayout])) {
    $currentListLayout = 'default';
							}
$previewLayoutSelectOptions = [];
$directStorageMode = !empty($this->direct_storage_mode);
$directStorageId = (int) ($this->direct_storage_id ?? 0);
$directStorageReadOnly = !empty($this->direct_storage_read_only);
$directStorageUnpublished = !empty($this->direct_storage_unpublished);
$directStoragePublishAllowed = $directStorageMode
    && !$directStorageReadOnly
    && ($isAdminPreview || $app->getIdentity()->authorise('core.edit.state', 'com_contentbuilderng'));
if ($directStorageReadOnly) {
    $edit_allowed = false;
    $delete_allowed = false;
    $new_allowed = false;
    $state_allowed = false;
    $publish_allowed = false;
							}
if ($directStorageMode && $directStorageId > 0 && $adminReturnContext !== 'storages') {
    $adminReturnUrl = Uri::root() . 'administrator/index.php?option=com_contentbuilderng&view=storage&layout=edit&id=' . $directStorageId;
							}
$listTarget = $directStorageMode
    ? ('storage_id=' . $directStorageId)
    : ('id=' . (int) $input->getInt('id', 0));
$listState = [
    'limit' => (int) (($this->state?->get('list.limit')) ?? ($this->pagination?->limit ?? ($requestList['limit'] ?? 0))),
    'start' => (int) ($this->lists['liststart'] ?? $this->pagination?->limitstart ?? ($requestList['start'] ?? 0)),
    'ordering' => (string) ($this->lists['order'] ?? (isset($requestList['ordering']) ? preg_replace('/[^A-Za-z0-9_\\.]/', '', (string) $requestList['ordering']) : '')),
    'direction' => (string) ($this->lists['order_Dir'] ?? (isset($requestList['direction']) ? strtolower((string) $requestList['direction']) : '')),
];
$listStart = (int) $listState['start'];
$limitValue = (int) $listState['limit'];
$listOrder = (string) $listState['ordering'];
$listDirn = (string) $listState['direction'];
$state = $this->state ?? null;
$exportQueryParams = [
    'option' => 'com_contentbuilderng',
    'view' => 'export',
    'id' => (int) $input->getInt('id', 0),
    'type' => 'xls',
    'format' => 'raw',
    'tmpl' => 'component',
    'Itemid' => (int) $input->getInt('Itemid', 0),
    'filter_order' => $listState['ordering'],
    'filter_order_Dir' => $listState['direction'],
    'filter' => (string) ($state?->get('formsd_filter') ?? $input->getString('filter', '')),
    'list_state_filter' => (int) ($state?->get('formsd_filter_state') ?? $input->getInt('list_state_filter', 0)),
    'list_publish_filter' => (int) ($state?->get('formsd_filter_publish') ?? $input->getInt('list_publish_filter', -1)),
    'list_language_filter' => (string) ($state?->get('formsd_filter_language') ?? $input->getCmd('list_language_filter', '')),
];
$menuExportContext = array_filter([
    'cb_new_list_menu' => $input->getInt('cb_new_list_menu', 0),
    MenuDataFilterService::INPUT_NAME => (string) $input->get(MenuDataFilterService::INPUT_NAME, '', 'raw'),
    'cb_menu_search_fields' => (string) $input->getString('cb_menu_search_fields', ''),
    'cb_menu_export_fields' => (string) $input->getString('cb_menu_export_fields', ''),
    'cb_menu_published_fields' => (string) $input->getString('cb_menu_published_fields', ''),
    'cblist_fields' => (string) $input->getString('cblist_fields', ''),
    'cblist_sort' => (string) $input->getString('cblist_sort', ''),
    'cblist_dir' => (string) $input->getString('cblist_dir', ''),
    'cblist_limit' => $input->getInt('cblist_limit', 0),
    'cb_export_filename_mode' => (string) $input->getCmd('cb_export_filename_mode', ''),
    'cb_export_filename' => (string) $input->getString('cb_export_filename', ''),
], static fn(string|int $value): bool => $value !== '' && $value !== 0);
$exportQueryParams = array_merge($exportQueryParams, $menuExportContext);
if ($isEmbeddedListRequest) {
    $exportQueryParams = array_merge($exportQueryParams, $embeddedListParams, [
        'cblist_sort' => $embeddedListSort,
        'cblist_dir' => $embeddedListDir,
    ]);
}
$listQuery = http_build_query(['list' => $listState]);
$formatListLastModification = static function ($value): string {
    $raw = trim((string) $value);

    if ($raw === '' || $raw === '0000-00-00 00:00:00') {
        return '';
    }

    return HTMLHelper::_('date', $raw, Text::_('DATE_FORMAT_LC5'));
							};
if ($isAdminPreview && !$directStorageMode) {
    $previewLayoutBaseParams = Uri::getInstance()->getQuery(true);
    // The router/dispatcher use a transient "controller"/"task" pair for SEF
    // resolution (see Router::parse() and Dispatcher); carrying them into a
    // freshly generated link causes Joomla to look for a "list" controller
    // class instead of dispatching view=list normally.
    unset($previewLayoutBaseParams['controller'], $previewLayoutBaseParams['task']);
    $previewLayoutBaseParams['list'] = $listState;

    foreach ($previewLayoutOptions as $layoutName => $layoutLabel) {
        $params = $previewLayoutBaseParams;
        // Explicitly set "default" rather than removing the key: a menu item
        // for this view/id can carry its own preset layout, which Joomla's
        // SEF router silently backfills into any generated link that omits
        // "layout" entirely, making "Default" resolve to the same URL as
        // whichever layout the menu item happens to default to.
        $params['layout'] = $layoutName;
        $previewLayoutSelectOptions[] = [
            'value' => Route::_('index.php?' . http_build_query($params), false),
            'label' => $layoutLabel,
            'selected' => $layoutName === $currentListLayout,
        ];
    }
    usort($previewLayoutSelectOptions, static function (array $a, array $b): int {
        return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
    });
							}
if ($directStorageMode) {
    $view_allowed = true;
    $publish_allowed = $directStoragePublishAllowed;
							}
if ($previewEnabled && $previewUntil > 0 && $previewSig !== '') {
    $previewQuery = PreviewLinkHelper::buildQuery(
        (int) $previewUntil,
        (int) $previewActorId,
        (string) $previewActorName,
        (int) $previewUserId,
        (string) $previewSig,
        (string) $adminReturnContext
    );
							}
$previewColorMode = PreviewColorModeHelper::resolve($input, $isAdminPreview || $directStorageMode);
$previewQuery = PreviewColorModeHelper::appendQuery($previewQuery, $previewColorMode);
$previewTheme = (string) ($this->preview_theme ?? '');
$previewQuery = PreviewThemeHelper::appendQuery($previewQuery, $previewTheme);

if ($isAdminPreview) {
    $view_allowed = true;
							}

// {CBList actions="..."} narrows what's rendered on top of ACL, never the
// other way round: every flag below already carries its ACL/ownership
// check (and, for direct storage or admin preview, the overrides above),
// so ANDing the action allow-list here can only turn an allowed control
// off, never turn a disallowed one on. Controls with no ACL dimension of
// their own (the search box, the state/publish/language *filter*
// dropdowns, which any viewer can use regardless of $state_allowed etc.)
// have no existing flag to narrow here — those are gated inline where
// they render, further down.
$new_allowed = $new_allowed && $cbListActionAllowed('new');
$delete_allowed = $delete_allowed && $cbListActionAllowed('delete');
$state_allowed = $state_allowed && $cbListActionAllowed('state');
$publish_allowed = $publish_allowed && $cbListActionAllowed('publish');
$rating_allowed = $rating_allowed && $cbListActionAllowed('rating');
$language_allowed = $language_allowed && $cbListActionAllowed('language');

$document = $app->getDocument();
$wa = $document->getWebAssetManager();
$ratingCsrfToken = Session::getFormToken();

// Charge le manifeste joomla.asset.json du composant
$wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');

$wa->useStyle('com_contentbuilderng.frontend');
$wa->useStyle('com_contentbuilderng.list');
if (!empty($this->debug_mode)) {
	$wa->useStyle('com_contentbuilderng.debug-panel');
							}
$wa->useScript('com_contentbuilderng.contentbuilderng');
PreviewColorModeHelper::registerAssets($wa, $previewColorMode);

$___getpost = 'post';
$___tableOrdering = "Joomla.tableOrdering = function";

$themeCss = trim((string) ($this->theme_css ?? ''));
if ($themeCss !== '') {
	$wa->addInlineStyle($themeCss);
							}

$themeJs = (string) ($this->theme_js ?? '');
if (trim($themeJs) !== '') {
    $wa->addInlineScript($themeJs);
							}


if (!empty($this->list_header_sticky)) {
	$wa->addInlineScript(
		<<<'JS'
(() => {
	const initStickyHeader = (form) => {
		const scrollBox = form.querySelector('.cb-scroll-x');
		const table = form.querySelector('.cb-list-table');
		const thead = table ? table.querySelector('thead') : null;

		if (!scrollBox || !table || !thead) {
			return;
		}

		const stickyBar = form.querySelector('.cb-list-sticky');
		const cloneHost = document.createElement('div');
		cloneHost.className = 'cb-list-sticky-head-clone';

		const cloneTable = document.createElement('table');
		cloneTable.className = table.className;

		const cloneHead = thead.cloneNode(true);
		cloneTable.appendChild(cloneHead);
		cloneHost.appendChild(cloneTable);
		document.body.appendChild(cloneHost);

		const sourceHeaders = Array.from(thead.querySelectorAll('th'));
		const cloneHeaders = Array.from(cloneHead.querySelectorAll('th'));

		const getTopOffset = () => {
			const offset = stickyBar ? Math.ceil(stickyBar.getBoundingClientRect().height) + 12 : 8;
			form.style.setProperty('--cb-list-table-header-sticky-top', `${offset}px`);
			return offset;
		};

		const syncGeometry = () => {
			const scrollRect = scrollBox.getBoundingClientRect();
			const tableRect = table.getBoundingClientRect();
			const headHeight = thead.getBoundingClientRect().height;
			const topOffset = getTopOffset();
			const shouldShow = tableRect.top < topOffset && tableRect.bottom - headHeight > topOffset;

			cloneHost.style.left = `${scrollRect.left}px`;
			cloneHost.style.width = `${scrollRect.width}px`;
			cloneHost.style.top = `${topOffset}px`;
			cloneTable.style.width = `${table.offsetWidth}px`;
			cloneTable.style.transform = `translateX(${-scrollBox.scrollLeft}px)`;

			sourceHeaders.forEach((header, index) => {
				if (!cloneHeaders[index]) {
					return;
				}
				const width = header.getBoundingClientRect().width;
				cloneHeaders[index].style.width = `${width}px`;
				cloneHeaders[index].style.minWidth = `${width}px`;
				cloneHeaders[index].style.maxWidth = `${width}px`;
			});

			cloneHost.classList.toggle('is-visible', shouldShow);
		};

		scrollBox.addEventListener('scroll', syncGeometry, { passive: true });
		window.addEventListener('scroll', syncGeometry, { passive: true });
		window.addEventListener('resize', syncGeometry);
		window.addEventListener('load', syncGeometry);

		syncGeometry();
	};

	const boot = () => {
		document.querySelectorAll('form.cb-list-has-sticky-header').forEach(initStickyHeader);
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot, { once: true });
	} else {
		boot();
	}
							})();
JS
	);
							}
$cbListConfig = [
	'ratingCsrfTokenParam' => $ratingCsrfToken . '=1',
	'text' => [
		'confirmDelete' => Text::_('COM_CONTENTBUILDERNG_CONFIRM_DELETE_MESSAGE'),
		'published' => Text::_('JPUBLISHED'),
		'unpublished' => Text::_('JUNPUBLISHED'),
	],
];
$cbListInitScriptPath = JPATH_ROOT . '/media/com_contentbuilderng/js/list-init.js';
$cbListInitScriptVersion = is_file($cbListInitScriptPath) ? (string) filemtime($cbListInitScriptPath) : '1';
?>
<script>
	window.cbListConfig = <?php echo json_encode($cbListConfig, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(Uri::root(true) . '/media/com_contentbuilderng/js/list-init.js?' . $cbListInitScriptVersion, ENT_QUOTES, 'UTF-8'); ?>"></script>

<?php if ($this->show_page_heading && $this->page_title && !$embeddedListTitleProvided): ?>
	<div class="cb-list-titlebar">
		<h1 class="h3 cb-list-title">
			<?php echo $this->page_title; ?>
		</h1>
	</div>
<?php endif; ?>
	<?php if (!empty($this->debug_mode)): ?>
		<div class="mb-3">
			<span class="badge text-bg-danger fs-6 px-3 py-2">
				<span class="fa-solid fa-bug me-1" aria-hidden="true"></span><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_BADGE'); ?>
			</span>
			<span class="badge text-bg-secondary fs-6 px-3 py-2 ms-2">
				<span class="fa-solid fa-stopwatch me-1" aria-hidden="true"></span><?php echo Text::sprintf('COM_CONTENTBUILDERNG_DEBUG_RENDER_TIME', $this->render_time_ms); ?>
			</span>
		</div>
		<?php
		$debugPermissions = DebugPermissionHelper::resolvePermissions(
			$permissionService,
			Factory::getApplication(),
			(int) ($this->form_id ?? 0),
			$frontend
		);
		$debugCbListActions = EmbeddedListActionFilterService::debugState(
			['search', 'state', 'publish', 'language', 'new', 'edit', 'delete', 'export', 'rating', 'detail'],
			$cbListAllowedActions
		);
		$debugFilters = [
			'search' => (string) ($state?->get('formsd_filter') ?? ''),
			'state' => (int) ($state?->get('formsd_filter_state') ?? 0),
			'published' => (int) ($state?->get('formsd_filter_publish') ?? -1),
			'language' => (string) ($state?->get('formsd_filter_language') ?? ''),
			'ordering' => $listState['ordering'],
			'direction' => $listState['direction'],
			'limit' => $listState['limit'],
			'start' => $listState['start'],
		];
		if (!empty($this->debug_enable_logs)) {
			Logger::info('Frontend list debug request', [
				'formId' => (int) ($this->form_id ?? 0),
				'total' => (int) ($this->total ?? 0),
			]);
		}
		$debugFields = [];
		$debugValidationsEnabled = (bool) ComponentHelper::getParams('com_contentbuilderng')->get('enable_validations', 1);
		$debugFormId = (int) ($this->form_id ?? 0);
		if ($debugFormId > 0) {
			$debugDb = \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getDatabase();
			$debugFieldsQuery = $debugDb->getQuery(true)
				->select([
					$debugDb->quoteName('label'),
					$debugDb->quoteName('reference_id'),
					$debugDb->quoteName('type'),
					$debugDb->quoteName('editable'),
					$debugDb->quoteName('published'),
					$debugDb->quoteName('validations'),
					$debugDb->quoteName('custom_validation_script'),
				])
				->from($debugDb->quoteName('#__contentbuilderng_elements'))
				->where($debugDb->quoteName('form_id') . ' = ' . $debugFormId)
				->order($debugDb->quoteName('ordering'));
			$debugDb->setQuery($debugFieldsQuery);
			$debugFields = $debugDb->loadAssocList() ?: [];
		}
		echo LayoutHelper::render('contentbuilderng.debug_panel', [
			'formId' => (int) ($this->form_id ?? 0),
			'showPermissions' => !empty($this->debug_show_permissions),
			'permissions' => $debugPermissions,
			'cbListActions' => $debugCbListActions,
			'showFilters' => !empty($this->debug_show_filters),
			'filters' => $debugFilters,
			'showLogs' => !empty($this->debug_enable_logs) && !empty($this->debug_show_request_logs),
			'logs' => Logger::getRequestEntries(),
			'fields' => $debugFields,
			'validationsEnabled' => $debugValidationsEnabled,
		]);
		?>
	<?php endif; ?>
	<?php // Le bandeau "Prévisualisation" ne doit s'afficher qu'en vraie
	// prévisualisation admin (signature cb_preview_* valide) : le mode
	// storage direct est désormais aussi un point d'entrée public normal
	// (item de menu créé par l'Assistant Storage), pas seulement un aperçu. ?>
	<?php if ($isAdminPreview): ?>
			<div class="alert alert-warning cbPreviewBanner d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
				<span>
					<span class="icon-eye icon-fw" aria-hidden="true"></span>
					<strong><?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW_MODE'); ?></strong>
					<?php if (!$directStorageMode && !empty($previewLayoutSelectOptions)) : ?>
						<span class="d-inline-flex align-items-center gap-2 ms-2">
							<label for="cb-preview-layout-select"><?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW_LIST_LAYOUT'); ?></label>
							<select
								id="cb-preview-layout-select"
								class="form-select form-select-sm w-auto cb-preview-layout-select"
								title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_PREVIEW_LIST_LAYOUT_TOOLTIP'), ENT_QUOTES, 'UTF-8'); ?>"
								onchange="if (this.value) { window.location.href = this.value; }">
								<?php foreach ($previewLayoutSelectOptions as $layoutOption) : ?>
									<option value="<?php echo htmlspecialchars($layoutOption['value'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $layoutOption['selected'] ? ' selected' : ''; ?>>
										<?php echo htmlspecialchars($layoutOption['label'], ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</span>
					<?php endif; ?>
					<?php echo LayoutHelper::render('contentbuilderng.preview_color_mode', ['mode' => $previewColorMode]); ?>
					<?php echo LayoutHelper::render('contentbuilderng.preview_theme', ['theme' => $previewTheme, 'storedTheme' => (string) ($this->stored_theme ?? ''), 'themes' => PreviewThemeHelper::availableThemes()]); ?>
					<?php echo ' - ' . Text::sprintf($directStorageMode ? 'COM_CONTENTBUILDERNG_PREVIEW_CURRENT_STORAGE' : 'COM_CONTENTBUILDERNG_PREVIEW_CURRENT_FORM', $previewFormName); ?>
	                <?php if ($previewActorLabel !== ''): ?>
	                    <span class="badge text-bg-secondary ms-2"><?php echo Text::sprintf('COM_CONTENTBUILDERNG_PREVIEW_ACTOR_BADGE', htmlspecialchars($previewActorLabel, ENT_QUOTES, 'UTF-8')); ?><?php echo $previewActorId > 0 ? ' (#' . (int) $previewActorId . ')' : ''; ?></span>
	                <?php endif; ?>
                <?php if ($showPreviewSessionBadge): ?>
                    <span class="badge text-bg-secondary ms-1"><?php echo Text::sprintf('COM_CONTENTBUILDERNG_PREVIEW_SESSION_BADGE', htmlspecialchars($currentSessionLabel, ENT_QUOTES, 'UTF-8')); ?></span>
                <?php endif; ?>
				<?php if (!$directStorageMode) : ?>
					<span class="cb-preview-config-help" title="<?php echo htmlspecialchars($previewConfigTabLabel, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($previewConfigTabLabel, ENT_QUOTES, 'UTF-8'); ?>" tabindex="0">
						<span class="fa-solid fa-circle-question" aria-hidden="true"></span>
					</span>
				<?php endif; ?>
                <?php if (!$this->preview_list_access_configured): ?>
                    <small class="d-block mt-1">
                        <?php echo htmlspecialchars($previewFrontendPermissionHint, ENT_QUOTES, 'UTF-8'); ?>
                    </small>
                <?php endif; ?>
			</span>
			<a class="btn btn-sm btn-outline-secondary" href="<?php echo $adminReturnUrl; ?>" data-cb-close-preview>
				<span class="fa-solid fa-xmark me-1" aria-hidden="true"></span>
				<?php echo Text::_('COM_CONTENTBUILDERNG_CLOSE_PREVIEW'); ?>
			</a>
		</div>
	<?php endif; ?>
<?php if ($directStorageMode && $directStorageUnpublished): ?>
	<div class="alert alert-warning mb-3">
		<?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW_UNPUBLISHED_STORAGE_NOTICE'); ?>
	</div>
<?php endif; ?>
<?php if (!empty($this->preview_no_list_fields)): ?>
	<div class="alert alert-warning mb-3">
		<?php echo !empty($this->invalid_list_setup)
			? Text::_('COM_CONTENTBUILDERNG_LIST_VIEW_INCOMPLETE')
			: Text::_($directStorageMode ? 'COM_CONTENTBUILDERNG_PREVIEW_NO_STORAGE_FIELDS' : 'COM_CONTENTBUILDERNG_PREVIEW_NO_LIST_FIELDS'); ?>
	</div>
<?php endif; ?>
<?php if ($this->embedded_list_errors !== []): ?>
	<div class="alert alert-warning mb-0" data-cblist-errors-only>
		<div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
			<strong>
				<span class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></span>
				<?php echo Text::_('COM_CONTENTBUILDERNG_CBLIST_VALIDATION_INTRO'); ?>
			</strong>
			<?php if ($this->embedded_list_help_url !== ''): ?>
				<a href="<?php echo htmlspecialchars($this->embedded_list_help_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
					<span class="fa-solid fa-circle-question me-1" aria-hidden="true"></span>
					<?php echo Text::_('COM_CONTENTBUILDERNG_CBLIST_SYNTAX_HELP'); ?>
				</a>
			<?php endif; ?>
		</div>
		<ul class="mb-0">
			<?php foreach ($this->embedded_list_errors as $embeddedListError): ?>
				<li><?php echo htmlspecialchars($embeddedListError, ENT_QUOTES, 'UTF-8'); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>
<?php if ($this->embedded_list_errors !== []) { return; } ?>
<?php if ($embeddedListTitleProvided) : ?>
	<?php if ($embeddedListTitle !== '') : ?>
		<div class="cb-list-titlebar">
			<h1 class="h3 cb-list-title">
				<?php echo nl2br(htmlspecialchars($embeddedListTitle, ENT_QUOTES, 'UTF-8'), false); ?>
			</h1>
		</div>
	<?php endif; ?>
<?php else : ?>
	<?php echo ContentbuilderngHelper::sanitizeStoredHtml($this->intro_text); ?>
<?php endif; ?>

	<form action="<?php echo Route::_('index.php?option=com_contentbuilderng&task=list.display&' . $listTarget . $currentListLayoutQuery . '&Itemid=' . (int) \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->getInt('Itemid', 0) . $embeddedListQuery . $embeddedListTitleQuery . $previewQuery); ?>"
		method="<?php echo $___getpost; ?>" name="adminForm" id="adminForm" class="cb-list-template-<?php echo htmlspecialchars($cbListTemplateVariant, ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($this->list_header_sticky) && !$isCardsVariant && !$isTilesVariant ? ' cb-list-has-sticky-header' : ''; ?>">
	<?php
	$showNewButton = ($new_allowed && !empty($this->new_button));
	$hasBulkSelection = !empty($this->select_column);
	$showStickyButtonBar = !empty($this->button_bar_sticky);
	$showPreviewLink = !empty($this->show_preview_link);
	$showTopBar = MenuParamHelper::resolveInputOrMenuToggle($app, 'cb_show_top_bar', (int) ($this->cb_show_top_bar ?? 1)) === 1;
	$showBottomBar = MenuParamHelper::resolveInputOrMenuToggle($app, 'cb_show_bottom_bar', (int) ($this->cb_show_bottom_bar ?? 1)) === 1;
	$hasTopBarContent = $language_allowed
			|| ($hasBulkSelection && $showStateBulkControl && $state_allowed && count($this->states))
		|| ($this->list_publish && $publish_allowed)
		|| ($this->display_filter && $cbListActionAllowed('search'))
		|| ($showStateFilter && count($this->states) && $cbListActionAllowed('state'))
		|| ($this->list_publish && $cbListActionAllowed('publish'))
		|| ($this->list_language && $cbListActionAllowed('language'))
		|| $showNewButton
		|| ($hasBulkSelection && $delete_allowed)
		|| ($this->show_records_per_page && !$embeddedListHidePagination)
		|| ($this->export_xls && empty($this->invalid_list_setup) && $cbListActionAllowed('export'));
	$showTopBar = $showTopBar && $hasTopBarContent;
	$listEditBaseParams = [
		'option' => 'com_contentbuilderng',
		'task' => 'edit.display',
		'backtolist' => 1,
		($directStorageMode ? 'storage_id' : 'id') => $directStorageMode ? $directStorageId : (int) \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->getInt('id', 0),
		'Itemid' => (int) \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->getInt('Itemid', 0),
	] + $embeddedListParams;
	$listEditTmpl = (string) \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->get('tmpl', '', 'string');
	if ($listEditTmpl !== '') {
		$listEditBaseParams['tmpl'] = $listEditTmpl;
	}
	$listEditLayout = (string) \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->get('layout', '', 'string');
	if ($listEditLayout !== '') {
		$listEditBaseParams['layout'] = $listEditLayout;
	}
	if ($listQuery !== '') {
		$listEditBaseParams['list'] = [
			'start' => $listStart,
			'limit' => $limitValue,
			'ordering' => $listOrder,
			'direction' => $listDirn,
		];
	}
	$listPublishBaseParams = [
		'option' => 'com_contentbuilderng',
		'task' => 'edit.publish',
		'backtolist' => 1,
		($directStorageMode ? 'storage_id' : 'id') => $directStorageMode ? $directStorageId : (int) \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->getInt('id', 0),
		'Itemid' => (int) \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->getInt('Itemid', 0),
	] + $embeddedListParams;
	if ($listEditTmpl !== '') {
		$listPublishBaseParams['tmpl'] = $listEditTmpl;
	}
	if ($listEditLayout !== '') {
		$listPublishBaseParams['layout'] = $listEditLayout;
	}
	if ($listQuery !== '') {
		$listPublishBaseParams['list'] = [
			'start' => $listStart,
			'limit' => $limitValue,
			'ordering' => $listOrder,
			'direction' => $listDirn,
		];
	}
	$listDetailsBaseParams = [
		'option' => 'com_contentbuilderng',
		'task' => 'details.display',
		($directStorageMode ? 'storage_id' : 'id') => $directStorageMode ? $directStorageId : (int) \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->getInt('id', 0),
		'Itemid' => (int) \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->getInt('Itemid', 0),
	] + $embeddedListParams;
	$listTmpl = (string) \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->get('tmpl', '', 'string');
	if ($listTmpl !== '') {
		$listDetailsBaseParams['tmpl'] = $listTmpl;
	}
	$listLayout = (string) \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->get('layout', '', 'string');
	if ($listLayout !== '') {
		$listDetailsBaseParams['layout'] = $listLayout;
	}
	if ($listQuery !== '') {
		$listDetailsBaseParams['list'] = [
			'start' => $listStart,
			'limit' => $limitValue,
			'ordering' => $listOrder,
			'direction' => $listDirn,
		];
	}
	$newRecordLink = '';
	if ($showNewButton) {
		$newRecordLink = Route::_(
			NavigationLinkHelper::buildRouteLink($listEditBaseParams + [
				'record_id' => 0,
			], $previewQuery)
		);
	}
	?>
	<?php if ($showTopBar) : ?>
		<div class="<?php echo $showStickyButtonBar ? 'cb-list-sticky' : ''; ?>">
			<div class="cb-list-panel cb-list-sticky-panel">
			<div class="cb-list-filters w-100">
				<?php if ($language_allowed) : ?>
					<div class="cb-list-filters-row">
						<div class="d-inline-flex align-items-center gap-1 me-2">
								<select class="form-select form-select-sm cb-filter-select-lang" name="list_language" aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_LANGUAGE'); ?>">
								<option value="*"> -
									<?php echo Text::_('COM_CONTENTBUILDERNG_LANGUAGE'); ?> -
								</option>
								<?php foreach ($this->languages as $filter_language) : ?>
									<option value="<?php echo $filter_language; ?>">
										<?php echo $filter_language; ?>
									</option>
								<?php endforeach; ?>
								</select>
								<button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" onclick="contentbuilderng_language();">
									<span class="fa-solid fa-check" aria-hidden="true"></span>
									<?php echo Text::_('COM_CONTENTBUILDERNG_APPLY'); ?>
								</button>
							</div>
					</div>
				<?php endif; ?>

				<div class="cb-list-filters-row">
						<div class="d-flex flex-wrap align-items-center gap-2">

						<!-- GAUCHE : filtre + selects + boutons (optionnel) -->
						<div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1">

								<?php if ($hasBulkSelection && $showStateBulkControl && $state_allowed && count($this->states)) : ?>
									<select class="form-select form-select-sm cb-filter-select-state" disabled
										name="list_state" id="list_state" title="<?php echo Text::_('COM_CONTENTBUILDERNG_BULK_OPTIONS'); ?>: <?php echo Text::_('COM_CONTENTBUILDERNG_STATE_CHANGER'); ?>"
										aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_STATE_CHANGER'); ?>"
										data-cb-state-select
										onchange="if (this.value !== '-1') { contentbuilderng_state(); }">
										<option value="-1"> - <?php echo Text::_('COM_CONTENTBUILDERNG_STATE_CHANGER'); ?> -</option>
										<option value="0">-</option>
										<?php foreach ($this->states as $state) : ?>
											<option value="<?php echo (int) $state['id']; ?>" data-state-color="<?php echo htmlspecialchars((string) ($state['color'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
												<?php echo htmlspecialchars((string) $state['title'], ENT_QUOTES, 'UTF-8'); ?>
											</option>
										<?php endforeach; ?>
									</select>
								<?php endif; ?>

								<?php if ($this->list_publish && $publish_allowed) : ?>
									<select class="form-select form-select-sm cb-filter-select-pub" disabled
										name="list_publish" id="list_publish"
										title="<?php echo Text::_('COM_CONTENTBUILDERNG_PUBLISH_CHANGER_TIP'); ?>"
										aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_UPDATE_STATUS'); ?>"
										onchange="if (this.value !== '-1') { contentbuilderng_publish(); }">
									<option value="-1"> - <?php echo Text::_('COM_CONTENTBUILDERNG_UPDATE_STATUS'); ?> -</option>
									<option value="1"><?php echo Text::_('COM_CONTENTBUILDERNG_PUBLISH'); ?></option>
									<option value="0"><?php echo Text::_('COM_CONTENTBUILDERNG_UNPUBLISH'); ?></option>
								</select>
							<?php endif; ?>

							<?php if ($this->display_filter && $cbListActionAllowed('search')) : ?>
									<div class="input-group input-group-sm cb-filter-search-group">
									<label class="input-group-text" for="contentbuilderng_filter">
										<?php echo Text::_('COM_CONTENTBUILDERNG_FILTER'); ?>
									</label>

									<input
										type="text"
										class="form-control"
										id="contentbuilderng_filter"
										name="filter"
										value="<?php echo $this->escape($this->lists['filter']); ?>"
										onchange="document.adminForm.submit();" />

										<button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-1" id="cbSearchButton" title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_LIST_SEARCH_TOOLTIP'), ENT_QUOTES, 'UTF-8'); ?>">
											<span class="fa-solid fa-magnifying-glass" aria-hidden="true"></span>
											<?php echo Text::_('COM_CONTENTBUILDERNG_SEARCH'); ?>
										</button>

										<button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1" id="cbResetButton"
											title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_LIST_RESET_TOOLTIP'), ENT_QUOTES, 'UTF-8'); ?>"
											onclick="document.getElementById('contentbuilderng_filter').value='';
                <?php echo $this->list_language && count($this->languages) ? "if(document.getElementById('list_language_filter')) document.getElementById('list_language_filter').selectedIndex=0;" : ""; ?>
                <?php echo $showStateFilter && count($this->states) ? "if(document.getElementById('list_state_filter')) document.getElementById('list_state_filter').selectedIndex=0;" : ""; ?>
                <?php echo $this->list_publish ? "if(document.getElementById('list_publish_filter')) document.getElementById('list_publish_filter').selectedIndex=0;" : ""; ?>
                document.adminForm.submit();">
											<span class="fa-solid fa-rotate-left" aria-hidden="true"></span>
											<?php echo Text::_('COM_CONTENTBUILDERNG_RESET'); ?>
										</button>
									</div>
								<?php endif; ?>

							<?php if ($showStateFilter && count($this->states) && $cbListActionAllowed('state')) : ?>
								<select class="form-select form-select-sm cb-filter-select-state"
									name="list_state_filter" id="list_state_filter"
									title="<?php echo Text::_('COM_CONTENTBUILDERNG_STATE_FILTER'); ?>"
									aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_STATE_FILTER'); ?>"
									data-cb-state-select
									onchange="document.adminForm.submit();">
									<option value="0"> - <?php echo Text::_('COM_CONTENTBUILDERNG_STATE_FILTER'); ?> -</option>
									<?php foreach ($this->states as $state) : ?>
										<option value="<?php echo (int) $state['id']; ?>" data-state-color="<?php echo htmlspecialchars((string) ($state['color'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" <?php echo $this->lists['filter_state'] == $state['id'] ? 'selected' : ''; ?>>
											<?php echo htmlspecialchars((string) $state['title'], ENT_QUOTES, 'UTF-8'); ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>

							<?php if ($this->list_publish && $cbListActionAllowed('publish')) : ?>
								<select class="form-select form-select-sm cb-filter-select-md"
									name="list_publish_filter" id="list_publish_filter"
									title="<?php echo Text::_('COM_CONTENTBUILDERNG_FILTER'); ?>: <?php echo Text::_('COM_CONTENTBUILDERNG_PUBLISH'); ?>"
									aria-label="<?php echo Text::_('JSTATUS'); ?>"
									onchange="document.adminForm.submit();">
									<option value="-1"> - <?php echo Text::_('JSTATUS'); ?> -</option>
									<option value="1" <?php echo $this->lists['filter_publish'] == 1 ? 'selected' : ''; ?>>
										<?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_PUBLISHED') ?>
									</option>
									<option value="0" <?php echo $this->lists['filter_publish'] == 0 ? 'selected' : ''; ?>>
										<?php echo Text::_('COM_CONTENTBUILDERNG_UNPUBLISHED') ?>
									</option>
								</select>
							<?php endif; ?>

							<?php if ($this->list_language && $cbListActionAllowed('language')) : ?>
								<select class="form-select form-select-sm cb-filter-select-lang"
									name="list_language_filter" id="list_language_filter"
									title="<?php echo Text::_('COM_CONTENTBUILDERNG_FILTER'); ?>: <?php echo Text::_('COM_CONTENTBUILDERNG_LANGUAGE'); ?>"
									aria-label="<?php echo Text::_('COM_CONTENTBUILDERNG_LANGUAGE'); ?>"
									onchange="document.adminForm.submit();">
									<option value=""> - <?php echo Text::_('COM_CONTENTBUILDERNG_LANGUAGE'); ?> -</option>
									<?php foreach ($this->languages as $filter_language) : ?>
										<option value="<?php echo $filter_language; ?>" <?php echo $this->lists['filter_language'] == $filter_language ? 'selected' : ''; ?>>
											<?php echo $filter_language; ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>

						</div>

						<!-- DROITE : actions + limitbox + excel -->
						<?php if ($showNewButton || ($hasBulkSelection && $delete_allowed) || ($this->show_records_per_page && !$embeddedListHidePagination) || ($this->export_xls && empty($this->invalid_list_setup) && $cbListActionAllowed('export'))) : ?>
								<div class="d-flex align-items-center gap-2 ms-auto cb-list-toolbar-actions">

										<?php if ($showNewButton) : ?>
											<a class="btn btn-sm btn-outline-secondary align-self-center d-inline-flex align-items-center gap-1 rounded-pill cb-list-new-btn"
												href="<?php echo $newRecordLink; ?>"
												title="<?php echo Text::_('COM_CONTENTBUILDERNG_NEW'); ?>">
												<span class="fa-solid fa-plus" aria-hidden="true"></span>
												<span><?php echo Text::_('COM_CONTENTBUILDERNG_NEW'); ?></span>
											</a>
										<?php endif; ?>

										<?php if ($hasBulkSelection && $delete_allowed) : ?>
											<button type="button" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 rounded-pill" onclick="contentbuilderng_delete();" title="<?php echo Text::_('COM_CONTENTBUILDERNG_DELETE_SELECTED_TOOLTIP'); ?>">
												<span class="fa-solid fa-trash" aria-hidden="true"></span>
												<span class="d-none d-md-inline"><?php echo Text::_('COM_CONTENTBUILDERNG_DELETE'); ?></span>
											</button>
										<?php endif; ?>

									<?php if ($this->show_records_per_page && !$embeddedListHidePagination) : ?>
										<div class="cb-filter-rpp-wrap">
											<?php
											$currentLimit = (int) (($this->state?->get('list.limit')) ?? ($this->pagination->limit ?? 20));
											$totalItems = (int) ($this->pagination->total ?? 0);
											$isAllLimit = $currentLimit === 0;
											$configuredLimitOptions = ListLimitHelper::getPaginationChoices();
											$showAllOption = in_array(ListLimitHelper::ALL, $configuredLimitOptions, true) || $isAllLimit;
											$limitOptions = array_values(array_filter(
												$configuredLimitOptions,
												static fn (int $value): bool => $value > ListLimitHelper::ALL
											));
											if ($currentLimit > 0 && !in_array($currentLimit, $limitOptions, true) && $currentLimit !== $totalItems) {
												$limitOptions = ListLimitHelper::insertCurrentPaginationChoice($limitOptions, $currentLimit);
											}
											$limitOptions = array_values(array_unique(array_filter(
												array_map('intval', $limitOptions),
												static fn (int $value): bool => $value > 0 && $value !== $totalItems
											)));
											?>
											<select
												id="list_limit"
												name="list[limit]"
												class="form-select form-select-sm cb-filter-select-rpp"
												aria-label="<?php echo Text::_('JGLOBAL_LIST_LIMIT'); ?>"
												onchange="document.getElementById('adminForm').elements['list[start]'].value = 0; Joomla.submitform('', document.getElementById('adminForm'));"
											>
												<?php foreach ($limitOptions as $opt) : ?>
													<?php $label = (string) $opt; ?>
													<option value="<?php echo $opt; ?>"<?php echo $opt === $currentLimit ? ' selected' : ''; ?>>
														<?php echo $label; ?>
													</option>
												<?php endforeach; ?>
											<?php if ($totalItems > 0 && $showAllOption) : ?>
													<option value="0"<?php echo $isAllLimit || $totalItems === $currentLimit ? ' selected' : ''; ?>>
														<?php echo Text::_('JALL'); ?>
													</option>
												<?php endif; ?>
											</select>
										</div>
									<?php endif; ?>

										<?php if ($this->export_xls && empty($this->invalid_list_setup) && $cbListActionAllowed('export')) : ?>
											<a class="btn btn-sm btn-outline-success align-self-center d-inline-flex align-items-center gap-1 rounded-pill"
												href="<?php echo Route::_('index.php?' . http_build_query($exportQueryParams) . $previewQuery, false); ?>"
												title="<?php echo Text::_('COM_CONTENTBUILDERNG_EXPORT_XLSX_TOOLTIP'); ?>">
												<span class="fa-solid fa-download" aria-hidden="true"></span>
												<span>XLSX</span>
											</a>
										<?php endif; ?>

							</div>
						<?php endif; ?>

						</div>
				</div>
			</div>
			</div>
		</div>
	<?php endif; ?>
		<?php if ($usesCardLayout) : ?>
		<div class="cb-list-panel cb-list-data-panel">
			<div class="cb-list-cards">
				<?php
				$n = count((array) $this->items);
				for ($i = 0; $i < $n; $i++) {
					$row = $this->items[$i];
					$link = Route::_(
						NavigationLinkHelper::buildRouteLink($listDetailsBaseParams + [
							'record_id' => (int) $row->colRecord,
						], $previewQuery)
					);
					$edit_link = Route::_(
						NavigationLinkHelper::buildRouteLink($listEditBaseParams + [
							'record_id' => (int) $row->colRecord,
						], $previewQuery)
					);
					$isPublished = isset($this->published_items[$row->colRecord]) && $this->published_items[$row->colRecord];
					$togglePublish = $isPublished ? 0 : 1;
					$toggle_link = Route::_(
						NavigationLinkHelper::buildRouteLink($listPublishBaseParams + [
							$directStorageMode ? 'storage_id' : 'id' => $directStorageMode ? $directStorageId : $this->form_id,
							'list_publish' => $togglePublish,
						], '&cid[]=' . (int) $row->colRecord . $previewQuery . '&' . Session::getFormToken() . '=1')
					);
					$rowCanView = ($view_allowed || $canAccessOwnedRecord('view', $row->colRecord)) && $cbListActionAllowed('detail');
					$rowCanEdit = ($edit_allowed || $canAccessOwnedRecord('edit', $row->colRecord)) && $cbListActionAllowed('edit');
						$visibleFields = [];
						foreach ($this->visible_cols as $referenceId) {
							$key = 'col' . $referenceId;
							if (!isset($row->$key)) {
								continue;
							}
							$value = $row->$key;
							$visibleFields[] = [
							'reference_id' => $referenceId,
							'label' => (string) ($this->labels[$referenceId] ?? $referenceId),
							'value' => $value,
								'linkable' => in_array($referenceId, $this->linkable_elements) && $rowCanView,
							];
						}
						$nonEmptyVisibleFields = array_values(array_filter($visibleFields, static function (array $field): bool {
							return trim(strip_tags((string) ($field['value'] ?? ''))) !== '';
						}));
						$titleLabelPatterns = '/\b(nom|name|title|titre|subject|libell|label)\b/i';
						$subtitleLabelPatterns = '/\b(pr[ée]nom|first\s*name|firstname)\b/i';
						$preferredTitleParts = [];
						foreach ($nonEmptyVisibleFields as $field) {
							$fieldLabel = (string) ($field['label'] ?? '');
							$fieldValueText = trim(strip_tags((string) ($field['value'] ?? '')));
							if ($fieldValueText === '') {
								continue;
							}
							if (preg_match($titleLabelPatterns, $fieldLabel)) {
								$preferredTitleParts[] = $field;
								break;
							}
						}
						if (!empty($preferredTitleParts)) {
							foreach ($nonEmptyVisibleFields as $field) {
								$fieldLabel = (string) ($field['label'] ?? '');
								if (preg_match($subtitleLabelPatterns, $fieldLabel)) {
									$preferredTitleParts[] = $field;
									break;
								}
							}
						}
						$preferredTitleField = null;
						foreach ($nonEmptyVisibleFields as $field) {
							$fieldValueText = trim(strip_tags((string) ($field['value'] ?? '')));
							if ($fieldValueText === '') {
								continue;
							}
							if (!preg_match('/^\d+$/', $fieldValueText)) {
								$preferredTitleField = $field;
								break;
							}
						}
						$primaryField = $preferredTitleParts[0] ?? $preferredTitleField ?? ($nonEmptyVisibleFields[0] ?? ($visibleFields[0] ?? null));
						$secondaryFields = [];
						foreach ($visibleFields as $field) {
							if ($primaryField !== null && (string) $field['reference_id'] === (string) $primaryField['reference_id']) {
								continue;
							}
							if (!empty($preferredTitleParts[1]) && (string) $field['reference_id'] === (string) $preferredTitleParts[1]['reference_id']) {
								continue;
							}
							if (trim(strip_tags((string) ($field['value'] ?? ''))) === '') {
								continue;
							}
							$secondaryFields[] = $field;
						}
						if ($isTilesVariant) {
							$secondaryFields = array_slice($secondaryFields, 0, 4);
						}
						$cardTitle = $primaryField !== null && trim(strip_tags((string) $primaryField['value'])) !== ''
							? $primaryField['value']
							: ('#' . (int) $row->colRecord);
						if (count($preferredTitleParts) > 1) {
							$cardTitle = implode(' ', array_map(static function (array $field): string {
								return trim(strip_tags((string) ($field['value'] ?? '')));
							}, $preferredTitleParts));
						}
						$cardSubtitle = $primaryField !== null
							? (string) $primaryField['label']
							: Text::_('COM_CONTENTBUILDERNG_RECORD_ID');
						$hasSelectionControl = $this->select_column && ($delete_allowed || $state_allowed || $publish_allowed);
						$hasStateControl = $showStateControl && $state_allowed && count($this->states);
						$hasStaticStateBadge = $showStateControl && !$hasStateControl && isset($this->state_titles[$row->colRecord]) && $this->state_titles[$row->colRecord] !== '';
						$stateBadgeStyle = $getStateBadgeStyle($row->colRecord, (array) ($this->state_colors ?? []));
						$showFooter = $hasSelectionControl || ($hasStateControl || ($hasStaticStateBadge && !$isTilesVariant));
						$footerClass = 'cb-list-card-footer';
						if (!$showFooter) {
							$footerClass .= ' is-empty';
						} elseif ($hasSelectionControl && !$hasStateControl && !$hasStaticStateBadge) {
							$footerClass .= ' is-selection-only';
						}
					?>
						<article class="cb-list-card">
							<header class="cb-list-card-header">
								<div class="cb-list-card-header-main">
									<h2 class="cb-list-card-title">
										<?php if (($primaryField['linkable'] ?? false) && $rowCanView) : ?>
											<a href="<?php echo $link; ?>"><?php echo $escapeListValue($cardTitle); ?></a>
									<?php else : ?>
										<?php echo $escapeListValue($cardTitle); ?>
									<?php endif; ?>
								</h2>
								<p class="cb-list-card-subtitle"><?php echo htmlspecialchars($cardSubtitle, ENT_QUOTES, 'UTF-8'); ?></p>
							</div>
							<div class="cb-list-card-actions">
								<?php if ($showPreviewLink && ($view_allowed || $hasOwnerViewRule)) : ?>
									<?php if ($rowCanView) : ?>
									<a class="btn btn-sm btn-outline-primary" href="<?php echo $link; ?>" title="<?php echo $directStorageMode ? Text::_('COM_CONTENTBUILDERNG_PREVIEW') : Text::_('COM_CONTENTBUILDERNG_DETAILS'); ?>">
										<span class="fa-solid fa-eye" aria-hidden="true"></span>
									</a>
									<?php else : ?>
										<span class="btn btn-sm btn-outline-primary invisible" aria-hidden="true">
											<span class="fa-solid fa-eye" aria-hidden="true"></span>
										</span>
									<?php endif; ?>
								<?php endif; ?>
									<?php if ($showEditAction && $rowCanEdit) : ?>
										<a class="btn btn-sm btn-outline-secondary" href="<?php echo $edit_link; ?>" title="<?php echo Text::_('COM_CONTENTBUILDERNG_EDIT'); ?>">
											<span class="fa-solid fa-pen" aria-hidden="true"></span>
										</a>
									<?php endif; ?>
								<?php if (($this->list_publish || $directStorageMode) && $publish_allowed) : ?>
									<a
										class="btn btn-sm btn-outline-secondary"
										href="<?php echo $toggle_link; ?>"
										title="<?php echo $isPublished ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED'); ?>"
										data-cb-publish-toggle
										data-record-id="<?php echo (int) $row->colRecord; ?>"
										data-published="<?php echo $isPublished ? '1' : '0'; ?>"
									>
										<span class="<?php echo $isPublished ? 'fa-solid fa-check text-success' : 'fa-solid fa-circle-xmark text-danger'; ?>" aria-hidden="true" data-cb-publish-icon></span>
									</a>
								<?php endif; ?>
							</div>
						</header>

							<div class="cb-list-card-meta">
								<span class="cb-list-card-badge">#<?php echo (int) $row->colRecord; ?></span>
								<?php if (!empty($this->debug_mode) && !empty($this->debug_show_cb_id)) : ?>
									<span class="cb-list-card-badge"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_CB_ID_COLUMN'); ?> #<?php echo (int) ($this->cb_record_ids[$row->colRecord] ?? 0); ?></span>
								<?php endif; ?>
								<?php if ($showStateControl && ($hasStateControl || $hasStaticStateBadge) && $cbListActionAllowed('state')) : ?>
									<?php if ($hasStateControl && !$isTilesVariant) : ?>
										<?php
										$currentStateTitle = $this->state_titles[$row->colRecord] ?? '';
										$currentStateId = '';
										foreach ($this->states as $state) {
											if ($currentStateTitle === (string) $state['title']) {
												$currentStateId = (string) (int) $state['id'];
												break;
											}
										}
										?>
										<select
											class="form-select form-select-sm cb-list-card-badge-select"
											onchange="contentbuilderng_state_single(this, this.value, <?php echo (int) $row->colRecord; ?>);"
											title="<?php echo Text::_('COM_CONTENTBUILDERNG_EDIT_STATE'); ?>"
											data-cb-state-select
											data-record-id="<?php echo (int) $row->colRecord; ?>"
											data-original-value="<?php echo htmlspecialchars($currentStateId, ENT_QUOTES, 'UTF-8'); ?>"
											<?php echo $stateBadgeStyle !== '' ? ' style="' . htmlspecialchars($stateBadgeStyle, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>
										>
											<option value="" data-state-title="" data-state-color="" <?php echo $currentStateTitle === '' ? 'selected' : ''; ?>>-</option>
											<?php foreach ($this->states as $state) : ?>
												<option
													value="<?php echo (int) $state['id']; ?>"
													data-state-title="<?php echo htmlspecialchars((string) $state['title'], ENT_QUOTES, 'UTF-8'); ?>"
													data-state-color="<?php echo htmlspecialchars((string) ($state['color'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
													<?php echo $currentStateTitle === $state['title'] ? 'selected' : ''; ?>
												>
													<?php echo htmlspecialchars($state['title'], ENT_QUOTES, 'UTF-8'); ?>
												</option>
											<?php endforeach; ?>
										</select>
									<?php else : ?>
										<span
											class="cb-list-card-badge"
											data-cb-state-badge
											data-record-id="<?php echo (int) $row->colRecord; ?>"
											<?php echo $stateBadgeStyle !== '' ? ' style="' . htmlspecialchars($stateBadgeStyle, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>
											<?php echo (isset($this->state_titles[$row->colRecord]) && $this->state_titles[$row->colRecord] !== '') ? '' : ' hidden'; ?>
										><?php echo isset($this->state_titles[$row->colRecord]) ? htmlspecialchars($this->state_titles[$row->colRecord], ENT_QUOTES, 'UTF-8') : ''; ?></span>
									<?php endif; ?>
								<?php endif; ?>
								<?php if ($this->list_language && $cbListActionAllowed('language')) : ?>
									<span class="cb-list-card-badge"><?php echo htmlspecialchars((string) (isset($this->lang_codes[$row->colRecord]) && $this->lang_codes[$row->colRecord] ? $this->lang_codes[$row->colRecord] : '*'), ENT_QUOTES, 'UTF-8'); ?></span>
								<?php endif; ?>
								<?php if (($this->list_publish || $directStorageMode) && $cbListActionAllowed('publish')) : ?>
									<span class="cb-list-card-badge" data-cb-publish-badge data-record-id="<?php echo (int) $row->colRecord; ?>">
										<span class="<?php echo $isPublished ? 'fa-solid fa-check text-success' : 'fa-solid fa-circle-xmark text-danger'; ?>" aria-hidden="true" data-cb-publish-icon></span>
										<span class="visually-hidden"><?php echo $isPublished ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED'); ?></span>
									</span>
								<?php endif; ?>
						</div>

							<div class="cb-list-card-body">
							<?php foreach ($secondaryFields as $field) : ?>
								<div class="cb-list-card-field">
									<div class="cb-list-card-label"><?php echo htmlspecialchars((string) $field['label'], ENT_QUOTES, 'UTF-8'); ?></div>
									<div class="cb-list-card-value">
										<?php if ($field['linkable']) : ?>
											<a href="<?php echo $link; ?>"><?php echo nl2br($escapeListValue($field['value'])); ?></a>
										<?php else : ?>
											<?php echo nl2br($escapeListValue($field['value'])); ?>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
								<?php if ($this->list_article && !empty($row->colArticleId)) : ?>
								<div class="cb-list-card-field">
									<div class="cb-list-card-label"><?php echo Text::_('COM_CONTENTBUILDERNG_ARTICLE'); ?></div>
									<div class="cb-list-card-value"><?php echo (int) ($row->colArticleId ?? 0); ?></div>
								</div>
							<?php endif; ?>
							<?php if ($this->list_author) : ?>
								<div class="cb-list-card-field">
									<div class="cb-list-card-label"><?php echo Text::_('COM_CONTENTBUILDERNG_AUTHOR'); ?></div>
									<div class="cb-list-card-value"><?php echo htmlspecialchars((string) ($row->colAuthor ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
								</div>
							<?php endif; ?>
							<?php if ($this->list_last_modification) : ?>
								<?php $lastModificationText = $formatListLastModification($row->colLastModification ?? ''); ?>
								<?php if ($lastModificationText !== '') : ?>
								<div class="cb-list-card-field">
									<div class="cb-list-card-label"><?php echo Text::_('COM_CONTENTBUILDERNG_LAST_MODIFICATION'); ?></div>
									<div class="cb-list-card-value"><?php echo htmlspecialchars($lastModificationText, ENT_QUOTES, 'UTF-8'); ?></div>
								</div>
								<?php endif; ?>
							<?php endif; ?>
							<?php if ($this->list_rating && $cbListActionAllowed('rating')) : ?>
								<div class="cb-list-card-field">
									<div class="cb-list-card-label"><?php echo Text::_('COM_CONTENTBUILDERNG_PERM_RATING'); ?></div>
									<div class="cb-list-card-value">
										<?php echo RatingHelper::getRating($input->getInt('id', 0), $row->colRecord, $row->colRating, $this->rating_slots, $input->getCmd('lang', ''), $rating_allowed, $row->colRatingCount, $row->colRatingSum); ?>
									</div>
								</div>
							<?php endif; ?>
							</div>

							<footer class="<?php echo $footerClass; ?>">
								<?php if ($hasSelectionControl) : ?>
										<label class="cb-list-card-selection">
											<input class="form-check-input" type="checkbox" name="cid[]" value="<?php echo (int) $row->colRecord; ?>"/>
											<span><?php echo Text::_('COM_CONTENTBUILDERNG_SELECT_COLUMN'); ?></span>
										</label>
								<?php elseif (!$isTilesVariant) : ?>
									<span></span>
								<?php endif; ?>

								<?php if ($showStateControl && !$isTilesVariant && !$hasStateControl && $cbListActionAllowed('state')) : ?>
									<div class="cb-list-card-state">
										<?php if ($hasStaticStateBadge) : ?>
											<span class="cb-list-card-badge" data-cb-state-badge data-record-id="<?php echo (int) $row->colRecord; ?>"<?php echo $stateBadgeStyle !== '' ? ' style="' . htmlspecialchars($stateBadgeStyle, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>><?php echo htmlspecialchars((string) $this->state_titles[$row->colRecord], ENT_QUOTES, 'UTF-8'); ?></span>
										<?php endif; ?>
									</div>
							<?php endif; ?>
						</footer>
					</article>
				<?php } ?>
			</div>
			<?php if (!$embeddedListHidePagination) : ?>
			<?php echo LayoutHelper::render('contentbuilderng.list_pagination', [
				'pagination' => $this->pagination,
				'lists' => $this->lists,
				'requestList' => $requestList,
				'navClass' => 'mt-3',
			]); ?>
			<?php endif; ?>
		</div>
	<?php else : ?>
	<div class="cb-scroll-x cb-list-panel cb-list-data-panel">
			<table class="table table-striped table-hover align-middle cb-list-table">
			<caption class="visually-hidden">
				<?php echo trim((string) ($this->page_title ?? '')) !== '' ? $this->escape($this->page_title) : Text::_('COM_CONTENTBUILDERNG_RECORDS'); ?>
			</caption>
			<thead>
				<tr>
					<?php if ($isBfLinked): ?>
						<th scope="col" class="table-light text-muted" title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_BF_ID_COLUMN_TIP'); ?>"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_BF_ID_COLUMN'); ?></th>
					<?php endif; ?>
					<?php if (!empty($this->debug_mode) && !empty($this->debug_show_cb_id)): ?>
						<th scope="col" class="table-light text-muted" title="<?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_CB_ID_COLUMN_TIP'); ?>"><?php echo Text::_('COM_CONTENTBUILDERNG_DEBUG_CB_ID_COLUMN'); ?></th>
					<?php endif; ?>
					<?php
						if ($showPreviewLink && ($view_allowed || $this->own_only)) {
						?>
							<th scope="col" class="table-light">
								<span class="visually-hidden"><?php echo Text::_('COM_CONTENTBUILDERNG_DETAILS'); ?></span>
							</th>
						<?php
						}

					if ($this->show_id_column) {
					?>
						<th scope="col" class="table-light">
							<?php echo HTMLHelper::_('grid.sort', htmlspecialchars('COM_CONTENTBUILDERNG_ID', ENT_QUOTES, 'UTF-8'), 'colRecord', $this->lists['order_Dir'], $this->lists['order']); ?>
						</th>
					<?php
					}

					if ($this->select_column && ($delete_allowed || $state_allowed || $publish_allowed)) {
					?>
						<th scope="col" class="table-light">
							<input class="contentbuilderng_select_all form-check-input" type="checkbox"
								onclick="contentbuilderng_selectAll(this);" />
						</th>
					<?php
					}

					if ($showEditAction) {
					?>
						<th scope="col" class="table-light">
							<?php echo Text::_('COM_CONTENTBUILDERNG_EDIT'); ?>
						</th>
					<?php
					}

						if ($showStateControl && $cbListActionAllowed('state')) {
						?>
							<th scope="col" class="table-light">
								<?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDERNG_EDIT_STATE'), 'colState', $this->lists['order_Dir'], $this->lists['order']); ?>
							</th>
						<?php
						}

						if (($this->list_publish || $directStorageMode) && $cbListActionAllowed('publish')) {
						?>
							<th scope="col" class="table-light">
								<?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDERNG_LIST_STATES_PUBLISHED'), 'colPublished', $this->lists['order_Dir'], $this->lists['order']); ?>
							</th>
						<?php
						}

						if ($this->list_language && $cbListActionAllowed('language')) {
						?>
							<th scope="col" class="table-light">
								<?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDERNG_LANGUAGE'), 'colLanguage', $this->lists['order_Dir'], $this->lists['order']); ?>
							</th>
						<?php
						}

					if ($this->list_article) {
					?>
						<th scope="col" class="table-light">
							<?php echo HTMLHelper::_('grid.sort', htmlspecialchars('COM_CONTENTBUILDERNG_ARTICLE', ENT_QUOTES, 'UTF-8'), 'colArticleId', $this->lists['order_Dir'], $this->lists['order']); ?>
						</th>
					<?php
					}

					if ($this->list_author) {
					?>
						<th scope="col" class="table-light">
							<?php echo HTMLHelper::_('grid.sort', htmlspecialchars('COM_CONTENTBUILDERNG_AUTHOR', ENT_QUOTES, 'UTF-8'), 'colAuthor', $this->lists['order_Dir'], $this->lists['order']); ?>
						</th>
					<?php
					}
					if ($this->list_last_modification) {
					?>
						<th scope="col" class="table-light">
							<?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDERNG_LAST_MODIFICATION'), 'colLastModification', $this->lists['order_Dir'], $this->lists['order']); ?>
						</th>
					<?php
					}

					if ($this->list_rating && $cbListActionAllowed('rating')) {
					?>
						<th scope="col" class="table-light">
							<?php echo HTMLHelper::_('grid.sort', htmlspecialchars('COM_CONTENTBUILDERNG_RATING', ENT_QUOTES, 'UTF-8'), 'colRating', $this->lists['order_Dir'], $this->lists['order']); ?>
						</th>
						<?php
					}

					if ($this->labels) {
						foreach ($this->labels as $reference_id => $label) {
							?>
								<th scope="col" class="table-light">
									<?php echo HTMLHelper::_('grid.sort', nl2br(htmlspecialchars($wordwrapLabel((string) $label), ENT_QUOTES, 'UTF-8')), "col$reference_id", $this->lists['order_Dir'], $this->lists['order']); ?>
								</th>
						<?php
						}
					}
					?>
				</tr>
			</thead>
			<tbody>
			<?php
			$n = count((array) $this->items);
			for ($i = 0; $i < $n; $i++) {
				$row = $this->items[$i];
				$link = Route::_(
					NavigationLinkHelper::buildRouteLink($listDetailsBaseParams + [
						'record_id' => (int) $row->colRecord,
					], $previewQuery)
				);
				$edit_link = Route::_(
					NavigationLinkHelper::buildRouteLink($listEditBaseParams + [
						'record_id' => (int) $row->colRecord,
					], $previewQuery)
				);
					$isPublished = isset($this->published_items[$row->colRecord]) && $this->published_items[$row->colRecord];
					$togglePublish = $isPublished ? 0 : 1;
					$toggle_link = Route::_(
						NavigationLinkHelper::buildRouteLink($listPublishBaseParams + [
							$directStorageMode ? 'storage_id' : 'id' => $directStorageMode ? $directStorageId : $this->form_id,
							'list_publish' => $togglePublish,
						], '&cid[]=' . (int) $row->colRecord . $previewQuery . '&' . Session::getFormToken() . '=1')
					);
					$select = '<input class="form-check-input" type="checkbox" name="cid[]" value="' . $row->colRecord . '"/>';
                    $rowCanView = ($view_allowed || $canAccessOwnedRecord('view', $row->colRecord)) && $cbListActionAllowed('detail');
                    $rowCanEdit = ($edit_allowed || $canAccessOwnedRecord('edit', $row->colRecord)) && $cbListActionAllowed('edit');
				?>
				<tr>
					<?php if ($isBfLinked): ?>
						<td class="text-muted small">
							<a href="<?php echo \Joomla\CMS\Uri\Uri::root(); ?>administrator/index.php?option=com_breezingformsng&act=managerecs&task=edit&record_id=<?php echo (int) $row->colRecord; ?>&form_selection=0" target="_blank" rel="noopener noreferrer" title="BreezingForms #<?php echo (int) $row->colRecord; ?>">
								<?php echo (int) $row->colRecord; ?>
							</a>
						</td>
					<?php endif; ?>
					<?php if (!empty($this->debug_mode) && !empty($this->debug_show_cb_id)): ?>
						<td class="text-muted small"><?php echo (int) ($this->cb_record_ids[$row->colRecord] ?? 0); ?></td>
					<?php endif; ?>
					<?php
					if ($showPreviewLink && ($view_allowed || $hasOwnerViewRule)) {
					?>
						<td>
							<?php if ($rowCanView) : ?>
								<a class="<?php echo $directStorageMode ? 'btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1' : 'text-primary'; ?>" href="<?php echo $link; ?>"
									title="<?php echo $directStorageMode ? Text::_('COM_CONTENTBUILDERNG_PREVIEW') : Text::_('COM_CONTENTBUILDERNG_DETAILS'); ?>">
									<span class="fa-solid fa-eye" aria-hidden="true"></span>
									<span class="visually-hidden"><?php echo $directStorageMode ? Text::_('COM_CONTENTBUILDERNG_PREVIEW') : Text::_('COM_CONTENTBUILDERNG_DETAILS'); ?></span>
								</a>
							<?php else : ?>
								<span class="<?php echo $directStorageMode ? 'btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 invisible' : 'text-primary invisible'; ?>" aria-hidden="true">
									<span class="fa-solid fa-eye" aria-hidden="true"></span>
								</span>
							<?php endif; ?>
						</td>
					<?php
					}

					if ($this->show_id_column) {
					?>
						<td>
							<?php
							if ($rowCanView) {
							?>
								<a href="<?php echo $link; ?>">
									<?php echo $row->colRecord; ?>
								</a>
							<?php
							} else {
							?>
								<?php echo $row->colRecord; ?>
							<?php
							}
							?>
						</td>
					<?php
					}
					?>
					<?php
					if ($this->select_column && ($delete_allowed || $state_allowed || $publish_allowed)) {
					?>
						<td>
							<?php echo $select; ?>
						</td>
					<?php
					}
					?>
					<?php
					if ($showEditAction) {
					?>
						<td>
							<?php if ($rowCanEdit) : ?>
								<a class="text-primary" href="<?php echo $edit_link; ?>"
									title="<?php echo Text::_('COM_CONTENTBUILDERNG_EDIT'); ?>">
									<span class="fa-solid fa-pen" aria-hidden="true"></span>
								</a>
							<?php else : ?>
								<span class="text-primary invisible" aria-hidden="true">
									<span class="fa-solid fa-pen" aria-hidden="true"></span>
								</span>
							<?php endif; ?>
						</td>
					<?php
					}
					?>
					<?php
					if ($showStateControl && $cbListActionAllowed('state')) {
						$stateControlStyle = $getStateBadgeStyle($row->colRecord, (array) ($this->state_colors ?? []));
					?>
						<td
							data-cb-state-cell
							data-record-id="<?php echo (int) $row->colRecord; ?>">
							<?php if ($state_allowed && count($this->states)) : ?>
								<?php
								$currentStateTitle = $this->state_titles[$row->colRecord] ?? '';
								$currentStateId = '';
								foreach ($this->states as $state) {
									if ($currentStateTitle === (string) $state['title']) {
										$currentStateId = (string) (int) $state['id'];
										break;
									}
								}
								?>
									<select
										class="form-select form-select-sm"
										onchange="contentbuilderng_state_single(this, this.value, <?php echo (int) $row->colRecord; ?>);"
										title="<?php echo Text::_('COM_CONTENTBUILDERNG_EDIT_STATE'); ?>"
										data-cb-state-select
										data-record-id="<?php echo (int) $row->colRecord; ?>"
										data-original-value="<?php echo htmlspecialchars($currentStateId, ENT_QUOTES, 'UTF-8'); ?>"
										<?php echo $stateControlStyle !== '' ? 'style="' . htmlspecialchars($stateControlStyle, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
									<option value="" data-state-title="" data-state-color="" <?php echo $currentStateTitle === '' ? 'selected' : ''; ?>>-</option>
									<?php foreach ($this->states as $state) : ?>
										<option
											value="<?php echo (int) $state['id']; ?>"
											data-state-title="<?php echo htmlspecialchars((string) $state['title'], ENT_QUOTES, 'UTF-8'); ?>"
											data-state-color="<?php echo htmlspecialchars((string) ($state['color'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
											<?php echo $currentStateTitle === $state['title'] ? 'selected' : ''; ?>
										>
											<?php echo htmlspecialchars($state['title'], ENT_QUOTES, 'UTF-8'); ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<?php if (isset($this->state_titles[$row->colRecord]) && $this->state_titles[$row->colRecord] !== '') : ?>
									<span class="badge" data-cb-state-badge data-record-id="<?php echo (int) $row->colRecord; ?>" <?php echo $stateControlStyle !== '' ? 'style="' . htmlspecialchars($stateControlStyle, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>><?php echo htmlspecialchars($this->state_titles[$row->colRecord], ENT_QUOTES, 'UTF-8'); ?></span>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					<?php
					}
					?>
						<?php
						if (($this->list_publish || $directStorageMode) && $cbListActionAllowed('publish')) {
						?>
							<td class="text-center align-middle">
								<?php
								$iconClass = $isPublished ? 'fa-solid fa-check text-success' : 'fa-solid fa-circle-xmark text-danger';
								$iconTitle = $isPublished ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED');
								?>
								<?php if ($publish_allowed) : ?>
									<a
										class="btn btn-sm btn-link p-0"
										href="<?php echo $toggle_link; ?>"
										title="<?php echo $iconTitle; ?>"
										data-cb-publish-toggle
										data-record-id="<?php echo (int) $row->colRecord; ?>"
										data-published="<?php echo $isPublished ? '1' : '0'; ?>"
									>
										<span class="<?php echo $iconClass; ?>" aria-hidden="true" data-cb-publish-icon></span>
										<span class="visually-hidden"><?php echo $iconTitle; ?></span>
									</a>
								<?php else : ?>
									<span class="<?php echo $iconClass; ?>" aria-hidden="true" title="<?php echo $iconTitle; ?>"></span>
									<span class="visually-hidden"><?php echo $iconTitle; ?></span>
								<?php endif; ?>
							</td>
						<?php
						}
						?>
					<?php
					if ($this->list_language && $cbListActionAllowed('language')) {
					?>
						<td>
							<?php echo isset($this->lang_codes[$row->colRecord]) && $this->lang_codes[$row->colRecord] ? $this->lang_codes[$row->colRecord] : '*'; ?>
						</td>
					<?php
					}
					?>
					<?php
					if ($this->list_article) {
					?>
						<td>
							<?php
							if ($rowCanView) {
							?>
								<a href="<?php echo $link; ?>">
									<?php echo $row->colArticleId; ?>
								</a>
							<?php
							} else {
							?>
								<?php echo $row->colArticleId; ?>
							<?php
							}
							?>
						</td>
					<?php
					}
					?>
					<?php
					if ($this->list_author) {
					?>
						<td>
							<?php echo htmlspecialchars($row->colAuthor, ENT_QUOTES, 'UTF-8'); ?>
						</td>
					<?php
					}
					?>
					<?php
					if ($this->list_last_modification) {
						$lastModificationText = $formatListLastModification($row->colLastModification ?? '');
					?>
						<td>
							<?php echo htmlspecialchars($lastModificationText, ENT_QUOTES, 'UTF-8'); ?>
						</td>
					<?php
					}
					?>
					<?php
					if ($this->list_rating && $cbListActionAllowed('rating')) {
					?>
						<td>
							<?php
								echo RatingHelper::getRating(\CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->getInt('id', 0), $row->colRecord, $row->colRating, $this->rating_slots, \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->getCmd('lang', ''), $rating_allowed, $row->colRatingCount, $row->colRatingSum);
							?>
						</td>
					<?php
					}
					?>
					<?php
					foreach ($this->visible_cols as $referenceId) {
							$key = 'col' . $referenceId;
						if (isset($row->$key)) {
							$value = $row->$key;
					?>
							<td>
								<?php
								if (in_array(str_replace('col', '', $key), $this->linkable_elements) && $rowCanView) {
								?>
									<a href="<?php echo $link; ?>">
										<?php echo nl2br($escapeListValue($value)); ?>
									</a>
								<?php
								} else {
								?>
									<?php echo nl2br($escapeListValue($value)); ?>
								<?php
								}
								?>
								</td>
						<?php
						}
					}
						?>
				</tr>
			<?php
			} ?>
			</tbody>
				<?php
				$paginationHtml = $embeddedListHidePagination ? '' : LayoutHelper::render('contentbuilderng.list_pagination', [
				    'pagination' => $this->pagination,
				    'lists' => $this->lists,
				    'requestList' => $requestList,
				]);

				if ($paginationHtml !== '') :
				?>
					<tfoot>
						<tr>
							<td colspan="1000">
								<?php echo $paginationHtml; ?>
							</td>
						</tr>
					</tfoot>
				<?php endif; ?>
			</table>
		</div>
		<?php endif; ?>
		<?php
		if (\CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->get('tmpl', '', 'string') != '') {
	?>
		<input type="hidden" name="tmpl" value="<?php echo \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->get('tmpl', '', 'string'); ?>" />
	<?php
	}
		if ($previewQuery !== '') {
		?>
			<?php echo PreviewLinkHelper::buildHiddenFields((int) $previewUntil, (int) $previewActorId, (string) $previewActorName, (int) $previewUserId, (string) $previewSig, (string) $adminReturnContext); ?>
		<?php
		}
	?>
	<input type="hidden" name="option" value="com_contentbuilderng" />
	<input type="hidden" name="task" id="task" value="" />
	<input type="hidden" name="view" id="view" value="list" />
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="Itemid" value="<?php echo \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->getInt('Itemid', 0); ?>" />
	<?php if ($embeddedListParams !== []) : ?>
	<input type="hidden" name="cblist_embed" value="<?php echo EmbeddedListFieldFilterService::REQUEST_CONTEXT; ?>" />
	<input type="hidden" name="cblist_fields" value="<?php echo htmlspecialchars($embeddedListFields, ENT_QUOTES, 'UTF-8'); ?>" />
	<input type="hidden" name="cblist_sort" value="<?php echo htmlspecialchars($embeddedListSort, ENT_QUOTES, 'UTF-8'); ?>" />
	<input type="hidden" name="cblist_dir" value="<?php echo htmlspecialchars($embeddedListDir, ENT_QUOTES, 'UTF-8'); ?>" />
	<input type="hidden" name="cblist_actions" value="<?php echo htmlspecialchars($embeddedListRawActions, ENT_QUOTES, 'UTF-8'); ?>" />
	<?php if ($embeddedListHidePagination) : ?>
	<input type="hidden" name="cblist_hide_pagination" value="1" />
	<?php endif; ?>
	<?php if ($embeddedListLimit !== '' && $embeddedListLimit !== '0') : ?>
	<input type="hidden" name="cblist_limit" value="<?php echo (int) $embeddedListLimit; ?>" />
	<?php endif; ?>
	<?php if ($embeddedListTitleProvided) : ?>
	<input type="hidden" name="cblist_title" value="<?php echo htmlspecialchars($embeddedListTitle, ENT_QUOTES, 'UTF-8'); ?>" />
	<?php endif; ?>
	<?php endif; ?>
	<?php if ($currentListLayout !== 'default') : ?>
	<input type="hidden" name="layout" value="<?php echo htmlspecialchars($currentListLayout, ENT_QUOTES, 'UTF-8'); ?>" />
	<?php endif; ?>
	<input type="hidden" name="list[start]" value="<?php echo (int) ($this->lists['liststart'] ?? 0); ?>" />
	<input type="hidden" name="id" value="<?php echo \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication()->getInput()->getInt('id', 0) ?>" />
	<input type="hidden" name="list[ordering]" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="list[direction]" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="list[fullordering]" value="<?php echo trim(($this->lists['order'] ?? '') . ' ' . ($this->lists['order_Dir'] ?? '')); ?>" />
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
