<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// Fichier d’entrée du composant (Site) - Joomla 6 Modern Dispatcher

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Application\SiteApplication;
use CB\Component\Contentbuilderng\Site\Helper\MenuParamHelper;

/** @var SiteApplication $app */
$app = Factory::getApplication();
$input = $app->input;
$session = $app->getSession();
$menu = $app->getMenu();

$menuParamDefaults = [
    'cb_controller' => null,
    'cb_category_id' => null,
    'cb_list_filterhidden' => null,
    'cb_list_orderhidden' => null,
    'cb_show_author' => null,
    'cb_show_top_bar' => null,
    'cb_show_details_top_bar' => null,
    'cb_show_bottom_bar' => null,
    'cb_show_details_bottom_bar' => null,
    'cb_latest' => null,
    'cb_show_details_back_button' => null,
    'cb_list_limit' => null,
    'cb_filter_in_title' => null,
    'cb_prefix_in_title' => null,
    'force_menu_item_id' => null,
    'cb_category_menu_filter' => null,
    'show_back_button' => null,
];

foreach ($menuParamDefaults as $key => $default) {
    $input->set($key, $default);
}

$itemId = $input->getInt('Itemid', 0);
$item = $itemId > 0 ? $menu->getItem($itemId) : $menu->getActive();

if ($itemId === 0 && is_object($item) && isset($item->id)) {
    $itemId = (int) $item->id;
    $input->set('Itemid', $itemId);
}

if ($itemId > 0) {
    $layout = $input->getString('layout', '');
    $layoutStateKey = 'com_contentbuilderng.layout.' . $itemId . $layout;

    if ($layout !== '') {
        $session->set($layoutStateKey, $layout);
    }

    $storedLayout = $session->get($layoutStateKey, null);
    if ($storedLayout !== null) {
        $input->set('layout', $storedLayout);
    }
}

if (is_object($item)) {
    $params = $item->getParams();
    $queryView = (string) ($item->query['view'] ?? '');
    $requestView = $input->getCmd('view', '');
    $requestTask = $input->getCmd('task', '');
    $requestFormId = $input->getInt('id', 0);
    $requestRecordId = $input->getString('record_id', '');
    $hasRequestView = $requestView !== '';
    $hasRequestTask = $requestTask !== '';
    $hasRequestRecordId = $requestRecordId !== '';

    // Préserve l'id explicite de l'URL (ex: task=list.display&id=15), sinon fallback menu form_id.
    $formId = (int) MenuParamHelper::getMenuParam($params, 'form_id', 0);
    if ($requestFormId <= 0 && $formId > 0) {
        $input->set('id', $formId);
    }

    $menuRecordId = MenuParamHelper::getMenuParam($params, 'record_id', null);
    if ($menuRecordId !== null && $queryView === 'details') {
        $input->set('record_id', $menuRecordId);
        $input->set('controller', $hasRequestView ? 'edit' : 'details');
    }

    // Respect explicit routes (?view=... / ?task=...) and only apply menu-driven
    // latest behavior when request does not target a specific view/task.
    if ($queryView === 'latest' && !$hasRequestView && !$hasRequestTask) {
        $input->set('view', 'latest');

        if ($requestView === 'edit' && $hasRequestRecordId) {
            $input->set('record_id', $requestRecordId);
            $input->set('controller', 'edit');
        } else {
            $input->set('controller', 'details');
        }
    }

    $input->set('cb_category_id', (int) MenuParamHelper::getMenuParam($params, 'cb_category_id', 0));
    $input->set('cb_controller', MenuParamHelper::getMenuParam($params, 'cb_controller', null));
    $input->set('cb_list_filterhidden', MenuParamHelper::getMenuParam($params, 'cb_list_filterhidden', null));
    $input->set('cb_list_orderhidden', MenuParamHelper::getMenuParam($params, 'cb_list_orderhidden', null));
    $input->set('cb_show_author', MenuParamHelper::getMenuParam($params, 'cb_show_author', null));
    $input->set('cb_show_bottom_bar', MenuParamHelper::getMenuParam($params, 'cb_show_bottom_bar', null));
    $input->set('cb_show_top_bar', MenuParamHelper::getMenuParam($params, 'cb_show_top_bar', null));
    $input->set('cb_show_details_bottom_bar', MenuParamHelper::getMenuParam($params, 'cb_show_details_bottom_bar', null));
    $input->set('cb_show_details_top_bar', MenuParamHelper::getMenuParam($params, 'cb_show_details_top_bar', null));
    $detailsBackButton = MenuParamHelper::getMenuParam($params, 'cb_show_details_back_button', null);
    if ($detailsBackButton === null) {
        $detailsBackButton = MenuParamHelper::getMenuParam($params, 'show_back_button', null);
    }
    $input->set('cb_show_details_back_button', $detailsBackButton);
    $input->set('show_back_button', $detailsBackButton);
    $input->set('cb_list_limit', MenuParamHelper::getMenuParam($params, 'cb_list_limit', 20));
    $input->set('cb_filter_in_title', MenuParamHelper::getMenuParam($params, 'cb_filter_in_title', null));
    $input->set('cb_prefix_in_title', MenuParamHelper::getMenuParam($params, 'cb_prefix_in_title', null));
    $input->set('force_menu_item_id', MenuParamHelper::getMenuParam($params, 'force_menu_item_id', 0));
    $input->set('cb_category_menu_filter', MenuParamHelper::getMenuParam($params, 'cb_category_menu_filter', 0));

    $list = (array) $input->get('list', [], 'array');
    $menuListLimit = (int) MenuParamHelper::getMenuParam($params, 'cb_list_limit', 0);
    if (!isset($list['limit']) && $menuListLimit > 0) {
        $list['limit'] = $menuListLimit;
        if (!isset($list['start'])) {
            $list['start'] = 0;
        }
        $input->set('list', $list);
    }
}

// If list is requested without a target form id, fallback to publicforms
// instead of throwing "form/view not found".
$requestedView = strtolower($input->getCmd('view', ''));
$requestedTask = strtolower($input->getCmd('task', ''));

if (
    $input->getInt('id', 0) <= 0
    && $input->getInt('storage_id', 0) <= 0
    && ($requestedView === 'list' || $requestedTask === 'list.display')
) {
    $input->set('controller', 'publicforms');
    $input->set('view', 'publicforms');
    $input->set('task', 'publicforms.display');
}

$controller = trim($input->getWord('controller', ''));
$view = $input->getCmd('view', '');
$task = $input->getCmd('task', '');
$taskController = '';

if ($task !== '') {
    $dotPos = strpos($task, '.');
    if ($dotPos !== false) {
        $taskController = substr($task, 0, $dotPos);
    }
}

if ($view === 'details' || ($view === 'latest' && $input->getCmd('controller', '') === '')) {
    $controller = 'details';
}

$cbController = $input->getString('cb_controller', '');
if ($cbController === 'edit') {
    $controller = 'edit';
} elseif ($cbController === 'publicforms' && $input->getInt('id', 0) <= 0) {
    $controller = 'publicforms';
}

if ($controller === '') {
    $controller = 'list';
}

// Task explicite prioritaire (ex: task=list.display), même si le menu actif est "latest/details".
if ($taskController !== '') {
    $controller = $taskController;
    $input->set('controller', $taskController);
    // A task like "list.display" must always drive the effective view.
    $input->set('view', $taskController);
}

if ($task === '') {
    $input->set('view', $controller);
    $input->set('task', $controller . '.display');
}

$component = $app->bootComponent('com_contentbuilderng');
$component->getDispatcher($app)->dispatch();
