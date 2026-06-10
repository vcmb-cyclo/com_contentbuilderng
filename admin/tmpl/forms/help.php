<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
?>
<div class="container-fluid p-3">
    <h1 class="h3 mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_TITLE'); ?></h1>

    <div class="alert alert-info mb-3">
        <?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_INTRO'); ?>
    </div>

    <ul class="mb-4">
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_POINT_1'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_POINT_2'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_POINT_3'); ?></li>
    </ul>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_SEC_WHAT'); ?></h2>
                    <p class="mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_SEC_WHAT_TEXT'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_SEC_DESIGN'); ?></h2>
                    <p class="mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_SEC_DESIGN_TEXT'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100 border-warning shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_SEC_CONFIG'); ?></h2>
                    <p><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_SEC_CONFIG_TEXT'); ?></p>
                    <p class="mb-0 text-warning-emphasis small">
                        <span class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_SEC_CONFIG_WARN'); ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_SEC_PROD'); ?></h2>
                    <p class="mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_VIEWS_SEC_PROD_TEXT'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
