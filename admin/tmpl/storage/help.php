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
    <h1 class="h3 mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGE_TITLE'); ?></h1>

    <div class="alert alert-info mb-3">
        <?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGE_INTRO'); ?>
    </div>

    <ul class="mb-4">
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGE_POINT_1'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGE_POINT_2'); ?></li>
        <li><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGE_POINT_3'); ?></li>
    </ul>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGE_SEC_PARAMS'); ?></h2>
                    <p class="mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGE_SEC_PARAMS_TEXT'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGE_SEC_TOOLBAR'); ?></h2>
                    <p class="mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGE_SEC_TOOLBAR_TEXT'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGE_SEC_FIELDS'); ?></h2>
                    <p class="mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGE_SEC_FIELDS_TEXT'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-2"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGE_SEC_CSV'); ?></h2>
                    <p class="mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGE_SEC_CSV_TEXT'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-warning">
        <div class="card-body">
            <h2 class="h5 mb-2">
                <span class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></span>
                <?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGE_SEC_ERRORS'); ?>
            </h2>
            <p class="mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_HELP_STORAGE_SEC_ERRORS_TEXT'); ?></p>
        </div>
    </div>
</div>
