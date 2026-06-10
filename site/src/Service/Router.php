<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\RouterInterface;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Database\DatabaseInterface;

final class Router implements RouterInterface
{
    public function __construct(
        private readonly CMSApplicationInterface $app,
        AbstractMenu $menu,
        ?CategoryFactoryInterface $categoryFactory = null,
        ?DatabaseInterface $db = null
    ) {
    }

    public function preprocess($query)
    {
        return $query;
    }

    public function build(&$query)
    {
        $segments = [];

        $controller = (string) ($query['controller'] ?? '');
        if ($controller === '' && isset($query['view']) && in_array((string) $query['view'], ['export', 'verify'], true)) {
            $controller = (string) $query['view'];
        }

        if ($controller !== '') {
            switch ($controller) {
                case 'export':
                case 'verify':
                    $segments[] = $controller;
                    $segments[] = isset($query['id']) ? (string) $query['id'] : '0';

                    unset($query['id'], $query['title']);
                    break;

                case 'list':
                    $segments[0] = '';
                    $segments[1] = isset($query['id']) ? (string) $query['id'] : '0';
                    $segments[2] = isset($query['title']) ? (string) $query['title'] : 'entry';

                    unset($query['id']);
                    break;

                case 'edit':
                case 'details':
                    $segments[0] = $controller;
                    $segments[1] = isset($query['id']) ? (string) $query['id'] : '0';
                    $segments[2] = isset($query['record_id']) ? (string) $query['record_id'] : '';
                    $segments[3] = isset($query['title']) ? (string) $query['title'] : 'entry';

                    unset($query['id'], $query['record_id']);
                    break;
            }
        }

        if (isset($query['limitstart']) && !$query['limitstart']) {
            unset($query['limitstart']);
        }

        if (isset($query['filter_order']) && !$query['filter_order']) {
            unset($query['filter_order']);
        }

        unset($query['view'], $query['controller']);

        return $segments;
    }

    public function parse(&$segments)
    {
        $vars = [];

        if (!isset($segments[0])) {
            return $vars;
        }

        $controller = (string) $segments[0];

        if (is_numeric($segments[0])) {
            $segments[0] = 'list';
            $segments[1] = $controller;
            $controller = 'list';
        }

        switch ($controller) {
            case 'list':
            case 'export':
            case 'verify':
                $vars['controller'] = $controller;
                $vars['id'] = $segments[1] ?? 0;
                $vars['title'] = $segments[2] ?? '';

                $this->app->getInput()->set('controller', $controller);
                $this->app->getInput()->set('id', $vars['id']);
                $this->app->getInput()->set('title', $vars['title']);
                break;

            case 'details':
            case 'edit':
                $vars['controller'] = $controller;
                $vars['id'] = $segments[1] ?? 0;
                $vars['record_id'] = isset($segments[2]) && $segments[2] !== 'entry' ? $segments[2] : '';
                $vars['title'] = $segments[3] ?? '';
                $vars['view'] = $controller;

                $this->app->getInput()->set('controller', $controller);
                $this->app->getInput()->set('id', $vars['id']);
                $this->app->getInput()->set('record_id', $vars['record_id']);
                $this->app->getInput()->set('title', $vars['title']);
                $this->app->getInput()->set('view', $controller);
                break;
        }

        $segments = [];

        return $vars;
    }
}
