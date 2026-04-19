<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;

function ContentbuilderngBuildRoute(&$query)
{
    $segments = array();

    if (isset($query['controller'])) {
        switch ($query['controller']) {

            case 'export':

                // idx 0 = controller
                $segments[] = 'export';

                // idx 1 = form id
                if (isset($query['id'])) {
                    $segments[] = $query['id'];
                    unset($query['id']);
                } else {
                    $segments[] = 0;
                }

                unset($query['title']);

                break;

            case 'list':
                // idx 0 = controller
                $segments[0] = '';

                if (isset($query['id'])) {
                    $segments[1] = $query['id'];
                    unset($query['id']);
                } else {
                    $segments[1] = 0;
                }

                if (isset($query['title'])) {
                    $segments[2] = $query['title'];
                    unset($query['title']);
                } else {
                    $segments[2] = 'entry';
                }

                break;

            case 'edit':
            case 'details':
                // idx 0 = controller
                $segments[0] = $query['controller'];

                // idx 1 = form id
                if (isset($query['id'])) {
                    $segments[1] = $query['id'];
                    unset($query['id']);
                } else {
                    $segments[1] = 0;
                }

                // idx 3 = record id
                if (isset($query['record_id'])) {
                    $segments[2] = $query['record_id'];
                    unset($query['record_id']);
                } else {
                    $segments[2] = '';
                }

                // idx 2 = slug
                if (isset($query['title'])) {
                    $segments[3] = $query['title'];
                    unset($query['title']);
                } else {
                    $segments[3] = 'entry';
                }

                break;
        }
    }

    if (isset($query['limitstart']) && !$query['limitstart']) {
        unset($query['limitstart']);
    }

    if (isset($query['filter_order']) && !$query['filter_order']) {
        unset($query['filter_order']);
    }

    unset($query['view']);
    unset($query['controller']);

    return $segments;
}

function ContentbuilderngParseRoute(&$segments)
{

    $vars = array();

    if (isset($segments[0])) {

        // The controller
        $controller = $segments[0];

        // Assuming lack of controller
        if (is_numeric($segments[0])) {
            $segments[0] = 'list';
            $segments[1] = $controller;
            $controller = 'list';
        }

        $app = Factory::getApplication();

        switch ($controller) {
            case 'list':
            case 'export':
                $vars['controller']   = $controller;
                $vars['id']    = $segments[1];
                if (isset($segments[2])) {
                    $vars['title'] = $segments[2];
                } else {
                    $vars['title'] = '';
                }

                $app->input->set('controller', $controller);
                $app->input->set('id', $vars['id']);
                $app->input->set('title', $vars['title']);
                break;

            case 'details':
                $vars['controller']   = $controller;
                $vars['id']           = $segments[1];
                $vars['record_id']    = '';
                if (isset($segments[2]) && $segments[2] !== 'entry') {
                    $vars['record_id'] = $segments[2];
                }
                $vars['title']        = isset($segments[3]) ? $segments[3] : '';
                $vars['view']         =  'details';

                $app->input->set('controller', $controller);
                $app->input->set('id', $vars['id']);
                $app->input->set('record_id', $vars['record_id']);
                $app->input->set('title', $vars['title']);
                $app->input->set('view', 'details');
                break;

            case 'edit':
                $vars['controller']   = $controller;
                $vars['id']           = $segments[1];
                $vars['record_id']    = '';
                if (isset($segments[2]) && $segments[2] !== 'entry') {
                    $vars['record_id'] = $segments[2];
                }
                $vars['title']        = isset($segments[3]) ? $segments[3] : '';
                $vars['view']         =  'edit';

                $app->input->set('controller', $controller);
                $app->input->set('id', $vars['id']);
                $app->input->set('record_id', $vars['record_id']);
                $app->input->set('title', $vars['title']);
                $app->input->set('view', 'edit');
                break;
        }

        $segments = array();
    }

    return $vars;
}
