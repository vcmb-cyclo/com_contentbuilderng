<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
?>
<div class="container-fluid p-3">
    <h1 class="h3 mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_TITLE'); ?></h1>

    <div class="alert alert-info mb-3">
        <?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_INTRO'); ?>
    </div>

    <ul class="mb-4">
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_POINT_1'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_POINT_2'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_POINT_3'); ?></li>
    </ul>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_SEC_WHAT'); ?></h2>
                    <p class="mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_SEC_WHAT_TEXT'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100 border-warning shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_SEC_SOURCE'); ?></h2>
                    <p><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_SEC_SOURCE_TEXT'); ?></p>
                    <div class="alert alert-warning mb-0">
                        <span class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_SEC_SOURCE_WARN'); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_SEC_TABS'); ?></h2>
                    <p class="mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_SEC_TABS_TEXT'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card h-100 border-danger shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_SEC_DEBUG'); ?></h2>
                    <p><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_SEC_DEBUG_TEXT'); ?></p>
                    <p><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_SEC_DEBUG_ENABLE'); ?></p>

                    <dl class="row mb-3">
                        <?php
                        $debugOptions = [
                            'COM_CONTENTBUILDERNG_DEBUG_SHOW_BF_ID' => 'COM_CONTENTBUILDERNG_DEBUG_SHOW_BF_ID_TIP',
                            'COM_CONTENTBUILDERNG_DEBUG_SHOW_CB_ID' => 'COM_CONTENTBUILDERNG_DEBUG_SHOW_CB_ID_TIP',
                            'COM_CONTENTBUILDERNG_DEBUG_ENABLE_LOGS' => 'COM_CONTENTBUILDERNG_DEBUG_ENABLE_LOGS_TIP',
                            'COM_CONTENTBUILDERNG_DEBUG_SHOW_REQUEST_LOGS' => 'COM_CONTENTBUILDERNG_DEBUG_SHOW_REQUEST_LOGS_TIP',
                            'COM_CONTENTBUILDERNG_DEBUG_SHOW_PERMISSIONS' => 'COM_CONTENTBUILDERNG_DEBUG_SHOW_PERMISSIONS_TIP',
                            'COM_CONTENTBUILDERNG_DEBUG_SHOW_FILTERS' => 'COM_CONTENTBUILDERNG_DEBUG_SHOW_FILTERS_TIP',
                        ];
                        ?>
                        <?php foreach ($debugOptions as $labelKey => $descriptionKey) : ?>
                            <dt class="col-12 col-lg-4"><?php echo Text::_($labelKey); ?></dt>
                            <dd class="col-12 col-lg-8"><?php echo Text::_($descriptionKey); ?></dd>
                        <?php endforeach; ?>
                    </dl>

                    <p><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_SEC_DEBUG_FRONTEND'); ?></p>
                    <div class="alert alert-danger mb-0">
                        <span class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_SEC_DEBUG_WARN'); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_SEC_PROD'); ?></h2>
                    <p class="mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEW_SEC_PROD_TEXT'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
