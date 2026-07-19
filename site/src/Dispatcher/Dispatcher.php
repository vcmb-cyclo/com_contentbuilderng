<?php
namespace CB\Component\Contentbuilderng\Site\Dispatcher;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Site\Helper\MenuParamHelper;
use Joomla\CMS\Dispatcher\ComponentDispatcher;

class Dispatcher extends ComponentDispatcher
{
    /**
     * Menu params whose request-provided value must survive the
     * menuParamDefaults reset below, so a URL override is still visible to
     * MenuParamHelper::resolveInputOrMenuToggle() once the menu item's own
     * value is injected.
     */
    private const REQUEST_OVERRIDABLE_MENU_PARAMS = [
        'cb_show_author',
        'cb_show_top_bar',
        'cb_show_details_top_bar',
        'cb_show_bottom_bar',
        'cb_show_details_bottom_bar',
        'cb_show_details_back_button',
        'cb_filter_in_title',
        'cb_prefix_in_title',
    ];

    #[\Override]
    public function dispatch(): void
    {
        $input = $this->input;
        $app = $this->app;
        $session = $app->getSession();
        $menu = $app->getMenu();
        $requestListLimitSubmitted = MenuParamHelper::hasExplicitListLimitRequest($app);

        $requestedMenuOverrides = [];
        foreach (self::REQUEST_OVERRIDABLE_MENU_PARAMS as $overridableKey) {
            $overrideValue = $input->get($overridableKey, null, 'raw');
            if ($overrideValue !== null && $overrideValue !== '') {
                $requestedMenuOverrides[$overridableKey] = $overrideValue;
            }
        }

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

            $formId = (int) MenuParamHelper::getMenuParam($params, 'form_id', 0);
            if ($requestFormId <= 0 && $formId > 0) {
                $input->set('id', $formId);
            }

            $menuRecordId = MenuParamHelper::getMenuParam($params, 'record_id', null);
            if ($menuRecordId !== null && $queryView === 'details') {
                $input->set('record_id', $menuRecordId);
                $input->set('controller', $hasRequestView ? 'edit' : 'details');
            }

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
            foreach (self::REQUEST_OVERRIDABLE_MENU_PARAMS as $overridableKey) {
                $input->set(
                    $overridableKey,
                    array_key_exists($overridableKey, $requestedMenuOverrides)
                        ? $requestedMenuOverrides[$overridableKey]
                        : MenuParamHelper::getMenuParam($params, $overridableKey, null)
                );
            }
            $input->set('cb_list_limit', MenuParamHelper::getMenuParam($params, 'cb_list_limit', 0));
            $input->set('force_menu_item_id', MenuParamHelper::getMenuParam($params, 'force_menu_item_id', 0));
            $input->set('cb_category_menu_filter', MenuParamHelper::getMenuParam($params, 'cb_category_menu_filter', 0));

            $list = (array) $input->get('list', [], 'array');
            $menuListLimit = (int) MenuParamHelper::getMenuParam($params, 'cb_list_limit', 0);
            $currentListLimit = array_key_exists('limit', $list) ? (int) $list['limit'] : 0;
            if (!$requestListLimitSubmitted && $menuListLimit > 0 && $currentListLimit !== $menuListLimit) {
                $list['limit'] = $menuListLimit;
                if (!isset($list['start'])) {
                    $list['start'] = 0;
                }
                $input->set('list', $list);
            }
        }

        $view = $input->getCmd('view', '');
        $task = $input->getCmd('task', '');
        $requestedView = strtolower($view);
        $requestedTask = strtolower($task);

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

        if (in_array($view, ['export', 'verify'], true)) {
            $controller = $view;
        } elseif ($view === 'details' || ($view === 'latest' && $input->getCmd('controller', '') === '')) {
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

        if ($taskController !== '') {
            $controller = $taskController;
            $input->set('controller', $taskController);
            $input->set('view', $taskController);
        }

        if ($task === '') {
            $input->set('view', $controller);
            $input->set('task', $controller . '.display');
        }

        parent::dispatch();
    }
}
