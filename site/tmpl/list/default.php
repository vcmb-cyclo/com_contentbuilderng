<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL 
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Factory;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderngHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\RatingHelper;
use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Site\Helper\NavigationLinkHelper;
use CB\Component\Contentbuilderng\Site\Helper\MenuParamHelper;
use CB\Component\Contentbuilderng\Site\Helper\PreviewLinkHelper;

/** @var SiteApplication $app */
$app = Factory::getApplication();
$cbListTemplateVariant = isset($cbListTemplateVariant) && is_string($cbListTemplateVariant)
	? trim(strtolower($cbListTemplateVariant))
	: 'default';
$isCardsVariant = $cbListTemplateVariant === 'cards';
$isCompactVariant = $cbListTemplateVariant === 'compact';
$isTilesVariant = $cbListTemplateVariant === 'tiles';
$usesCardLayout = $isCardsVariant || $isTilesVariant;
$frontend = $app->isClient('site');
$permissionService = new PermissionService();
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

	return 'background-color:#' . $color . ';color:' . $textColor . ';';
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
$currentUser = $app->getIdentity();
$currentSessionLabel = trim((string) ($currentUser->name ?? ''));
if ($currentSessionLabel === '') {
    $currentSessionLabel = trim((string) ($currentUser->username ?? ''));
}
if ($currentSessionLabel === '') {
    $currentSessionLabel = Text::_('COM_CONTENTBUILDERNG_GUEST');
}
$currentUserId = (int) ($currentUser->id ?? 0);
$formInstance = $this->form ?? null;
$ownerPermissionMatrix = (array) $app->getSession()->get('com_contentbuilderng.permissions_fe', []);
$ownerRuleSet = (array) ($ownerPermissionMatrix['own_fe'] ?? []);
$hasOwnerViewRule = !empty($ownerRuleSet['view']);
$hasOwnerEditRule = !empty($ownerRuleSet['edit']);
$ownerPermissionCache = [];
$canAccessOwnedRecord = static function (string $action, $recordId) use (&$ownerPermissionCache, $currentUserId, $formInstance, $ownerRuleSet): bool {
    $recordId = (string) $recordId;

    if (
        $recordId === ''
        || $recordId === '0'
        || $currentUserId <= 0
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

    $ownerPermissionCache[$cacheKey] = (bool) $formInstance->isOwner($currentUserId, $recordId);

    return $ownerPermissionCache[$cacheKey];
};
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
$previewLayoutOptions = [
    'default' => Text::_('COM_CONTENTBUILDERNG_PREVIEW_LIST_LAYOUT_DEFAULT'),
    'listone' => Text::_('COM_CONTENTBUILDERNG_PREVIEW_LIST_LAYOUT_LISTONE'),
    'listtwo' => Text::_('COM_CONTENTBUILDERNG_PREVIEW_LIST_LAYOUT_LISTTWO'),
    'listthree' => Text::_('COM_CONTENTBUILDERNG_PREVIEW_LIST_LAYOUT_LISTTHREE'),
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
$directStorageUnpublished = !empty($this->direct_storage_unpublished);
$directStoragePublishAllowed = $directStorageMode
    && ($isAdminPreview || $app->getIdentity()->authorise('core.edit.state', 'com_contentbuilderng'));
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
    $previewLayoutBaseParams['list'] = $listState;

    foreach ($previewLayoutOptions as $layoutName => $layoutLabel) {
        $params = $previewLayoutBaseParams;
        if ($layoutName === 'default') {
            unset($params['layout']);
        } else {
            $params['layout'] = $layoutName;
        }
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

if ($isAdminPreview) {
    $view_allowed = true;
}

$document = $app->getDocument();
$wa = $document->getWebAssetManager();
$ratingCsrfToken = Session::getFormToken();

// Charge le manifeste joomla.asset.json du composant
$wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');

$wa->useScript('jquery');
$wa->useScript('com_contentbuilderng.contentbuilderng');

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


$wa->addInlineStyle(
	<<<'CSS'
.cb-list-sticky{
	position:sticky;
	top:var(--cb-list-sticky-top,.5rem);
	z-index:var(--cb-list-sticky-z-index,9);
	margin:0 0 .75rem;
}
.cb-list-sticky .cb-list-panel{
	margin:0;
}
.cb-list-sticky .cb-list-header{
	margin:0 0 .55rem;
}
.cb-list-sticky .cb-list-actions{
	flex-wrap:wrap;
	justify-content:flex-end;
}
.cb-list-sticky .cb-list-filters{
	margin:0;
}
.cb-list-has-sticky-header .cb-list-table{
	border-collapse:separate;
	border-spacing:0;
}
.cb-list-has-sticky-header .cb-scroll-x,
.cb-list-has-sticky-header .cb-list-data-panel{
	position:relative;
	overflow-x:auto!important;
	overflow-y:visible!important;
}
.cb-list-sticky-head-clone{
	position:fixed;
	top:var(--cb-list-table-header-sticky-top,.5rem);
	left:0;
	z-index:12;
	display:none;
	overflow:hidden;
	pointer-events:none;
}
.cb-list-sticky-head-clone.is-visible{
	display:block;
}
.cb-list-sticky-head-clone .cb-list-table{
	margin:0;
}
.cb-list-sticky-head-clone a,
.cb-list-sticky-head-clone button,
.cb-list-sticky-head-clone input,
.cb-list-sticky-head-clone select,
.cb-list-sticky-head-clone label{
	pointer-events:auto;
}
.cb-list-has-sticky-header .cb-list-table thead th{
	position:static;
	z-index:8;
	background:linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.98))!important;
	background-clip:padding-box;
	border-top:1px solid rgba(15,23,42,.06);
	border-bottom:1px solid rgba(15,23,42,.08);
	box-shadow:0 10px 18px -18px rgba(15,23,42,.45), inset 0 -1px 0 rgba(15,23,42,.06);
	backdrop-filter:blur(8px);
}
.cb-list-has-sticky-header .cb-list-table thead th.table-light{
	background:linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.98))!important;
}
.cb-list-has-sticky-header .cb-list-table thead th a{
	position:relative;
	z-index:1;
}
.cb-list-has-sticky-header .cb-list-table thead th:first-child{
	border-top-left-radius:.7rem;
}
.cb-list-has-sticky-header .cb-list-table thead th:last-child{
	border-top-right-radius:.7rem;
}
.cb-list-titlebar{
	display:flex;
	align-items:center;
	justify-content:space-between;
	gap:.8rem;
	margin:0 0 .9rem;
	padding:0 0 .55rem;
	border:0;
	border-bottom:1px solid rgba(0,0,0,.12);
	background:none;
	box-shadow:none;
}
.cb-list-title{
	margin:0!important;
	font-weight:600;
	letter-spacing:0;
	color:inherit!important;
}
.cb-list-title::after{
	display:none!important;
}
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
.cb-preview-layout-select{
	min-width:160px;
	appearance:none;
	-webkit-appearance:none;
	-moz-appearance:none;
	border-color:#d6b07a!important;
	background-color:#fff3e0!important;
	color:#5f3b00!important;
	box-shadow:none!important;
	background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%235f3b00' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.8' d='m3.5 6 4.5 4.5L12.5 6'/%3E%3C/svg%3E"), linear-gradient(180deg, #fff8ec 0%, #ffe9c8 100%)!important;
	background-repeat:no-repeat, repeat!important;
	background-position:right .75rem center, 0 0!important;
	background-size:16px 12px, 100% 100%!important;
	padding-right:2.25rem!important;
}
.cb-preview-layout-select:focus{
	border-color:#c98a2e!important;
	background-color:#fff7eb!important;
	box-shadow:0 0 0 .18rem rgba(201,138,46,.18)!important;
	color:#5f3b00!important;
	background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%235f3b00' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.8' d='m3.5 6 4.5 4.5L12.5 6'/%3E%3C/svg%3E"), linear-gradient(180deg, #fffaf0 0%, #ffefcf 100%)!important;
}
.cb-preview-layout-select option{
	color:#2f2416;
	background:#fffaf2;
}
.cb-list-template-cards .cb-pagination-summary{
	font-weight:500;
}
@media (prefers-color-scheme: dark){
	.cb-list-titlebar{
		border-bottom-color:rgba(255,255,255,.16);
	}
	.cb-preview-config-help{
		color:#f5d38f;
		background:rgba(255,255,255,.08);
	}
	.cb-preview-config-help:hover,
	.cb-preview-config-help:focus{
		color:#ffe8b3;
		background:rgba(255,255,255,.14);
	}
	.cb-list-has-sticky-header .cb-list-table thead th,
	.cb-list-has-sticky-header .cb-list-table thead th.table-light{
		background:linear-gradient(180deg, rgba(16,25,36,.98), rgba(20,32,46,.98))!important;
		border-top-color:rgba(148,163,184,.14);
		border-bottom-color:rgba(148,163,184,.16);
		box-shadow:0 10px 18px -18px rgba(0,0,0,.7), inset 0 -1px 0 rgba(148,163,184,.14);
	}
}
@media (max-width:767.98px){
	.cb-list-sticky{
		top:0;
	}
	.cb-list-titlebar{
		padding:0 0 .45rem;
		margin-bottom:.75rem;
	}
}
CSS
);
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
$wa->addInlineStyle(
	<<<'CSS'
.cb-list-template-cards .cb-list-cards{
	display:grid;
	grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));
	gap:1rem;
}
.cb-list-template-cards .cb-list-card{
	display:flex;
	flex-direction:column;
	height:100%;
	border:1px solid rgba(0,0,0,.08);
	border-radius:18px;
	background:linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.98));
	box-shadow:0 14px 30px rgba(15,23,42,.08);
	overflow:hidden;
}
.cb-list-template-cards .cb-list-card-header{
	display:flex;
	align-items:flex-start;
	justify-content:space-between;
	gap:1rem;
	padding:1rem 1rem .85rem;
	border-bottom:1px solid rgba(0,0,0,.06);
}
.cb-list-template-cards .cb-list-card-title{
	margin:0;
	font-size:1.02rem;
	font-weight:700;
	line-height:1.35;
}
.cb-list-template-cards .cb-list-card-title a{
	text-decoration:none;
}
.cb-list-template-cards .cb-list-card-subtitle{
	margin:.2rem 0 0;
	font-size:.75rem;
	letter-spacing:.05em;
	text-transform:uppercase;
	color:#64748b;
}
.cb-list-template-cards .cb-list-card-actions{
	display:flex;
	flex-wrap:wrap;
	gap:.45rem;
	justify-content:flex-end;
}
.cb-list-template-cards .cb-list-card-meta{
	display:flex;
	flex-wrap:wrap;
	gap:.5rem;
	padding:.85rem 1rem 0;
}
.cb-list-template-cards .cb-list-card-badge{
	display:inline-flex;
	align-items:center;
	gap:.35rem;
	padding:.28rem .55rem;
	border-radius:999px;
	background:rgba(15,23,42,.06);
	font-size:.78rem;
	font-weight:600;
}
.cb-list-template-cards .cb-list-card-badge-select{
	width:auto;
	min-width:0;
	max-width:100%;
	appearance:none;
	-webkit-appearance:none;
	-moz-appearance:none;
	padding-top:.28rem;
	padding-bottom:.28rem;
	padding-left:.55rem;
	padding-right:1.85rem;
	border-radius:999px;
	border-color:transparent!important;
	box-shadow:none!important;
	background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23111827' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.8' d='m3.5 6 4.5 4.5L12.5 6'/%3E%3C/svg%3E")!important;
	background-repeat:no-repeat!important;
	background-position:right .55rem center!important;
	background-size:14px 10px!important;
	background-clip:padding-box!important;
	font-size:.78rem;
	font-weight:600;
	line-height:1.2;
}
.cb-list-template-cards .cb-list-card-badge-select:focus{
	border-color:transparent!important;
	box-shadow:0 0 0 .18rem rgba(13,110,253,.14)!important;
}
.cb-list-template-cards .cb-list-card-body{
	display:grid;
	gap:.85rem;
	padding:1rem;
}
.cb-list-template-cards .cb-list-card-field{
	display:grid;
	gap:.2rem;
}
.cb-list-template-cards .cb-list-card-label{
	font-size:.76rem;
	font-weight:700;
	letter-spacing:.04em;
	text-transform:uppercase;
	color:#64748b;
}
.cb-list-template-cards .cb-list-card-value{
	font-size:.95rem;
	line-height:1.45;
	word-break:break-word;
}
.cb-list-template-cards .cb-list-card-value a{
	text-decoration:none;
}
.cb-list-template-cards .cb-list-card-footer{
	display:flex;
	align-items:center;
	justify-content:space-between;
	gap:.75rem;
	padding:0 1rem 1rem;
	margin-top:auto;
}
.cb-list-template-cards .cb-list-card-selection{
	display:flex;
	align-items:center;
	gap:.45rem;
	font-size:.82rem;
	color:#475569;
}
.cb-list-template-cards .cb-list-card-state{
	min-width:150px;
}
.cb-list-template-compact .cb-list-panel{
	border-radius:.75rem;
	padding:.45rem .55rem;
	box-shadow:0 .22rem .6rem rgba(0,0,0,.05);
}
.cb-list-template-compact .cb-list-table{
	margin-top:.1rem!important;
}
.cb-list-template-compact .cb-list-table th{
	font-size:.76rem;
	letter-spacing:.03em;
	text-transform:uppercase;
	padding:.52rem .45rem!important;
}
.cb-list-template-compact .cb-list-table td{
	padding:.45rem .45rem!important;
	font-size:.89rem;
	line-height:1.28;
}
.cb-list-template-compact .cb-list-table .btn,
.cb-list-template-compact .cb-list-table .form-select,
.cb-list-template-compact .cb-list-table .form-control{
	font-size:.82rem;
}
.cb-list-template-compact .cb-list-table .form-select{
	padding-top:.22rem;
	padding-bottom:.22rem;
}
.cb-list-template-tiles .cb-list-cards{
	display:grid;
	grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));
	gap:1rem;
}
.cb-list-template-tiles .cb-list-card{
	display:flex;
	flex-direction:column;
	min-height:100%;
	position:relative;
	border:0;
	border-radius:22px;
	background:
		radial-gradient(circle at top right, rgba(13,110,253,.18), transparent 38%),
		linear-gradient(180deg, rgba(255,255,255,.99), rgba(242,247,255,.98));
	box-shadow:0 16px 34px rgba(13,110,253,.12);
	overflow:hidden;
	padding:.1rem;
}
.cb-list-template-tiles .cb-list-card-header{
	position:relative;
	display:grid;
	grid-template-columns:minmax(0, 1fr) auto;
	align-items:start;
	gap:.65rem;
	padding:1rem 1rem .35rem;
	background:linear-gradient(180deg, rgba(13,110,253,.08), rgba(13,110,253,0));
	border-bottom:0;
}
.cb-list-template-tiles .cb-list-card-header-main{
	min-width:0;
}
.cb-list-template-tiles .cb-list-card-title{
	font-size:1.02rem;
	font-weight:800;
	line-height:1.25;
}
.cb-list-template-tiles .cb-list-card-subtitle{
	margin-top:.25rem;
	font-size:.68rem;
	letter-spacing:.08em;
	color:#2563eb;
}
.cb-list-template-tiles .cb-list-card-actions{
	align-items:center;
	justify-content:flex-end;
	gap:.55rem;
}
.cb-list-template-tiles .cb-list-card-actions .btn{
	display:inline-flex;
	align-items:center;
	justify-content:center;
	min-width:auto;
	min-height:auto;
	border:0;
	width:auto;
	height:auto;
	padding:0;
	background:transparent;
	box-shadow:none;
	line-height:1;
	color:#1e3a8a;
}
.cb-list-template-tiles .cb-list-card-actions .btn:hover,
.cb-list-template-tiles .cb-list-card-actions .btn:focus{
	background:transparent;
	box-shadow:none;
	color:#0b5ed7;
	transform:translateY(-1px);
}
.cb-list-template-tiles .cb-list-card-actions .btn .fa-solid{
	font-size:1.12rem;
}
.cb-list-template-tiles .cb-list-card-meta{
	padding:0 1rem .45rem;
	gap:.4rem;
}
.cb-list-template-tiles .cb-list-card-badge{
	padding:.24rem .52rem;
	font-size:.7rem;
	font-weight:700;
	letter-spacing:.03em;
	background:#e8f1ff;
	color:#1d4ed8;
}
.cb-list-template-tiles .cb-list-card-body{
	display:grid;
	grid-template-columns:repeat(2, minmax(0, 1fr));
	gap:.65rem;
	padding:.65rem 1rem 1rem;
}
.cb-list-template-tiles .cb-list-card-field{
	padding:.6rem .65rem;
	border-radius:14px;
	background:#f8fbff;
	border:1px solid rgba(37,99,235,.08);
	gap:.16rem;
}
.cb-list-template-tiles .cb-list-card-label{
	font-size:.68rem;
	letter-spacing:.06em;
	color:#64748b;
}
.cb-list-template-tiles .cb-list-card-value{
	font-size:.82rem;
	font-weight:600;
	line-height:1.28;
}
.cb-list-template-tiles .cb-list-card-footer{
	margin-top:auto;
	padding:0 1rem .9rem;
	gap:.6rem;
	border-top:1px solid rgba(37,99,235,.08);
}
.cb-list-template-tiles .cb-list-card-footer.is-selection-only{
	border-top:0;
	padding-top:0;
}
.cb-list-template-tiles .cb-list-card-footer.is-empty{
	display:none;
}
.cb-list-template-tiles .cb-list-card-state{
	min-width:110px;
}
.cb-list-template-tiles .cb-list-card-selection{
	font-size:.74rem;
}
.cb-list-template-tiles .cb-list-card-title a{
	color:inherit;
	text-decoration:none;
}
.cb-list-template-tiles .cb-list-card:hover{
	transform:translateY(-2px);
	transition:transform .18s ease, box-shadow .18s ease;
	box-shadow:0 20px 38px rgba(13,110,253,.16);
}
@media (prefers-color-scheme: dark){
	.cb-preview-layout-select{
		border-color:#7c5a2b!important;
		background-color:#3b2a14!important;
		color:#ffe6bf!important;
		background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23ffe6bf' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.8' d='m3.5 6 4.5 4.5L12.5 6'/%3E%3C/svg%3E"), linear-gradient(180deg, #4a3316 0%, #35230f 100%)!important;
	}
	.cb-preview-layout-select:focus{
		border-color:#c98a2e!important;
		background-color:#4a3316!important;
		color:#fff2dc!important;
		box-shadow:0 0 0 .18rem rgba(201,138,46,.24)!important;
		background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23fff2dc' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.8' d='m3.5 6 4.5 4.5L12.5 6'/%3E%3C/svg%3E"), linear-gradient(180deg, #573a18 0%, #3f2912 100%)!important;
	}
	.cb-preview-layout-select option{
		color:#fff2dc;
		background:#2d2113;
	}
	.cb-list-template-compact .cb-list-panel{
		background:#101924;
		border-color:rgba(148,163,184,.2);
		box-shadow:0 .35rem .9rem rgba(0,0,0,.32);
	}
	.cb-list-template-compact .cb-list-table{
		--bs-table-bg:#101924;
		--bs-table-color:#ffffff;
		--bs-table-border-color:rgba(148,163,184,.16);
		--bs-table-striped-bg:#162231;
		--bs-table-striped-color:#ffffff;
		--bs-table-hover-bg:#1c2b3d;
		--bs-table-hover-color:#ffffff;
	}
	.cb-list-template-compact .cb-list-table th{
		color:#ffffff!important;
		font-weight:700;
		text-shadow:0 1px 1px rgba(0,0,0,.35);
	}
	.cb-list-template-compact .cb-list-table td{
		color:#ffffff;
	}
	.cb-list-template-compact .cb-list-table a{
		color:#d8e9ff;
		font-weight:500;
	}
	.cb-list-template-compact .cb-list-table th a{
		color:#ffffff!important;
		font-weight:700;
		text-decoration-color:rgba(255,255,255,.7);
		text-underline-offset:.14rem;
	}
	.cb-list-template-compact .cb-list-filters .input-group-text,
	.cb-list-template-compact .cb-list-filters .form-control,
	.cb-list-template-compact .cb-list-filters .form-select,
	.cb-list-template-compact .cb-list-table .form-control,
	.cb-list-template-compact .cb-list-table .form-select{
		color:#ffffff!important;
		background-color:#0f1824!important;
		border-color:rgba(190,213,241,.42)!important;
		box-shadow:none!important;
		color-scheme:dark;
	}
	.cb-list-template-compact .cb-list-filters .form-control::placeholder,
	.cb-list-template-compact .cb-list-table .form-control::placeholder{
		color:#8fa4bd!important;
		opacity:1;
	}
	.cb-list-template-compact .cb-list-filters .form-select,
	.cb-list-template-compact .cb-list-table .form-select{
		background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.8' d='m3.5 6 4.5 4.5L12.5 6'/%3E%3C/svg%3E")!important;
		background-repeat:no-repeat!important;
		background-position:right .75rem center!important;
		background-size:16px 12px!important;
	}
	.cb-list-template-compact .cb-list-filters .form-control:disabled,
	.cb-list-template-compact .cb-list-filters .form-select:disabled,
	.cb-list-template-compact .cb-list-table .form-control:disabled,
	.cb-list-template-compact .cb-list-table .form-select:disabled{
		color:#ffffff!important;
		background-color:#172638!important;
		border-color:rgba(190,213,241,.34)!important;
		opacity:1;
	}
	.cb-list-template-compact .cb-list-filters .form-control:focus,
	.cb-list-template-compact .cb-list-filters .form-select:focus,
	.cb-list-template-compact .cb-list-table .form-control:focus,
	.cb-list-template-compact .cb-list-table .form-select:focus{
		color:#ffffff!important;
		background-color:#111d2b!important;
		border-color:#78a6dd!important;
		box-shadow:0 0 0 .18rem rgba(120,166,221,.2)!important;
	}
	.cb-list-template-compact .cb-list-table tfoot td{
		color:#ffffff!important;
		background:#101924!important;
		border-color:rgba(148,163,184,.16)!important;
	}
	.cb-list-template-compact .cb-list-filters .btn,
	.cb-list-template-compact .cb-list-table .btn{
		font-weight:600;
		box-shadow:0 1px 0 rgba(255,255,255,.08), 0 .2rem .55rem rgba(0,0,0,.18);
	}
	.cb-list-template-compact .btn-outline-primary{
		color:#ffffff!important;
		background:rgba(63,131,248,.18)!important;
		border-color:#8abaff!important;
	}
	.cb-list-template-compact .btn-outline-primary:hover,
	.cb-list-template-compact .btn-outline-primary:focus{
		color:#ffffff!important;
		background:#1f5fa8!important;
		border-color:#b6d7ff!important;
	}
	.cb-list-template-compact .btn-outline-secondary{
		color:#ffffff!important;
		background:rgba(148,163,184,.16)!important;
		border-color:#bac8d9!important;
	}
	.cb-list-template-compact .btn-outline-secondary:hover,
	.cb-list-template-compact .btn-outline-secondary:focus{
		color:#ffffff!important;
		background:#334458!important;
		border-color:#ffffff!important;
	}
	.cb-list-template-compact .btn-outline-danger{
		color:#ffd7df!important;
		background:rgba(220,53,69,.16)!important;
		border-color:#ff9aaa!important;
	}
	.cb-list-template-compact .btn-outline-danger:hover,
	.cb-list-template-compact .btn-outline-danger:focus{
		color:#ffffff!important;
		background:#b02a37!important;
		border-color:#ffc1ca!important;
	}
	.cb-list-template-compact .btn-outline-success{
		color:#d8ffe5!important;
		background:rgba(25,135,84,.18)!important;
		border-color:#8ee0af!important;
	}
	.cb-list-template-compact .btn-outline-success:hover,
	.cb-list-template-compact .btn-outline-success:focus{
		color:#ffffff!important;
		background:#146c43!important;
		border-color:#b8f3ce!important;
	}
	.cb-list-template-compact .cb-list-table .pagination .page-link,
	.cb-list-template-compact .pagination__wrapper .pagination .page-link{
		color:#ffffff!important;
		background:#111d2b!important;
		border-color:rgba(190,213,241,.34)!important;
	}
	.cb-list-template-compact .cb-list-table .pagination .page-link:hover,
	.cb-list-template-compact .cb-list-table .pagination .page-link:focus,
	.cb-list-template-compact .pagination__wrapper .pagination .page-link:hover,
	.cb-list-template-compact .pagination__wrapper .pagination .page-link:focus{
		color:#ffffff!important;
		background:#1b2d42!important;
		border-color:rgba(158,197,254,.42)!important;
		box-shadow:none!important;
	}
	.cb-list-template-compact .cb-list-table .pagination .active .page-link,
	.cb-list-template-compact .pagination__wrapper .pagination .active .page-link{
		color:#ffffff!important;
		background:#1f4f8a!important;
		border-color:#4f8fd3!important;
	}
	.cb-list-template-compact .cb-list-table .pagination .disabled .page-link,
	.cb-list-template-compact .pagination__wrapper .pagination .disabled .page-link{
		color:#aebfd3!important;
		background:#0d1520!important;
		border-color:rgba(148,163,184,.12)!important;
	}
	.cb-list-template-compact .cb-pagination-summary{
		color:#ffffff!important;
	}
	.cb-list-template-cards .cb-list-card{
		background:linear-gradient(180deg, rgba(30,41,59,.96), rgba(15,23,42,.96));
		border-color:rgba(255,255,255,.08);
		box-shadow:0 14px 30px rgba(0,0,0,.28);
	}
	.cb-list-template-cards .cb-list-card-header{
		border-bottom-color:rgba(255,255,255,.08);
	}
	.cb-list-template-cards .cb-list-card-badge{
		background:rgba(255,255,255,.08);
	}
	.cb-list-template-cards .cb-list-card-subtitle,
	.cb-list-template-cards .cb-list-card-label,
	.cb-list-template-cards .cb-list-card-selection{
		color:#cbd5e1;
	}
	.cb-list-template-cards .cb-list-card-title,
	.cb-list-template-cards .cb-list-card-title a,
	.cb-list-template-cards .cb-list-card-value{
		color:#f8fbff;
	}
	.cb-list-template-cards .cb-list-card-value a{
		color:#9ec5fe;
	}
	.cb-list-template-cards .cb-list-card-field{
		border-color:rgba(148,163,184,.12);
	}
	.cb-list-template-tiles .cb-list-card{
		background:
			radial-gradient(circle at top right, rgba(96,165,250,.18), transparent 36%),
			linear-gradient(180deg, rgba(23,34,49,.98), rgba(14,23,36,.98));
		box-shadow:0 16px 34px rgba(0,0,0,.34);
	}
	.cb-list-template-tiles .cb-list-card-title,
	.cb-list-template-tiles .cb-list-card-title a{
		color:#f8fbff;
	}
	.cb-list-template-tiles .cb-list-card-badge{
		background:rgba(96,165,250,.16);
		color:#bfdbfe;
	}
	.cb-list-template-tiles .cb-list-card-actions .btn{
		color:#dbeafe;
	}
	.cb-list-template-tiles .cb-list-card-actions .btn:hover,
	.cb-list-template-tiles .cb-list-card-actions .btn:focus{
		color:#ffffff;
	}
	.cb-list-template-tiles .cb-list-card-field{
		background:rgba(15,23,42,.42);
		border-color:rgba(148,163,184,.14);
	}
	.cb-list-template-tiles .cb-list-card-label{
		color:#96a9bf;
	}
	.cb-list-template-tiles .cb-list-card-value{
		color:#f3f7fc;
	}
	.cb-list-template-tiles .cb-list-card-value a{
		color:#9ec5fe;
	}
	.cb-list-template-tiles .cb-list-card-subtitle{
		color:#93c5fd;
	}
	.cb-list-template-tiles .cb-list-card-footer{
		border-top-color:rgba(148,163,184,.14);
	}
}
@media (max-width:767.98px){
	.cb-list-template-cards .cb-list-cards{
		grid-template-columns:1fr;
	}
	.cb-list-template-tiles .cb-list-card-header{
		grid-template-columns:1fr;
	}
	.cb-list-template-tiles .cb-list-card-body{
		grid-template-columns:1fr;
	}
}
CSS
);
?>
<script>
	// The list view has no unsaved-edit workflow; prevent external dirty handlers
	// from blocking pagination or other list navigation.
	window.onbeforeunload = null;
	window.addEventListener('beforeunload', function(event) {
		event.stopImmediatePropagation();
	}, true);

	Joomla.tableOrdering = function(order, dir, task) {
		var form = document.getElementById('adminForm');
		if (!form) return;

		// Joomla 6 native list state
		if (form.elements['list[start]']) {
			form.elements['list[start]'].value = 0;
		}
		if (form.elements['list[ordering]']) {
			form.elements['list[ordering]'].value = order;
		}
		if (form.elements['list[direction]']) {
			form.elements['list[direction]'].value = dir;
		}
		if (form.elements['list[fullordering]']) {
			form.elements['list[fullordering]'].value = order + ' ' + dir;
		}

		Joomla.submitform(task || '', form);
	};

	<?php if ($rating_allowed && !empty($this->list_rating)) : ?>
	window.cbRate = function(url, lastId) {
		var tokenParam = <?php echo json_encode($ratingCsrfToken . '=1'); ?>;
		var separator = url.indexOf('?') === -1 ? '?' : '&';
		var requestUrl = url + separator + tokenParam;

		fetch(requestUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: tokenParam
		})
			.then(function(response) {
				return response.text().then(function(text) {
					var payload = null;

					try {
						payload = JSON.parse(text);
					} catch (error) {
						payload = {success: false, message: text || 'Rating error'};
					}

					var result = payload && typeof payload.success !== 'undefined'
						? (payload.success ? (payload.data || {}) : {code: 1, msg: payload.message || ''})
						: payload;
					var messageBox = document.getElementById(lastId);

					if (messageBox) {
						messageBox.style.display = 'block';
						messageBox.textContent = (result && result.msg) ? result.msg : '';
						window.setTimeout(function() {
							messageBox.style.display = 'none';
						}, 1800);
					}

					if (result && result.code === 0) {
						var counter = document.getElementById(lastId + 'Counter');
						if (counter && !isNaN(Number(counter.textContent))) {
							counter.textContent = String(Number(counter.textContent) + 1);
						}
					}

					if (!response.ok) {
						throw new Error((result && result.msg) ? result.msg : 'Rating error');
					}
				});
			})
			.catch(function(error) {
				var messageBox = document.getElementById(lastId);
				if (messageBox) {
					messageBox.style.display = 'block';
					messageBox.textContent = error && error.message ? error.message : 'Rating error';
					window.setTimeout(function() {
						messageBox.style.display = 'none';
					}, 1800);
				}
			});

		return false;
	};
	<?php endif; ?>

	function contentbuilderng_selectedCount(form) {
		if (!form) return 0;
		var boxchecked = form.querySelector('input[name="boxchecked"]');
		if (boxchecked) {
			var value = parseInt(boxchecked.value, 10);
			return isNaN(value) ? 0 : value;
		}
		return form.querySelectorAll('input[name="cid[]"]:checked').length;
	}

	function contentbuilderng_updateBulkActionsAvailability(form) {
		if (!form) return;
		var hasSelection = contentbuilderng_selectedCount(form) > 0;

		var bulkStateSelect = form.querySelector('select[name="list_state"]');
		if (bulkStateSelect) {
			bulkStateSelect.disabled = !hasSelection;

			if (!hasSelection && bulkStateSelect.value !== '-1') {
				bulkStateSelect.value = '-1';
			}
		}

		var bulkPublishSelect = form.querySelector('select[name="list_publish"]');
		if (bulkPublishSelect) {
			bulkPublishSelect.disabled = !hasSelection;

			if (!hasSelection && bulkPublishSelect.value !== '-1') {
				bulkPublishSelect.value = '-1';
			}
		}
	}

	function contentbuilderng_getResetFilterValues(form) {
		if (!form) return [];

		var values = [];
		var filterInput = form.querySelector('#contentbuilderng_filter');
		if (filterInput) {
			values.push(String(filterInput.value || '').trim());
		}

		['list_state_filter', 'list_publish_filter', 'list_language_filter'].forEach(function(name) {
			var select = form.querySelector('[name="' + name + '"]');
			if (select) {
				values.push(String(select.value || '').trim());
			}
		});

		return values;
	}

	function contentbuilderng_updateResetButtonState(form) {
		if (!form) return;

		var resetButton = form.querySelector('#cbResetButton');
		if (!resetButton) return;

		var hasActiveFilter = contentbuilderng_getResetFilterValues(form).some(function(value) {
			return value !== '' && value !== '0' && value !== '-1' && value !== '*';
		});

		resetButton.classList.remove('btn-outline-secondary', 'btn-warning');
		resetButton.classList.add(hasActiveFilter ? 'btn-warning' : 'btn-outline-secondary');
	}

	function contentbuilderng_updateBoxchecked(form) {
		if (!form) return;
		var boxes = form.querySelectorAll('input[name="cid[]"]');
		var checked = 0;
		boxes.forEach(function(box) {
			if (box.checked) checked++;
		});
		var boxchecked = form.querySelector('input[name="boxchecked"]');
		if (boxchecked) {
			boxchecked.value = String(checked);
		}
		contentbuilderng_updateBulkActionsAvailability(form);
	}

	function contentbuilderng_selectAll(toggle) {
		var form = document.getElementById('adminForm');
		if (!form) return;
		var boxes = form.querySelectorAll('input[name="cid[]"]');
		boxes.forEach(function(box) {
			box.checked = !!toggle.checked;
		});
		contentbuilderng_updateBoxchecked(form);
	}

	function contentbuilderng_delete() {
		if (confirm('<?php echo Text::_('COM_CONTENTBUILDERNG_CONFIRM_DELETE_MESSAGE'); ?>')) {
			var form = document.getElementById('adminForm');
			document.getElementById('task').value = 'list.delete';
			Joomla.submitform('list.delete', form);
		}
	}

	function contentbuilderng_state() {
		var form = document.getElementById('adminForm');
		if (!form) return;
		if (contentbuilderng_selectedCount(form) < 1) {
			var stateSelect = form.querySelector('select[name="list_state"]');
			if (stateSelect) {
				stateSelect.value = '-1';
			}
			contentbuilderng_updateBulkActionsAvailability(form);
			return;
		}
		document.getElementById('task').value = 'list.state';
		Joomla.submitform('list.state', form);
	}

	function contentbuilderngGetStateBadgeStyle(color) {
		var hex = String(color || '').replace(/^#/, '').toUpperCase();
		if (/^[0-9A-F]{3}$/.test(hex)) {
			hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
		}

		if (!/^[0-9A-F]{6}$/.test(hex)) {
			return '';
		}

		var red = parseInt(hex.substring(0, 2), 16);
		var green = parseInt(hex.substring(2, 4), 16);
		var blue = parseInt(hex.substring(4, 6), 16);
		var brightness = ((red * 299) + (green * 587) + (blue * 114)) / 1000;
		var textColor = brightness > 160 ? '#111827' : '#F9FAFB';

		return 'background-color:#' + hex + ';color:' + textColor + ';';
	}

	function contentbuilderngUpdateStateUi(recordId, stateId, title, color) {
		var selector = '[data-record-id="' + contentbuilderngEscapeSelector(recordId) + '"]';
		var stateCells = document.querySelectorAll('[data-cb-state-cell]' + selector);
		var stateBadges = document.querySelectorAll('[data-cb-state-badge]' + selector);
		var stateSelects = document.querySelectorAll('[data-cb-state-select]' + selector);
		var normalizedColor = String(color || '').replace(/^#/, '');
		var badgeStyle = contentbuilderngGetStateBadgeStyle(normalizedColor);

		stateSelects.forEach(function(select) {
			select.value = stateId === '0' ? '' : stateId;
			if (badgeStyle !== '') {
				select.setAttribute('style', badgeStyle);
			} else {
				select.removeAttribute('style');
			}
		});

		stateCells.forEach(function(stateCell) {
			stateCell.style.backgroundColor = normalizedColor !== '' ? ('#' + normalizedColor) : '#FFFFFF';
		});

		stateBadges.forEach(function(stateBadge) {
			if (title === '') {
				stateBadge.hidden = true;
				stateBadge.textContent = '';
				stateBadge.removeAttribute('style');
			} else {
				stateBadge.hidden = false;
				stateBadge.textContent = title;
				if (badgeStyle !== '') {
					stateBadge.setAttribute('style', badgeStyle);
				} else {
					stateBadge.removeAttribute('style');
				}
			}
		});
	}

	function contentbuilderng_state_single(selectOrStateId, stateIdOrRecordId, recordId) {
		var form = document.getElementById('adminForm');
		if (!form) return;
		var select = selectOrStateId && typeof selectOrStateId.tagName === 'string' ? selectOrStateId : null;
		var targetRecordId = select ? recordId : stateIdOrRecordId;
		var rawStateId = select ? stateIdOrRecordId : selectOrStateId;
		if (rawStateId === undefined || rawStateId === null) return;
		var normalizedStateId = String(rawStateId) === '' ? '0' : String(rawStateId);
		var selectedOption = select && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
		var stateTitle = selectedOption ? String(selectedOption.getAttribute('data-state-title') || '') : '';
		var stateColor = selectedOption ? String(selectedOption.getAttribute('data-state-color') || '') : '';
		var originalValue = select ? String(select.getAttribute('data-original-value') || '') : '';

		// Ensure only the clicked record is selected.
		var boxes = form.querySelectorAll('input[name="cid[]"]');
		boxes.forEach(function (box) {
			box.checked = String(box.value) === String(targetRecordId);
		});
		contentbuilderng_updateBoxchecked(form);

		var formData = new FormData(form);
		formData.set('task', 'edit.state');
		formData.set('cb_ajax', '1');
		formData.set('list_state', normalizedStateId);
		formData.delete('cid[]');
		formData.append('cid[]', String(targetRecordId));
		formData.set('boxchecked', '1');

		if (select) {
			select.disabled = true;
		}

		fetch(form.getAttribute('action') || window.location.href, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		})
			.then(function (response) {
				return response.text().then(function (text) {
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
			.then(function () {
				if (select) {
					select.setAttribute('data-original-value', normalizedStateId === '0' ? '' : normalizedStateId);
				}

				contentbuilderngUpdateStateUi(String(targetRecordId), normalizedStateId, stateTitle, stateColor);
			})
			.catch(function (error) {
				if (select) {
					select.value = originalValue;
				}

				var message = error && error.message ? error.message : 'Save failed';
				if (window.Joomla && typeof Joomla.renderMessages === 'function') {
					Joomla.renderMessages({ error: [message] });
				} else {
					alert(message);
				}
			})
			.finally(function () {
				if (select) {
					select.disabled = false;
				}

				boxes.forEach(function (box) {
					box.checked = false;
				});
				contentbuilderng_updateBoxchecked(form);
			});
	}

	function contentbuilderng_publish() {
		var form = document.getElementById('adminForm');
		if (!form) return;
		if (contentbuilderng_selectedCount(form) < 1) {
			var publishSelect = form.querySelector('select[name="list_publish"]');
			if (publishSelect) {
				publishSelect.value = '-1';
			}
			contentbuilderng_updateBulkActionsAvailability(form);
			return;
		}
		document.getElementById('task').value = 'list.publish';
		Joomla.submitform('list.publish', form);
	}

	function contentbuilderng_language() {
		var form = document.getElementById('adminForm');
		document.getElementById('task').value = 'list.language';
		Joomla.submitform('list.language', form);
	}

	function contentbuilderngEscapeSelector(value) {
		if (window.CSS && typeof window.CSS.escape === 'function') {
			return window.CSS.escape(String(value));
		}

		return String(value).replace(/([ !"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1');
	}

	function contentbuilderngUpdatePublishUi(recordId, published) {
		var selector = '[data-record-id="' + contentbuilderngEscapeSelector(recordId) + '"]';
		var nodes = document.querySelectorAll('[data-cb-publish-toggle]' + selector + ', [data-cb-publish-badge]' + selector);
		var iconClass = published ? 'fa-solid fa-check text-success' : 'fa-solid fa-circle-xmark text-danger';
		var title = published
			? <?php echo json_encode(Text::_('JPUBLISHED'), JSON_UNESCAPED_UNICODE); ?>
			: <?php echo json_encode(Text::_('JUNPUBLISHED'), JSON_UNESCAPED_UNICODE); ?>;

		nodes.forEach(function (node) {
			if (node.hasAttribute('data-cb-publish-toggle')) {
				node.setAttribute('data-published', published ? '1' : '0');
				node.setAttribute('title', title);

				var href = String(node.getAttribute('href') || '');
				if (href !== '') {
					node.setAttribute(
						'href',
						href.replace(/([?&]list_publish=)(0|1)\b/, '$1' + (published ? '0' : '1'))
					);
				}
			}

			var icon = node.querySelector('[data-cb-publish-icon]');
			if (icon) {
				icon.className = iconClass;
				icon.setAttribute('title', title);
			}

			var sr = node.querySelector('.visually-hidden');
			if (sr) {
				sr.textContent = title;
			}
		});
	}

	function contentbuilderngHandlePublishToggleClick(toggle) {
		if (!toggle) {
			return;
		}

		var href = String(toggle.getAttribute('href') || '');
		var recordId = String(toggle.getAttribute('data-record-id') || '');
		if (href === '' || recordId === '' || toggle.getAttribute('data-cb-publish-busy') === '1') {
			return;
		}

		toggle.setAttribute('data-cb-publish-busy', '1');

		fetch(href + (href.indexOf('?') === -1 ? '?' : '&') + 'cb_ajax=1', {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		})
			.then(function (response) {
				return response.text().then(function (text) {
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
			.then(function () {
				contentbuilderngUpdatePublishUi(recordId, toggle.getAttribute('data-published') !== '1');
			})
			.catch(function (error) {
				var message = error && error.message ? error.message : 'Save failed';
				if (window.Joomla && typeof Joomla.renderMessages === 'function') {
					Joomla.renderMessages({ error: [message] });
				} else {
					alert(message);
				}
			})
			.finally(function () {
				toggle.removeAttribute('data-cb-publish-busy');
			});
	}

	document.addEventListener('DOMContentLoaded', function() {
		const form = document.getElementById('adminForm');
		if (!form) return;

		function syncListLimitFromSelect() {
			const select = form.querySelector('select[name="limit"], select[name="list[limit]"]');
			if (!select || !form.elements['list[limit]']) return;
			// Force Joomla 6 naming on the select itself.
			if (select.name !== 'list[limit]') {
				select.name = 'list[limit]';
				select.id = 'list_limit';
			}
			form.elements['list[limit]'].value = select.value;
		}

		// Limit box select (legacy name="limit" or Joomla name="list[limit]")
		const limitSelect = form.querySelector('select[name="limit"], select[name="list[limit]"]');
		if (limitSelect) {
			limitSelect.classList.add('form-select', 'form-select-sm');
			limitSelect.style.maxWidth = '120px';
			limitSelect.style.width = 'auto';
			// Mirror legacy limit into Joomla 6 list[limit] and submit immediately.
			limitSelect.addEventListener('change', function() {
				syncListLimitFromSelect();
				if (form.elements['list[start]']) {
					form.elements['list[start]'].value = 0;
				}
				Joomla.submitform('', form);
			});
		}

		// Ensure the hidden Joomla 6 limit always reflects the visible select.
		form.addEventListener('submit', syncListLimitFromSelect);

		// Keep boxchecked in sync with manual row selection.
		const rowBoxes = form.querySelectorAll('input[name="cid[]"]');
		rowBoxes.forEach(function(box) {
			box.addEventListener('change', function() {
				contentbuilderng_updateBoxchecked(form);
			});
		});

		const resetButton = document.getElementById('cbResetButton');
		if (resetButton) {
			['contentbuilderng_filter', 'list_state_filter', 'list_publish_filter', 'list_language_filter'].forEach(function(name) {
				var field = form.querySelector('#' + name + ', [name="' + name + '"]');
				if (!field) return;
				field.addEventListener('change', function() {
					contentbuilderng_updateResetButtonState(form);
				});
				if (name === 'contentbuilderng_filter') {
					field.addEventListener('input', function() {
						contentbuilderng_updateResetButtonState(form);
					});
				}
			});
			contentbuilderng_updateResetButtonState(form);
		}

		document.addEventListener('click', function(event) {
			var toggle = event.target ? event.target.closest('[data-cb-publish-toggle]') : null;
			if (!toggle) {
				return;
			}

			event.preventDefault();
			contentbuilderngHandlePublishToggleClick(toggle);
		});

		contentbuilderng_updateBoxchecked(form);
		});
	</script>

<?php if ($this->show_page_heading && $this->page_title): ?>
	<div class="cb-list-titlebar">
		<h1 class="h3 cb-list-title">
			<?php echo $this->page_title; ?>
		</h1>
	</div>
<?php endif; ?>
	<?php if ($isAdminPreview || $directStorageMode): ?>
			<div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
				<span>
					<strong><?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW_MODE'); ?></strong>
					<?php if ($directStorageMode) : ?>
						<?php echo ' - ' . Text::sprintf('COM_CONTENTBUILDERNG_PREVIEW_CURRENT_STORAGE', $previewFormName); ?>
					<?php elseif (!empty($previewLayoutSelectOptions)) : ?>
						<span class="d-inline-flex align-items-center gap-2 ms-2">
							<span><?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW_LIST_LAYOUT'); ?></span>
							<select
								class="form-select form-select-sm w-auto cb-preview-layout-select"
								title="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_PREVIEW_LIST_LAYOUT_TOOLTIP'), ENT_QUOTES, 'UTF-8'); ?>"
								aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_PREVIEW_LIST_LAYOUT_TOOLTIP'), ENT_QUOTES, 'UTF-8'); ?>"
								onchange="if (this.value) { window.location.href = this.value; }">
								<?php foreach ($previewLayoutSelectOptions as $layoutOption) : ?>
									<option value="<?php echo htmlspecialchars($layoutOption['value'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $layoutOption['selected'] ? ' selected' : ''; ?>>
										<?php echo htmlspecialchars($layoutOption['label'], ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</span>
					<?php endif; ?>
					<?php echo ' - ' . Text::sprintf($directStorageMode ? 'COM_CONTENTBUILDERNG_PREVIEW_CURRENT_STORAGE' : 'COM_CONTENTBUILDERNG_PREVIEW_CURRENT_FORM', $previewFormName); ?>
	                <?php if ($previewActorLabel !== ''): ?>
	                    <span class="badge text-bg-secondary ms-2">Preview actor: <?php echo htmlspecialchars($previewActorLabel, ENT_QUOTES, 'UTF-8'); ?><?php echo $previewActorId > 0 ? ' (#' . (int) $previewActorId . ')' : ''; ?></span>
	                <?php endif; ?>
                <?php if ($showPreviewSessionBadge): ?>
                    <span class="badge text-bg-secondary ms-1">Session: <?php echo htmlspecialchars($currentSessionLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
				<?php if (!$directStorageMode) : ?>
					<span class="cb-preview-config-help" title="<?php echo htmlspecialchars($previewConfigTabLabel, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($previewConfigTabLabel, ENT_QUOTES, 'UTF-8'); ?>" tabindex="0">
						<span class="fa-solid fa-circle-question" aria-hidden="true"></span>
					</span>
				<?php endif; ?>
                <br />
                <small class="d-inline-block mt-1">
                    <?php echo htmlspecialchars($previewFrontendPermissionHint, ENT_QUOTES, 'UTF-8'); ?>
                </small>
			</span>
			<a class="btn btn-sm btn-outline-secondary" href="<?php echo $adminReturnUrl; ?>">
				<span class="fa-solid fa-arrow-left me-1" aria-hidden="true"></span>
				<?php echo Text::_('COM_CONTENTBUILDERNG_BACK_TO_ADMIN'); ?>
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
			? 'This view is incomplete and cannot render a list.'
			: Text::_($directStorageMode ? 'COM_CONTENTBUILDERNG_PREVIEW_NO_STORAGE_FIELDS' : 'COM_CONTENTBUILDERNG_PREVIEW_NO_LIST_FIELDS'); ?>
	</div>
<?php endif; ?>
<?php echo $this->intro_text; ?>

<!-- 2023-12-19 XDA / GIL - BEGIN - Fix
<form action="index.php" method=<php echo $___getpost;?>" name="adminForm" id
Fix search, delete, pagination and 404 behavior.
Replace line 144 of media/com_contentbuilderng/images/list/tmpl/default.php
by this block. -->
	<form action="<?php echo Route::_('index.php?option=com_contentbuilderng&task=list.display&' . $listTarget . $currentListLayoutQuery . '&Itemid=' . (int) Factory::getApplication()->getInput()->getInt('Itemid', 0) . $previewQuery); ?>"
		method="<?php echo $___getpost; ?>" name="adminForm" id="adminForm" class="cb-list-template-<?php echo htmlspecialchars($cbListTemplateVariant, ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($this->list_header_sticky) && !$isCardsVariant && !$isTilesVariant ? ' cb-list-has-sticky-header' : ''; ?>">

	<!-- 2023-12-19 END -->
	<?php
	$showNewButton = ($new_allowed && !empty($this->new_button));
	$showStickyButtonBar = !empty($this->button_bar_sticky);
	$showPreviewLink = !empty($this->show_preview_link);
	$showTopBar = MenuParamHelper::resolveInputOrMenuToggle($app, 'cb_show_top_bar', (int) ($this->cb_show_top_bar ?? 1)) === 1;
	$showBottomBar = MenuParamHelper::resolveInputOrMenuToggle($app, 'cb_show_bottom_bar', (int) ($this->cb_show_bottom_bar ?? 1)) === 1;
	$listEditBaseParams = [
		'option' => 'com_contentbuilderng',
		'task' => 'edit.display',
		'backtolist' => 1,
		($directStorageMode ? 'storage_id' : 'id') => $directStorageMode ? $directStorageId : (int) Factory::getApplication()->getInput()->getInt('id', 0),
		'Itemid' => (int) Factory::getApplication()->getInput()->getInt('Itemid', 0),
	];
	$listEditTmpl = (string) Factory::getApplication()->getInput()->get('tmpl', '', 'string');
	if ($listEditTmpl !== '') {
		$listEditBaseParams['tmpl'] = $listEditTmpl;
	}
	$listEditLayout = (string) Factory::getApplication()->getInput()->get('layout', '', 'string');
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
		($directStorageMode ? 'storage_id' : 'id') => $directStorageMode ? $directStorageId : (int) Factory::getApplication()->getInput()->getInt('id', 0),
		'Itemid' => (int) Factory::getApplication()->getInput()->getInt('Itemid', 0),
	];
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
		($directStorageMode ? 'storage_id' : 'id') => $directStorageMode ? $directStorageId : (int) Factory::getApplication()->getInput()->getInt('id', 0),
		'Itemid' => (int) Factory::getApplication()->getInput()->getInt('Itemid', 0),
	];
	$listTmpl = (string) Factory::getApplication()->getInput()->get('tmpl', '', 'string');
	if ($listTmpl !== '') {
		$listDetailsBaseParams['tmpl'] = $listTmpl;
	}
	$listLayout = (string) Factory::getApplication()->getInput()->get('layout', '', 'string');
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
			<table class="cbFilterTable cb-list-filters" width="100%">
				<?php if ($language_allowed) : ?>
					<tr>
						<td>
							<div class="d-inline-flex align-items-center gap-1 me-2">
									<select class="form-select form-select-sm" style="max-width: 100px;" name="list_language">
									<option value="*"> -
										<?php echo Text::_('COM_CONTENTBUILDERNG_LANGUAGE'); ?> -
									</option>
									<option value="*">
										<?php echo Text::_('COM_CONTENTBUILDERNG_ANY'); ?>
									</option>
									<?php foreach ($this->languages as $filter_language) : ?>
										<option value="<?php echo $filter_language; ?>">
											<?php echo $filter_language; ?>
										</option>
									<?php endforeach; ?>
									</select>
									<button class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" onclick="contentbuilderng_language();">
										<span class="fa-solid fa-check" aria-hidden="true"></span>
										<?php echo Text::_('COM_CONTENTBUILDERNG_APPLY'); ?>
									</button>
								</div>
						</td>
					</tr>
				<?php endif; ?>

				<tr>
					<td>
						<div class="d-flex flex-wrap align-items-center gap-2">

						<!-- GAUCHE : filtre + selects + boutons (optionnel) -->
						<div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1">

								<?php if ($this->list_state && $state_allowed && count($this->states)) : ?>
									<select class="form-select form-select-sm" style="max-width: 140px;" disabled
										name="list_state" title="<?php echo Text::_('COM_CONTENTBUILDERNG_BULK_OPTIONS'); ?>: <?php echo Text::_('COM_CONTENTBUILDERNG_EDIT_STATE'); ?>"
										onchange="if (this.value !== '-1') { contentbuilderng_state(); }">
										<option value="-1"> - <?php echo Text::_('COM_CONTENTBUILDERNG_EDIT_STATE'); ?> -</option>
										<option value="0">-</option>
										<?php foreach ($this->states as $state) : ?>
											<option value="<?php echo $state['id']; ?>">
												<?php echo $state['title']; ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>

								<?php if ($this->list_publish && $publish_allowed) : ?>
									<select class="form-select form-select-sm" style="max-width: 160px;" disabled
										name="list_publish" title="<?php echo Text::_('COM_CONTENTBUILDERNG_BULK_OPTIONS'); ?>: <?php echo Text::_('COM_CONTENTBUILDERNG_PUBLISH'); ?>"
										onchange="if (this.value !== '-1') { contentbuilderng_publish(); }">
									<option value="-1"> - <?php echo Text::_('COM_CONTENTBUILDERNG_UPDATE_STATUS'); ?> -</option>
									<option value="1"><?php echo Text::_('COM_CONTENTBUILDERNG_PUBLISH'); ?></option>
									<option value="0"><?php echo Text::_('COM_CONTENTBUILDERNG_UNPUBLISH'); ?></option>
								</select>
							<?php endif; ?>

							<?php if ($this->display_filter) : ?>
									<div class="input-group input-group-sm" style="max-width: 360px;">
									<span class="input-group-text">
										<?php echo Text::_('COM_CONTENTBUILDERNG_FILTER'); ?>
									</span>

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
                <?php echo $this->list_state && count($this->states) ? "if(document.getElementById('list_state_filter')) document.getElementById('list_state_filter').selectedIndex=0;" : ""; ?>
                <?php echo $this->list_publish ? "if(document.getElementById('list_publish_filter')) document.getElementById('list_publish_filter').selectedIndex=0;" : ""; ?>
                document.adminForm.submit();">
											<span class="fa-solid fa-rotate-left" aria-hidden="true"></span>
											<?php echo Text::_('COM_CONTENTBUILDERNG_RESET'); ?>
										</button>
									</div>
								<?php endif; ?>

							<?php if ($this->list_state && count($this->states)) : ?>
								<select class="form-select form-select-sm" style="max-width: 160px;"
									name="list_state_filter" id="list_state_filter"
									title="<?php echo Text::_('COM_CONTENTBUILDERNG_FILTER'); ?>: <?php echo Text::_('COM_CONTENTBUILDERNG_EDIT_STATE'); ?>"
									onchange="document.adminForm.submit();">
									<option value="0"> - <?php echo Text::_('COM_CONTENTBUILDERNG_EDIT_STATE'); ?> -</option>
									<?php foreach ($this->states as $state) : ?>
										<option value="<?php echo $state['id'] ?>" <?php echo $this->lists['filter_state'] == $state['id'] ? 'selected' : ''; ?>>
											<?php echo $state['title'] ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>

							<?php if ($this->list_publish) : ?>
								<select class="form-select form-select-sm" style="max-width: 190px;"
									name="list_publish_filter" id="list_publish_filter"
									title="<?php echo Text::_('COM_CONTENTBUILDERNG_FILTER'); ?>: <?php echo Text::_('COM_CONTENTBUILDERNG_PUBLISH'); ?>"
									onchange="document.adminForm.submit();">
									<option value="-1"> - <?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?> -</option>
									<option value="1" <?php echo $this->lists['filter_publish'] == 1 ? 'selected' : ''; ?>>
										<?php echo Text::_('COM_CONTENTBUILDERNG_LIST_STATES_PUBLISHED') ?>
									</option>
									<option value="0" <?php echo $this->lists['filter_publish'] == 0 ? 'selected' : ''; ?>>
										<?php echo Text::_('COM_CONTENTBUILDERNG_UNPUBLISHED') ?>
									</option>
								</select>
							<?php endif; ?>

							<?php if ($this->list_language) : ?>
								<select class="form-select form-select-sm" style="max-width: 160px;"
									name="list_language_filter" id="list_language_filter"
									title="<?php echo Text::_('COM_CONTENTBUILDERNG_FILTER'); ?>: <?php echo Text::_('COM_CONTENTBUILDERNG_LANGUAGE'); ?>"
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
						<?php if ($showNewButton || $delete_allowed || $this->show_records_per_page || ($this->export_xls && empty($this->invalid_list_setup))) : ?>
								<div class="d-flex align-items-center gap-2 ms-auto">

										<?php if ($showNewButton) : ?>
											<a class="btn btn-sm btn-outline-primary align-self-center d-inline-flex align-items-center gap-1 rounded-pill cb-list-new-btn"
												href="<?php echo $newRecordLink; ?>"
												title="<?php echo Text::_('COM_CONTENTBUILDERNG_NEW'); ?>">
												<span class="fa-solid fa-plus" aria-hidden="true"></span>
												<span><?php echo Text::_('COM_CONTENTBUILDERNG_NEW'); ?></span>
											</a>
										<?php endif; ?>

										<?php if ($delete_allowed) : ?>
											<button class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 rounded-pill" onclick="contentbuilderng_delete();" title="<?php echo Text::_('COM_CONTENTBUILDERNG_DELETE_SELECTED_TOOLTIP'); ?>">
												<span class="fa-solid fa-trash" aria-hidden="true"></span>
												<span class="d-none d-md-inline"><?php echo Text::_('COM_CONTENTBUILDERNG_DELETE'); ?></span>
											</button>
										<?php endif; ?>

									<?php if ($this->show_records_per_page) : ?>
										<div style="max-width: 120px;">
											<?php
											$currentLimit = (int) (($this->state?->get('list.limit')) ?? ($this->pagination->limit ?? 20));
											$totalItems = (int) ($this->pagination->total ?? 0);
											$limitOptions = [5, 10, 20, 25, 50, 100, 500];
											if ($currentLimit > 0 && $currentLimit !== $totalItems) {
												$limitOptions[] = $currentLimit;
											}
											$limitOptions = array_values(array_unique(array_filter(
												array_map('intval', $limitOptions),
												static fn (int $value): bool => $value > 0 && $value !== $totalItems
											)));
											sort($limitOptions, SORT_NUMERIC);
											?>
											<select
												id="list_limit"
												name="list[limit]"
												class="form-select form-select-sm"
												onchange="document.getElementById('adminForm').elements['list[start]'].value = 0; Joomla.submitform('', document.getElementById('adminForm'));"
											>
												<?php foreach ($limitOptions as $opt) : ?>
													<?php $label = (string) $opt; ?>
													<option value="<?php echo $opt; ?>"<?php echo $opt === $currentLimit ? ' selected' : ''; ?>>
														<?php echo $label; ?>
													</option>
												<?php endforeach; ?>
												<?php if ($totalItems > 0) : ?>
													<option value="<?php echo $totalItems; ?>"<?php echo $totalItems === $currentLimit ? ' selected' : ''; ?>>
														<?php echo Text::_('JALL'); ?>
													</option>
												<?php endif; ?>
											</select>
										</div>
									<?php endif; ?>

										<?php if ($this->export_xls && empty($this->invalid_list_setup)) : ?>
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
					</td>
				</tr>
			</table>
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
						], '&cid[]=' . (int) $row->colRecord . $previewQuery)
					);
					$rowCanView = $view_allowed || $canAccessOwnedRecord('view', $row->colRecord);
					$rowCanEdit = $edit_allowed || $canAccessOwnedRecord('edit', $row->colRecord);
						$visibleFields = [];
						foreach ($row as $key => $value) {
							if (strpos((string) $key, 'col') !== 0) {
								continue;
							}
							$referenceId = str_replace('col', '', $key);
							if (!in_array($referenceId, $this->visible_cols)) {
								continue;
							}
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
						$hasStateControl = $this->list_state && $state_allowed && count($this->states);
						$hasStaticStateBadge = $this->list_state && !$hasStateControl && isset($this->state_titles[$row->colRecord]) && $this->state_titles[$row->colRecord] !== '';
						$stateBadgeStyle = $getStateBadgeStyle($row->colRecord, $this->state_colors);
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
											<a href="<?php echo $link; ?>"><?php echo $cardTitle; ?></a>
									<?php else : ?>
										<?php echo $cardTitle; ?>
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
								<?php if ($this->edit_button && ($edit_allowed || $hasOwnerEditRule)) : ?>
									<?php if ($rowCanEdit) : ?>
									<a class="btn btn-sm btn-outline-secondary" href="<?php echo $edit_link; ?>" title="<?php echo Text::_('COM_CONTENTBUILDERNG_EDIT'); ?>">
										<span class="fa-solid fa-pen" aria-hidden="true"></span>
									</a>
									<?php else : ?>
										<span class="btn btn-sm btn-outline-secondary invisible" aria-hidden="true">
											<span class="fa-solid fa-pen" aria-hidden="true"></span>
										</span>
									<?php endif; ?>
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
								<?php if ($this->list_state && ($hasStateControl || $hasStaticStateBadge)) : ?>
									<?php if ($hasStateControl && !$isTilesVariant) : ?>
										<?php $currentStateTitle = $this->state_titles[$row->colRecord] ?? ''; ?>
										<?php $currentStateId = ''; ?>
										<?php foreach ($this->states as $state) : ?>
											<?php if ($currentStateTitle === (string) $state['title']) { $currentStateId = (string) (int) $state['id']; break; } ?>
										<?php endforeach; ?>
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
								<?php if ($this->list_language) : ?>
									<span class="cb-list-card-badge"><?php echo htmlspecialchars((string) (isset($this->lang_codes[$row->colRecord]) && $this->lang_codes[$row->colRecord] ? $this->lang_codes[$row->colRecord] : '*'), ENT_QUOTES, 'UTF-8'); ?></span>
								<?php endif; ?>
								<?php if ($this->list_publish || $directStorageMode) : ?>
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
											<a href="<?php echo $link; ?>"><?php echo $field['value']; ?></a>
										<?php else : ?>
											<?php echo $field['value']; ?>
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
							<?php if ($this->list_rating) : ?>
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

								<?php if ($this->list_state && !$isTilesVariant && !$hasStateControl) : ?>
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
			<?php
			$pagTotal = (int) ($this->pagination->total ?? 0);
			$pagLimit = max(1, (int) ($this->pagination->limit ?? 0));
			$pagStart = (int) ($this->lists['liststart'] ?? ($requestList['start'] ?? 0));
			$pagPages = (int) ceil($pagTotal / $pagLimit);
			$pagCurrent = $pagPages > 0 ? (int) floor($pagStart / $pagLimit) + 1 : 1;
			$pagLastStart = $pagPages > 0 ? max(0, ($pagPages - 1) * $pagLimit) : 0;
			$showSummary = $pagTotal > 0;
			$showPagination = $pagPages > 1;
			$rangeStart = $pagTotal > 0 ? $pagStart + 1 : 0;
			$rangeEnd = $pagTotal > 0 ? min($pagStart + $pagLimit, $pagTotal) : 0;
			if ($showSummary) :
				$params = Uri::getInstance()->getQuery(true);
				$params['option'] = 'com_contentbuilderng';
				$params['task'] = 'list.display';
				$params['id'] = $input->getInt('id', 0);
				$params['Itemid'] = $input->getInt('Itemid', 0);
				$params['list'] = [
					'limit' => $pagLimit,
					'ordering' => $this->lists['order'],
					'direction' => $this->lists['order_Dir'],
					'start' => 0,
				];
				$buildPageLink = static function (int $start) use ($params): string {
					$params['list']['start'] = max(0, $start);
					return Route::_('index.php?' . http_build_query($params), false);
				};
			?>
				<nav class="pagination__wrapper d-flex flex-wrap align-items-center justify-content-start gap-2 mt-3" aria-label="Pagination">
					<div class="small text-muted me-2 cb-pagination-summary">
						<?php echo $rangeStart . ' - ' . $rangeEnd . ' / ' . $pagTotal . ' items'; ?>
					</div>
					<?php if ($showPagination) : ?>
						<ul class="pagination pagination-sm mb-0">
							<li class="page-item<?php echo $pagCurrent <= 1 ? ' disabled' : ''; ?>"><a class="page-link" href="<?php echo $buildPageLink(0); ?>">&lt;&lt;</a></li>
							<li class="page-item<?php echo $pagCurrent <= 1 ? ' disabled' : ''; ?>"><a class="page-link" href="<?php echo $buildPageLink($pagStart - $pagLimit); ?>">&lt;</a></li>
							<?php for ($p = 1; $p <= $pagPages; $p++) : $startForPage = ($p - 1) * $pagLimit; ?>
								<li class="page-item<?php echo $p === $pagCurrent ? ' active' : ''; ?>">
									<a class="page-link" href="<?php echo $buildPageLink($startForPage); ?>"><?php echo $p; ?></a>
								</li>
							<?php endfor; ?>
							<li class="page-item<?php echo $pagCurrent >= $pagPages ? ' disabled' : ''; ?>"><a class="page-link" href="<?php echo $buildPageLink($pagStart + $pagLimit); ?>">&gt;</a></li>
							<li class="page-item<?php echo $pagCurrent >= $pagPages ? ' disabled' : ''; ?>"><a class="page-link" href="<?php echo $buildPageLink($pagLastStart); ?>">&gt;&gt;</a></li>
						</ul>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
		</div>
	<?php else : ?>
	<div class="cb-scroll-x cb-list-panel cb-list-data-panel">
			<table class="table table-striped table-hover align-middle cb-list-table">
			<thead>
				<tr>
					<?php
						if ($showPreviewLink && ($view_allowed || $this->own_only)) {
						?>
							<th class="table-light" width="20">
								<span class="visually-hidden"><?php echo Text::_('COM_CONTENTBUILDERNG_DETAILS'); ?></span>
							</th>
						<?php
						}

					if ($this->show_id_column) {
					?>
						<th class="table-light hidden-phone" width="5">
							<?php echo HTMLHelper::_('grid.sort', htmlspecialchars('COM_CONTENTBUILDERNG_ID', ENT_QUOTES, 'UTF-8'), 'colRecord', $this->lists['order_Dir'], $this->lists['order']); ?>
						</th>
					<?php
					}

					if ($this->select_column && ($delete_allowed || $state_allowed || $publish_allowed)) {
					?>
						<th class="table-light hidden-phone" width="20">
							<input class="contentbuilderng_select_all form-check-input" type="checkbox"
								onclick="contentbuilderng_selectAll(this);" />
						</th>
					<?php
					}

					if ($this->edit_button && $edit_allowed) {
					?>
						<th class="table-light" width="20">
							<?php echo Text::_('COM_CONTENTBUILDERNG_EDIT'); ?>
						</th>
					<?php
					}

						if ($this->list_state) {
						?>
							<th class="table-light hidden-phone">
								<?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDERNG_EDIT_STATE'), 'colState', $this->lists['order_Dir'], $this->lists['order']); ?>
							</th>
						<?php
						}

						if ($this->list_publish || $directStorageMode) {
						?>
							<th class="table-light" width="20">
								<?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDERNG_LIST_STATES_PUBLISHED'), 'colPublished', $this->lists['order_Dir'], $this->lists['order']); ?>
							</th>
						<?php
						}

						if ($this->list_language) {
						?>
							<th class="table-light hidden-phone" width="20">
								<?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDERNG_LANGUAGE'), 'colLanguage', $this->lists['order_Dir'], $this->lists['order']); ?>
							</th>
						<?php
						}

					if ($this->list_article) {
					?>
						<th class="table-light hidden-phone">
							<?php echo HTMLHelper::_('grid.sort', htmlspecialchars('COM_CONTENTBUILDERNG_ARTICLE', ENT_QUOTES, 'UTF-8'), 'colArticleId', $this->lists['order_Dir'], $this->lists['order']); ?>
						</th>
					<?php
					}

					if ($this->list_author) {
					?>
						<th class="table-light hidden-phone">
							<?php echo HTMLHelper::_('grid.sort', htmlspecialchars('COM_CONTENTBUILDERNG_AUTHOR', ENT_QUOTES, 'UTF-8'), 'colAuthor', $this->lists['order_Dir'], $this->lists['order']); ?>
						</th>
					<?php
					}
					if ($this->list_last_modification) {
					?>
						<th class="table-light hidden-phone">
							<?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDERNG_LAST_MODIFICATION'), 'colLastModification', $this->lists['order_Dir'], $this->lists['order']); ?>
						</th>
					<?php
					}

					if ($this->list_rating) {
					?>
						<th class="table-light hidden-phone">
							<?php echo HTMLHelper::_('grid.sort', htmlspecialchars('COM_CONTENTBUILDERNG_RATING', ENT_QUOTES, 'UTF-8'), 'colRating', $this->lists['order_Dir'], $this->lists['order']); ?>
						</th>
						<?php
					}

					if ($this->labels) {
						$label_count = 0;
						$hidden = ' hidden-phone';
						foreach ($this->labels as $reference_id => $label) {
							if ($label_count == 0) {
								$hidden = '';
							} else {
								$hidden = ' hidden-phone';
							}
							?>
								<th class="table-light<?php echo $hidden; ?>">
									<?php echo HTMLHelper::_('grid.sort', nl2br(htmlspecialchars($wordwrapLabel((string) $label), ENT_QUOTES, 'UTF-8')), "col$reference_id", $this->lists['order_Dir'], $this->lists['order']); ?>
								</th>
						<?php
							$label_count++;
						}
					}
					?>
				</tr>
			</thead>
			<?php
			$k = 0;
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
						], '&cid[]=' . (int) $row->colRecord . $previewQuery)
					);
					$select = '<input class="form-check-input" type="checkbox" name="cid[]" value="' . $row->colRecord . '"/>';
                    $rowCanView = $view_allowed || $canAccessOwnedRecord('view', $row->colRecord);
                    $rowCanEdit = $edit_allowed || $canAccessOwnedRecord('edit', $row->colRecord);
				?>
				<tr class="<?php echo "row$k"; ?>">
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
						<td class="hidden-phone">
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
						<td class="hidden-phone">
							<?php echo $select; ?>
						</td>
					<?php
					}
					?>
					<?php
					if ($this->edit_button && ($edit_allowed || $hasOwnerEditRule)) {
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
					if ($this->list_state) {
					?>
						<td class="hidden-phone"
							data-cb-state-cell
							data-record-id="<?php echo (int) $row->colRecord; ?>"
							style="background-color: #<?php echo isset($this->state_colors[$row->colRecord]) ? $this->state_colors[$row->colRecord] : 'FFFFFF'; ?>;">
							<?php if ($state_allowed && count($this->states)) : ?>
								<?php $currentStateTitle = $this->state_titles[$row->colRecord] ?? ''; ?>
								<?php $currentStateId = ''; ?>
								<?php foreach ($this->states as $state) : ?>
									<?php if ($currentStateTitle === (string) $state['title']) { $currentStateId = (string) (int) $state['id']; break; } ?>
								<?php endforeach; ?>
									<select
										class="form-select form-select-sm"
										style="display:inline-block;width:auto;min-width:0;max-width:100%;"
										onchange="contentbuilderng_state_single(this, this.value, <?php echo (int) $row->colRecord; ?>);"
										title="<?php echo Text::_('COM_CONTENTBUILDERNG_EDIT_STATE'); ?>"
										data-cb-state-select
										data-record-id="<?php echo (int) $row->colRecord; ?>"
										data-original-value="<?php echo htmlspecialchars($currentStateId, ENT_QUOTES, 'UTF-8'); ?>">
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
								<?php echo isset($this->state_titles[$row->colRecord]) ? htmlspecialchars($this->state_titles[$row->colRecord], ENT_QUOTES, 'UTF-8') : ''; ?>
							<?php endif; ?>
						</td>
					<?php
					}
					?>
						<?php
						if ($this->list_publish || $directStorageMode) {
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
					if ($this->list_language) {
					?>
						<td class="hidden-phone">
							<?php echo isset($this->lang_codes[$row->colRecord]) && $this->lang_codes[$row->colRecord] ? $this->lang_codes[$row->colRecord] : '*'; ?>
						</td>
					<?php
					}
					?>
					<?php
					if ($this->list_article) {
					?>
						<td class="hidden-phone">
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
						<td class="hidden-phone">
							<?php echo htmlspecialchars($row->colAuthor, ENT_QUOTES, 'UTF-8'); ?>
						</td>
					<?php
					}
					?>
					<?php
					if ($this->list_last_modification) {
						$lastModificationText = $formatListLastModification($row->colLastModification ?? '');
					?>
						<td class="hidden-phone">
							<?php echo htmlspecialchars($lastModificationText, ENT_QUOTES, 'UTF-8'); ?>
						</td>
					<?php
					}
					?>
					<?php
					if ($this->list_rating) {
					?>
						<td class="hidden-phone">
							<?php
								echo RatingHelper::getRating(Factory::getApplication()->getInput()->getInt('id', 0), $row->colRecord, $row->colRating, $this->rating_slots, Factory::getApplication()->getInput()->getCmd('lang', ''), $rating_allowed, $row->colRatingCount, $row->colRatingSum);
							?>
						</td>
					<?php
					}
					?>
					<?php
					$label_count = 0;
					$hidden = ' class="hidden-phone"';
					foreach ($row as $key => $value) {
						// filtering out disallowed columns
						if (in_array(str_replace('col', '', $key), $this->visible_cols)) {
							if ($label_count == 0) {
								$hidden = '';
							} else {
								$hidden = ' class="hidden-phone"';
							}
					?>
							<td<?php echo $hidden; ?>>
								<?php
								if (in_array(str_replace('col', '', $key), $this->linkable_elements) && $rowCanView) {
								?>
									<a href="<?php echo $link; ?>">
										<?php echo $value; ?>
									</a>
								<?php
								} else {
								?>
									<?php echo $value; ?>
								<?php
								}
								?>
								</td>
						<?php
							$label_count++;
						}
					}
						?>
				</tr>
			<?php
				$k = 1 - $k;
			} ?>
				<?php
				$pagTotal = (int) ($this->pagination->total ?? 0);
				$pagLimit = max(1, (int) ($this->pagination->limit ?? 0));
				$pagStart = (int) ($this->lists['liststart'] ?? ($requestList['start'] ?? 0));
				$pagPages = (int) ceil($pagTotal / $pagLimit);
				$pagCurrent = $pagPages > 0 ? (int) floor($pagStart / $pagLimit) + 1 : 1;
				$pagLastStart = $pagPages > 0 ? max(0, ($pagPages - 1) * $pagLimit) : 0;
				$showSummary = $pagTotal > 0;
				$showPagination = $pagPages > 1;
				$rangeStart = $pagTotal > 0 ? $pagStart + 1 : 0;
				$rangeEnd = $pagTotal > 0 ? min($pagStart + $pagLimit, $pagTotal) : 0;

				if ($showSummary) :
				    $params = Uri::getInstance()->getQuery(true);
				    $params['option'] = 'com_contentbuilderng';
				    $params['task'] = 'list.display';
				    $params['id'] = Factory::getApplication()->getInput()->getInt('id', 0);
				    $params['Itemid'] = Factory::getApplication()->getInput()->getInt('Itemid', 0);
				    $params['list'] = [
				        'limit' => $pagLimit,
				        'ordering' => $this->lists['order'],
				        'direction' => $this->lists['order_Dir'],
				        'start' => 0,
				    ];

				    $buildPageLink = static function (int $start) use ($params): string {
				        $params['list']['start'] = max(0, $start);
				        return Route::_('index.php?' . http_build_query($params), false);
				    };

				?>
					<tfoot>
						<tr>
							<td colspan="1000">
									<nav class="pagination__wrapper d-flex flex-wrap align-items-center justify-content-start gap-2" aria-label="Pagination">
										<div class="small text-muted me-2 cb-pagination-summary">
											<?php echo $rangeStart . ' - ' . $rangeEnd . ' / ' . $pagTotal . ' items'; ?>
										</div>
									<?php if ($showPagination) : ?>
										<ul class="pagination pagination-sm mb-0">
											<li class="page-item<?php echo $pagCurrent <= 1 ? ' disabled' : ''; ?>">
												<a class="page-link" href="<?php echo $buildPageLink(0); ?>" aria-label="First">
													<span aria-hidden="true">&lt;&lt;</span>
												</a>
											</li>
											<li class="page-item<?php echo $pagCurrent <= 1 ? ' disabled' : ''; ?>">
												<a class="page-link" href="<?php echo $buildPageLink($pagStart - $pagLimit); ?>" aria-label="Previous">
													<span aria-hidden="true">&lt;</span>
												</a>
											</li>
											<?php for ($p = 1; $p <= $pagPages; $p++) :
											    $startForPage = ($p - 1) * $pagLimit;
											?>
												<li class="page-item<?php echo $p === $pagCurrent ? ' active' : ''; ?>">
													<a class="page-link" href="<?php echo $buildPageLink($startForPage); ?>">
														<?php echo $p; ?>
													</a>
												</li>
											<?php endfor; ?>
											<li class="page-item<?php echo $pagCurrent >= $pagPages ? ' disabled' : ''; ?>">
												<a class="page-link" href="<?php echo $buildPageLink($pagStart + $pagLimit); ?>" aria-label="Next">
													<span aria-hidden="true">&gt;</span>
												</a>
											</li>
											<li class="page-item<?php echo $pagCurrent >= $pagPages ? ' disabled' : ''; ?>">
												<a class="page-link" href="<?php echo $buildPageLink($pagLastStart); ?>" aria-label="Last">
													<span aria-hidden="true">&gt;&gt;</span>
												</a>
											</li>
										</ul>
									<?php endif; ?>
								</nav>
							</td>
						</tr>
					</tfoot>
				<?php endif; ?>
			</table>
		</div>
		<?php endif; ?>
		<?php
		if (Factory::getApplication()->getInput()->get('tmpl', '', 'string') != '') {
	?>
		<input type="hidden" name="tmpl" value="<?php echo Factory::getApplication()->getInput()->get('tmpl', '', 'string'); ?>" />
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
	<input type="hidden" name="Itemid" value="<?php echo Factory::getApplication()->getInput()->getInt('Itemid', 0); ?>" />
	<?php if ($currentListLayout !== 'default') : ?>
	<input type="hidden" name="layout" value="<?php echo htmlspecialchars($currentListLayout, ENT_QUOTES, 'UTF-8'); ?>" />
	<?php endif; ?>
	<input type="hidden" name="list[start]" value="<?php echo (int) ($this->lists['liststart'] ?? 0); ?>" />
	<input type="hidden" name="id" value="<?php echo Factory::getApplication()->getInput()->getInt('id', 0) ?>" />
	<input type="hidden" name="list[ordering]" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="list[direction]" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="list[fullordering]" value="<?php echo trim(($this->lists['order'] ?? '') . ' ' . ($this->lists['order_Dir'] ?? '')); ?>" />
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
